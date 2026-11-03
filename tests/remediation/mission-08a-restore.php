<?php
/** Régressions de provenance sur nouvelles copies Mission08A sauvegardées exclusivement. */
if(!defined('WP_CLI')||!WP_CLI||strpos(DB_NAME,'nodevora_m08a_')!==0||home_url()!=='http://127.0.0.1:18201'||!getenv('M08A_PRIVATE')){throw new RuntimeException('Isolated backed-up Mission08A fixture required.');}
wp_set_current_user(1);global$wpdb;$db=new Nodevora_Financial_DB();$tests=array();$run=bin2hex(random_bytes(4));$private=getenv('M08A_PRIVATE');
$check=static function($id,$ok)use(&$tests){$tests[]=array('test'=>$id,'status'=>$ok?'PASS':'FAIL');if(!$ok){throw new RuntimeException('Assertion: '.$id);}};
$maps=Nodevora_Backup::table_map($db)+array('user_meta'=>$wpdb->usermeta,'post_meta'=>$wpdb->postmeta,'comment_meta'=>$wpdb->commentmeta,'order_meta'=>$wpdb->prefix.'wc_orders_meta','item_meta'=>$wpdb->prefix.'woocommerce_order_itemmeta');
$fingerprint=static function()use($db,$maps){$a=array();foreach($maps as$k=>$t){$where=substr($k,-5)==='_meta'?" WHERE meta_key REGEXP '^(rewardly_loyalty_|_rewardly|_nodevora)'":'';$rows=array_map('json_encode',$db->query('SELECT * FROM '.$t.$where));sort($rows);$a[$k]=hash('sha256',implode("\n",$rows));}return$a;};
$clear=static function()use($db,$maps){$db->begin();try{foreach($maps as$k=>$t){$db->query('DELETE FROM '.$t.(substr($k,-5)==='_meta'?' WHERE meta_key REGEXP ?':''),substr($k,-5)==='_meta'?array('^(rewardly_loyalty_|_rewardly|_nodevora)'):array());}$db->commit();wp_cache_flush();}catch(Throwable$e){$db->rollback();throw$e;}};
$export=static function($name)use($private,$run){$path=$private.'/m08a-'.$run.'-'.$name.'.ndjson';$f=Nodevora_Backup::create();file_put_contents($path,stream_get_contents($f));fclose($f);chmod($path,0600);return$path;};
$import=static function($p){return Nodevora_Restore::apply($p,Nodevora_Backup::inspect($p)['sha256']);};
$reject=static function($id,$f)use($check){$denied=false;try{$f();}catch(Throwable$e){$denied=true;}$check($id,$denied);};
$settings=get_option('rewardly_loyalty_settings');$initial=Nodevora_Financial_Setup::state();$check('source_is_genuine_fresh_active',$initial['phase']==='active'&&$initial['origin']==='fresh');
Nodevora_Installation::ensure_wallet(1);$w=new Nodevora_Financial_Wallet();$w->apply(1,'m08a:'.$run.':credit','credit',300,array('type'=>'adjust_add'));$w->apply(1,'m08a:'.$run.':debit','debit',119,array('type'=>'redeem'));
$q=wp_insert_user(array('user_login'=>'m08a_review_'.$run,'user_pass'=>wp_generate_password(32),'role'=>'customer'));$db->query('UPDATE '.$db->table('wallet').' SET state=? WHERE user_id=?',array('needs_review',$q));
$event=$db->one('SELECT id FROM '.$db->table('event').' WHERE user_id=1 ORDER BY id LIMIT 1');$db->query('INSERT INTO '.$db->table('outbox').' (event_id,user_id,effect,payload) VALUES (?,?,?,?)',array($event['id'],1,'unknown_m08a','{}'));
$order=wc_create_order(array('customer_id'=>1));$order->update_meta_data('_nodevora_m08a_fixture','reference');$order->save();$fee=new WC_Order_Item_Fee();$fee->set_name('Synthetic restore reference');$fee->set_total('0');$fee->add_meta_data('_nodevora_wallet_fee','v1');$order->add_item($fee);$order->save();update_user_meta(1,'_nodevora_m08a_fixture','retained');
$before=$fingerprint();$active=$export('active');$source_records=array_map(static function($line){return json_decode($line,true);},file($active));
foreach(array('active','paused')as$phase){
 $s=$initial;$s['phase']=$phase;update_option(Nodevora_Financial_Setup::OPTION,$s,false);$path=$export($phase.'-source');$clear();$import($path);
 $check($phase.'_import_preserves_records',$fingerprint()===$before);$check($phase.'_requires_new_review',Nodevora_Financial_Setup::state()['phase']==='restore_review'&&Nodevora_Restore::can_resume_review());
 Nodevora_Restore::resume_reviewed(false);$check($phase.'_explicit_resume',Nodevora_Financial_Setup::state()['phase']==='active'&&empty(Nodevora_Financial_Setup::state()['recovery_approved']));
 $check($phase.'_records_unchanged_after_resume',$fingerprint()===$before);
}
$clear();$import($active);
for($i=1;$i<=3;$i++){
 $path=$export('review-depth-'.$i);$clear();$import($path);$s=Nodevora_Financial_Setup::state();
 $check('review_'.$i.'_root_retained',$s['phase']==='restore_review'&&$s['restore_previous_phase']==='active'&&$s['restore_previous_authority']==='wallet'&&empty($s['recovery_approved']));
 $check('review_'.$i.'_records_identical',$fingerprint()===$before);
 $reject('review_'.$i.'_duplicate_import_refused',static function()use($import,$path){$import($path);});
}
Nodevora_Restore::resume_reviewed();$check('nested_review_resumes_explicitly',Nodevora_Financial_Setup::state()['phase']==='active');$check('nested_review_preserves_invariants',(new Nodevora_Financial_Migration())->verify(1)['verified']&&$w->read(1)['balance']===181);
$reject('quarantined_wallet_stays_blocked',static function()use($q,$w,$run){$w->apply($q,'m08a:'.$run.':quarantine','debit',1);});
$check('quarantine_unchanged',$db->one('SELECT state,balance FROM '.$db->table('wallet').' WHERE user_id=?',array($q))['state']==='needs_review');
$effects=$db->query('SELECT * FROM '.$db->table('outbox').' ORDER BY id');do_action('nodevora_financial_recover');$check('pending_unknown_outbox_not_executed',$db->query('SELECT * FROM '.$db->table('outbox').' ORDER BY id')===$effects&&empty(Nodevora_Financial_Setup::state()['recovery_approved']));
$reject('active_wallet_with_new_activity_refuses_restore',static function()use($import,$active){$import($active);});$check('dangerous_import_has_no_mutation',$fingerprint()===$before);
$write=static function($records,$suffix)use($private,$run){array_pop($records);$raw='';$rows=0;foreach($records as$r){$raw.=json_encode($r,JSON_UNESCAPED_UNICODE)."\n";if(isset($r['table'])){$rows++;}}$raw.=json_encode(array('complete'=>true,'rows'=>$rows,'sha256'=>hash('sha256',$raw)))."\n";$p=$private.'/m08a-'.$run.'-'.$suffix.'.ndjson';file_put_contents($p,$raw);chmod($p,0600);return$p;};
$set_source=static function($state,$authority='wallet')use($source_records){$a=$source_records;foreach($a as&$r){if(($r['option']??'')===Nodevora_Financial_Setup::OPTION){$r['value']=serialize($state);}if(($r['option']??'')==='nodevora_financial_authority'){$r['value']=$authority;}}unset($r);return$a;};
foreach(array(
 'incomplete_migration'=>array('phase'=>'migrating','origin'=>'synchronized','scan_complete'=>false,'batch'=>array(array('user_id'=>1))),
 'paused_incomplete_migration'=>array('phase'=>'paused','origin'=>'synchronized','scan_complete'=>false),
 'false_review_root'=>array('phase'=>'restore_review','origin'=>'fresh','restore_previous_phase'=>'migrating','restore_previous_authority'=>'wallet','restore_hash'=>str_repeat('a',64),'restored_at'=>gmdate('c')),
 'lost_legacy_provenance'=>array('phase'=>'restore_review','origin'=>'fresh','restore_previous_phase'=>'active','restore_hash'=>str_repeat('a',64),'restored_at'=>gmdate('c')),
 'unprovable_nested_legacy_review'=>array('phase'=>'restore_review','origin'=>'fresh','restore_previous_phase'=>'restore_review'),
 'unknown_origin'=>array('phase'=>'active','origin'=>'unverified'),
 'paused_disguised_review'=>array('phase'=>'paused','origin'=>'fresh','restore_previous_phase'=>'migrating','restore_previous_authority'=>'wallet','restore_hash'=>str_repeat('a',64),'restored_at'=>gmdate('c')),
 'active_unverified_review'=>array('phase'=>'active','origin'=>'fresh','restore_previous_phase'=>'active'),
 'invalid_review_marker'=>array('phase'=>'restore_review','origin'=>'fresh','restore_previous_phase'=>'active','restore_previous_authority'=>'wallet','restore_hash'=>'invalid','restored_at'=>gmdate('c')),
 )as$id=>$state){$path=$write($set_source($state),$id);$clear();$import($path);$h=$fingerprint();$reject($id.'_resume_refused',static function(){Nodevora_Restore::resume_reviewed();});$check($id.'_safe_and_preserved',!Nodevora_Restore::can_resume_review()&&Nodevora_Financial_Setup::state()['phase']==='restore_review'&&$fingerprint()===$h);}
