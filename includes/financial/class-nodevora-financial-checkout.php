<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Nodevora_Financial_Checkout {
	const FEE_ID='nodevora-wallet-discount';
	public static function init() {
		add_action('woocommerce_checkout_create_order_fee_item',array(__CLASS__,'tag_fee'),10,4);
		add_action('woocommerce_before_pay_action',array(__CLASS__,'pay'),PHP_INT_MAX);
		add_action('woocommerce_checkout_order_processed',array(__CLASS__,'classic'),PHP_INT_MAX,3);
		add_action('woocommerce_store_api_checkout_order_processed',array(__CLASS__,'blocks'),PHP_INT_MAX);
	}
	public static function require_enabled($recovery=false) {
		if (!$recovery&&Nodevora_Financial_Setup::paused()){throw new Nodevora_Financial_Error('New financial writes are paused; recovery remains available.');}
		if(!Nodevora_Financial_Compatibility::selected()){throw new Nodevora_Financial_Error('Verified financial setup is required.');}
		if (is_multisite() || (defined('NODEVORA_FINANCIAL_INTEGRATION_ALLOWED')&&!NODEVORA_FINANCIAL_INTEGRATION_ALLOWED) || (!Nodevora_Financial_Setup::integrated() && ('local'!==wp_get_environment_type()||!defined('NODEVORA_FINANCIAL_INTEGRATION_ALLOWED')||true!==NODEVORA_FINANCIAL_INTEGRATION_ALLOWED))) { throw new Nodevora_Financial_Error('Financial integration is disabled pending local validation.'); }
	}
	public static function policy() {
		$s=Rewardly_Loyalty_Helpers::get_settings();$p=array('ratio'=>max(1,(int)$s['redeem_points_per_dh']),'decimals'=>(int)wc_get_price_decimals(),'currency'=>strtoupper(get_woocommerce_currency()));
		$p['scale']=Nodevora_Financial_Money::scale($p['decimals']);$p['version']=hash('sha256',json_encode($p,JSON_THROW_ON_ERROR));return $p;
	}
	public static function cart($cart) {
		if(!$cart instanceof WC_Cart||!WC()->session){return;}
		/* Ne pas salir la session par un aller-retour à zéro lors d'une simple relecture. */
		if(!is_user_logged_in()||!Rewardly_Loyalty_Helpers::is_enabled()||'yes'!==WC()->session->get('rewardly_use_points')){self::clear_cart_projection();return;}
		self::require_enabled();$s=Rewardly_Loyalty_Helpers::get_settings();$available=Nodevora_Financial_Compatibility::balance(get_current_user_id());$requested=(int)WC()->session->get('rewardly_requested_points');
		if($requested<1||$available<(int)$s['min_points_to_redeem']||$requested>$available){throw new Nodevora_Financial_Error('Requested points are no longer available.');}
		$p=self::policy();WC()->session->__unset('nodevora_financial_quote_error');$minor=Nodevora_Financial_Money::discount($requested,$p['ratio'],$p['scale']);
		$cap=Nodevora_Financial_Money::minor(wc_format_decimal($cart->get_cart_contents_total(),$p['decimals']),$p['decimals']);
		if((float)$s['max_discount_per_order']>0){$cap=min($cap,Nodevora_Financial_Money::minor(wc_format_decimal($s['max_discount_per_order'],$p['decimals']),$p['decimals']));}
		$minor=min($minor,max(0,$cap));$points=Nodevora_Financial_Money::charge($minor,$p['ratio'],$p['scale']);if(!$minor||!$points){self::clear_cart_projection();return;}
		$amount=Nodevora_Financial_Money::decimal(-$minor,$p['decimals']);
		$cart->fees_api()->add_fee(array('id'=>self::FEE_ID,'name'=>__('Loyalty discount','nodevora-customer-rewards-for-woocommerce'),'amount'=>$amount,'taxable'=>false));
		WC()->session->set('nodevora_quote_policy',$p);WC()->session->set('rewardly_applied_points',$points);WC()->session->set('rewardly_loyalty_discount_amount',Nodevora_Financial_Money::decimal($minor,$p['decimals']));
	}
	private static function clear_cart_projection() { WC()->session->set('rewardly_loyalty_discount_amount',0);WC()->session->set('rewardly_applied_points',0); }
	public static function tag_fee($item,$fee_key,$fee,$order) { if(Nodevora_Financial_Compatibility::selected()&&self::FEE_ID===$fee_key){$item->add_meta_data('_nodevora_wallet_fee','v1',true);} }
	private static function normalize($value) {
		if(is_array($value)){ksort($value);foreach($value as $key=>$part){$value[$key]=self::normalize($part);}return $value;}
		return is_numeric($value)?wc_format_decimal($value,6):$value;
	}
	private static function fingerprint($order,$q) {
		$items=array();foreach($order->get_items(array('line_item','fee','shipping','coupon','tax')) as $id=>$item){$data=$item->get_data();unset($data['meta_data'],$data['name'],$data['amount']);foreach(array('subtotal_tax','total_tax','tax_total','shipping_tax_total') as $field){if(isset($data[$field])&&''===$data[$field]){$data[$field]='0';}}$items[]=array('id'=>(int)$id,'type'=>$item->get_type(),'data'=>$data);}
		// CRUD item data contains metadata objects: convert at the boundary, never in a transaction.
		return hash('sha256',wp_json_encode(self::normalize(array('user'=>(int)$order->get_user_id(),'currency'=>$order->get_currency(),'total'=>$order->get_total(),'tax'=>$order->get_total_tax(),'discount'=>$q['discount_minor'],'points'=>$q['points'],'policy'=>$q['policy'],'items'=>$items))));
	}
	private static function fee_minor($order,$decimals) {
		$minor=0;foreach($order->get_items('fee') as $fee){if('v1'===$fee->get_meta('_nodevora_wallet_fee')){$amount=Nodevora_Financial_Money::minor((string)$fee->get_total(),$decimals);if($amount>=0){throw new Nodevora_Financial_Error('Invalid loyalty fee.');}$minor-=$amount;}}
		return $minor;
	}
	public static function project($order) {
		$p=WC()->session?WC()->session->get('nodevora_quote_policy'):null;
		if(!$p){if((int)$order->get_meta('_rewardly_loyalty_points_spent')>0){throw new Nodevora_Financial_Error('Missing financial quote.');}return;}
		$minor=self::fee_minor($order,$p['decimals']);if(!$minor){return;}self::require_enabled();
		$user=(int)$order->get_user_id();if(!$user||$user!==get_current_user_id()||$order->get_currency()!==$p['currency']){throw new Nodevora_Financial_Error('Quote customer or currency mismatch.');}
		$points=Nodevora_Financial_Money::charge($minor,$p['ratio'],$p['scale']);if(!$points){throw new Nodevora_Financial_Error('Discount has no integral point backing.');}
		$q=array('intent'=>bin2hex(random_bytes(16)),'points'=>$points,'discount_minor'=>$minor,'currency'=>$p['currency'],'scale'=>$p['scale'],'policy'=>$p);
		$order->update_meta_data('_nodevora_financial_quote',$q);$order->update_meta_data('_rewardly_loyalty_points_spent',$points);$order->update_meta_data('_rewardly_loyalty_discount_amount',Nodevora_Financial_Money::decimal($minor,$p['decimals']));
	}
	public static function quote($order) {
		$q=$order->get_meta('_nodevora_financial_quote');if(!is_array($q)||!isset($q['policy'])){throw new Nodevora_Financial_Error('Order requires a current financial quote.');}
		$p=$q['policy'];if($p['currency']!==$order->get_currency()||self::fee_minor($order,$p['decimals'])!==$q['discount_minor']||Nodevora_Financial_Money::charge($q['discount_minor'],$p['ratio'],$p['scale'])!==$q['points']){throw new Nodevora_Financial_Error('Order discount changed; confirmation required.');}
		$q['order_id']=(int)$order->get_id();$q['fingerprint']=self::fingerprint($order,$q);return $q;
	}
	public static function operation($order,$action,$details=array()) {
		self::require_enabled(in_array($action,array('compensate','paid','close'),true));
		$q=self::quote($order);$kind='control';$amount=0;
		if('fund'===$action){$kind='debit';$amount=$q['points'];}
		if('compensate'===$action){$kind='restore';$amount=$q['points'];}
		$d=array_merge($details,array('order'=>$q,'order_action'=>$action,'order_id'=>$q['order_id'],'discount_minor'=>$q['discount_minor'],'scale'=>$q['scale'],'type'=>'fund'===$action?'spend':$action));
		return (new Nodevora_Financial_Wallet())->apply((int)$order->get_user_id(),'order:'.$q['order_id'].':'.$q['intent'].':'.$action,$kind,$amount,$d);
	}
	public static function supported_payment($method,$order) {
		if (Nodevora_Financial_Money::minor((string)$order->get_total(),(int)wc_get_price_decimals())===0) {return true;}
		$allowed=array('bacs','cod');
		if('local'===wp_get_environment_type()&&defined('NODEVORA_FINANCIAL_QA_GATEWAYS')&&is_array(NODEVORA_FINANCIAL_QA_GATEWAYS)){$allowed=array_merge($allowed,NODEVORA_FINANCIAL_QA_GATEWAYS);}
		return in_array($method,$allowed,true);
	}
	public static function gate($order,$retry=false) {
		if(Nodevora_Financial_Lifecycle::hold_required($order)){throw new Nodevora_Financial_Error('Refund reconciliation is required before payment.');}
		if(!$order instanceof WC_Order){throw new Nodevora_Financial_Error('Order unavailable.');}
		if(!$retry&&function_exists('WC')&&WC()->session&&'yes'===WC()->session->get('nodevora_financial_quote_error')){throw new Nodevora_Financial_Error('Loyalty quote changed; remove or reconfirm the points selection before payment.');}
		$has=(int)$order->get_meta('_rewardly_loyalty_points_spent')>0||is_array($order->get_meta('_nodevora_financial_quote'));
		foreach($order->get_items('fee') as $fee){$has=$has||'v1'===$fee->get_meta('_nodevora_wallet_fee')||((float)$fee->get_total()<0&&in_array($fee->get_name(),array('Loyalty discount',__('Loyalty discount','nodevora-customer-rewards-for-woocommerce')),true));}if(!$has){return;}self::require_enabled();
		if(!self::supported_payment($retry&&isset($_POST['payment_method'])?sanitize_key(wp_unslash($_POST['payment_method'])):$order->get_payment_method(),$order)){throw new Nodevora_Financial_Error('This gateway requires a verified funding/outcome adapter.');}
		if((int)$order->get_user_id()!==get_current_user_id()){throw new Nodevora_Financial_Error('Order customer mismatch.');}
		if($retry){$state=Nodevora_Financial_Order::read($order->get_id());if($state&&'closed'===$state['state']){$q=$order->get_meta('_nodevora_financial_quote');$q['intent']=bin2hex(random_bytes(16));$order->update_meta_data('_nodevora_financial_quote',$q);$order->save();}}
		self::operation($order,'fund');self::operation($order,'dispatch');
	}
	public static function classic($id,$posted,$order) {try{self::gate(wc_get_order($id));}catch(Throwable $e){throw new Exception(__('Nodevora could not verify order funding. Please retry or contact the store.','nodevora-customer-rewards-for-woocommerce'));}}
	public static function blocks($order) {try{self::gate(wc_get_order($order->get_id()));}catch(Throwable $e){throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('nodevora_funding_failed',__('Nodevora could not verify order funding.','nodevora-customer-rewards-for-woocommerce'),409);}}
	public static function pay($order) {try{self::gate($order,true);}catch(Throwable $e){wc_add_notice(__('Nodevora could not verify order funding. Contact the store before retrying.','nodevora-customer-rewards-for-woocommerce'),'error');wp_safe_redirect($order->get_checkout_payment_url());exit;}}
}
