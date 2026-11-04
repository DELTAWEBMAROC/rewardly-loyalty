"""HTTP Settings API regression; only the explicitly named, backed-up M08A copy."""
from pathlib import Path
import sys,json,re,urllib.parse,os,hashlib,html,importlib.util
from decimal import Decimal
R=Path(os.environ.get('NODEVORA_WORKSPACE',str(Path(__file__).resolve().parents[3])));spec=importlib.util.spec_from_file_location('m08a_driver',Path(__file__).with_name('mission-08a-driver.py'));d=importlib.util.module_from_spec(spec);spec.loader.exec_module(d)
label='settings';assert d.meta(label)['database']=='nodevora_m08a_settings'
tests=[];s=d.session(label);sessions=[s]
def state():return d.ev(label,"global $wpdb;echo json_encode(maybe_unserialize($wpdb->get_var($wpdb->prepare('SELECT option_value FROM '.$wpdb->options.' WHERE option_name=%s','rewardly_loyalty_settings'))));")
def check(name,ok,**more):
 tests.append({'test':name,'status':'PASS'if ok else'FAIL',**more})
 (d.B/'settings-running.json').write_text(json.dumps(tests,indent=2))
 if not ok:
  (d.B/'settings-partial-failure.json').write_text(json.dumps(tests,indent=2));raise AssertionError(name)
def save(actor,section,values,result,language='en',changed=()):
 _,page,_=d.request(label,actor,'/wp-admin/admin.php?page=rewardly-loyalty-settings&tab='+('design'if section=='design'else'general'))
 form=re.search(r'<form[^>]*action="options.php".*?</form>',page,re.S).group();nonce=re.search(r'name="_wpnonce" value="([^"]+)"',form).group(1);before=state()
 post={'option_page':'rewardly_loyalty_group','action':'update','_wpnonce':nonce,'_wp_http_referer':'/wp-admin/admin.php?page=rewardly-loyalty-settings&tab='+('design'if section=='design'else'general'),'rewardly_loyalty_settings[_section]':section}
 for k,v in values.items():post['rewardly_loyalty_settings['+k+']']=v
 status,_,loc=d.request(label,actor,'/wp-admin/options.php',post,follow=False);after=state();q=urllib.parse.parse_qs(urllib.parse.urlparse(loc).query)
 _,html,_=d.request(label,actor,loc);notice=re.findall(r'<div[^>]*class="[^"]*nodevora-settings-notice[^"]*".*?</div>',html,re.S);plain=[__import__('html').unescape(re.sub('<[^>]*>',' ',n)).strip()for n in notice]
 ok=status==302 and q.get('nodevora_settings_result')==[result] and len(notice)==1
 expected={'en':{'saved':'Your settings have been saved.','unchanged':'These settings are already saved. No changes were needed.','failed':'Your settings could not be saved.','invalid':'Some submitted settings are invalid'},'fr':{'saved':'Vos réglages ont été enregistrés.','unchanged':'Ces réglages sont déjà enregistrés.','failed':'Vos réglages n’ont pas pu être enregistrés.','invalid':'Certains réglages transmis sont invalides'}}
 ok=ok and expected[language][result]in plain[0] and 'M08A controlled settings failure'not in html and not any(x in html for x in ['SQLSTATE[45000]','mysqli_sql_exception'])
 check(language+'_'+section+'_'+result+'_'+str(len(tests)),ok,http=status,message=plain,actual_result=q.get('nodevora_settings_result'))
 check('neighbor_sections_preserved_'+str(len(tests)),all((Decimal(str(after.get(k)))==Decimal(str(v)) if k=='max_discount_per_order' else after.get(k)==v) for k,v in before.items()if k not in changed))
 if result in ['unchanged','failed','invalid']:check('no_unexpected_persistence_'+str(len(tests)),after==before)
 return loc
def mode(actor,advanced):
 _,page,_=d.request(label,actor,'/wp-admin/admin.php?page=rewardly-loyalty-settings')
 f=re.search(r'<form[^>]*class="nodevora-mode".*?</form>',page,re.S).group();nonce=re.search(r'name="_wpnonce" value="([^"]+)"',f).group(1)
 d.request(label,actor,'/wp-admin/admin-post.php',{'action':'nodevora_preferences','_wpnonce':nonce,**({'advanced':'1'}if advanced else{})},follow=False)
def inject():
 table=d.ev(label,"global $wpdb;echo json_encode($wpdb->options);");assert re.fullmatch('[a-zA-Z0-9_]+',table)
 d.wp(label,'eval',"global $wpdb;$q=$wpdb->query(\"CREATE TRIGGER m08a_settings_failure BEFORE UPDATE ON "+table+" FOR EACH ROW BEGIN IF NEW.option_name='rewardly_loyalty_settings' AND NEW.option_value<>OLD.option_value THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='M08A controlled settings failure'; END IF; END\");if($q===false){throw new RuntimeException('Trigger setup failed');}")
