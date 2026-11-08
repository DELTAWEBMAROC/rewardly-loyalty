<?php
/** Adapts the validated 4.8.6 presentation to the native V2 core. */
if (!defined('ABSPATH')) { exit; }
define('REWARDLY_LOYALTY_PATH',dirname(NODEVORA_FILE).'/');
define('REWARDLY_LOYALTY_URL',plugin_dir_url(NODEVORA_FILE));
define('NODEVORA_CUSTOMER_REWARDS_VERSION',NODEVORA_VERSION);
foreach (array('design/class-rewardly-loyalty-design','core/class-rewardly-loyalty-helpers','core/class-rewardly-loyalty-pro','core/class-rewardly-loyalty-emails','core/class-rewardly-loyalty-redeem','admin/class-nodevora-access','admin/class-nodevora-design-admin','admin/class-rewardly-loyalty-settings','admin/class-rewardly-loyalty-admin-adjustments','admin/class-rewardly-loyalty-admin','front/class-rewardly-loyalty-account','front/class-rewardly-loyalty-product','shortcodes/class-rewardly-loyalty-shortcodes') as $nodevora_module) { require_once __DIR__.'/presentation/'.$nodevora_module.'.php'; }
require_once __DIR__.'/class-nodevora-data.php';
class Nodevora_Parity {
 public static function starts($text,$prefix) { return 0 === strpos($text,$prefix); }
 public static function core_settings() {
  $s=Rewardly_Loyalty_Helpers::get_settings();$v=Nodevora_Settings::defaults();
  $map=array('earn_rate'=>'earn_points_per_dh','redeem_rate'=>'redeem_points_per_dh','minimum'=>'min_points_to_redeem','maximum_discount'=>'max_discount_per_order','trigger'=>'order_status_trigger','expiration_days'=>'points_expiration_days','registration_bonus'=>'pro_points_registration','first_order_bonus'=>'pro_points_first_order','review_bonus'=>'pro_points_review','radius'=>'border_radius','notice_mode'=>'notice_display_mode','bronze'=>'pro_level_bronze_threshold','silver'=>'pro_level_silver_threshold','gold'=>'pro_level_gold_threshold');
  foreach($map as $to=>$from){$v[$to]=in_array($to,array('trigger','notice_mode'),true)?$s[$from]:('maximum_discount'===$to?(float)$s[$from]:(int)$s[$from]);}
  foreach(array('enabled'=>'enabled','front_styles'=>'enable_front_styles','exclude_sale'=>'pro_exclude_sale_products','levels'=>'pro_levels_enabled')as$to=>$from){$v[$to]='yes'===$s[$from]?1:0;}
  foreach(array('primary_color','accent_color')as$key){$v[$key]=$s[$key];}
  return $v;
 }
 public static function init() {
  global $wp_textdomain_registry;if(isset($wp_textdomain_registry)){ $wp_textdomain_registry->set_custom_path('nodevora-customer-rewards-for-woocommerce',REWARDLY_LOYALTY_PATH.'languages'); }
  foreach(array('Nodevora_Access','Rewardly_Loyalty_Settings','Rewardly_Loyalty_Admin','Rewardly_Loyalty_Admin_Adjustments','Rewardly_Loyalty_Account','Rewardly_Loyalty_Product','Rewardly_Loyalty_Shortcodes','Rewardly_Loyalty_Pro')as$class){$class::init();}
  Nodevora_Data::init();
  add_action('template_redirect',array(__CLASS__,'request'));
  add_action('wp_enqueue_scripts',array(__CLASS__,'assets'));
  add_action('woocommerce_before_cart',static function(){echo wp_kses(self::card('cart'),self::card_html());});
  add_action('woocommerce_before_checkout_form',static function(){echo wp_kses(self::card('checkout'),self::card_html());});
  add_action('nodevora_v2_committed',array(__CLASS__,'notify'));
  add_action('nodevora_v2_email',array(__CLASS__,'notify'));
  add_action('admin_post_nodevora_v2_refund',array('Nodevora_Admin','refund'));
  add_action('admin_post_nodevora_v2_retry',array('Nodevora_Admin','retry'));
  require_once __DIR__.'/presentation/front/class-nodevora-loyalty-blocks.php';Nodevora_Loyalty_Blocks::init();
 }
 public static function card_html() {
 $allowed=wp_kses_allowed_html('post');foreach(array('form','input','button','label')as$tag){$allowed[$tag]=array_fill_keys(array('action','method','class','id','name','type','value','min','max','step','required','for','aria-label','aria-live','disabled'),true);}return $allowed;
 }
 public static function card($context) {
  $mode=Rewardly_Loyalty_Redeem::get_notice_display_mode();
  if('shortcode'===$mode){return '';}
  return $mode==='both'||$mode===$context?Rewardly_Loyalty_Redeem::get_loyalty_card_html($context):Rewardly_Loyalty_Redeem::render_notice_shortcode(array('context'=>$context));
 }
 public static function request() {
  if(is_user_logged_in()){wc_maybe_define_constant('DONOTCACHEPAGE',true);nocache_headers();}
  if(!isset($_POST['rewardly_loyalty_action'])){return;}
  if(!is_user_logged_in()||'POST'!==(isset($_SERVER['REQUEST_METHOD'])?sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])):'')){wp_die('', '',array('response'=>403));}
  check_admin_referer('rewardly_loyalty_apply','_rewardly_nonce');
  $action=sanitize_key(wp_unslash($_POST['rewardly_loyalty_action']));
  try{if(!in_array($action,array('apply','remove'),true)){throw new RuntimeException('Invalid action');}Nodevora_Commerce::select('remove'===$action?'0':(isset($_POST['rewardly_points_to_use'])?sanitize_text_field(wp_unslash($_POST['rewardly_points_to_use'])):''));}
  catch(Throwable $e){wc_add_notice($e->getMessage(),'error');}
  wp_safe_redirect(is_checkout()?wc_get_checkout_url():wc_get_cart_url());exit;
 }
 public static function assets() {
  if('yes'===Rewardly_Loyalty_Helpers::get_settings()['enable_front_styles']){wp_enqueue_style('rewardly-loyalty',REWARDLY_LOYALTY_URL.'assets/css/loyalty.css',array(),NODEVORA_VERSION);wp_add_inline_style('rewardly-loyalty',Rewardly_Loyalty_Design::get_front_inline_css());}
 }
 public static function notify($event) {
  if(is_numeric($event)){ $rows=Nodevora_Store::rows('snapshot',array(Nodevora_Store::table('events'),Nodevora_Backup::MAX_ROWS));foreach($rows as$row){if((int)$row['id']===(int)$event){$event=$row;break;}} }
  if(!is_array($event)||!(int)$event['delta']||!Rewardly_Loyalty_Helpers::email_notifications_enabled()){return;}
  $key='nodevora_v2_mail_'.(int)$event['id'];if(!add_option($key,'sending','',false)){return;}
  $types=array('credit'=>'earn','debit'=>'spend','reverse'=>'revoke','restore'=>'restore','expire'=>'expire');$type=$types[$event['kind']]??'adjust';
  if(self::starts($event['operation_key'],'adjust:')){$type=$event['delta']>0?'adjust_add':'adjust_remove';}
  $p=json_decode($event['payload'],true);
  try{$ok=Rewardly_Loyalty_Emails::maybe_send_points_notification((int)$event['user_id'],$type,abs((int)$event['delta']),Rewardly_Loyalty_Helpers::convert_points_to_amount(abs((int)$event['delta'])),$p['details']['note']??'','nodevora-event-'.$event['id']);}catch(Throwable$e){$ok=false;}
  if($ok){update_option($key,'sent',false);}else{delete_option($key);if(!wp_next_scheduled('nodevora_v2_email',array((int)$event['id']))){wp_schedule_single_event(time()+300,'nodevora_v2_email',array((int)$event['id']));}}
 }
 public static function render_reviews() {
  if(!current_user_can('manage_options')){return;}
  // Retain the V2 review actions, without the replaced raw settings page.
  Nodevora_Admin::reviews();
 }
}

/** Presentation-only estimate; awards remain in Nodevora_Commerce. */
class Rewardly_Loyalty_Points {
 public static function get_potential_first_order_bonus($order){ return Nodevora_Store::event('user:'.$order->get_user_id().':first-order')?0:(int)Nodevora_Settings::get()['first_order_bonus']; }
}
