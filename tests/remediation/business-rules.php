<?php
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'){throw new RuntimeException('Dedicated test site required.');}
require_once getenv('NODEVORA_TEST_SOURCE').'/nodevora-customer-rewards-for-woocommerce.php';
Rewardly_Loyalty_Settings::init();
$tests=array();$check=static function($id,$ok)use(&$tests){$tests[]=array('test'=>$id,'status'=>$ok?'PASS':'FAIL');};
$s=Rewardly_Loyalty_Helpers::get_default_settings();$s['enabled']='no';$s['email_notifications']='no';$s['pro_excluded_product_ids']=array(16);$s['pro_excluded_category_ids']=array(999);$s['pro_category_rules']=array(array('term_id'=>999,'rate'=>8));$s['pro_levels_enabled']='yes';$s['delete_data_on_uninstall']='yes';$s['future_setting']='preserve';
$filter=static function()use(&$s){return $s;};add_filter('pre_option_rewardly_loyalty_settings',$filter);
foreach(array('general'=>array('earn_points_per_dh'=>2),'design'=>array('primary_color'=>'#123456'),'rules'=>array('pro_exclude_sale_products'=>'yes'),'bonuses'=>array('pro_points_review'=>9),'pending'=>array('pro_pending_points_notice_text'=>'Updated'),'levels'=>array('pro_level_gold_threshold'=>900)) as $scope=>$changes){
 $actual=Rewardly_Loyalty_Settings::sanitize_settings(array_merge(array('_section'=>$scope),$changes));$expected=$s;
 foreach($changes as $k=>$v){$expected[$k]=$v;}if('rules'===$scope){$expected['pro_excluded_product_ids']=array();$expected['pro_excluded_category_ids']=array();$expected['pro_category_rules']=array();}if('pending'===$scope){$expected['pro_pending_points_statuses']=array();}
 $check('settings_'.$scope.'_isolated',$actual===$expected);
}
$a=Rewardly_Loyalty_Settings::sanitize_settings(array('_section'=>'design','enabled'=>'yes','pro_excluded_product_ids'=>array(),'primary_color'=>'#112233'));$check('settings_cross_section_injection_rejected',$a['enabled']==='no'&&$a['pro_excluded_product_ids']===array(16));
$a=Rewardly_Loyalty_Settings::sanitize_settings(array('earn_points_per_dh'=>'-3','points_expiration_days'=>array(5),'pro_pending_points_statuses'=>array(array('bad')),'max_discount_per_order'=>'1e30'));$check('settings_malformed_preserved',$a===$s);
$check('programmatic_partial_merge',Rewardly_Loyalty_Settings::sanitize_settings(array('accent_color'=>'#224466'))===array_replace($s,array('accent_color'=>'#224466')));
$check('settings_save_capability',apply_filters('option_page_capability_rewardly_loyalty_group','manage_options')==='manage_woocommerce');
$s['enabled']='yes';$s['pro_excluded_category_ids']=array();$s['pro_category_rules']=array();$s['pro_excluded_product_ids']=array();
$p=new WC_Product_Variable();$p->set_name('Mission05 temporary variable');$p->save();$v=new WC_Product_Variation();$v->set_parent_id($p->get_id());$v->set_regular_price('10');$v->save();
try {
 update_post_meta($p->get_id(),'_rewardly_loyalty_product_points_rate','10');$check('variation_parent_rate',Rewardly_Loyalty_Helpers::get_effective_product_earning_rate($v)===10);
 update_post_meta($v->get_id(),'_rewardly_loyalty_product_points_rate','3');$check('variation_own_override',Rewardly_Loyalty_Helpers::get_effective_product_earning_rate($v)===3);
 update_post_meta($v->get_id(),'_rewardly_loyalty_product_points_rate','0');$check('variation_zero_override',Rewardly_Loyalty_Helpers::get_effective_product_earning_rate($v)===0);
 delete_post_meta($v->get_id(),'_rewardly_loyalty_product_points_rate');update_post_meta($p->get_id(),'_rewardly_loyalty_product_points_rate','0');$check('variation_parent_zero',Rewardly_Loyalty_Helpers::get_effective_product_earning_rate($v)===0);
 update_post_meta($p->get_id(),'_rewardly_loyalty_product_points_rate','10');$s['pro_excluded_product_ids']=array($p->get_id());$check('variation_parent_exclusion',Rewardly_Loyalty_Helpers::get_effective_product_earning_rate($v)===0);
 $s['pro_excluded_product_ids']=array();$s['pro_exclude_sale_products']='yes';$v->set_sale_price('8');$check('sale_variation_exclusion',Rewardly_Loyalty_Helpers::get_effective_product_earning_rate($v)===0);
 $s['pro_exclude_sale_products']='no';$check('variation_actual_points',Rewardly_Loyalty_Helpers::calculate_product_earned_points($v,'16')===160);
} finally { $v->delete(true);$p->delete(true); }
$method=new ReflectionMethod('Rewardly_Loyalty_Redeem','get_request_method');$method->setAccessible(true);$_SERVER['REQUEST_METHOD']='POST';$check('post_method_superglobal',$method->invoke(null)==='POST');$_SERVER['REQUEST_METHOD']='GET';$check('get_remains_get',$method->invoke(null)==='GET');
$validate=new ReflectionMethod('Rewardly_Loyalty_Redeem','validate_requested_points');$validate->setAccessible(true);foreach(array('999999999999999999999999',array(1),'-3','1e3','1.2') as $i=>$bad){$check('input_rejected_'.$i,is_wp_error($validate->invoke(null,$bad,PHP_INT_MAX,PHP_INT_MAX)));}
$check('valid_integer_accepted',$validate->invoke(null,'119',200,200)===119);
foreach(array('UTC','Africa/Casablanca','Europe/Paris') as $tz){$tzfilter=static function()use($tz){return $tz;};add_filter('pre_option_timezone_string',$tzfilter);$legacy=(object)array('created_at'=>'2026-10-27 14:00:00');$check('legacy_timezone_'.$tz,Rewardly_Loyalty_Helpers::format_log_date($legacy)==='27/10/2026 14:00');$log=(object)array('created_at_utc'=>'2026-10-27 14:00:00');$expected=wp_date('d/m/Y H:i',strtotime('2026-10-27 14:00:00 UTC'),new DateTimeZone($tz));$check('journal_timezone_'.$tz,Rewardly_Loyalty_Helpers::format_log_date($log)===$expected);remove_filter('pre_option_timezone_string',$tzfilter);}
foreach(array('earn'=>'+','restore'=>'+','bonus_review'=>'+','spend'=>'-','revoke'=>'-','expire'=>'-','adjust_remove'=>'-','adjust_add'=>'+') as $type=>$sign){$check('history_sign_'.$type,Rewardly_Loyalty_Helpers::log_sign((object)array('type'=>$type,'points'=>5))===$sign);}
$s['enabled']='no';$u=wp_insert_user(array('user_login'=>'qa_m05_disabled_'.bin2hex(random_bytes(4)),'user_pass'=>wp_generate_password(32),'role'=>'customer'));Rewardly_Loyalty_Points::maybe_award_registration_bonus($u);$check('disabled_no_pending_bonus',get_user_meta($u,'_nodevora_registration_pending',true)==='');
echo json_encode(array('tests'=>$tests),JSON_PRETTY_PRINT);
