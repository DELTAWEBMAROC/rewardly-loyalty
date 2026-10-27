"""Explicit LocalWP integration runner. Loads audited site_driver through --driver-dir."""
import argparse,sys,os,json,tempfile,subprocess,concurrent.futures,pathlib
parser=argparse.ArgumentParser();parser.add_argument('--driver-dir',required=True);parser.add_argument('--output',required=True);args=parser.parse_args();sys.path.insert(0,args.driver_dir)
from site_driver import wp,PHP,WP,SOCKET,SITE
source=pathlib.Path(__file__).resolve().parents[2];os.environ['NODEVORA_TEST_SOURCE']=str(source)
assert wp('option','get','siteurl').stdout.strip()=='http://localhost:10004'
results=[]
for case in ['two_debits','credit_debit','duplicate_credit']:
 run=os.urandom(6).hex()
 code="require_once getenv('NODEVORA_TEST_SOURCE').'/includes/financial/bootstrap.php';$u=wp_insert_user(['user_login'=>'qa_race_"+run+"','user_pass'=>wp_generate_password(32),'user_email'=>'qa_race_"+run+"@example.invalid','role'=>'customer']);$db=new Nodevora_Financial_DB();$db->query('INSERT INTO '.$db->table('wallet').' (user_id,state) VALUES (?,?)',[$u,'ready']);$w=new Nodevora_Financial_Wallet();$w->apply($u,'"+run+":opening','credit',"+('0' if case=='duplicate_credit' else '100')+");echo json_encode(['user'=>$u]);"
 user=json.loads(wp('eval',code).stdout)['user'];barrier=tempfile.mkdtemp(prefix='nodevora-04b-race-',dir='/private/tmp')
 def worker(slot):
  kind='debit';amount=80;key=run+':'+slot
  if case=='credit_debit' and slot=='a':kind='credit';amount=50
  if case=='duplicate_credit':kind='credit';amount=100;key=run+':same-event'
  env=os.environ.copy();env.update(NODEVORA_BARRIER=barrier,NODEVORA_SLOT=slot,NODEVORA_USER=str(user),NODEVORA_OPERATION=key,NODEVORA_KIND=kind,NODEVORA_AMOUNT=str(amount),MYSQL_UNIX_PORT=SOCKET)
  r=subprocess.run([PHP,'-d','mysqli.default_socket='+SOCKET,WP,'--path='+str(SITE),'eval-file',str(source/'tests/financial/concurrency-worker.php')],env=env,text=True,capture_output=True,timeout=60)
  return {'slot':slot,'exit':r.returncode,'data':json.loads(r.stdout) if r.stdout else None,'stderr':r.stderr}
 with concurrent.futures.ThreadPoolExecutor(2) as pool:workers=list(pool.map(worker,['a','b']))
 state=json.loads(wp('eval',"require_once getenv('NODEVORA_TEST_SOURCE').'/includes/financial/bootstrap.php';$w=new Nodevora_Financial_Wallet();$db=new Nodevora_Financial_DB();echo json_encode(['wallet'=>$w->read("+str(user)+"),'journal'=>$db->one('SELECT SUM(delta_balance) AS total,COUNT(*) AS events FROM '.$db->table('event').' WHERE user_id=?', ["+str(user)+"])]);").stdout)
 successes=sum(x['data'] is not None and x['data']['outcome']=='committed' for x in workers);expected={'two_debits':20,'credit_debit':70,'duplicate_credit':100}[case]
 ok=all(x['exit']==0 for x in workers) and state['wallet']['available']==expected and int(state['journal']['total'])==expected and successes==(1 if case=='two_debits' else 2)
 if case=='duplicate_credit':ok=ok and int(state['journal']['events'])==2
 results.append({'case':case,'status':'PASS' if ok else 'FAIL','method':'two independent PHP processes and primary InnoDB connections; common pre-transaction barrier','workers':workers,'actual':state})
pathlib.Path(args.output).write_text(json.dumps(results,indent=2));print(json.dumps([{'case':x['case'],'status':x['status'],'balance':x['actual']['wallet']['available']} for x in results],indent=2))
