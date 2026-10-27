<?php
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'){throw new RuntimeException('Dedicated test site required.');}
require_once getenv('NODEVORA_TEST_SOURCE').'/nodevora-customer-rewards-for-woocommerce.php';
define('NODEVORA_FINANCIAL_MIGRATION_ALLOWED',true);define('NODEVORA_FINANCIAL_WRITERS_DRAINED',true);define('NODEVORA_FINANCIAL_INTEGRATION_ALLOWED',true);
$tests=array();$check=function($id,$ok,$actual=null)use(&$tests){$tests[]=array('test'=>$id,'status'=>$ok?'PASS':'FAIL','actual'=>$actual);};
$run=bin2hex(random_bytes(6));$u=wp_insert_user(array('user_login'=>'qa_checkout_'.$run,'user_pass'=>wp_generate_password(32),'user_email'=>$run.'@example.invalid','role'=>'customer'));
update_user_meta($u,'rewardly_loyalty_points_balance',1000);update_user_meta($u,'rewardly_loyalty_point_lots',array(array('points_remaining'=>1000,'earned_at'=>current_time('mysql'))));(new Nodevora_Financial_Migration())->migrate($u);define('NODEVORA_FINANCIAL_MODE','wallet');
wp_set_current_user($u);WC()->initialize_session();WC()->session->set('nodevora_quote_policy',Nodevora_Financial_Checkout::policy());
$make=function($discount)use($u){$o=wc_create_order(array('customer_id'=>$u));$o->set_currency(get_woocommerce_currency());$item=new WC_Order_Item_Product();$item->set_product(wc_get_product(12));$item->set_subtotal('100');$item->set_total('100');$o->add_item($item);$fee=new WC_Order_Item_Fee();$fee->set_name('Loyalty discount');$fee->set_amount('-'.$discount);$fee->set_total('-'.$discount);$fee->add_meta_data('_nodevora_wallet_fee','v1');$o->add_item($fee);$o->calculate_totals(false);Nodevora_Financial_Checkout::project($o);$o->save();return $o;};
$check('remainder_preserved',Nodevora_Financial_Money::discount(119,20,100)===595&&Nodevora_Financial_Money::charge(595,20,100)===119);
$o=$make('5.95');$q=Nodevora_Financial_Checkout::quote($o);$r=Nodevora_Financial_Checkout::operation($o,'fund');$check('exact_final_fee_funded',$r['amount']===119&&$r['balance']===881&&$q['discount_minor']===595);
$check('fund_replay_same_event',Nodevora_Financial_Checkout::operation($o,'fund')['event_id']===$r['event_id']);
Nodevora_Financial_Checkout::operation($o,'dispatch');$check('durable_dispatch_marker',Nodevora_Financial_Order::read($o->get_id())['state']==='dispatched');
try{Nodevora_Financial_Checkout::operation($o,'dispatch');$check('unknown_dispatch_never_retried',false);}catch(Nodevora_Financial_Unknown $e){$check('unknown_dispatch_never_retried',true);}
Nodevora_Financial_Checkout::operation($o,'compensate',array('original_event_id'=>$r['event_id']));$w=new Nodevora_Financial_Wallet();$check('failed_payment_compensated_once',$w->read($u)['available']===1000);
Nodevora_Financial_Checkout::operation($o,'compensate',array('original_event_id'=>$r['event_id']));$check('compensation_replay',$w->read($u)['available']===1000);
try{Nodevora_Financial_Checkout::operation($o,'fund');$check('restored_debit_not_authorization',false);}catch(Nodevora_Financial_Error $e){$check('restored_debit_not_authorization',true);}
Nodevora_Financial_Checkout::gate($o,true);$check('retry_fresh_intent_fresh_debit',$w->read($u)['available']===881&&Nodevora_Financial_Order::read($o->get_id())['debit_event_id']!=$r['event_id']);
$bad=$make('100');try{Nodevora_Financial_Checkout::classic($bad->get_id(),array(),$bad);$check('classic_insufficient_controlled_exception',false);}catch(Exception $e){$check('classic_insufficient_controlled_exception',!Nodevora_Financial_Order::read($bad->get_id()));}
try{Nodevora_Financial_Checkout::blocks($bad);$check('store_api_insufficient_route_exception',false);}catch(\Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e){$check('store_api_insufficient_route_exception',!Nodevora_Financial_Order::read($bad->get_id()));}
$clip=$make('1.00');$check('actual_clipped_fee_reduces_points',Nodevora_Financial_Checkout::quote($clip)['points']===20);
Nodevora_Financial_Checkout::operation($clip,'fund');$fee=current($clip->get_items('fee'));$fee->set_total('-2.00');$fee->save();$clip=wc_get_order($clip->get_id());try{Nodevora_Financial_Checkout::gate($clip);$check('changed_final_fee_blocks_payment',false);}catch(Nodevora_Financial_Error $e){$check('changed_final_fee_blocks_payment',true);}
echo json_encode(array('tests'=>$tests,'scope'=>'Real WC CRUD and shared funding gates; HTTP gateway dispatch acceptance remains separate.'),JSON_PRETTY_PRINT);
