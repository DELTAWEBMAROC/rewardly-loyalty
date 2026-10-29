<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Parcours opérateur explicite ; les attestations ne remplacent jamais une sauvegarde réelle. */
class Nodevora_Financial_Setup {
	private static $scope = '';
	const OPTION = 'nodevora_financial_setup';
	public static function state() { return (array) get_option( self::OPTION, array() ); }
	/** Barrière partagée en transaction : une pause attend les écritures déjà engagées. */
	public static function lock_state( $db ) {
		global $wpdb;
		$row = $db->one( 'SELECT option_value FROM `' . $wpdb->options . '` WHERE option_name=? LOCK IN SHARE MODE', array( self::OPTION ) );
		if ( ! $row ) { return array(); }
		$state = @unserialize( $row['option_value'], array( 'allowed_classes' => false ) );
		if ( ! is_array( $state ) ) { throw new Nodevora_Financial_Error( 'Financial control state is invalid.' ); }
		return $state;
	}

	public static function paused() {
		$s = self::state();
		return ( defined('NODEVORA_FINANCIAL_WRITES_PAUSED') && true === NODEVORA_FINANCIAL_WRITES_PAUSED ) || ( isset($s['phase']) && 'active' !== $s['phase'] );
	}
	public static function integrated() { $s=self::state();return !is_multisite() && isset($s['phase']) && in_array($s['phase'],array('active','paused'),true) && 'wallet'===get_option('nodevora_financial_authority'); }
	public static function permits($scope) { return self::$scope===$scope && current_user_can('manage_options') && !is_multisite(); }
	private static function save($s) { update_option(self::OPTION,$s,false);if(get_option(self::OPTION)!==$s){throw new Nodevora_User_Error(__('Setup state was not persisted. Retry without enabling writes.','nodevora-customer-rewards-for-woocommerce'));} }
	public static function init() {

		add_action('admin_post_nodevora_financial_setup',array(__CLASS__,'handle'));
		add_action('woocommerce_check_cart_items',array(__CLASS__,'maintenance'));
		add_action('woocommerce_store_api_checkout_order_processed',array(__CLASS__,'maintenance'),1);
	}
	public static function maintenance() {
		$s=self::state();if(isset($s['phase'])&&in_array($s['phase'],array('prepared','migrating'),true)){$message=__('Store checkout is paused for financial setup. Please try again later.','nodevora-customer-rewards-for-woocommerce');if('woocommerce_store_api_checkout_order_processed'===current_filter()){throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('nodevora_setup_maintenance',$message,503);}wc_add_notice($message,'error');}
	}
	public static function handle() {
		if(!current_user_can('manage_options')){wp_die('', '', array('response'=>403));}
		if(!isset($_SERVER['REQUEST_METHOD'])||'POST'!==$_SERVER['REQUEST_METHOD']){wp_die('', '', array('response'=>405));}
		check_admin_referer('nodevora_financial_setup');
		$step=isset($_POST['setup_step'])&&is_string($_POST['setup_step'])?sanitize_key(wp_unslash($_POST['setup_step'])):'';
		$lock=null;$locked=false;
		try{$lock=new Nodevora_Financial_DB();$row=$lock->one('SELECT GET_LOCK(?,0) AS acquired',array('nodevora:'.substr(hash('sha256',DB_NAME),0,32).':setup'));$locked=1===(int)$row['acquired'];if(!$locked){throw new Nodevora_User_Error(__('Another setup operation is running. Retry while paused.','nodevora-customer-rewards-for-woocommerce'));}self::perform($step);delete_user_meta(get_current_user_id(),'_nodevora_setup_error');update_user_meta(get_current_user_id(),'_nodevora_setup_notice',true);}
		catch(Throwable $e){delete_user_meta(get_current_user_id(),'_nodevora_setup_notice');update_user_meta(get_current_user_id(),'_nodevora_setup_error',$e instanceof Nodevora_Financial_Error?$e->getMessage():__('Setup failed. Writes remain paused. Check the backup and database permissions before retrying.','nodevora-customer-rewards-for-woocommerce'));}
		finally{self::$scope='';if($locked){$lock->one('SELECT RELEASE_LOCK(?) AS released',array('nodevora:'.substr(hash('sha256',DB_NAME),0,32).':setup'));}if($lock){$lock->close();}}
		wp_safe_redirect(admin_url('admin.php?page=rewardly-loyalty-settings&tab=data'));exit;
	}
	private static function perform($step) {
		if(is_multisite()||!class_exists('WooCommerce')){throw new Nodevora_User_Error(__('Single-site WooCommerce is required.','nodevora-customer-rewards-for-woocommerce'));}
		$s=self::state();
		if('prepare'===$step){
			$reference=isset($_POST['backup_reference'])&&is_string($_POST['backup_reference'])?sanitize_text_field(wp_unslash($_POST['backup_reference'])):'';
			if(strlen($reference)<3||strlen($reference)>160||empty($_POST['backup_verified'])||empty($_POST['writers_drained'])){throw new Nodevora_User_Error(__('Confirm a tested database/files backup and stopped/drained legacy writers.','nodevora-customer-rewards-for-woocommerce'));}
			self::save(array('phase'=>'prepared','backup_reference'=>$reference,'operator'=>get_current_user_id(),'prepared_at'=>gmdate('c'),'cursor'=>0));return;
		}
		if('pause'===$step){if(!self::integrated()){throw new Nodevora_User_Error(__('No operator cutover exists.','nodevora-customer-rewards-for-woocommerce'));}$s['phase']='paused';self::save($s);return;}
		if('resolve_refund'===$step){
			if(empty($_POST['review_confirmed'])){throw new Nodevora_User_Error(__('Confirm the external financial review before resolving a hold.','nodevora-customer-rewards-for-woocommerce'));}
			Nodevora_Financial_Lifecycle::resolve_refund(absint($_POST['order_id']??0),absint($_POST['refund_id']??0),isset($_POST['review_note'])&&is_string($_POST['review_note'])?sanitize_textarea_field(wp_unslash($_POST['review_note'])):'');return;
		}
		if(!isset($s['phase'])||!in_array($s['phase'],array('prepared','migrating','paused'),true)){throw new Nodevora_User_Error(__('Start with the backup and maintenance step.','nodevora-customer-rewards-for-woocommerce'));}
		if('schema'===$step){self::$scope='schema';$db=new Nodevora_Financial_DB();try{Nodevora_Financial_Schema::install($db);}finally{$db->close();}$s['phase']='migrating';self::save($s);return;}
		$db=new Nodevora_Financial_DB();try{Nodevora_Financial_Schema::verify($db);}finally{$db->close();}
		if('inspect'===$step){
			global $wpdb;$ids=$wpdb->get_col($wpdb->prepare('SELECT ID FROM %i WHERE ID>%d ORDER BY ID LIMIT 20',$wpdb->users,(int)$s['cursor']));if($wpdb->last_error){throw new Nodevora_User_Error(__('Account discovery failed; retry while paused.','nodevora-customer-rewards-for-woocommerce'));}
			$batch=array();$m=new Nodevora_Financial_Migration();foreach($ids as$id){$batch[]=$m->inspect((int)$id);}$s['batch']=$batch;$s['scan_complete']=!$ids;self::save($s);return;
		}
		if('migrate'===$step){
			if(!isset($s['batch'])||!$s['batch']){throw new Nodevora_User_Error(__('Inspect the next account batch first.','nodevora-customer-rewards-for-woocommerce'));}
			self::$scope='migration';$m=new Nodevora_Financial_Migration();$quarantined=0;foreach($s['batch']as$row){$result=$m->migrate((int)$row['user_id'],$row['checksum']);if('needs_review'===$result['state']){$quarantined++;}}
			$s['processed']=(int)($s['processed']??0)+count($s['batch']);$s['quarantined']=(int)($s['quarantined']??0)+$quarantined;
			$s['cursor']=(int)end($s['batch'])['user_id'];$s['batch']=array();$s['scan_complete']=false;self::save($s);return;
		}
		if('activate'===$step){ self::activate_state($s,!empty($_POST['cutover_confirmed']),!empty($_POST['quarantine_confirmed']),!empty($_POST['recovery_reviewed']));return; }
		throw new Nodevora_User_Error(__('Unknown setup action.','nodevora-customer-rewards-for-woocommerce'));
	}
	/** Une requête traite au plus vingt comptes ; les preuves et le curseur sont persistants. */
	public static function synchronize( $writers_confirmed = false ) {
		if ( ! current_user_can('manage_options') || is_multisite() ) { throw new Nodevora_Financial_Error('Administrator synchronization permission required.'); }
		$db = new Nodevora_Financial_DB(); $locked = false;
		try {
			$key = 'nodevora:' . substr(hash('sha256',DB_NAME),0,32) . ':setup';
			$locked = 1 === (int) $db->one('SELECT GET_LOCK(?,0) AS acquired',array($key))['acquired'];
			if ( ! $locked ) { throw new Nodevora_Financial_Error('Another synchronization is running.'); }
			$s = self::state();
			if ( isset($s['phase']) && 'active' === $s['phase'] ) { Nodevora_Financial_Checkout::require_enabled();return array('complete'=>true); }
			if ( ! isset($s['sync_authorized']) ) {
				if ( ! $writers_confirmed ) { throw new Nodevora_User_Error( __('Stop any previous loyalty tools and external loyalty processing before confirming synchronization. Ordinary store settings will be retained.','nodevora-customer-rewards-for-woocommerce') ); }
				// Conserver les règles initiales ; les sources et historiques ne sont jamais supprimés.
				add_option('nodevora_sync_source_settings',get_option('rewardly_loyalty_settings',array()),'',false);
				$s=array('phase'=>'prepared','origin'=>'synchronized','cursor'=>0,'processed'=>0,'quarantined'=>0,'sync_authorized'=>get_current_user_id(),'prepared_at'=>gmdate('c'),'recovery_approved'=>false);
				self::save($s);
			}
			if ( 'prepared' === $s['phase'] ) { self::perform('schema'); return array('complete'=>false,'processed'=>(int)($s['processed']??0)); }
			if ( 'migrating' !== $s['phase'] ) { throw new Nodevora_User_Error( __('This store needs an administrator review before synchronization can continue.','nodevora-customer-rewards-for-woocommerce') ); }
			if ( ! empty($s['batch']) ) { self::perform('migrate'); return array('complete'=>false,'processed'=>(int)(self::state()['processed']??0)); }
			if ( empty($s['scan_complete']) ) { self::perform('inspect'); return array('complete'=>false,'processed'=>(int)($s['processed']??0)); }
			// Une fin explicite, sans autoriser une file de récupération historique.
			self::activate_state($s,true,true,false); update_option('nodevora_installation_status','ready',false);
			return array('complete'=>true,'processed'=>(int)($s['processed']??0));
		} finally { self::$scope=''; if($locked){$db->one('SELECT RELEASE_LOCK(?) AS released',array($key));} $db->close(); }
	}

