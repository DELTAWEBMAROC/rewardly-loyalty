from pathlib import Path
import os,sys,json,subprocess,tempfile,concurrent.futures,time
r=Path('/Users/user/Desktop/work/nodevora-plugin');sys.path.insert(0,str(r/'audit-reports/mission-03/evidence'));import site_driver as d
assert json.loads(d.wp('eval',"echo json_encode(array(home_url(),DB_NAME));").stdout)==['http://localhost:10004','local'];tests=[]
def fixture(seed):return int(d.wp('eval',"$s=Rewardly_Loyalty_Helpers::get_settings();$s['pro_points_registration']=0;add_filter('pre_option_rewardly_loyalty_settings',static function()use($s){return$s;});$u=wp_insert_user(array('user_login'=>'qa_m06d_race_'.bin2hex(random_bytes(5)),'user_pass'=>wp_generate_password(40),'role'=>'customer'));"+("(new Nodevora_Financial_Wallet())->apply($u,'m06d:race:'.$u.':seed','credit',100);"if seed else'')+"echo$u;").stdout)
def worker(u,folder,slot,kind,amount,key,slow=False):
 env=os.environ.copy();env.update(MYSQL_UNIX_PORT=d.SOCKET,M06D_USER=str(u),M06D_BARRIER=folder,M06D_SLOT=slot,M06D_KIND=kind,M06D_AMOUNT=str(amount),M06D_KEY=key,M06D_PAUSE_WRITER='1'if slow else'')
 p=subprocess.run([d.PHP,'-d','mysqli.default_socket='+d.SOCKET,d.WP,'--path='+str(d.SITE),'eval-file',str(r/'github/tests/remediation/mission-06d-race-worker.php')],env=env,capture_output=True,text=True,timeout=50);assert p.returncode==0,p.stderr;return json.loads(p.stdout)
def read(u):return json.loads(d.wp('eval',"$d=new Nodevora_Financial_DB();echo json_encode(array('balance'=>(new Nodevora_Financial_Wallet())->read("+str(u)+")['balance'],'events'=>$d->one('SELECT COUNT(*) AS n,SUM(delta_balance) AS b FROM '.$d->table('event').' WHERE user_id=?',array("+str(u)+")),'invariants'=>(new Nodevora_Financial_Migration())->verify("+str(u)+")['verified']));").stdout)
for case in ['two_debits','duplicate_credit']:
 u=fixture(case=='two_debits');folder=tempfile.mkdtemp(prefix='m06d-race-',dir='/private/tmp')
 with concurrent.futures.ThreadPoolExecutor(2)as pool:out=list(pool.map(lambda slot:worker(u,folder,slot,'debit'if case=='two_debits'else'credit',80 if case=='two_debits'else 100,'m06d:race:'+str(u)+(':'+slot if case=='two_debits'else':same')),['a','b']))
 state=read(u);ok=state['invariants']and int(state['balance'])==(20 if case=='two_debits'else 100)and int(state['events']['n'])==(2 if case=='two_debits'else 1)and sum(w['outcome']=='committed'for w in out)==(1 if case=='two_debits'else 2);tests.append({'test':case,'status':'PASS'if ok else'FAIL','workers':out,'state':state});assert ok
u=fixture(False);folder=tempfile.mkdtemp(prefix='m06d-pause-',dir='/private/tmp');Path(folder+'/b.ready').write_text('ready')
with concurrent.futures.ThreadPoolExecutor(2)as pool:
 future=pool.submit(worker,u,folder,'a','credit',10,'m06d:barrier:'+str(u),True)
 until=time.time()+25
 while not Path(folder+'/locked').exists():
  assert time.time()<until;time.sleep(.02)
 start=time.monotonic();d.wp('eval',"$s=Nodevora_Financial_Setup::state();$s['phase']='restoring';update_option(Nodevora_Financial_Setup::OPTION,$s,false);");elapsed=time.monotonic()-start;out=future.result()
 late=worker(u,folder,'a','credit',1,'m06d:barrier:'+str(u)+':late');state=read(u)
 d.wp('eval',"$s=Nodevora_Financial_Setup::state();if($s['phase']!=='restoring'){throw new RuntimeException('Unexpected state');}$s['phase']='active';update_option(Nodevora_Financial_Setup::OPTION,$s,false);")
 ok=out['outcome']=='committed'and late['outcome']=='rejected'and int(state['balance'])==10 and state['invariants'];tests.append({'test':'pause_barrier_inflight_and_delayed_callback','status':'PASS'if ok else'FAIL','first':out,'late':late,'state':state,'pause_request_seconds':round(elapsed,3)});assert ok
for f in [r/'audit-reports/mission-06D-evidence/concurrency-targeted.json',r/'github/tests/remediation/mission-06d-evidence/concurrency-targeted.json']:f.write_text(json.dumps(tests,indent=2))
print(json.dumps(tests))
