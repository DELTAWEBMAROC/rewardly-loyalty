<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** WC CRUD policy boundary. Financial transactions never invoke these callbacks. */
class Nodevora_Financial_Lifecycle {
	public static function init() {
		add_action('woocommerce_before_order_object_save',array(__CLASS__,'enforce_hold'),1);
		add_filter('woocommerce_payment_complete_order_status',static function($status,$id,$order){return self::hold_required($order)?'on-hold':$status;},PHP_INT_MAX,3);
		add_action('woocommerce_order_status_failed',array(__CLASS__,'failed'));
		add_action('woocommerce_order_refunded',array(__CLASS__,'refund'),20,2);
		add_action('woocommerce_payment_complete',array(__CLASS__,'payment_complete'),5);
		add_action('transition_comment_status',array(__CLASS__,'review_transition'),20,3);
		add_action('delete_comment',array(__CLASS__,'review_delete'),20,2);
	}
	public static function event($key) {
		$db=new Nodevora_Financial_DB();try{return $db->one('SELECT * FROM '.$db->table('event').' WHERE operation_key=?',array($key));}finally{$db->close();}
	}
	public static function credit_details($type,$order_id=0) {
		$s=Rewardly_Loyalty_Helpers::get_settings();$now=gmdate('Y-m-d H:i:s');$days=max(0,(int)$s['points_expiration_days']);
		return array('type'=>$type,'order_id'=>(int)$order_id,'earned_at'=>$now,'expires_at'=>$days?gmdate('Y-m-d H:i:s',time()+$days*DAY_IN_SECONDS):null);
	}
	public static function claim($user,$key,$amount,$details) {
		$old=self::event($key);if($old){if((int)$old['user_id']!==$user){throw new Nodevora_Financial_Error('Claim owner conflict.');}return $old;}
		try {(new Nodevora_Financial_Wallet())->apply($user,$key,'credit',$amount,$details);}
		catch(Nodevora_Financial_Error $e){$old=self::event($key);if(!$old||(int)$old['user_id']!==$user){throw $e;}}
		return self::event($key);
	}
	/** Persist a zero-discount intent too, so earning and reversal serialize on the order. */
	private static function bind($order) {
		$q=$order->get_meta('_nodevora_financial_quote');
		if(!is_array($q)){
			foreach($order->get_meta_data() as $meta){$data=$meta->get_data();if(0===strpos($data['key'],'_rewardly')&&$data['value']!==''){throw new Nodevora_Financial_Error('Legacy order entitlement needs review.');}}
			$p=Nodevora_Financial_Checkout::policy();$q=array('intent'=>substr(hash('sha256','order:'.$order->get_id().':earning:v1'),0,32),'points'=>0,'discount_minor'=>0,'currency'=>$order->get_currency(),'scale'=>$p['scale'],'policy'=>$p);$order->update_meta_data('_nodevora_financial_quote',$q);$order->save();
		}
		$state=Nodevora_Financial_Order::read($order->get_id());
		if($state&&'closed'===$state['state']){
			if($q['points']){throw new Nodevora_Financial_Error('Revived order requires fresh payment funding.');}
			$q['intent']=bin2hex(random_bytes(16));$order->update_meta_data('_nodevora_financial_quote',$q);$order->save();
		}
		return Nodevora_Financial_Checkout::quote($order);
	}
	public static function earn($id) {
		$order=wc_get_order($id);if(!$order){return;}if(self::hold_required($order)){self::enforce_hold($order);$order->save();return;}if(!$order->get_user_id()||!Rewardly_Loyalty_Helpers::is_enabled()){return;}
		try{Nodevora_Financial_Checkout::require_enabled();}catch(Nodevora_Financial_Error $e){$order->update_meta_data('_nodevora_earning_pending','yes');$order->save();return;}
		$s=Rewardly_Loyalty_Helpers::get_settings();if($s['order_status_trigger']!==$order->get_status()){return;}
		if($order->get_refunds()){return;}
		try{
			$q=self::bind($order);$key='order:'.$id.':'.$q['intent'].':earn';$old=self::event($key);
			if(!$old){$details=self::credit_details('earn',$id);$details['order']=$q;$details['order_action']='earn';$old=self::claim((int)$order->get_user_id(),$key,Rewardly_Loyalty_Helpers::calculate_order_earned_points($order),$details);}
			$order->update_meta_data('_rewardly_loyalty_points_earned',(int)$old['gross_points']);$order->update_meta_data('_rewardly_loyalty_processed','yes');$order->delete_meta_data('_nodevora_earning_pending');$order->save();
			if(class_exists('Rewardly_Loyalty_Pro')&&Rewardly_Loyalty_Pro::can_use_feature('extra_points')){
				$amount=max(0,(int)$s['pro_points_first_order']);if($amount){$details=self::credit_details('bonus_first_order',$id);$details['order']=$q;$details['order_action']='bonus';self::claim((int)$order->get_user_id(),'user:'.$order->get_user_id().':first_order:v1',$amount,$details);}
			}
		}catch(Nodevora_Financial_Error $e){self::exception($order,$e->getMessage());}
	}
	public static function registration($user) {
		try{Nodevora_Financial_Checkout::require_enabled();}catch(Nodevora_Financial_Error $e){if(Rewardly_Loyalty_Helpers::is_enabled()){update_user_meta($user,'_nodevora_registration_pending','yes');}return;}
		// The user_register path shares the restore/cutover barrier with all wallet writers.
		$payload=json_encode(array('rows'=>array(),'new_account'=>true),JSON_THROW_ON_ERROR);$hash=hash('sha256',$payload);
		$db=null;
		try{
			$db=new Nodevora_Financial_DB();$db->begin();$state=Nodevora_Financial_Setup::lock_state($db);
			if(($state['phase']??'')!=='active'){throw new Nodevora_Financial_Error('Account initialization is paused.');}
			$db->query('INSERT IGNORE INTO '.$db->table('wallet').' (user_id,state,migration_version,checksum) VALUES (?,?,1,?)',array((int)$user,'ready',$hash));
			$w=$db->one('SELECT * FROM '.$db->table('wallet').' WHERE user_id=? FOR UPDATE',array((int)$user));
			if(!$w||'ready'!==$w['state']){throw new Nodevora_Financial_Error('New account conflicts with migration state.');}
			$snapshot=$db->one('SELECT checksum,payload FROM '.$db->table('snapshot').' WHERE user_id=? AND version=1',array((int)$user));
			if(!$snapshot){
				if($w['checksum']!==$hash){throw new Nodevora_Financial_Error('New account snapshot conflicts with existing evidence.');}
				$db->query('INSERT INTO '.$db->table('snapshot').' (user_id,version,checksum,payload,created_at) VALUES (?,1,?,?,UTC_TIMESTAMP())',array((int)$user,$hash,$payload));
			}elseif($w['checksum']===null&&$snapshot['payload']===$payload&&hash_equals($hash,$snapshot['checksum'])){
				// Repair only this previously generated, fully identified empty opening snapshot.
				$db->query('UPDATE '.$db->table('wallet').' SET checksum=? WHERE user_id=? AND checksum IS NULL',array($hash,(int)$user));
			}elseif($w['checksum']!==$snapshot['checksum']||!hash_equals($snapshot['checksum'],hash('sha256',$snapshot['payload']))){throw new Nodevora_Financial_Error('New account snapshot conflicts with existing evidence.');}
			$db->commit();
		}catch(Throwable$e){if($db){$db->rollback();}update_user_meta($user,'_nodevora_registration_pending','yes');return;}finally{if($db){$db->close();}}

		$s=Rewardly_Loyalty_Helpers::get_settings();if(Rewardly_Loyalty_Helpers::is_enabled()&&class_exists('Rewardly_Loyalty_Pro')&&Rewardly_Loyalty_Pro::can_use_feature('extra_points')&&(int)$s['pro_points_registration']>0){self::claim((int)$user,'user:'.$user.':registration:v1',(int)$s['pro_points_registration'],self::credit_details('bonus_registration'));}
	}
	public static function review($id,$approved,$data) {
		$c=get_comment($id);$s=Rewardly_Loyalty_Helpers::get_settings();
		if(!$c||!$c->user_id||(int)$c->comment_parent!==0||!in_array($c->comment_type,array('review',''),true)||'product'!==get_post_type($c->comment_post_ID)||1!==(int)$approved||!Rewardly_Loyalty_Helpers::is_enabled()||!class_exists('Rewardly_Loyalty_Pro')||!Rewardly_Loyalty_Pro::can_use_feature('extra_points')||(int)$s['pro_points_review']<1){return;}
		if('yes'===get_comment_meta($id,'_rewardly_bonus_review_awarded',true)){return;}
		try {
			Nodevora_Financial_Checkout::require_enabled();
			self::claim((int)$c->user_id,'review:'.$id.':bonus:v1',(int)$s['pro_points_review'],self::credit_details('bonus_review'));
			delete_comment_meta($id,'_nodevora_review_pending');
		} catch(Nodevora_Financial_Error $e) { update_comment_meta($id,'_nodevora_review_pending','yes'); }
	}