	/** Explicit approval never runs the queue inline or overrides a server pause. */
	public static function approve_recovery($review_hash) {
		if(!current_user_can('manage_options')||is_multisite()){throw new Nodevora_Financial_Error('Administrator recovery permission required.');}
		$db=new Nodevora_Financial_DB();$key='nodevora:'.substr(hash('sha256',DB_NAME),0,32).':setup';$locked=false;
		try{$locked=1===(int)$db->one('SELECT GET_LOCK(?,0) AS acquired',array($key))['acquired'];if(!$locked){throw new Nodevora_Financial_Error('Another data operation is running.');}
			Nodevora_Financial_Checkout::require_enabled();$state=self::state();
			$rows=$db->query('SELECT id,event_id,effect,state,attempts FROM '.$db->table('outbox').' WHERE state<>? ORDER BY id LIMIT 251',array('delivered'));
			if(!is_string($review_hash)||!hash_equals(hash_hmac('sha256',json_encode($rows),wp_salt('nonce')),$review_hash)){throw new Nodevora_Financial_Error('Pending effects changed. Review the list again.');}
			if(count($rows)>250){throw new Nodevora_Financial_Error('Too many pending effects for this review. Keep recovery paused and request an operator review.');}
			$state['recovery_approved']=true;$state['recovery_approved_by']=get_current_user_id();$state['recovery_approved_at']=gmdate('c');self::save($state);
		}finally{if($locked){$db->one('SELECT RELEASE_LOCK(?) AS released',array($key));}$db->close();}
	}

