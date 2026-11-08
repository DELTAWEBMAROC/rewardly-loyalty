<?php
if(!defined('ABSPATH')){exit;}
/** Preview UI from 4.8.6; V2 archive validation and restoration remain authoritative. */
class Nodevora_Data {
 public static function init(){add_action('wp_ajax_nodevora_backup_preview',array(__CLASS__,'preview'));foreach(array('export','import')as$action){add_action('admin_post_nodevora_data_'.$action,static function()use($action){self::handle($action);});}}
 public static function label(){return __('Data & Backups','nodevora-customer-rewards-for-woocommerce');}
 private static function guard($ajax=false){if(!current_user_can('manage_options')){wp_die('','',array('response'=>403));}if('POST'!==(isset($_SERVER['REQUEST_METHOD'])?sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])):'')){wp_die('','',array('response'=>405));}if($ajax){check_ajax_referer('nodevora_data','_wpnonce');}else{check_admin_referer('nodevora_data');}}
 private static function uploaded($ajax=false){
 if($ajax){check_ajax_referer('nodevora_data','_wpnonce');}else{check_admin_referer('nodevora_data');}
  if(!isset($_FILES['nodevora_backup']['error'],$_FILES['nodevora_backup']['size'],$_FILES['nodevora_backup']['tmp_name'])||UPLOAD_ERR_OK!==$_FILES['nodevora_backup']['error']||$_FILES['nodevora_backup']['size']>Nodevora_Backup::MAX_BYTES){throw new RuntimeException(esc_html__('Choose a V2 backup smaller than 8 MiB.','nodevora-customer-rewards-for-woocommerce'));}
  $path=sanitize_text_field(wp_unslash($_FILES['nodevora_backup']['tmp_name']));if(!is_uploaded_file($path)){throw new RuntimeException(esc_html__('Invalid upload.','nodevora-customer-rewards-for-woocommerce'));}$file=new SplFileObject($path,'rb');return $file->fread(Nodevora_Backup::MAX_BYTES+1);
 }
 private static function token($hash){return hash_hmac('sha256',get_current_user_id().':'.$hash,wp_salt('nonce'));}
 public static function preview(){self::guard(true);check_ajax_referer('nodevora_data','_wpnonce');try{$raw=self::uploaded(true);$d=Nodevora_Backup::validate($raw);$rows=0;foreach($d['tables']as$table){$rows+=count($table);}$hash=hash('sha256',$raw);wp_send_json_success(array('rows'=>$rows,'bytes'=>strlen($raw),'created'=>$d['created'],'format'=>$d['format'],'sha256'=>$hash,'preview_token'=>self::token($hash)));}catch(Throwable$e){wp_send_json_error(array('message'=>$e->getMessage()),400);}}
 public static function handle($action){
  self::guard();check_admin_referer('nodevora_data');try{
   if('export'===$action){$json=Nodevora_Backup::export();nocache_headers();header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="nodevora-v2-backup.json"');header('X-Content-Type-Options: nosniff');wp_send_json(json_decode($json,true));}
   $raw=self::uploaded();$hash=hash('sha256',$raw);$expected=isset($_POST['expected_hash'])?sanitize_text_field(wp_unslash($_POST['expected_hash'])):'';$token=isset($_POST['preview_token'])?sanitize_text_field(wp_unslash($_POST['preview_token'])):'';
   if(empty($_POST['import_confirmed'])||!hash_equals($hash,$expected)||!hash_equals(self::token($hash),$token)){throw new RuntimeException(esc_html__('Preview and explicitly confirm the selected backup before importing.','nodevora-customer-rewards-for-woocommerce'));}
   Nodevora_Backup::restore($raw);update_user_meta(get_current_user_id(),'nodevora_data_notice',__('Operation completed. Review the current status.','nodevora-customer-rewards-for-woocommerce'));delete_user_meta(get_current_user_id(),'nodevora_data_error');
  }catch(Throwable$e){update_user_meta(get_current_user_id(),'nodevora_data_error',$e->getMessage());}
  wp_safe_redirect(admin_url('admin.php?page=rewardly-loyalty-settings&tab=data'));exit;
 }
	private static function form($action,$multipart=false){echo'<form method="post" '.($multipart?'enctype="multipart/form-data" ':'').'action="'.esc_url(admin_url('admin-post.php')).'" class="nodevora-data-form"'.('import'===$action&&$multipart?' id="nodevora-import"':'').'>';wp_nonce_field('nodevora_data');echo'<input type="hidden" name="action" value="nodevora_data_'.esc_attr($action).'">';}
	public static function render() {
		if(!current_user_can('manage_options')){echo '<p>'.esc_html__('Ask an administrator to manage backups.','nodevora-customer-rewards-for-woocommerce').'</p>';return;}

		echo '<section class="rewardly-admin-card nodevora-data"><h2>'.esc_html(self::label()).'</h2>';
		foreach(array('nodevora_data_notice','nodevora_data_error')as$key){$message=get_user_meta(get_current_user_id(),$key,true);if($message){echo '<p role="status">'.esc_html($message).'</p>';if('nodevora_data_notice'===$key){delete_user_meta(get_current_user_id(),$key);}}}
		echo '<h3>'.esc_html__('Export your data','nodevora-customer-rewards-for-woocommerce').'</h3><p>'.esc_html__('This Nodevora-only file may contain personal data. Store it privately. It does not back up WordPress or other plugins.','nodevora-customer-rewards-for-woocommerce').'</p>';self::form('export');echo '<button class="button">'.esc_html__('Export a backup','nodevora-customer-rewards-for-woocommerce').'</button></form>';
		echo '<h3>'.esc_html__('Import a backup','nodevora-customer-rewards-for-woocommerce').'</h3><p>'.esc_html__('Imports require an empty loyalty target with matching accounts and orders. Existing activity is never overwritten. Preview the same file and confirm before importing. Limits: 8 MiB and 20,000 records per table.','nodevora-customer-rewards-for-woocommerce').'</p>';

		self::form('import',true);echo '<label for="nodevora-backup-file">'.esc_html__('Nodevora backup file','nodevora-customer-rewards-for-woocommerce').'</label><input type="file" id="nodevora-backup-file" name="nodevora_backup" accept=".json" required><input type="hidden" name="expected_hash"><input type="hidden" name="preview_token"><button type="button" class="button" id="nodevora-import-preview">'.esc_html__('Preview import','nodevora-customer-rewards-for-woocommerce').'</button><p id="nodevora-import-status" role="status"></p><label><input type="checkbox" name="import_confirmed" value="1" required> '.esc_html__('I stopped previous loyalty tools and external callbacks and confirm this import into the verified empty target.','nodevora-customer-rewards-for-woocommerce').'</label><button class="button button-primary" id="nodevora-import-confirm" disabled>'.esc_html__('Import reviewed backup','nodevora-customer-rewards-for-woocommerce').'</button></form></section>';
	}
}