	public static function review_transition($new,$old,$comment) {
		if(!Nodevora_Financial_Compatibility::selected()||$new===$old){return;}
		if('approved'===$new){self::review($comment->comment_ID,1,array());}
		elseif(in_array($new,array('spam','trash','unapproved'),true)){self::review_delete($comment->comment_ID,$comment);}
	}

	public static function review_delete($id,$comment=null) {
		if(!Nodevora_Financial_Compatibility::selected()){return;}
		/* Une suppression ne recrée jamais un droit historique ni un second bonus. */
		try {
			Nodevora_Financial_Checkout::require_enabled(true);$origin=self::event('review:'.$id.':bonus:v1');
			if(!$origin||'credit'!==$origin['kind']||(int)$origin['gross_points']===0){return;}
			(new Nodevora_Financial_Wallet())->apply((int)$origin['user_id'],'review:'.$id.':reverse:v1','reverse',(int)$origin['gross_points'],array('original_event_id'=>(int)$origin['id'],'type'=>'revoke','note'=>'Review reward withdrawn after moderation.'));
			delete_comment_meta($id,'_nodevora_review_pending');
		} catch(Nodevora_Financial_Error $e) { update_comment_meta($id,'_nodevora_review_reconciliation','yes'); }
	}
	public static function reverse($id) {
		Nodevora_Financial_Checkout::require_enabled(true);$order=wc_get_order($id);if(!$order||!$order->get_user_id()||!in_array($order->get_status(),array('cancelled','refunded','failed'),true)){return;}
		try{
			$pre=Nodevora_Financial_Order::read($id);if($pre&&'dispatched'===$pre['state']&&!Nodevora_Financial_Checkout::supported_payment($order->get_payment_method(),$order)&&'refunded'!==$order->get_status()){throw new Nodevora_Financial_Unknown('Uncertain provider outcome: retain funding for reviewed reconciliation.');}
			$q=$order->get_meta('_nodevora_financial_quote')?Nodevora_Financial_Checkout::quote($order):self::bind($order);$uid=(int)$order->get_user_id();$state=Nodevora_Financial_Order::read($id);if(!$state){Nodevora_Financial_Checkout::operation($order,'close');return;}
			foreach(array('earn'=>'order:'.$id.':'.$q['intent'].':earn','bonus'=>'user:'.$uid.':first_order:v1') as $type=>$key){
				$origin=self::event($key);if(!$origin||'credit'!==$origin['kind']){continue;}$p=json_decode($origin['payload'],true,512,JSON_THROW_ON_ERROR);if((int)$p['details']['order_id']!==(int)$id){continue;}
				if(self::event('reverse:'.$origin['id'].':v1')){continue;}
				(new Nodevora_Financial_Wallet())->apply($uid,'reverse:'.$origin['id'].':v1','reverse',(int)$origin['gross_points'],array('original_event_id'=>(int)$origin['id'],'order'=>$q,'order_action'=>'reverse_'.$type,'order_id'=>(int)$id,'type'=>'revoke'));
			}
			if($state['debit_event_id']){Nodevora_Financial_Checkout::operation($order,'compensate',array('original_event_id'=>(int)$state['debit_event_id']));}
			else{Nodevora_Financial_Checkout::operation($order,'close');}
		}catch(Nodevora_Financial_Error $e){self::exception($order,$e->getMessage());}
	}
	public static function failed($id) { if(Nodevora_Financial_Compatibility::selected()){self::reverse($id);} }
	public static function refund($id,$refund_id) {
		if(!Nodevora_Financial_Compatibility::selected()){return;}
		$order=wc_get_order($id);$refund=wc_get_order($refund_id);
		if(!$order||!$refund instanceof WC_Order_Refund||(int)$refund->get_parent_id()!==(int)$id){return;}
		if('refunded'===$order->get_status()){self::reverse($id);return;}
		self::exception($order,'Partial-refund allocation policy requires reconciliation; refund '.(int)$refund_id.'.');
		if(!$order->get_user_id()){return;}
		try {
			Nodevora_Financial_Checkout::require_enabled(true);
			(new Nodevora_Financial_Wallet())->apply((int)$order->get_user_id(),'refund:'.(int)$refund_id.':review:v1','control',0,array('type'=>'refund_review','order_id'=>(int)$id,'refund_id'=>(int)$refund_id,'note'=>'Partial refund requires reconciliation; no proportional points adjustment was applied.'));
		} catch(Nodevora_Financial_Error $e) { $order->update_meta_data('_nodevora_refund_record_pending',(int)$refund_id);$order->save(); }
	}
	public static function payment_complete($id) {
		if(!Nodevora_Financial_Compatibility::selected()){return;}$o=wc_get_order($id);if($o&&self::hold_required($o)){self::enforce_hold($o);$o->save();return;}if(!$o||!$o->get_meta('_rewardly_loyalty_points_spent')){return;}
		try{Nodevora_Financial_Checkout::operation($o,'paid');}catch(Nodevora_Financial_Error $e){self::exception($o,'Payment received without current funding; fulfillment requires manual review.');}
	}
	/** Le registre immuable prévaut sur un objet WooCommerce ancien ou un indicateur effacé. */
	public static function hold_required($order) {
		if(!$order instanceof WC_Order || $order instanceof WC_Order_Refund || !$order->get_id() || !Nodevora_Financial_Compatibility::selected()){return false;}
		$refunds=wc_get_orders(array('type'=>'shop_order_refund','parent'=>$order->get_id(),'limit'=>-1,'return'=>'ids'));
		foreach($refunds as$id){
			try{if(!self::event('refund:'.(int)$id.':resolution:v1')){return true;}}
			catch(Nodevora_Financial_Error $e){return true;}
		}
		return false;
	}
	public static function enforce_hold($order) {
		if(self::hold_required($order)&&in_array($order->get_status(),array('processing','completed'),true)){
			$order->set_status('on-hold');
			$order->update_meta_data('_nodevora_financial_exception','Partial refund requires an authorized reconciliation decision.');
		}
	}
	public static function resolve_refund($order_id,$refund_id,$note) {
		if(!current_user_can('manage_options')){throw new Nodevora_Financial_Error('Administrator financial review required.');}
		if(!is_string($note)||strlen(trim($note))<10||strlen($note)>2000){throw new Nodevora_Financial_Error('Record the authorized review decision and reference (10–2000 characters).');}
		$order=wc_get_order($order_id);$refund=wc_get_order($refund_id);
		if(!$order||!$refund instanceof WC_Order_Refund||(int)$refund->get_parent_id()!==(int)$order_id||!$order->get_user_id()){throw new Nodevora_Financial_Error('A matching registered-customer refund is required.');}
		Nodevora_Financial_Checkout::require_enabled(true);
		$key='refund:'.$refund_id.':resolution:v1';$old=self::event($key);if($old){return $old;}
		$review=self::event('refund:'.$refund_id.':review:v1');
		if(!$review){self::refund($order_id,$refund_id);$review=self::event('refund:'.$refund_id.':review:v1');}
		if(!$review){throw new Nodevora_Financial_Error('The immutable refund review must be recorded before resolution.');}
		$result=(new Nodevora_Financial_Wallet())->apply((int)$order->get_user_id(),$key,'control',0,array('type'=>'refund_resolution','order_id'=>$order_id,'refund_id'=>$refund_id,'review_event_id'=>(int)$review['id'],'reviewer'=>get_current_user_id(),'note'=>$note));
		$order->add_order_note(__('Authorized refund reconciliation recorded; no automatic points movement or status change.','nodevora-customer-rewards-for-woocommerce').' #'.$result['event_id']);
		if(!self::hold_required($order)){$order->delete_meta_data('_nodevora_financial_exception');$order->delete_meta_data('_nodevora_refund_record_pending');}$order->save();return $result;
	}

	public static function exception($order,$reason) {
		$order->update_meta_data('_nodevora_financial_exception',$reason);$order->save();
		// Persist an explicit fulfillment hold outside financial transactions.
		if(in_array($order->get_status(),array('processing','completed'),true)){$order->update_status('on-hold','Nodevora financial reconciliation required.');}
	}
}
