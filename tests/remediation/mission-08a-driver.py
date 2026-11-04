from pathlib import Path
import json,os,subprocess,sys,urllib.request,urllib.error,urllib.parse
from types import SimpleNamespace
R=Path(os.environ.get('NODEVORA_WORKSPACE',str(Path(__file__).resolve().parents[3])));B=R/'audit-reports/mission-08A/evidence';P=R/'.private/mission-08A';B.mkdir(parents=True,exist_ok=True)
base=SimpleNamespace(SOCKET=os.environ.get('NODEVORA_MYSQL_SOCKET',str(Path.home()/'Library/Application Support/Local/run/6JRuOv4ug/mysql/mysqld.sock')),MYSQL=os.environ.get('NODEVORA_MYSQL_BIN',str(Path.home()/'Library/Application Support/Local/lightning-services/mysql-8.0.35+4/bin/darwin/bin')),WP=os.environ.get('NODEVORA_WP_CLI_PHAR','/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar'))
def meta(label):
 m=json.loads((P/'fixtures.json').read_text())[label];assert m['database'].startswith('nodevora_m08a_')and Path(m['site']).is_relative_to(P)and m['port'] in [18201,18202,18203];return m
def wp(label,*args,check=True):
 m=meta(label);env=os.environ.copy();env['MYSQL_UNIX_PORT']=base.SOCKET;env['PATH']=base.MYSQL+':'+str(Path(m['php']).parent)+':'+env.get('PATH','')
 q=subprocess.run([m['php'],'-d','mysqli.default_socket='+base.SOCKET,base.WP,'--path='+m['site'],*args],env=env,text=True,capture_output=True,timeout=180)
 if check and q.returncode:raise RuntimeError('Fixture command failed: '+str(args[:1])+'\n'+q.stderr[-1400:]+q.stdout[-1000:])
 return q
def ev(label,code):return json.loads(wp(label,'eval',code).stdout)
def session(label,uid=1):
 return ev(label,"$id="+str(uid)+";wp_set_current_user($id);$t=WP_Session_Tokens::get_instance($id)->create(time()+1800);echo json_encode(array('id'=>$id,'token'=>$t,'cookies'=>array(AUTH_COOKIE=>wp_generate_auth_cookie($id,time()+1800,'auth',$t),LOGGED_IN_COOKIE=>wp_generate_auth_cookie($id,time()+1800,'logged_in',$t))));")
def revoke(label,s):wp(label,'eval',"WP_Session_Tokens::get_instance("+str(s['id'])+")->destroy('"+s['token']+"');")
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
opener=urllib.request.build_opener(NoRedirect)
def request(label,s,path,data=None,follow=True):
 headers={'Cookie':'; '.join(k+'='+v for k,v in s.get('cookies',{}).items())};body=None if data is None else urllib.parse.urlencode(data).encode()
 url=path if path.startswith('http://127.0.0.1:'+str(meta(label)['port']))else'http://127.0.0.1:'+str(meta(label)['port'])+path
 try:
  with (urllib.request.build_opener()if follow else opener).open(urllib.request.Request(url,data=body,headers=headers),timeout=60)as r:return r.status,r.read().decode(errors='replace'),r.headers.get('Location','')
 except urllib.error.HTTPError as e:return e.code,e.read().decode(errors='replace'),e.headers.get('Location','')
if __name__=='__main__':
 label=sys.argv[1]
 if sys.argv[2]=='serve':
  m=meta(label);env=os.environ.copy();env['MYSQL_UNIX_PORT']=base.SOCKET
  with (P/(label+'-http.log')).open('a')as log:subprocess.run([m['php'],'-d','mysqli.default_socket='+base.SOCKET,'-S','127.0.0.1:'+str(m['port']),'-t',m['site'],str(P/'router.php')],env=env,stdout=log,stderr=log)
 else:
  q=wp(label,*sys.argv[2:],check=False);print(q.stdout,end='');print(q.stderr,end='',file=sys.stderr);sys.exit(q.returncode)
