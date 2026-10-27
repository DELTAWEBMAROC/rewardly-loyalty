<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Compatibility views never use mutable legacy projections as financial authority. */
class Nodevora_Financial_Compatibility {
	public static function selected() { return (defined('NODEVORA_FINANCIAL_MODE') && 'wallet' === NODEVORA_FINANCIAL_MODE)||'wallet'===get_option('nodevora_financial_authority','legacy'); }
	public static function balance($user_id) { $wallet = new Nodevora_Financial_Wallet(); $state = $wallet->read((int)$user_id); return $state['available']; }
	public static function cutover() {
		if(!defined('WP_CLI')||!WP_CLI||'local'!==wp_get_environment_type()||!defined('NODEVORA_FINANCIAL_WRITERS_DRAINED')||true!==NODEVORA_FINANCIAL_WRITERS_DRAINED){throw new Nodevora_Financial_Error('Explicit isolated local cutover required.');}
		Nodevora_Financial_Checkout::require_enabled();Nodevora_Financial_Schema::verify(new Nodevora_Financial_DB());update_option('nodevora_financial_authority','wallet',false);
	}
	/** Read-only customer views show unavailable funds explicitly; financial callers use balance/apply. */
	public static function view($user,$kind,$limit=20) {
		try{if('balance'===$kind){return self::balance($user);}if('lots'===$kind){return self::lots($user);}if('logs'===$kind){return self::logs($user,$limit);}return self::total($user,$kind);}
		catch(Nodevora_Financial_Error $e){
			if(defined('WP_CLI')&&WP_CLI){throw $e;}
			static $shown=array();if(!isset($shown[$user])){$shown[$user]=true;$message=__('Loyalty spending is unavailable while this account is reconciled. Existing points are preserved.','nodevora-customer-rewards-for-woocommerce');
				$db=null;try{$db=new Nodevora_Financial_DB();$row=$db->one('SELECT payload FROM '.$db->table('snapshot').' WHERE user_id=? AND version=1',array((int)$user));if($row){$p=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);foreach($p['rows'] as $meta){if('rewardly_loyalty_points_balance'===$meta['meta_key']&&preg_match('/^[0-9]{1,19}$/D',$meta['meta_value'])){$message.=' '.sprintf(__('Preserved opening balance: %s points.','nodevora-customer-rewards-for-woocommerce'),$meta['meta_value']);break;}}}}catch(Throwable $ignored){}finally{if($db){$db->close();}}
				add_action('wp_footer',static function()use($message){echo '<div class="woocommerce-info" role="status">'.esc_html($message).'</div>';});
			}
			return in_array($kind,array('lots','logs'),true)?array():0;
		}
	}
	public static function legacy_write_guard() {
		// L’ancien stockage reste consultable, mais ne constitue plus une autorité d’écriture.
		throw new Nodevora_Financial_Error('Verified financial setup and an operation identity are required.');
	}
	/** Immutable opening reporting totals, followed by gross journal semantics. */
	public static function total($user_id, $type) {
		self::balance($user_id); // Fail closed for unmigrated/reconciliation accounts.
		$db = new Nodevora_Financial_DB();
		try {
			$row = $db->one('SELECT payload FROM '.$db->table('snapshot').' WHERE user_id=? AND version=1',array((int)$user_id));
			if (!$row) { throw new Nodevora_Financial_Error('Opening reporting snapshot is missing.'); }
			$snapshot = json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR); $total = 0;
			$key = 'rewardly_loyalty_points_'.$type.'_total';
			foreach ($snapshot['rows'] as $meta) { if ($key === $meta['meta_key']) { $total = (int)$meta['meta_value']; } }
			$events = $db->query('SELECT kind,gross_points,payload FROM '.$db->table('event').' WHERE user_id=? AND kind IN (?,?,?) ORDER BY id',array((int)$user_id,'credit','debit','commit'));
			foreach ($events as $event) {
				$payload = $event['payload'] ? json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR) : array();
				$source = isset($payload['details']['type']) ? $payload['details']['type'] : '';
				if ('spent' === $type && in_array($event['kind'],array('debit','commit'),true)) { $total += (int)$event['gross_points']; }
				if ('earned' === $type && 'credit' === $event['kind'] && in_array($source,array('earn','bonus_registration','bonus_first_order','bonus_review','bonus_birthday','bonus_referral'),true)) { $total += (int)$event['gross_points']; }
			}
			return Nodevora_Financial_Wallet::points($total);
		} finally { $db->close(); }
	}
	public static function lots($user_id) {
		self::balance($user_id); $db = new Nodevora_Financial_DB();
		try {
			$rows = $db->query('SELECT * FROM '.$db->table('lot').' WHERE user_id=? ORDER BY id',array((int)$user_id)); $lots = array();
			foreach ($rows as $row) {
				$source = json_decode($row['provenance'],true,512,JSON_THROW_ON_ERROR);
				$lots[] = array('id'=>'v2:'.$row['id'],'points_remaining'=>(int)$row['remaining'],'points_reserved'=>(int)$row['reserved'],'earned_at'=>$row['earned_at'] ? get_date_from_gmt($row['earned_at']) : '', 'expires_at_utc'=>$row['expires_at'],'source_type'=>isset($source['type'])?$source['type']:(isset($source['source_type'])?$source['source_type']:'migration'),'order_id'=>isset($source['order_id'])?(int)$source['order_id']:0,'note'=>isset($source['note'])?$source['note']:'');
			}
			return $lots;
		} finally { $db->close(); }
	}
	/** Historical legacy rows stay read-only; no new journal event is mirrored into that table. */
	public static function logs($user_id, $limit) {
		self::balance($user_id); global $wpdb; $limit = min(1000,max(1,(int)$limit));
		$legacy = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE user_id=%d ORDER BY id DESC LIMIT %d',$wpdb->prefix.'rewardly_loyalty_log',$user_id,$limit));
		if (null === $legacy || $wpdb->last_error) { throw new Nodevora_Financial_Error('Historical reporting is unavailable.'); }
		$db = new Nodevora_Financial_DB();
		try {
			$events = $db->query('SELECT * FROM '.$db->table('event').' WHERE user_id=? AND kind NOT IN (?,?) ORDER BY id DESC LIMIT '.$limit,array((int)$user_id,'opening','legacy_claim'));
			foreach ($events as $event) {
				$p = $event['payload'] ? json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR) : array(); $d = isset($p['details'])?$p['details']:array();
				$legacy[] = (object)array('id'=>'v2:'.$event['id'],'user_id'=>(int)$user_id,'order_id'=>isset($d['order_id'])?(int)$d['order_id']:0,'type'=>isset($d['type'])?$d['type']:$event['kind'],'points'=>'expire'===$event['kind']?abs((int)$event['delta_balance']):(int)$event['gross_points'],'amount_dh'=>isset($d['discount_minor'],$d['scale'])?$d['discount_minor']/$d['scale']:0,'note'=>isset($d['note'])?$d['note']:'','created_at'=>get_date_from_gmt($event['created_at']),'created_at_utc'=>$event['created_at']);
			}
			usort($legacy,function($a,$b){$date=strcmp($b->created_at,$a->created_at);return $date?:strcmp((string)$b->id,(string)$a->id);});
			return array_slice($legacy,0,$limit);
		} finally { $db->close(); }
	}
}
