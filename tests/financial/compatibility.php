<?php
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='http://localhost:10004'){throw new RuntimeException('Dedicated test site required.');}
if(class_exists('Rewardly_Loyalty_Helpers')){throw new RuntimeException('Run with --skip-plugins=nodevora-customer-rewards-for-woocommerce.');}
require_once getenv('NODEVORA_TEST_SOURCE').'/nodevora-customer-rewards-for-woocommerce.php';
$db=new Nodevora_Financial_DB();$row=$db->one('SELECT user_id FROM '.$db->table('wallet').' WHERE state=? AND migration_version=1 ORDER BY user_id DESC LIMIT 1',array('ready'));$u=(int)$row['user_id'];$old=(int)get_user_meta($u,'rewardly_loyalty_points_balance',true);$w=new Nodevora_Financial_Wallet();$w->apply($u,'compatibility:'.bin2hex(random_bytes(6)),'debit',5);$expected=$w->read($u)['available'];$tests=array();
$tests[]=array('test'=>'legacy_default_unchanged','status'=>Rewardly_Loyalty_Helpers::get_user_points($u)===$old?'PASS':'FAIL');
define('NODEVORA_FINANCIAL_MODE','wallet');$tests[]=array('test'=>'public_helper_authoritative_read','status'=>Rewardly_Loyalty_Helpers::get_user_points($u)===$expected?'PASS':'FAIL');wp_set_current_user($u);$html=do_shortcode('[rewardly_points_balance]');
// Init registration can be called directly in this isolated, plugin-skipped CLI process.
Rewardly_Loyalty_Shortcodes::init();$html=do_shortcode('[rewardly_points_balance]');$tests[]=array('test'=>'balance_shortcode_compatibility','status'=>false!==strpos($html,(string)$expected.' points')?'PASS':'FAIL');
try{Rewardly_Loyalty_Helpers::add_points($u,10);$tests[]=array('test'=>'legacy_mutator_no_fallback','status'=>'FAIL');}catch(Nodevora_Financial_Error $e){$tests[]=array('test'=>'legacy_mutator_no_fallback','status'=>get_user_meta($u,'rewardly_loyalty_points_balance',true)===(string)$old?'PASS':'FAIL');}
wp_set_current_user(0);$html=do_shortcode('[rewardly_points_balance]');$tests[]=array('test'=>'guest_shortcode_no_balance','status'=>false===strpos($html,(string)$expected.' points')?'PASS':'FAIL');echo json_encode(array('tests'=>$tests),JSON_PRETTY_PRINT);
