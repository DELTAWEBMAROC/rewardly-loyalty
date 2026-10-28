<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Leased at-least-once effects; WordPress, mail and gateways are outside financial transactions. */
class Nodevora_Financial_Recovery {
	public static function init() { add_action('nodevora_financial_recover',array(__CLASS__,'run'));add_action('init',array(__CLASS__,'schedule')); }
	public static function schedule(){if(Nodevora_Financial_Compatibility::selected()&&(('local'===wp_get_environment_type()&&defined('NODEVORA_FINANCIAL_INTEGRATION_ALLOWED')&&NODEVORA_FINANCIAL_INTEGRATION_ALLOWED)||(Nodevora_Financial_Setup::integrated()&&!empty(Nodevora_Financial_Setup::state()['recovery_approved'])))&&!wp_next_scheduled('nodevora_financial_recover')){wp_schedule_event(time()+60,'hourly','nodevora_financial_recover');}}
	private static function take($user=0) {
		$db=new Nodevora_Financial_DB();try{$db->begin();$row=$db->one('SELECT * FROM '.$db->table('outbox').' WHERE state IN (?,?) AND (lease_until IS NULL OR lease_until<=UTC_TIMESTAMP())'.($user?' AND user_id='.(int)$user:'').' ORDER BY id LIMIT 1 FOR UPDATE',array('pending','processing'));if(!$row){$db->rollback();return null;}
			if(1!==$db->query('UPDATE '.$db->table('outbox').' SET state=?,attempts=attempts+1,lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 60 SECOND) WHERE id=?',array('processing',(int)$row['id']))){throw new Nodevora_Financial_Error('Outbox lease failed.');}$row['attempts']=(int)$row['attempts']+1;$db->commit();return $row;
		}catch(Throwable $e){$db->rollback();throw $e;}finally{$db->close();}
	}
	private static function finish($row,$success) {
		$db=new Nodevora_Financial_DB();try{$state=$success?'delivered':($row['attempts']>=5?'dead':'pending');$seconds=min(3600,30*(int)pow(2,$row['attempts']));
			$db->query('UPDATE '.$db->table('outbox').' SET state=?,lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL '.$seconds.' SECOND) WHERE id=? AND state=? AND attempts=?',array($state,(int)$row['id'],'processing',$row['attempts']));
		}finally{$db->close();}
	}
	private static function effect($row) {
		$user=(int)$row['user_id'];$wallet=(new Nodevora_Financial_Wallet())->read($user);
		if('project'===$row['effect']){
			update_user_meta($user,'rewardly_loyalty_points_balance',(int)$wallet['balance']);
			update_user_meta($user,'rewardly_loyalty_points_earned_total',Nodevora_Financial_Compatibility::total($user,'earned'));
			update_user_meta($user,'rewardly_loyalty_points_spent_total',Nodevora_Financial_Compatibility::total($user,'spent'));
			update_user_meta($user,'rewardly_loyalty_point_lots',Nodevora_Financial_Compatibility::lots($user));
			$result=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
			if(isset($result['order_id'])){
				$state=Nodevora_Financial_Order::read($result['order_id']);$order=wc_get_order($result['order_id']);
				if($state&&$order&&(int)$order->get_user_id()===$user){
					if(!$order->get_meta('_nodevora_financial_quote')){$quote=json_decode($state['data'],true,512,JSON_THROW_ON_ERROR);unset($quote['order_id'],$quote['fingerprint']);$order->update_meta_data('_nodevora_financial_quote',$quote);}
					$order->update_meta_data('_nodevora_financial_state',$state['state']);$order->update_meta_data('_nodevora_financial_debit_event',(int)$state['debit_event_id']);
					$order->update_meta_data('_rewardly_loyalty_spent_processed',$state['debit_event_id']?'yes':'no');$order->update_meta_data('_rewardly_loyalty_spent_restored','closed'===$state['state']&&$state['debit_event_id']?'yes':'no');if(!$order->save()){throw new Nodevora_Financial_Error('Order projection was not persisted.');}
					$current=Nodevora_Financial_Order::read($result['order_id']);if($current['revision']!==$state['revision']){throw new Nodevora_Financial_Error('Order projection revision changed; retry.');}
				}
			}
			if((string)get_user_meta($user,'rewardly_loyalty_points_balance',true)!==(string)$wallet['balance']||(int)get_user_meta($user,'rewardly_loyalty_points_earned_total',true)!==Nodevora_Financial_Compatibility::total($user,'earned')||(int)get_user_meta($user,'rewardly_loyalty_points_spent_total',true)!==Nodevora_Financial_Compatibility::total($user,'spent')||get_user_meta($user,'rewardly_loyalty_point_lots',true)!==Nodevora_Financial_Compatibility::lots($user)){throw new Nodevora_Financial_Error('Metadata projection was not persisted.');}
			clean_user_cache($user);
			$after=(new Nodevora_Financial_Wallet())->read($user);if($after['revision']!==$wallet['revision']){throw new Nodevora_Financial_Error('Projection revision changed; retry.');}
			return;
		}
		if('notify'!==$row['effect']){throw new Nodevora_Financial_Error('Unknown outbox effect.');}
		$db=new Nodevora_Financial_DB();try{$event=$db->one('SELECT * FROM '.$db->table('event').' WHERE id=? AND user_id=?',array((int)$row['event_id'],$user));}finally{$db->close();}
		if(!$event||!$event['payload']){throw new Nodevora_Financial_Error('Notification event unavailable.');}
		$p=json_decode($event['payload'],true,512,JSON_THROW_ON_ERROR);$d=$p['details'];$type=isset($d['type'])?$d['type']:$event['kind'];
		$amount=isset($d['discount_minor'],$d['scale'])?$d['discount_minor']/$d['scale']:0;
		if(false===Rewardly_Loyalty_Emails::maybe_send_points_notification($user,$type,(int)$event['gross_points'],$amount,isset($d['note'])?$d['note']:'','nodevora-event-'.$event['id'])){throw new Nodevora_Financial_Error('Notification delivery failed.');}
	}
	public static function outbox($limit=20,$user=0) {
		Nodevora_Financial_Checkout::require_enabled(true);$done=0;$failed=0;
		for($i=0;$i<min(100,max(1,(int)$limit));$i++){$row=self::take((int)$user);if(!$row){break;}try{self::effect($row);self::finish($row,true);$done++;}catch(Throwable $e){self::finish($row,false);$failed++;}}
		return array('delivered'=>$done,'failed'=>$failed);
	}
	public static function holds($user=0) {
		Nodevora_Financial_Checkout::require_enabled(true);$db=new Nodevora_Financial_DB();try{
			$holds=$db->query('SELECT e.id,e.user_id,e.operation_key,e.result,SUM(a.delta_reserved) AS held FROM '.$db->table('event').' e JOIN '.$db->table('allocation').' a ON a.reservation_key=e.operation_key WHERE e.kind=? AND JSON_UNQUOTE(JSON_EXTRACT(e.result,?))<=UTC_TIMESTAMP()'.($user?' AND e.user_id='.(int)$user:'').' GROUP BY e.id,e.user_id,e.operation_key,e.result HAVING held>0 ORDER BY e.id LIMIT 50',array('reserve','$.deadline'));
		}finally{$db->close();}
		foreach($holds as $hold){$r=json_decode($hold['result'],true,512,JSON_THROW_ON_ERROR);if(isset($r['deadline'])&&$r['deadline']<=gmdate('Y-m-d H:i:s')){(new Nodevora_Financial_Wallet())->apply((int)$hold['user_id'],'hold:'.$hold['id'].':release','release',(int)$hold['held'],array('reservation'=>$hold['operation_key']));}}
	}
	/** Le curseur est indicatif : chaque effet conserve son identité et son contrôle transactionnel. */
	public static function order_batch($after=0,$limit=50) {
		$db=new Nodevora_Financial_DB();
		try{return $db->query('SELECT order_id,state FROM '.$db->table('order_state').' WHERE order_id>? AND state IN (?,?,?,?,?) ORDER BY order_id LIMIT '.min(50,max(1,(int)$limit)),array(max(0,(int)$after),'bound','funded','dispatched','paid','reversing'));}
		finally{$db->close();}
	}
	/** Réconcilier une seule commande permet une reprise ciblée sans vider une file inconnue. */
	public static function recover_order($id) {
		Nodevora_Financial_Checkout::require_enabled(true);$order=wc_get_order($id);if(!$order){return;}
		if(Nodevora_Financial_Lifecycle::hold_required($order)){Nodevora_Financial_Lifecycle::enforce_hold($order);$order->save();return;}
		$state=Nodevora_Financial_Order::read($id);if(!$state){return;}
		if(in_array($order->get_status(),array('cancelled','refunded','failed'),true)){Nodevora_Financial_Lifecycle::reverse($id);}
		elseif(in_array($order->get_status(),array('completed','processing'),true)){Nodevora_Financial_Lifecycle::payment_complete($id);if(!Nodevora_Financial_Setup::paused()){Nodevora_Financial_Lifecycle::earn($id);}}
		elseif('dispatched'===$state['state']){$order->update_meta_data('_nodevora_financial_recovery','Payment outcome unknown; retain funding. Do not redispatch automatically.');$order->save();}
	}
	public static function run() {
		Nodevora_Financial_Checkout::require_enabled(true);$db=new Nodevora_Financial_DB();try{
			$orders=self::order_batch((int)get_option('nodevora_financial_order_cursor',0));
			if(!$orders){update_option('nodevora_financial_order_cursor',0,false);}

			$expiry_cursor=(int)get_option('nodevora_financial_expiry_cursor',0);
			$users=$db->query('SELECT DISTINCT l.user_id FROM '.$db->table('lot').' l JOIN '.$db->table('wallet').' w ON w.user_id=l.user_id WHERE l.user_id>? AND w.state=? AND l.remaining>l.reserved AND l.expires_at<=UTC_TIMESTAMP() ORDER BY l.user_id LIMIT 50',array($expiry_cursor,'ready'));
			if(!$users){update_option('nodevora_financial_expiry_cursor',0,false);}
		}finally{$db->close();}
		foreach($orders as $row){update_option('nodevora_financial_order_cursor',(int)$row['order_id'],false);self::recover_order((int)$row['order_id']);}
		self::holds();
		foreach($users as $row){$uid=(int)$row['user_id'];update_option('nodevora_financial_expiry_cursor',$uid,false);$wallet=(new Nodevora_Financial_Wallet())->read($uid);(new Nodevora_Financial_Wallet())->apply($uid,'user:'.$uid.':expire:'.$wallet['revision'],'expire',0);}
		return self::outbox();
	}
}
