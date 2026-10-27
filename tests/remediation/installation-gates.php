<?php
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'){throw new RuntimeException('Dedicated test site required.');}
if(defined('NODEVORA_FINANCIAL_MODE')){throw new RuntimeException('Run with the mode constant temporarily absent; preserve the real authority option.');}
add_filter('pre_option_nodevora_financial_authority',static function(){return 'legacy';});
require_once getenv('NODEVORA_TEST_SOURCE').'/nodevora-customer-rewards-for-woocommerce.php';
$tests=array();$check=static function($id,$ok)use(&$tests){$tests[]=array('test'=>$id,'status'=>$ok?'PASS':'FAIL');};$run=bin2hex(random_bytes(6));
$u=wp_insert_user(array('user_login'=>'qa_m05_gate_'.$run,'user_pass'=>wp_generate_password(32),'user_email'=>$run.'@example.invalid','role'=>'customer'));update_user_meta($u,'rewardly_loyalty_points_balance',100);$before=get_user_meta($u);
$check('unconfigured_read_only_balance_preserved',Rewardly_Loyalty_Helpers::get_user_points($u)===100&&!Nodevora_Financial_Compatibility::selected());
foreach(array('set_user_points'=>array($u,999),'set_user_point_lots'=>array($u,array()),'add_points'=>array($u,50),'subtract_points'=>array($u,50),'subtract_points_exact'=>array($u,50)) as $name=>$args){try{call_user_func_array(array('Rewardly_Loyalty_Helpers',$name),$args);$check('unconfigured_'.$name.'_closed',false);}catch(Nodevora_Financial_Error $e){$check('unconfigured_'.$name.'_closed',get_user_meta($u)===$before);}}
Rewardly_Loyalty_Points::maybe_award_registration_bonus($u);$check('unconfigured_registration_no_credit',get_user_meta($u,'rewardly_loyalty_points_balance',true)==='100');
$o=wc_create_order(array('customer_id'=>$u));$o->update_meta_data('_rewardly_loyalty_points_spent',100);$o->set_total('0');$o->save();Rewardly_Loyalty_Points::maybe_reward_points($o->get_id());Rewardly_Loyalty_Points::maybe_handle_order_reversal($o->get_id());$check('unconfigured_callbacks_no_legacy_mutations',get_user_meta($u,'rewardly_loyalty_points_balance',true)==='100');
wp_set_current_user($u);WC()->initialize_session();WC()->session->set('rewardly_use_points','yes');WC()->session->set('rewardly_requested_points',100);$cart=new WC_Cart();Rewardly_Loyalty_Redeem::apply_discount($cart);$check('unconfigured_cart_has_no_discount',!$cart->get_fees()&&(int)WC()->session->get('rewardly_applied_points')===0);
try{Nodevora_Financial_Checkout::classic($o->get_id(),array(),$o);$check('unconfigured_classic_zero_total_rejected',false);}catch(Exception $e){$check('unconfigured_classic_zero_total_rejected',true);}
try{Nodevora_Financial_Checkout::blocks($o);$check('unconfigured_store_api_rejected',false);}catch(\Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e){$check('unconfigured_store_api_rejected',true);}
WC()->session->__unset('nodevora_financial_quote_error');try{Nodevora_Financial_Checkout::gate($o,true);$check('unconfigured_existing_order_pay_rejected',false);}catch(Nodevora_Financial_Error $e){$check('unconfigured_existing_order_pay_rejected',true);}
$cash=wc_create_order(array('customer_id'=>$u));$cash->set_total('100');$cash->save();try{Nodevora_Financial_Checkout::gate($cash);$check('ordinary_cash_order_unaffected',true);}catch(Throwable $e){$check('ordinary_cash_order_unaffected',false);}
$check('readiness_explains_setup',Nodevora_Financial_Readiness::state()==='setup_required');ob_start();Nodevora_Financial_Readiness::notice();$notice=ob_get_clean();$check('customer_cannot_read_admin_diagnostics',$notice==='');
$db=new Nodevora_Financial_DB();$check('no_financial_events_before_cutover',(int)$db->one('SELECT COUNT(*) AS n FROM '.$db->table('event').' WHERE user_id=?',array($u))['n']===0);
echo json_encode(array('tests'=>$tests),JSON_PRETTY_PRINT);
