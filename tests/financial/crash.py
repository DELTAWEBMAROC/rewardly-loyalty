import argparse,sys,pathlib,os,subprocess,tempfile,time,json
p=argparse.ArgumentParser();p.add_argument('--driver-dir',required=True);p.add_argument('--fixture',required=True);p.add_argument('--output',required=True);a=p.parse_args();sys.path.insert(0,a.driver_dir)
from site_driver import wp,PHP,WP,SOCKET,SITE
source=pathlib.Path(__file__).resolve().parents[2];os.environ['NODEVORA_TEST_SOURCE']=str(source);user=json.loads(pathlib.Path(a.fixture).read_text())['fixture_user'];op='crash:'+os.urandom(6).hex();marker=pathlib.Path(tempfile.mkdtemp(prefix='nodevora-04b-crash-',dir='/private/tmp'))/'ready'
code="require_once getenv('NODEVORA_TEST_SOURCE').'/includes/financial/bootstrap.php';$w=new Nodevora_Financial_Wallet();echo json_encode($w->read("+str(user)+"));"
before=json.loads(wp('eval',code).stdout);env=os.environ.copy();env.update(NODEVORA_USER=str(user),NODEVORA_OPERATION=op,NODEVORA_CRASH_MARKER=str(marker),MYSQL_UNIX_PORT=SOCKET)
proc=subprocess.Popen([PHP,'-d','mysqli.default_socket='+SOCKET,WP,'--path='+str(SITE),'eval-file',str(source/'tests/financial/crash-worker.php')],env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
ready=False
try:
 deadline=time.monotonic()+25
 while time.monotonic()<deadline and proc.poll() is None:
  if marker.exists():ready=True;break
  time.sleep(.05)
finally:
 if proc.poll() is None:proc.kill()
 stdout,stderr=proc.communicate(timeout=10)
after=json.loads(wp('eval',code).stdout);count=int(wp('eval',"require_once getenv('NODEVORA_TEST_SOURCE').'/includes/financial/bootstrap.php';$d=new Nodevora_Financial_DB();echo $d->one('SELECT COUNT(*) AS n FROM '.$d->table('event').' WHERE operation_key=?',['"+op+"'])['n'];").stdout)
r={'test':'SIGKILL_before_commit','status':'PASS' if ready and proc.returncode==-9 and before==after and count==0 else 'FAIL','method':'separate real PHP/MySQL process killed after wallet UPDATE and before COMMIT','reached_uncommitted_write':ready,'exit':proc.returncode,'wallet_unchanged':before==after,'committed_event_count':count,'stderr':stderr};pathlib.Path(a.output).write_text(json.dumps(r,indent=2));print(json.dumps(r,indent=2))
