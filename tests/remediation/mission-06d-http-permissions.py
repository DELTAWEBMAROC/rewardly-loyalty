from pathlib import Path
import sys,json,urllib.request,urllib.error,urllib.parse,re
r=Path('/Users/user/Desktop/work/nodevora-plugin');sys.path.insert(0,str(r/'audit-reports/mission-03/evidence'));import site_driver as d
assert json.loads(d.wp('eval',"echo json_encode(array(home_url(),DB_NAME));").stdout)==['http://localhost:10004','local']
# Sessions and nonces exist only in this process memory; never write their values to disk or logs.
code=r'''$out=array();foreach(array('administrator','shop_manager','editor','customer','subscriber')as$role){$id=wp_insert_user(array('user_login'=>'qa_m06d_'.$role.'_'.bin2hex(random_bytes(4)),'user_pass'=>wp_generate_password(40),'role'=>$role));wp_set_current_user($id);$token=WP_Session_Tokens::get_instance($id)->create(time()+1800);$cookies=array(AUTH_COOKIE=>wp_generate_auth_cookie($id,time()+1800,'auth',$token),LOGGED_IN_COOKIE=>wp_generate_auth_cookie($id,time()+1800,'logged_in',$token));$_COOKIE[LOGGED_IN_COOKIE]=$cookies[LOGGED_IN_COOKIE];$n=array();foreach(array('nodevora_preferences','nodevora_data','nodevora_financial_setup','rewardly_loyalty_adjust_points','rewardly_loyalty_group-options','wp_rest')as$a){$n[$a]=wp_create_nonce($a);}$out[$role]=array('id'=>$id,'cookies'=>$cookies,'nonces'=>$n);}echo json_encode($out);'''
actors=json.loads(d.wp('eval',code).stdout);actors['guest']={'id':0,'cookies':{},'nonces':{}}
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):return None
opener=urllib.request.build_opener(NoRedirect)
def req(actor,path,data=None,rest=False):
 a=actors[actor];headers={'Cookie':'; '.join(k+'='+v for k,v in a['cookies'].items())}
 if rest:headers.update({'X-WP-Nonce':a['nonces'].get('wp_rest',''),'Content-Type':'application/json'});body=json.dumps(data).encode()
 else:body=urllib.parse.urlencode(data,doseq=True).encode()if data is not None else None
 request=urllib.request.Request('http://localhost:10004'+path,data=body,headers=headers,method='PUT'if rest else None)
 try:
  with opener.open(request,timeout=40)as resp:return resp.status,resp.read().decode(errors='replace')
 except urllib.error.HTTPError as e:return e.code,e.read().decode(errors='replace')
results=[]
def check(name,ok,status=None):
 results.append({'test':name,'status':'PASS'if ok else'FAIL','http':status})
 if not ok:print('FAIL:',name,status,flush=True)
original=json.loads(d.wp('eval',"echo json_encode(array('policy'=>get_option('nodevora_access_policy',null),'settings'=>get_option('rewardly_loyalty_settings')));").stdout)
try:
 for scenario,policy in [('default',{'administrators_only':False,'roles':['shop_manager']}),('editor_allowed',{'administrators_only':False,'roles':['shop_manager','editor']}),('restricted',{'administrators_only':True,'roles':['shop_manager','editor']}),('roles_removed',{'administrators_only':False,'roles':[]})]:
  # Policy mutation itself goes through the supported administrator POST action.
  admin=actors['administrator'];post={'action':'nodevora_preferences','policy':'1','_wpnonce':admin['nonces']['nodevora_preferences'],'roles[]':policy['roles']}
  if policy['administrators_only']:post['administrators_only']='1'
  status,_=req('administrator','/wp-admin/admin-post.php',post);check(scenario+'_admin_policy_post',status==302,status)
  for role,a in actors.items():
   allowed=role=='administrator'or(scenario!='restricted'and(role=='shop_manager'and'shop_manager'in policy['roles']or role=='editor'and'editor'in policy['roles']))
   edit=allowed and role in ['administrator','shop_manager'];sensitive=role=='administrator'
   status,body=req(role,'/wp-admin/admin.php?page=rewardly-loyalty-settings&tab=general');check(scenario+'_'+role+'_direct_url',(status==200 and'nodevora-admin'in body)if allowed else(status in [302,403]),status)
   if role=='editor'and allowed:check(scenario+'_editor_readonly','fieldset disabled class="nodevora-readonly"'in body)
   data={'option_page':'rewardly_loyalty_group','action':'update','_wpnonce':a['nonces'].get('rewardly_loyalty_group-options',''),'rewardly_loyalty_settings[_section]':'general','rewardly_loyalty_settings[enabled]':original['settings'].get('enabled','yes')}
   status,body=req(role,'/wp-admin/options.php',data);check(scenario+'_'+role+'_settings_post',status==302 if edit else status in [302,403],status)
   status,body=req(role,'/wp-admin/admin-ajax.php',{'action':'nodevora_backup_preview','_wpnonce':a['nonces'].get('nodevora_data','')});check(scenario+'_'+role+'_sensitive_ajax',status==409 if sensitive else status in [400,403],status)
   status,body=req(role,'/wp-admin/admin-post.php',{'action':'nodevora_data_resume','_wpnonce':a['nonces'].get('nodevora_data','')});check(scenario+'_'+role+'_sensitive_post',status==302 if sensitive else status in [400,403],status)
   status,body=req(role,'/wp-json/wc/v3/products/12',{'meta_data':[{'key':'_rewardly_loyalty_product_points_rate','value':'2'}]},rest=True);check(scenario+'_'+role+'_product_rest',status==200 if edit else status in [401,403],status)
  print('Completed permission scenario:',scenario,flush=True)
 # A manager cannot grant policy rights even while their panel is allowed.
 req('administrator','/wp-admin/admin-post.php',{'action':'nodevora_preferences','policy':'1','_wpnonce':admin['nonces']['nodevora_preferences'],'roles[]':['shop_manager']})
 status,_=req('shop_manager','/wp-admin/admin-post.php',{'action':'nodevora_preferences','policy':'1','_wpnonce':actors['shop_manager']['nonces']['nodevora_preferences'],'roles[]':['editor']});check('manager_cannot_change_access_policy',status==403,status)
 status,_=req('administrator','/wp-admin/admin-ajax.php',{'action':'nodevora_backup_preview','_wpnonce':'invalid'});check('admin_bad_nonce_refused',status==403,status)
 check('no_unauthorized_business_change',json.loads(d.wp('eval',"echo json_encode(Rewardly_Loyalty_Helpers::get_settings()['enabled']);").stdout)==original['settings']['enabled'])
finally:
 # Restore merchant settings/policy, preserving unset/default distinctions and ending only our synthetic sessions.
 import os
 os.environ['M06D_POLICY_RESTORE']=json.dumps(original)
 d.wp('eval',"$saved=json_decode(getenv('M06D_POLICY_RESTORE'),true);if($saved['policy']===null){delete_option('nodevora_access_policy');}else{update_option('nodevora_access_policy',$saved['policy'],false);}update_option('rewardly_loyalty_settings',$saved['settings']);")
 for a in actors.values():
  if a['id']:d.wp('eval','WP_Session_Tokens::get_instance('+str(a['id'])+')->destroy_all();')
for folder in [r/'audit-reports/mission-06D-evidence',r/'github/tests/remediation/mission-06d-evidence']:(folder/'permissions-http.json').write_text(json.dumps(results,indent=2))
print(json.dumps({'assertions':len(results),'failed':[x for x in results if x['status']=='FAIL']}));assert all(x['status']=='PASS'for x in results)
