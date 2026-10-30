<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Routes administrateur du parcours marchand ; les services conservent leurs propres garde-fous. */
class Nodevora_Data {
	public static function init() {
		add_action('wp_ajax_nodevora_sync',array(__CLASS__,'sync'));
		add_action('wp_ajax_nodevora_backup_preview',array(__CLASS__,'preview'));
		foreach(array('export','import','resume','repair','recovery')as$action){add_action('admin_post_nodevora_data_'.$action,static function()use($action){self::handle($action);});}
	}
	private static function guard($ajax=false) {
		if(!current_user_can('manage_options')){wp_die('','',array('response'=>403));}
		if('POST'!==($_SERVER['REQUEST_METHOD']??'')){wp_die('','',array('response'=>405));}
		if($ajax){check_ajax_referer('nodevora_data','_wpnonce');}else{check_admin_referer('nodevora_data');}
	}
	public static function label(){ $s=Nodevora_Financial_Setup::state();return empty($s['phase'])||in_array($s['phase'],array('prepared','migrating','failed'),true)?__('Synchronization','nodevora-customer-rewards-for-woocommerce'):__('Data & Backups','nodevora-customer-rewards-for-woocommerce'); }
	public static function relevant() {
		$s=Nodevora_Financial_Setup::state();if(Nodevora_Financial_Setup::paused()||'blocked'===get_option('nodevora_installation_status')){return true;}if(($s['origin']??'')!=='fresh'||($s['phase']??'')!=='active'){return true;}
		$db=null;try{$db=new Nodevora_Financial_DB();return(bool)$db->one('SELECT 1 AS present FROM '.$db->table('wallet').' LIMIT 1');}catch(Throwable$e){return true;}finally{if($db){$db->close();}}
	}
	private static function upload() {
		$f=$_FILES['nodevora_backup']??null;
		if(!is_array($f)||($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($f['tmp_name'])||!is_uploaded_file($f['tmp_name'])||filesize($f['tmp_name'])>Nodevora_Backup::MAX_BYTES){throw new Nodevora_User_Error(__('Choose a valid Nodevora backup smaller than 32 MiB.','nodevora-customer-rewards-for-woocommerce'));}
		return$f['tmp_name'];
	}
	private static function token($hash){return hash_hmac('sha256',get_current_user_id().':'.$hash,wp_salt('nonce'));}
	public static function preview() {
		self::guard(true);try{$info=Nodevora_Restore::preview(self::upload());$info['preview_token']=self::token($info['sha256']);wp_send_json_success($info);}catch(Throwable$e){wp_send_json_error(array('message'=>self::message($e)),409);}
	}
	public static function sync() {
		self::guard(true);try{$result=Nodevora_Financial_Setup::synchronize(!empty($_POST['writers_confirmed']));wp_send_json_success($result);}catch(Throwable$e){wp_send_json_error(array('message'=>self::message($e)),409);}
	}
	private static function message($e){
		if($e instanceof Nodevora_User_Error){return $e->getMessage();}
		if($e instanceof Nodevora_Financial_Error){
			$messages=array(
				'Dry-run checksum changed.'=>__('Loyalty data changed during synchronization. Keep external loyalty tools stopped and ask your administrator to review the saved batch before retrying.','nodevora-customer-rewards-for-woocommerce'),
				'Account or order identity does not match this store.'=>__('This backup refers to different accounts or orders. Use its matching site copy; no records were imported.','nodevora-customer-rewards-for-woocommerce'),
				'Incompatible backup format, schema or plugin version.'=>__('This backup requires the same Nodevora version and supported backup format. Install the matching version on an empty test copy first.','nodevora-customer-rewards-for-woocommerce'),
				'Backup integrity verification failed.'=>__('This backup is damaged or incomplete. Select a complete original export and preview it again.','nodevora-customer-rewards-for-woocommerce'),
				'Incomplete backup refused.'=>__('This backup is damaged or incomplete. Select a complete original export and preview it again.','nodevora-customer-rewards-for-woocommerce'),
				'Another synchronization is running.'=>__('Another data operation is running. Wait for it to finish, then resume.','nodevora-customer-rewards-for-woocommerce'),
				'Another data operation is running.'=>__('Another data operation is running. Wait for it to finish, then resume.','nodevora-customer-rewards-for-woocommerce'),
				'Pending effects changed. Review the list again.'=>__('Pending effects changed. Reload and review the current list before approving recovery.','nodevora-customer-rewards-for-woocommerce'),
				'This store already contains loyalty activity. An older backup cannot replace it. Use an empty test copy or request reviewed reconciliation.'=>__('This store already contains loyalty activity. An older backup cannot replace it. Use an empty test copy or request reviewed reconciliation.','nodevora-customer-rewards-for-woocommerce'),
				'Existing loyalty metadata conflicts with this restore.'=>__('This store still contains loyalty records. Import into an empty matching test copy; existing records are preserved.','nodevora-customer-rewards-for-woocommerce'),
				'Backup exceeds the supported size or could not be written.'=>__('The backup exceeds 32 MiB or private temporary storage is unavailable. Ask your administrator for an assisted export.','nodevora-customer-rewards-for-woocommerce'),
				'Backup row limit exceeded; request an operator-assisted export.'=>__('The backup exceeds 100,000 records. Ask your administrator for an assisted export.','nodevora-customer-rewards-for-woocommerce'),
			);
			if(isset($messages[$e->getMessage()])){return$messages[$e->getMessage()];}
		}
		return __('The operation could not finish. Your data is preserved. Check the selected file, database access and any active safety pause, then ask your administrator before retrying.','nodevora-customer-rewards-for-woocommerce');
	}
	public static function handle($action) {
		self::guard();try{
			if('export'===$action){$f=Nodevora_Backup::create();try{nocache_headers();header('Content-Type: application/x-ndjson; charset=utf-8');header('Content-Disposition: attachment; filename="nodevora-'.gmdate('Y-m-d-His').'.ndjson"');header('X-Content-Type-Options: nosniff');header('Content-Length: '.fstat($f)['size']);fpassthru($f);}finally{fclose($f);}exit;}
			if('import'===$action){$hash=isset($_POST['expected_hash'])&&is_string($_POST['expected_hash'])?sanitize_text_field(wp_unslash($_POST['expected_hash'])):'';$token=isset($_POST['preview_token'])&&is_string($_POST['preview_token'])?sanitize_text_field(wp_unslash($_POST['preview_token'])):'';if(empty($_POST['import_confirmed'])||!preg_match('/^[a-f0-9]{64}$/D',$hash)||!hash_equals(self::token($hash),$token)){throw new Nodevora_User_Error(__('Preview and explicitly confirm the selected backup before importing.','nodevora-customer-rewards-for-woocommerce'));}Nodevora_Restore::apply(self::upload(),$hash);}
			if('resume'===$action){if(empty($_POST['resume_confirmed'])){throw new Nodevora_User_Error(__('Review the restored data before enabling points.','nodevora-customer-rewards-for-woocommerce'));}Nodevora_Restore::resume_reviewed(!empty($_POST['recovery_confirmed']));}
			if('recovery'===$action){if(empty($_POST['recovery_confirmed'])){throw new Nodevora_User_Error(__('Review pending effects before authorizing background recovery.','nodevora-customer-rewards-for-woocommerce'));}Nodevora_Financial_Setup::approve_recovery(isset($_POST['review_hash'])&&is_string($_POST['review_hash'])?sanitize_text_field(wp_unslash($_POST['review_hash'])):'');}
			if('repair'===$action){Nodevora_Installation::activate();}
			update_user_meta(get_current_user_id(),'nodevora_data_notice',__('Operation completed. Review the current status.','nodevora-customer-rewards-for-woocommerce'));delete_user_meta(get_current_user_id(),'nodevora_data_error');
		}catch(Throwable$e){update_user_meta(get_current_user_id(),'nodevora_data_error',self::message($e));}
		wp_safe_redirect(admin_url('admin.php?page=rewardly-loyalty-settings&tab=data'));exit;
	}
	private static function form($action,$multipart=false){echo'<form method="post" '.($multipart?'enctype="multipart/form-data" ':'').'action="'.esc_url(admin_url('admin-post.php')).'" class="nodevora-data-form"'.('import'===$action?' id="nodevora-import"':'').'>';wp_nonce_field('nodevora_data');echo'<input type="hidden" name="action" value="nodevora_data_'.esc_attr($action).'">';}
	public static function render() {
		if(!current_user_can('manage_options')){echo'<p>'.esc_html__('Ask an administrator to manage synchronization and backups.','nodevora-customer-rewards-for-woocommerce').'</p>';return;}
		$s=Nodevora_Financial_Setup::state();$phase=$s['phase']??'';$ready='configured'===Nodevora_Financial_Readiness::state();
		echo'<section class="rewardly-admin-card nodevora-data"><h2>'.esc_html(self::label()).'</h2><p role="status">'.esc_html($ready?__('Your loyalty data is ready.','nodevora-customer-rewards-for-woocommerce'):__('Your data is preserved. Synchronization or administrator review is required before enabling points.','nodevora-customer-rewards-for-woocommerce')).'</p>';
		foreach(array('nodevora_data_notice','nodevora_data_error','_nodevora_setup_error','nodevora_installation_error')as$key){$message='nodevora_installation_error'===$key?get_option($key):get_user_meta(get_current_user_id(),$key,true);if($message){echo'<p role="status">'.esc_html($message).'</p>';if('nodevora_data_notice'===$key){delete_user_meta(get_current_user_id(),$key);}}}
		if('synchronized'===($s['origin']??'')){echo '<p>'.esc_html__('Consistent accounts are available. Accounts requiring review remain protected; their original records are retained.','nodevora-customer-rewards-for-woocommerce').'</p>';if(!empty($s['quarantined'])){echo '<p>'.esc_html(sprintf(_n('%d account requires review.','%d accounts require review.',(int)$s['quarantined'],'nodevora-customer-rewards-for-woocommerce'),(int)$s['quarantined'])).'</p>';}}
		if('restore_review'===$phase){self::form('resume');echo'<label><input type="checkbox" name="resume_confirmed" value="1" required> '.esc_html__('I reviewed the restored data and authorize new points operations.','nodevora-customer-rewards-for-woocommerce').'</label><label class="nodevora-advanced"><input type="checkbox" name="recovery_confirmed" value="1"> '.esc_html__('I reviewed all pending effects and authorize background recovery.','nodevora-customer-rewards-for-woocommerce').'</label><button class="button button-primary">'.esc_html__('Enable reviewed points','nodevora-customer-rewards-for-woocommerce').'</button></form>';}
		elseif(!$ready){echo'<div id="nodevora-sync"><p>'.esc_html__('Existing loyalty records will be preserved. Consistent accounts will be synchronized; ambiguous accounts remain protected for review. Previously imported balances cannot be credited twice. Checkout is temporarily protected during this process; finish or resume synchronization before reopening purchases.','nodevora-customer-rewards-for-woocommerce').'</p><label><input type="checkbox" name="writers_confirmed" value="1"> '.esc_html__('I stopped previous loyalty tools and external loyalty processing.','nodevora-customer-rewards-for-woocommerce').'</label><button type="button" class="button button-primary" id="nodevora-sync-start">'.esc_html(!empty($s['sync_authorized'])?__('Resume synchronization','nodevora-customer-rewards-for-woocommerce'):__('Synchronize my data','nodevora-customer-rewards-for-woocommerce')).'</button><p id="nodevora-sync-status" role="status"></p></div>';}
		echo'<h3>'.esc_html__('Export your data','nodevora-customer-rewards-for-woocommerce').'</h3><p>'.esc_html__('This Nodevora-only file may contain personal data. Store it privately. It does not back up WordPress or other plugins.','nodevora-customer-rewards-for-woocommerce').'</p>';self::form('export');echo'<button class="button">'.esc_html__('Export a backup','nodevora-customer-rewards-for-woocommerce').'</button></form>';
		echo'<h3>'.esc_html__('Import a backup','nodevora-customer-rewards-for-woocommerce').'</h3><p>'.esc_html__('Imports require an empty loyalty target with matching accounts and orders. Existing activity is never overwritten. Preview the same file and confirm before importing. Limits: 32 MiB and 100,000 records.','nodevora-customer-rewards-for-woocommerce').'</p>';self::form('import',true);echo'<label for="nodevora-backup-file">'.esc_html__('Nodevora backup file','nodevora-customer-rewards-for-woocommerce').'</label><input type="file" id="nodevora-backup-file" name="nodevora_backup" accept=".ndjson" required><input type="hidden" name="expected_hash"><input type="hidden" name="preview_token"><button type="button" class="button" id="nodevora-import-preview">'.esc_html__('Preview import','nodevora-customer-rewards-for-woocommerce').'</button><p id="nodevora-import-status" role="status"></p><label><input type="checkbox" name="import_confirmed" value="1" required> '.esc_html__('I stopped previous loyalty tools and external callbacks and confirm this import into the verified empty target.','nodevora-customer-rewards-for-woocommerce').'</label><button class="button button-primary" id="nodevora-import-confirm" disabled>'.esc_html__('Import reviewed backup','nodevora-customer-rewards-for-woocommerce').'</button></form>';
		if('blocked'===get_option('nodevora_installation_status')){self::form('repair');echo'<button class="button">'.esc_html__('Retry preparation','nodevora-customer-rewards-for-woocommerce').'</button></form>';}
		echo'</section>';
		if(Nodevora_Access::advanced()){self::recovery_form($s);self::refund_form();}
	}
	private static function recovery_form($s) {
		if(($s['phase']??'')!=='active'||!empty($s['recovery_approved'])){return;}
		echo '<section class="rewardly-admin-card"><h2>'.esc_html__('Background recovery review','nodevora-customer-rewards-for-woocommerce').'</h2><p>'.esc_html__('Background recovery is paused after synchronization. Review pending effects before authorizing scheduled retries and notifications. This does not resolve quarantined accounts or refund holds.','nodevora-customer-rewards-for-woocommerce').'</p>';
		$db=null;try{$db=new Nodevora_Financial_DB();$rows=$db->query('SELECT id,event_id,effect,state,attempts FROM '.$db->table('outbox').' WHERE state<>? ORDER BY id LIMIT 251',array('delivered'));
			if(count($rows)>250){echo '<p>'.esc_html__('Too many pending effects for this review. Keep recovery paused and request an operator review.','nodevora-customer-rewards-for-woocommerce').'</p>';}
			else{echo '<ul>';foreach($rows as$r){echo '<li>'.esc_html('#'.$r['id'].' / #'.$r['event_id'].' — '.$r['effect'].' — '.$r['state'].' — '.$r['attempts']).'</li>';}echo '</ul>';if(!$rows){echo '<p>'.esc_html__('No pending effects. Approval enables future scheduled recovery.','nodevora-customer-rewards-for-woocommerce').'</p>';}self::form('recovery');echo '<input type="hidden" name="review_hash" value="'.esc_attr(hash_hmac('sha256',json_encode($rows),wp_salt('nonce'))).'"><label><input type="checkbox" name="recovery_confirmed" value="1" required> '.esc_html__('I reviewed these effects and authorize background recovery.','nodevora-customer-rewards-for-woocommerce').'</label><button class="button">'.esc_html__('Authorize reviewed recovery','nodevora-customer-rewards-for-woocommerce').'</button></form>';}
		}catch(Throwable$e){echo '<p>'.esc_html__('Pending effects could not be read. Recovery remains paused.','nodevora-customer-rewards-for-woocommerce').'</p>';}finally{if($db){$db->close();}}echo '</section>';
	}
	private static function refund_form() {
		echo'<section class="rewardly-admin-card"><h2>'.esc_html__('Review a partial refund','nodevora-customer-rewards-for-woocommerce').'</h2><p>'.esc_html__('Administrator-only external reconciliation. Recording a review does not adjust points or advance fulfillment. A later refund requires another review.','nodevora-customer-rewards-for-woocommerce').'</p><form class="nodevora-data-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('nodevora_financial_setup');echo'<input type="hidden" name="action" value="nodevora_financial_setup"><input type="hidden" name="setup_step" value="resolve_refund">';
		foreach(array('order_id'=>__('Order ID','nodevora-customer-rewards-for-woocommerce'),'refund_id'=>__('Refund ID','nodevora-customer-rewards-for-woocommerce'))as$name=>$label){echo'<label for="nodevora-data-'.esc_attr($name).'">'.esc_html($label).'</label><input id="nodevora-data-'.esc_attr($name).'" name="'.esc_attr($name).'" type="number" min="1" required>';}
		echo'<label for="nodevora-refund-decision">'.esc_html__('Authorized review decision and reference','nodevora-customer-rewards-for-woocommerce').'</label><textarea id="nodevora-refund-decision" name="review_note" minlength="10" maxlength="2000" required></textarea><label><input type="checkbox" name="review_confirmed" value="1" required> '.esc_html__('I completed and authorize this external financial reconciliation.','nodevora-customer-rewards-for-woocommerce').'</label><button class="button">'.esc_html__('Record refund resolution','nodevora-customer-rewards-for-woocommerce').'</button></form></section>';
	}
}