	private static function activate_state($s,$cutover,$quarantine,$recovery) {
		$verified_db=new Nodevora_Financial_DB();try{Nodevora_Financial_Schema::verify($verified_db);}finally{$verified_db->close();}
			if(!$cutover||!$quarantine){throw new Nodevora_User_Error(__('Confirm cutover and that unreconciled accounts must remain blocked.','nodevora-customer-rewards-for-woocommerce'));}
			if((defined('NODEVORA_FINANCIAL_WRITES_PAUSED')&&NODEVORA_FINANCIAL_WRITES_PAUSED)||(defined('NODEVORA_FINANCIAL_INTEGRATION_ALLOWED')&&!NODEVORA_FINANCIAL_INTEGRATION_ALLOWED)){throw new Nodevora_User_Error(__('A server safety override prevents activation. Ask the operator to review it; this page never changes server configuration.','nodevora-customer-rewards-for-woocommerce'));}
			global $wpdb;$db=new Nodevora_Financial_DB();try{$missing=$db->one('SELECT COUNT(*) AS n FROM `'.$wpdb->users.'` u LEFT JOIN '.$db->table('wallet').' w ON w.user_id=u.ID WHERE w.user_id IS NULL');if((int)$missing['n']>0||empty($s['scan_complete'])){throw new Nodevora_User_Error(__('Inspect and migrate every account batch, including newly created accounts, before cutover.','nodevora-customer-rewards-for-woocommerce'));}}finally{$db->close();}
			update_option('nodevora_financial_authority','wallet',false);if('wallet'!==get_option('nodevora_financial_authority')){throw new Nodevora_User_Error(__('Financial authority was not persisted.','nodevora-customer-rewards-for-woocommerce'));}
			$s['recovery_approved']=$recovery;$s['phase']='active';$s['activated_at']=gmdate('c');$s['activated_by']=get_current_user_id();self::save($s);return;
	}

