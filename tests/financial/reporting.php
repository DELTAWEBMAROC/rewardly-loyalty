<?php
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'){throw new RuntimeException('Dedicated test site required.');}
require_once getenv('NODEVORA_TEST_SOURCE').'/nodevora-customer-rewards-for-woocommerce.php';
define('NODEVORA_FINANCIAL_MIGRATION_ALLOWED',true);define('NODEVORA_FINANCIAL_WRITERS_DRAINED',true);
$tests=array();$check=function($id,$ok)use(&$tests){$tests[]=array('test'=>$id,'status'=>$ok?'PASS':'FAIL');};
$run=bin2hex(random_bytes(6));$u=wp_insert_user(array('user_login'=>'qa_reports_'.$run,'user_pass'=>wp_generate_password(32),'user_email'=>$run.'@example.invalid','role'=>'customer'));
update_user_meta($u,'rewardly_loyalty_points_balance',100);update_user_meta($u,'rewardly_loyalty_points_earned_total',250);update_user_meta($u,'rewardly_loyalty_points_spent_total',150);
update_user_meta($u,'rewardly_loyalty_point_lots',array(array('id'=>'original','points_remaining'=>100,'earned_at'=>current_time('mysql'),'source_type'=>'earn','order_id'=>0)));
$m=new Nodevora_Financial_Migration();$m->migrate($u);$w=new Nodevora_Financial_Wallet();$w->apply($u,$run.':earn','credit',40,array('type'=>'earn'));$w->apply($u,$run.':adjust','credit',10,array('type'=>'adjust'));$w->apply($u,$run.':spend','debit',20);define('NODEVORA_FINANCIAL_MODE','wallet');
$check('opening_plus_gross_earning',Rewardly_Loyalty_Helpers::get_total_earned($u)===290);
$check('opening_plus_gross_spending',Rewardly_Loyalty_Helpers::get_total_spent($u)===170);
update_user_meta($u,'rewardly_loyalty_points_earned_total',999);$check('stale_projection_not_authoritative',Rewardly_Loyalty_Helpers::get_total_earned($u)===290);
$lots=Rewardly_Loyalty_Helpers::get_user_point_lots($u);$check('authoritative_lots',array_sum(array_column($lots,'points_remaining'))===130&&count($lots)===3);
$logs=Rewardly_Loyalty_Helpers::get_user_logs($u);$check('journal_history_once',count($logs)===3&&count(array_unique(array_column($logs,'id')))===3);
$v=wp_insert_user(array('user_login'=>'qa_legacy_order_'.$run,'user_pass'=>wp_generate_password(32),'user_email'=>'o'.$run.'@example.invalid'));
$o=wc_create_order(array('customer_id'=>$v));$o->update_meta_data('_rewardly_loyalty_spent_processed','yes');$o->update_meta_data('_rewardly_loyalty_points_spent',100);$o->save();$before=$o->get_meta_data();$r=$m->migrate($v);$db=new Nodevora_Financial_DB();$state=$db->one('SELECT * FROM '.$db->table('order_state').' WHERE order_id=?',array($o->get_id()));
$check('ambiguous_order_has_durable_review_baseline',$r['state']==='needs_review'&&$state['state']==='legacy_review'&&null===$state['debit_event_id']);
$check('review_resume_idempotent',$m->migrate($v)['state']==='needs_review');
$check('original_order_flags_preserved',wc_get_order($o->get_id())->get_meta('_rewardly_loyalty_spent_processed')==='yes');
echo json_encode(array('tests'=>$tests),JSON_PRETTY_PRINT);
