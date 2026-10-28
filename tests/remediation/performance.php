<?php
/** Mesures locales sur un compte synthétique ; aucun traitement global de récupération. */
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'){throw new RuntimeException('Dedicated test site required.');}
require_once getenv('NODEVORA_TEST_SOURCE').'/nodevora-customer-rewards-for-woocommerce.php';
class Nodevora_M05_Measured_DB extends Nodevora_Financial_DB {public $queries=0;public function query($sql,$params=array()){$this->queries++;return parent::query($sql,$params);}}
$run=bin2hex(random_bytes(6));$db=new Nodevora_M05_Measured_DB();$uid=(int)getenv('NODEVORA_PERFORMANCE_USER');if($uid && 0!==strpos(get_userdata($uid)->user_login,'qa_m05_perf_')){throw new RuntimeException('Synthetic fixture required.');}
if(!$uid){$uid=wp_insert_user(array('user_login'=>'qa_m05_perf_'.$run,'user_pass'=>wp_generate_password(32),'user_email'=>$run.'@example.invalid','role'=>'customer'));
$db->query('INSERT INTO '.$db->table('wallet').' (user_id,state) VALUES (?,?)',array($uid,'ready'));
$db->query('INSERT INTO '.$db->table('snapshot').' (user_id,version,checksum,payload,created_at) VALUES (?,1,?,?,UTC_TIMESTAMP())',array($uid,str_repeat('0',64),json_encode(array('rows'=>array()))));
$wallet=new Nodevora_Financial_Wallet();$wallet->apply($uid,$run.':seed','credit',100,array('type'=>'earn'));$source=$db->one('SELECT MAX(id) AS id FROM '.$db->table('event').' WHERE user_id=?',array($uid))['id'];
$db->begin();
try{
 for($i=0;$i<5000;$i++){
  $payload=json_encode(array('details'=>array('type'=>$i%2?'adjust':'earn')));
  $db->query('INSERT INTO '.$db->table('event').' (user_id,operation_key,kind,payload_hash,payload,gross_points,result,created_at) VALUES (?,?,?,?,?,0,?,UTC_TIMESTAMP())',array($uid,$run.':zero:'.$i,'credit',hash('sha256',$payload),$payload,'{}'));
  $db->query('INSERT INTO '.$db->table('lot').' (user_id,source_event_id,remaining,provenance) VALUES (?,?,0,?)',array($uid,$source,'{}'));
 }
 $db->commit();
}catch(Throwable $e){$db->rollback();throw $e;}
}
$wallet=new Nodevora_Financial_Wallet();
require_once getenv('NODEVORA_BASELINE_COMPATIBILITY');
$measure=function($callback)use($db){$samples=array();$answer=null;for($n=0;$n<5;$n++){$query_before=$db->queries;$memory=memory_get_usage();if(function_exists('memory_reset_peak_usage')){memory_reset_peak_usage();}$start=hrtime(true);$answer=$callback();$samples[]=array('ms'=>(hrtime(true)-$start)/1e6,'instrumented_connection_queries'=>$db->queries-$query_before,'peak_extra_bytes'=>function_exists('memory_reset_peak_usage')?max(0,memory_get_peak_usage()-$memory):null);}return array('samples'=>$samples,'result'=>$answer);};
$before=$measure(function()use($db,$uid){$db->begin();try{$rows=$db->query('SELECT * FROM '.$db->table('lot').' WHERE user_id=? ORDER BY expires_at IS NULL,expires_at,id FOR UPDATE',array($uid));return count($rows);}finally{$db->rollback();}});
$reflect=new ReflectionClass($wallet);foreach(array('db'=>$db,'wallet'=>array('user_id'=>$uid))as$key=>$value){$p=$reflect->getProperty($key);$p->setAccessible(true);$p->setValue($wallet,$value);}$method=$reflect->getMethod('lots');$method->setAccessible(true);
$after=$measure(function()use($db,$wallet,$method){$db->begin();try{return count($method->invoke($wallet));}finally{$db->rollback();}});
$history_before=$measure(function()use($uid){return Nodevora_Baseline_Compatibility::total($uid,'earned');});
$history_after=$measure(function()use($uid){return Nodevora_Financial_Compatibility::total($uid,'earned');});
$plan=$db->query('EXPLAIN SELECT * FROM '.$db->table('lot').' WHERE user_id=? AND remaining>0 ORDER BY expires_at IS NULL,expires_at,id FOR UPDATE',array($uid));
$tests=array();$check=function($name,$ok)use(&$tests){$tests[]=array('test'=>$name,'status'=>$ok?'PASS':'FAIL');};
$check('active_lot_index_used',$plan[0]['key']==='active_lots'&&(int)$plan[0]['rows']<10);
$check('exhausted_lots_excluded',$before['result']===5001&&$after['result']===1);
$check('reporting_total_preserved',$history_before['result']===100&&$history_after['result']===100);
$check('read_balance_preserved',$wallet->read($uid)['available']===100);
$check('exhausted_lots_preserved_in_ledger',(int)$db->one('SELECT COUNT(*) AS n FROM '.$db->table('lot').' WHERE user_id=?',array($uid))['n']===5001&&count(Nodevora_Financial_Compatibility::lots($uid))===1);
global $wpdb;
if(!(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE user_id=%d',$wpdb->prefix.'rewardly_loyalty_log',$uid))){for($i=0;$i<3;$i++){$wpdb->insert($wpdb->prefix.'rewardly_loyalty_log',array('user_id'=>$uid,'order_id'=>0,'type'=>'earn','points'=>0,'amount_dh'=>0,'note'=>'Mission05 mixed pagination fixture','created_at'=>wp_date('Y-m-d H:i:s',time()+86400)));if(!$wpdb->insert_id){throw new RuntimeException('History fixture failed.');}}}
$seen=array();$cursor=array(PHP_INT_MAX,PHP_INT_MAX);
for($page=0;$page<6;$page++){$rows=Nodevora_Financial_Compatibility::logs($uid,51,$cursor);foreach(array_slice($rows,0,50)as$row){$seen[]=$row->id;$slot=0===strpos((string)$row->id,'v2:')?1:0;$cursor[$slot]=min($cursor[$slot],(int)str_replace('v2:','',(string)$row->id));}}
$check('history_cursor_beyond_200_without_duplicates',count($seen)===300&&count(array_unique($seen))===300&&count(array_filter($seen,static function($id){return 0!==strpos((string)$id,'v2:');}))===3);
$check('bounded_history',count(Nodevora_Financial_Compatibility::logs($uid,11))===11);
// Le lot borné est lu sans exécuter les effets des anciennes données QA.
$db->begin();try{
 $max=(int)$db->one('SELECT COALESCE(MAX(order_id),0) AS id FROM '.$db->table('order_state'))['id'];
 for($i=1;$i<=65;$i++){$db->query('INSERT INTO '.$db->table('order_state').' (order_id,user_id,intent,state,fingerprint,currency,data,updated_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())',array($max+$i,$uid,$run.':'.$i,'dispatched',str_repeat('a',64),'MAD','{}'));}
 // La méthode ouvre sa connexion : les données sont rendues visibles puis conservées comme fixtures identifiées.
 $db->commit();
}catch(Throwable $e){$db->rollback();throw $e;}
$a=Nodevora_Financial_Recovery::order_batch($max);$b=Nodevora_Financial_Recovery::order_batch((int)end($a)['order_id']);
$check('recovery_first_batch_bounded',count($a)===50);
$check('recovery_second_batch_reachable',count($b)===15&&(int)$b[0]['order_id']===$max+51);
$check('recovery_no_repeated_order',!array_intersect(array_column($a,'order_id'),array_column($b,'order_id')));
// Suppression limitée aux états factices sans commande WooCommerce, jamais aux écritures financières.
$db->query('DELETE FROM '.$db->table('order_state').' WHERE user_id=? AND order_id>? AND order_id<=?',array($uid,$max,$max+65));
$db->close();
echo json_encode(array('fixture_user'=>$uid,'zero_lots'=>5000,'journal_rows'=>5001,'active_lot_query_plan'=>$plan,'measurements'=>array('lots_before'=>$before,'lots_after'=>$after,'reporting_before'=>$history_before,'reporting_after'=>$history_after),'tests'=>$tests),JSON_PRETTY_PRINT);