	private static function button($value,$label) { echo '<button class="button button-secondary" type="submit" name="setup_step" value="'.esc_attr($value).'">'.esc_html($label).'</button> '; }
	public static function render() {
		if(!current_user_can('manage_options')){wp_die('', '', array('response'=>403));}
		$s=self::state();$error=get_user_meta(get_current_user_id(),'_nodevora_setup_error',true);
		echo '<div class="wrap nodevora-setup"><h1>'.esc_html__('Financial setup','nodevora-customer-rewards-for-woocommerce').'</h1><style>.nodevora-setup{max-width:1000px;overflow-wrap:anywhere}.nodevora-setup input,.nodevora-setup textarea{max-width:100%;box-sizing:border-box}.nodevora-setup label{display:block;margin:12px 0}.nodevora-setup td{overflow-wrap:anywhere}.nodevora-setup .button{white-space:normal;margin:4px 0}</style>';
		if($error){echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';}
		if(get_user_meta(get_current_user_id(),'_nodevora_setup_notice',true)){echo '<div class="notice notice-success" role="status"><p>'.esc_html__('Operation completed. Review the current state before the next step.','nodevora-customer-rewards-for-woocommerce').'</p></div>';delete_user_meta(get_current_user_id(),'_nodevora_setup_notice');}
		if(isset($s['cursor'])){echo '<p>'.esc_html(sprintf(__('Reviewed through account #%d.','nodevora-customer-rewards-for-woocommerce'),(int)$s['cursor'])).' '.esc_html(!empty($s['scan_complete'])?__('Account discovery is complete. Review quarantine before explicit cutover.','nodevora-customer-rewards-for-woocommerce'):__('Inspect the next batch; an empty batch confirms account discovery is complete.','nodevora-customer-rewards-for-woocommerce')).'</p>';}
		echo '<p>'.esc_html__('No account is migrated and no financial writes are enabled automatically. Keep checkout, payment callbacks and legacy workers stopped until setup is complete.','nodevora-customer-rewards-for-woocommerce').'</p>';
		echo '<p><strong>'.esc_html__('Current state:','nodevora-customer-rewards-for-woocommerce').'</strong> '.esc_html(isset($s['phase'])?$s['phase']:__('Not configured','nodevora-customer-rewards-for-woocommerce')).(self::paused()?' — '.esc_html__('Writes paused','nodevora-customer-rewards-for-woocommerce'):'').'</p>';
		echo '<ol><li>'.esc_html__('Export the full database and plugin files outside the web root. Test restoring the backup on an isolated snapshot. Never enter credentials or backup contents here.','nodevora-customer-rewards-for-woocommerce').'</li><li>'.esc_html__('Enable store maintenance, stop external payment callbacks and all previous loyalty writers, then wait for in-flight work to finish. The confirmations below are operator attestations, not a mutex.','nodevora-customer-rewards-for-woocommerce').'</li><li>'.esc_html__('Prepare setup, install/verify the schema, inspect each batch, then explicitly migrate that reviewed batch. Retry interrupted batches with the same evidence; opening balances cannot be imported twice.','nodevora-customer-rewards-for-woocommerce').'</li><li>'.esc_html__('Accounts needing review retain their raw snapshots and remain unable to spend. Review the displayed reasons against the backup; never invent missing lots, dates or historical debits. An approved reconciliation plan is required before changing their financial value.','nodevora-customer-rewards-for-woocommerce').'</li><li>'.esc_html__('After the final empty inspection batch, explicitly activate eligible wallets. Keep unreconciled accounts blocked. Restore maintenance/callbacks deliberately and verify a supported offline checkout.','nodevora-customer-rewards-for-woocommerce').'</li></ol>';
		echo '<p>'.esc_html__('Before cutover, rollback requires stopped writers and a complete consistent restore. After committed wallet transactions, keep wallet authority and journal: do not downgrade PHP or restore a pre-cutover database. Pause and reconcile instead.','nodevora-customer-rewards-for-woocommerce').'</p>';
		echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('nodevora_financial_setup');echo '<input type="hidden" name="action" value="nodevora_financial_setup">';
		echo '<label for="nodevora-backup">'.esc_html__('Backup reference (no secrets)','nodevora-customer-rewards-for-woocommerce').'</label><input id="nodevora-backup" name="backup_reference" maxlength="160" type="text"><label><input type="checkbox" name="backup_verified" value="1"> '.esc_html__('I verified a restorable database and files backup.','nodevora-customer-rewards-for-woocommerce').'</label><label><input type="checkbox" name="writers_drained" value="1"> '.esc_html__('Maintenance is enabled and legacy writers and callbacks are stopped and drained.','nodevora-customer-rewards-for-woocommerce').'</label>';
		self::button('prepare',__('Prepare and pause','nodevora-customer-rewards-for-woocommerce'));self::button('schema',__('Install / verify schema','nodevora-customer-rewards-for-woocommerce'));self::button('inspect',__('Inspect next 20 accounts','nodevora-customer-rewards-for-woocommerce'));self::button('migrate',__('Migrate reviewed batch','nodevora-customer-rewards-for-woocommerce'));
		if(!empty($s['batch'])){echo '<ul>';foreach($s['batch']as$row){echo '<li>'.esc_html('#'.$row['user_id'].' — '.$row['state'].' — '.$row['balance'].' / '.$row['lot_total'].' — '.implode(', ',$row['reasons'])).'</li>';}echo '</ul>';}
		echo '<label><input type="checkbox" name="quarantine_confirmed" value="1"> '.esc_html__('Unreconciled accounts must remain blocked.','nodevora-customer-rewards-for-woocommerce').'</label><label><input type="checkbox" name="cutover_confirmed" value="1"> '.esc_html__('I authorize wallet cutover and new financial writes for ready accounts.','nodevora-customer-rewards-for-woocommerce').'</label>';
		echo '<label><input type="checkbox" name="recovery_reviewed" value="1"> '.esc_html__('I reviewed pending effects and authorize background recovery. Leave unchecked to keep recovery manual.','nodevora-customer-rewards-for-woocommerce').'</label>';
		self::button('activate',__('Activate reviewed wallets','nodevora-customer-rewards-for-woocommerce'));self::button('pause',__('Pause new financial writes','nodevora-customer-rewards-for-woocommerce'));
		echo '<h2>'.esc_html__('Resolve a reviewed partial refund','nodevora-customer-rewards-for-woocommerce').'</h2><p>'.esc_html__('No proportional refund policy is applied. Record the authorized external reconciliation decision. This clears only the selected refund hold; it does not move points or advance the order status. A later refund requires a new review.','nodevora-customer-rewards-for-woocommerce').'</p>';
		foreach(array('order_id'=>__('Order ID','nodevora-customer-rewards-for-woocommerce'),'refund_id'=>__('Refund ID','nodevora-customer-rewards-for-woocommerce'))as$name=>$label){echo '<label for="nodevora-'.$name.'">'.esc_html($label).'</label><input id="nodevora-'.$name.'" name="'.esc_attr($name).'" type="number" min="1">';}
		echo '<label for="nodevora-review-note">'.esc_html__('Authorized review decision and reference','nodevora-customer-rewards-for-woocommerce').'</label><textarea id="nodevora-review-note" name="review_note" rows="3"></textarea><label><input type="checkbox" name="review_confirmed" value="1"> '.esc_html__('I completed and authorize this external financial reconciliation.','nodevora-customer-rewards-for-woocommerce').'</label>';self::button('resolve_refund',__('Record refund resolution','nodevora-customer-rewards-for-woocommerce'));echo '</form></div>';
	}
}