$path=$write($set_source($initial,'legacy'),'legacy-authority');$clear();$import($path);$reject('unvalidated_source_authority_refused',static function(){Nodevora_Restore::resume_reviewed();});
$clear();$import($active);update_option('nodevora_financial_authority','legacy',false);$reject('changed_target_authority_refused',static function(){Nodevora_Restore::resume_reviewed();});update_option('nodevora_financial_authority','wallet',false);
$clear();$corrupt=$private.'/m08a-'.$run.'-corrupt.ndjson';file_put_contents($corrupt,str_replace('reference','tampered',file_get_contents($active)));$reject('corrupt_file_refused',static function()use($import,$corrupt){$import($corrupt);});
$path=$write($source_records,'valid-reencoded');$hash=Nodevora_Backup::inspect($path)['sha256'];$reject('preview_hash_mismatch_refused',static function()use($path){Nodevora_Restore::apply($path,str_repeat('0',64));});
$trigger='m08a_import_failure';try{
 $wpdb->query("CREATE TRIGGER ".$trigger." BEFORE INSERT ON ".$db->table('event')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='M08A controlled import failure'");
 $reject('real_sql_failure_refused',static function()use($path,$hash){Nodevora_Restore::apply($path,$hash);});
 $check('real_sql_failure_rolls_back_all_rows',(int)$db->one('SELECT COUNT(*) n FROM '.$db->table('wallet'))['n']===0&&(int)$db->one('SELECT COUNT(*) n FROM '.$db->table('event'))['n']===0);
 $check('real_sql_failure_keeps_pause',Nodevora_Financial_Setup::state()['phase']==='restoring');
}finally{$wpdb->query('DROP TRIGGER IF EXISTS '.$trigger);}
$import($active);Nodevora_Restore::resume_reviewed(false);$check('retry_after_sql_failure_preserves_all_records',$fingerprint()===$before);$check('business_settings_unchanged',get_option('rewardly_loyalty_settings')===$settings);
$s=Nodevora_Financial_Setup::state();$s['phase']='paused';$s['recovery_approved']=false;update_option(Nodevora_Financial_Setup::OPTION,$s,false);$db->close();
echo json_encode(array('run'=>$run,'tests'=>$tests,'final_phase'=>'paused','original_balance'=>181,'outbox_preserved'=>true),JSON_PRETTY_PRINT);
