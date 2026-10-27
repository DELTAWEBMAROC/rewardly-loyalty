<?php
/** Development-only integration test. Requires LocalWP + private backup; no shipped frontend route. */
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'){throw new RuntimeException('Dedicated test site required.');}
require_once getenv('NODEVORA_TEST_SOURCE').'/includes/financial/bootstrap.php';
Nodevora_Financial_Schema::install(new Nodevora_Financial_DB());
$results=array();$run=bin2hex(random_bytes(6));$db=new Nodevora_Financial_DB();$uid=wp_insert_user(array('user_login'=>'qa_fin_'.$run,'user_pass'=>wp_generate_password(32),'user_email'=>'qa_fin_'.$run.'@example.invalid','role'=>'customer'));
if(is_wp_error($uid)){throw new RuntimeException('Fixture failed.');}
$db->query('INSERT INTO '.$db->table('wallet').' (user_id,state) VALUES (?,?)',array($uid,'ready'));
$service=new Nodevora_Financial_Wallet();
$check=function($name,$ok,$actual=null)use(&$results){$results[]=array('test'=>$name,'status'=>$ok?'PASS':'FAIL','actual'=>$actual);};
$credit=$service->apply($uid,$run.':credit','credit',100);$check('credit',$service->read($uid)['available']===100);
$a=$service->apply($uid,$run.':debit','debit',80);$check('exact_debit',$service->read($uid)['available']===20);
$check('replay_same_identity',$service->apply($uid,$run.':debit','debit',80)===$a);
try{$service->apply($uid,$run.':debit','debit',10);$check('identity_conflict',false);}catch(Nodevora_Financial_Error $e){$check('identity_conflict',true);}
try{$service->apply($uid,$run.':overspend','debit',21);$check('overspend_rollback',false);}catch(Nodevora_Financial_Error $e){$check('overspend_rollback',$service->read($uid)['available']===20);}
$r=$service->apply($uid,$run.':reserve','reserve',20);$check('hold',$service->read($uid)['available']===0);
$c=$service->apply($uid,$run.':commit','commit',20,array('reservation'=>$r['reservation']));$check('commit_hold',$service->read($uid)['available']===0);
$service->apply($uid,$run.':restore','restore',80,array('original_event_id'=>$a['event_id']));$check('restore_provenance',$service->read($uid)['available']===80);
try{$service->apply($uid,$run.':restore-again','restore',1,array('original_event_id'=>$a['event_id']));$check('restore_cap',false);}catch(Nodevora_Financial_Error $e){$check('restore_cap',$service->read($uid)['available']===80);}
$service->apply($uid,$run.':reverse','reverse',100,array('original_event_id'=>$credit['event_id']));$w=$service->read($uid);$check('debt_creation',(int)$w['balance']===0&&(int)$w['debt']===20,$w['debt']);
$service->apply($uid,$run.':future','credit',50);$w=$service->read($uid);$check('debt_settlement',$w['available']===30&&(int)$w['debt']===0);
$service->apply($uid,$run.':expired','credit',50,array('earned_at'=>'2020-01-01 00:00:00','expires_at'=>'2020-01-02 00:00:00'));$check('expired_funds_unspendable',$service->read($uid)['available']===30);
// Fail each SQL write, before dispatch, and verify rollback on a separate connection.
class Nodevora_Test_Write_Failure extends Nodevora_Financial_DB {public $fail_at;private $writes=0;public function __construct($at){parent::__construct();$this->fail_at=$at;}public function query($sql,$params=array()){if(preg_match('/^(INSERT|UPDATE)/',$sql)&&++$this->writes===$this->fail_at){throw new Nodevora_Financial_Error('Injected write failure.');}return parent::query($sql,$params);}}
for($at=1;$at<=7;$at++){$before=$service->read($uid);$broken=new Nodevora_Financial_Wallet(function()use($at){return new Nodevora_Test_Write_Failure($at);});try{$broken->apply($uid,$run.':fault'.$at,'credit',5);$check('write_failure_'.$at,false);}catch(Nodevora_Financial_Error $e){$after=$service->read($uid);$check('write_failure_'.$at,$before===$after);}}
class Nodevora_Test_Lost_Commit extends Nodevora_Financial_DB {public function commit(){parent::commit();throw new Nodevora_Financial_Unknown('Injected lost acknowledgment.');}}
$lost=new Nodevora_Financial_Wallet(function(){return new Nodevora_Test_Lost_Commit();});$r=$lost->apply($uid,$run.':unknown','credit',7);$check('lost_commit_resolution',$service->apply($uid,$run.':unknown','credit',7)===$r&&$service->read($uid)['available']===37);
$check('journal_sum',(int)$db->one('SELECT SUM(delta_balance) AS total FROM '.$db->table('event').' WHERE user_id=?',array($uid))['total']===(int)$service->read($uid)['balance']);
echo json_encode(array('run'=>$run,'fixture_user'=>$uid,'tests'=>$results),JSON_PRETTY_PRINT);
