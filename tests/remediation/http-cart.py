import sys,json,urllib.request,urllib.parse,http.cookiejar,re,tempfile,os
from pathlib import Path
root=Path(__file__).resolve().parents[3];sys.path.insert(0,str(root/'audit-reports/mission-03/evidence'));from site_driver import wp
assert wp('option','get','home').stdout.strip()=='http://localhost:10004'
assert wp('eval','echo NODEVORA_FINANCIAL_WRITES_PAUSED ? "paused" : "scoped";').stdout.strip()=='scoped'
private=Path(tempfile.mkdtemp(prefix='nodevora-http-cart-',dir='/private/tmp'));private.chmod(0o700);file=private/'auth.json'
os.environ['NODEVORA_CART_AUTH']=str(file)
code="$p=wp_generate_password(32);$login='qa_m05_httpcart_'.bin2hex(random_bytes(5));$u=wp_insert_user(array('user_login'=>$login,'user_pass'=>$p,'role'=>'customer'));(new Nodevora_Financial_Wallet())->apply($u,'qa:m05:httpcart:'.$u,'credit',5000,array('type'=>'adjust_add'));file_put_contents(getenv('NODEVORA_CART_AUTH'),json_encode(array('id'=>$u,'login'=>$login,'password'=>$p)));chmod(getenv('NODEVORA_CART_AUTH'),0600);echo 'created';"
wp('eval',code);auth=json.loads(file.read_text());op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));base='http://localhost:10004';nonce='';tests=[]
def request(path,data=None,store=False,headers=None):
 global nonce
 h={'User-Agent':'Nodevora scoped LocalWP regression'}
 if store:
  h['Content-Type']='application/json'
  if nonce:h['Nonce']=nonce
 h.update(headers or {})
 payload=None if data is None else (json.dumps(data).encode() if store else urllib.parse.urlencode(data).encode())
 try:r=op.open(urllib.request.Request(base+path,payload,h),timeout=30)
 except urllib.error.HTTPError as e:r=e
 body=r.read().decode();nonce=r.headers.get('Nonce',nonce)
 try:body=json.loads(body)
 except ValueError:pass
 return r.status,body
request('/wp-login.php');request('/wp-login.php',{'log':auth['login'],'pwd':auth['password'],'wp-submit':'Log In','redirect_to':base+'/my-account/','testcookie':'1'})
status,cart=request('/wp-json/wc/store/v1/cart')
def check(name,ok,actual=None):tests.append({'test':name,'status':'PASS' if ok else 'FAIL','actual':actual})
status,cart=request('/wp-json/wc/store/v1/cart/add-item',{'id':12,'quantity':1},True);check('http_add_synthetic_item',status in (200,201),{'http':status})
status,cart=request('/wp-json/wc/store/v1/cart/extensions',{'namespace':'nodevora-loyalty','data':{'action':'apply','points':'2000'}},True);check('http_apply_owned_points',status==200 and cart['extensions']['nodevora-loyalty']['applied']==2000)
status,cart=request('/wp-json/wc/store/v1/cart/apply-coupon',{'code':'qa-fixed90'},True);check('http_coupon_clips_points',status==200 and cart['extensions']['nodevora-loyalty']['applied']==200,cart.get('extensions'))
for i in range(3):
 request('/blocks-cart/')
 status,cart=request('/wp-json/wc/store/v1/cart')
 check('http_coupon_persists_navigation_'+str(i),status==200 and any(c['code']=='qa-fixed90' for c in cart['coupons']) and cart['extensions']['nodevora-loyalty']['applied']==200,{'coupons':[c['code'] for c in cart.get('coupons',[])],'state':cart.get('extensions')})
status,data=request('/wp-json/wc/store/v1/cart/extensions',{'namespace':'nodevora-loyalty','data':{'action':'apply','points':'119'}},True,{'Nonce':'invalid'});check('http_invalid_store_nonce_denied',status in (401,403),{'http':status})
status,data=request('/wp-json/wc/store/v1/cart/extensions',{'namespace':'nodevora-loyalty','data':{'action':'apply','points':'-1'}},True);check('http_negative_points_denied',status==400,{'http':status})
status,data=request('/wp-json/wc/store/v1/cart/extensions',{'namespace':'nodevora-loyalty','data':{'action':'remove'}},True);check('http_remove_points',status==200 and data['extensions']['nodevora-loyalty']['applied']==0)
state=json.loads(wp('eval',"$d=new Nodevora_Financial_DB();echo json_encode(array('balance'=>(new Nodevora_Financial_Wallet())->read("+str(auth['id'])+")['available'],'debits'=>$d->one('SELECT COUNT(*) AS n FROM '.$d->table('event').' WHERE user_id=? AND kind=?',array("+str(auth['id'])+",'debit'))['n']));").stdout);check('http_cart_actions_no_wallet_debit',state['balance']==5000 and int(state['debits'])==0,state)
(root/'audit-reports/mission-05-evidence/http-cart.json').write_text(json.dumps({'tests':tests},indent=2));print(json.dumps({'tests':len(tests),'failed':[t for t in tests if t['status']!='PASS']}));assert all(t['status']=='PASS' for t in tests)
