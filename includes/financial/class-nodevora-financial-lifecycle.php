<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** WC CRUD policy boundary. Financial transactions never invoke these callbacks. */
class Nodevora_Financial_Lifecycle {
	public static function init() {
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
		$order=wc_get_order($id);if(!$order||!$order->get_user_id()||!Rewardly_Loyalty_Helpers::is_enabled()){return;}
		try{Nodevora_Financial_Checkout::require_enabled();}catch(Nodevora_Financial_Error $e){$order->update_meta_data('_nodevora_earning_pending','yes');$order->save();return;}
		$s=Rewardly_Loyalty_Helpers::get_settings();if($s['order_status_trigger']!==$order->get_status()){return;}
		if($order->get_refunds()&&'refunded'!==$order->get_status()){self::exception($order,'Partial-refund earning policy requires reconciliation.');return;}
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
		// A newly inserted WP user has no legacy financial history; never use this for existing users.
		$db=new Nodevora_Financial_DB();try{$db->begin();$db->query('INSERT IGNORE INTO '.$db->table('wallet').' (user_id,state,migration_version) VALUES (?,?,1)',array((int)$user,'ready'));$w=$db->one('SELECT * FROM '.$db->table('wallet').' WHERE user_id=? FOR UPDATE',array((int)$user));if(!$w||'ready'!==$w['state']){throw new Nodevora_Financial_Error('New account conflicts with migration state.');}$payload=json_encode(array('rows'=>array(),'new_account'=>true),JSON_THROW_ON_ERROR);$db->query('INSERT IGNORE INTO '.$db->table('snapshot').' (user_id,version,checksum,payload,created_at) VALUES (?,1,?,?,UTC_TIMESTAMP())',array((int)$user,hash('sha256',$payload),$payload));$db->commit();}catch(Throwable $e){$db->rollback();throw $e;}finally{$db->close();}
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
		if(!Nodevora_Financial_Compatibility::selected()){return;}$o=wc_get_order($id);if(!$o||!$o->get_meta('_rewardly_loyalty_points_spent')){return;}
		try{Nodevora_Financial_Checkout::operation($o,'paid');}catch(Nodevora_Financial_Error $e){self::exception($o,'Payment received without current funding; fulfillment requires manual review.');}
	}
	public static function exception($order,$reason) {
		$order->update_meta_data('_nodevora_financial_exception',$reason);$order->save();
		// Persist an explicit fulfillment hold outside financial transactions.
		if(in_array($order->get_status(),array('processing','completed'),true)){$order->update_status('on-hold','Nodevora financial reconciliation required.');}
	}
}