def drop():d.wp(label,'eval',"global $wpdb;$wpdb->query('DROP TRIGGER IF EXISTS m08a_settings_failure');")
original=state()
try:
 d.request(label,s,'/wp-admin/admin.php?page=rewardly-loyalty-settings')
 for language,locale,earn,color in [('en','en_US',3,'#123456'),('fr','fr_FR',4,'#234567')]:
  d.wp(label,'eval',"update_user_meta(1,'locale','"+locale+"');");mode(s,False)
  save(s,'general',{'earn_points_per_dh':earn},'saved',language,changed=['earn_points_per_dh'])
  save(s,'general',{'earn_points_per_dh':earn},'unchanged',language)
  inject()
  try:
   save(s,'general',{'earn_points_per_dh':earn+4},'failed',language)
   save(s,'general',{'earn_points_per_dh':earn},'unchanged',language)
  finally:drop()
  mode(s,True)
  save(s,'design',{'primary_color':color},'saved',language,changed=['primary_color'])
  inject()
  try:save(s,'design',{'primary_color':'#654321'},'failed',language)
  finally:drop()
  save(s,'general',{'points_expiration_days':400 if language=='en' else 401},'saved',language,changed=['points_expiration_days'])
  save(s,'design',{'primary_color':'bad-hex'},'invalid',language)
  save(s,'not-a-section',{},'invalid',language)
 d.wp(label,'eval',"update_user_meta(1,'locale','en_US');")
 before=state();status,_,_=d.request(label,s,'/wp-admin/options.php',{'option_page':'rewardly_loyalty_group','action':'update','_wpnonce':'invalid','rewardly_loyalty_settings[_section]':'general','rewardly_loyalty_settings[earn_points_per_dh]':'999'},follow=False)
 check('invalid_nonce_refused',status==403 and state()==before,http=status)
 # Valid nonce, forged result code: never show an unsigned success from a query alone.
 _,html,_=d.request(label,s,'/wp-admin/admin.php?page=rewardly-loyalty-settings&nodevora_settings_result=saved&nodevora_settings_nonce=invalid');check('forged_result_not_displayed','nodevora-settings-notice'not in html)
 # Explicitly enabled Editor retains read-only capability; its own valid nonce is not permission.
 ids=d.ev(label,"$out=array();foreach(array('shop_manager','editor','customer')as$r){$out[$r]=wp_insert_user(array('user_login'=>'m08a_settings_'.$r.'_'.bin2hex(random_bytes(3)),'user_pass'=>wp_generate_password(32),'role'=>$r));}update_option('nodevora_access_policy',array('roles'=>array('shop_manager','editor'),'administrators_only'=>false));echo json_encode($out);")
 for role,uid in ids.items():
  actor=d.session(label,uid);sessions.append(actor)
  if role=='shop_manager':save(actor,'general',{'min_points_to_redeem':111},'saved',changed=['min_points_to_redeem']);continue
  os.environ['M08A_ACTOR_COOKIES']=json.dumps(actor['cookies'])
  nonce=d.ev(label,"$c=json_decode(getenv('M08A_ACTOR_COOKIES'),true);$_COOKIE[LOGGED_IN_COOKIE]=$c[LOGGED_IN_COOKIE];wp_set_current_user("+str(uid)+");echo json_encode(wp_create_nonce('rewardly_loyalty_group-options'));")
  before=state();status,_,_=d.request(label,actor,'/wp-admin/options.php',{'option_page':'rewardly_loyalty_group','action':'update','_wpnonce':nonce,'rewardly_loyalty_settings[_section]':'general','rewardly_loyalty_settings[earn_points_per_dh]':'999'},follow=False)
  check(role+'_valid_nonce_still_forbidden',status in [302,403] and state()==before,http=status)
 # Restriction ON: the previously authorized manager loses write access.
 d.wp(label,'eval',"update_option('nodevora_access_policy',array('roles'=>array('shop_manager'),'administrators_only'=>true));")
 manager=sessions[-3];os.environ['M08A_ACTOR_COOKIES']=json.dumps(manager['cookies'])
 nonce=d.ev(label,"$c=json_decode(getenv('M08A_ACTOR_COOKIES'),true);$_COOKIE[LOGGED_IN_COOKIE]=$c[LOGGED_IN_COOKIE];wp_set_current_user("+str(ids['shop_manager'])+");echo json_encode(wp_create_nonce('rewardly_loyalty_group-options'));")
 before=state();status,_,_=d.request(label,manager,'/wp-admin/options.php',{'option_page':'rewardly_loyalty_group','action':'update','_wpnonce':nonce,'rewardly_loyalty_settings[_section]':'general','rewardly_loyalty_settings[earn_points_per_dh]':'999'},follow=False);check('restricted_manager_refused',status in [302,403]and state()==before,http=status)
finally:
 drop()
 for a in sessions:d.revoke(label,a)
# Keep synthetic test settings as evidence; no financial operation, email or HTTP is executed.
(d.B/'settings-after.json').write_text(json.dumps({'tests':tests,'original_hash':hashlib.sha256(json.dumps(original,sort_keys=True).encode()).hexdigest(),'final_settings_recorded_privately':True},indent=2));(d.P/'settings-after-tests.json').write_text(json.dumps(state(),indent=2))
print(json.dumps({'assertions':len(tests),'failures':[t for t in tests if t['status']=='FAIL']}))
