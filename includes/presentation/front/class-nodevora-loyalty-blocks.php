<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Données réactives du panier ; aucune écriture financière ne dépend du navigateur. */
class Nodevora_Loyalty_Blocks {
	const NAMESPACE_KEY = 'nodevora-loyalty';
	public static function init() {
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register' ) );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) { self::register(); }
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}
	public static function register_blocks() {
		if ( ! class_exists( 'WooCommerce' ) ) { return; }
		/* Les blocs forcés utilisent le registre WooCommerce sans déplacer son DOM React. */
		foreach ( array( 'cart' => 'woocommerce/cart-totals-block', 'checkout' => 'woocommerce/checkout-contact-information-block' ) as $context => $parent ) {
			register_block_type( 'nodevora/' . $context . '-loyalty', array( 'api_version' => 2, 'parent' => array( $parent ), 'attributes' => array( 'lock' => array( 'type' => 'object', 'default' => array( 'remove' => true, 'move' => true ) ) ), 'render_callback' => static function() use ( $context ) { return '<div data-block-name="nodevora/' . esc_attr( $context ) . '-loyalty"></div>'; } ) );
		}
	}

	public static function register() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) { return; }
		if ( interface_exists( 'Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationInterface' ) ) {
			require_once REWARDLY_LOYALTY_PATH . 'includes/presentation/front/class-nodevora-blocks-integration.php';
			foreach ( array( 'cart', 'checkout' ) as $block ) { add_action( 'woocommerce_blocks_' . $block . '_block_registration', static function( $registry ) { $registry->register( new Nodevora_Blocks_Integration() ); } ); }
		}
		woocommerce_store_api_register_endpoint_data( array(
			'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
			'namespace' => self::NAMESPACE_KEY, 'data_callback' => array( __CLASS__, 'data' ), 'schema_callback' => array( __CLASS__, 'schema' ), 'schema_type' => ARRAY_A,
		) );
		woocommerce_store_api_register_update_callback( array( 'namespace' => self::NAMESPACE_KEY, 'callback' => array( __CLASS__, 'update' ) ) );
	}
	public static function schema() {
		$schema = array();
		foreach ( array( 'enabled', 'logged_in', 'ready', 'balance_known', 'selected' ) as $key ) { $schema[$key] = array( 'type' => 'boolean', 'readonly' => true ); }
		foreach ( array( 'balance', 'potential', 'maximum', 'applied' ) as $key ) { $schema[$key] = array( 'type' => 'integer', 'readonly' => true ); }
		foreach ( array( 'message', 'display', 'discount', 'login_url' ) as $key ) { $schema[$key] = array( 'type' => 'string', 'readonly' => true ); }
		return $schema;
	}
	public static function data() {
		$s = Rewardly_Loyalty_Helpers::get_settings();
		$data = array( 'enabled' => Rewardly_Loyalty_Helpers::is_enabled(), 'logged_in' => is_user_logged_in(), 'ready' => false, 'selected' => false, 'balance_known' => false, 'balance' => 0, 'potential' => 0, 'maximum' => 0, 'applied' => 0, 'message' => '', 'display' => $s['notice_display_mode'], 'discount' => '', 'login_url' => wc_get_page_permalink( 'myaccount' ) );
		if ( ! $data['enabled'] || ! WC()->cart || WC()->cart->is_empty() ) { $data['enabled'] = false; return $data; }
		$data['potential'] = Rewardly_Loyalty_Helpers::calculate_cart_earned_points( WC()->cart );
		if ( ! $data['logged_in'] ) { return $data; }
		$data['selected'] = Nodevora_Commerce::selected()>0;
		try {
			$data['balance'] = Rewardly_Loyalty_Helpers::get_user_points( get_current_user_id() );
			$data['balance_known'] = true;

			$data['maximum'] = $data['balance'] >= (int) $s['min_points_to_redeem'] ? Rewardly_Loyalty_Redeem::get_max_redeemable_points_for_cart( get_current_user_id(), WC()->cart ) : 0;
			$data['ready'] = $data['maximum'] > 0;
			$data['applied'] = (int) Nodevora_Commerce::selected();
			$data['discount'] = html_entity_decode( wp_strip_all_tags( wc_price( Rewardly_Loyalty_Helpers::convert_points_to_amount(Nodevora_Commerce::selected()) ) ), ENT_QUOTES, 'UTF-8' );
		} catch ( Throwable $e ) { $data['message'] = __( 'Your points are preserved. Redemption is temporarily unavailable while the store completes financial setup.', 'nodevora-customer-rewards-for-woocommerce' ); }
		return $data;
	}
public static function update($data) {
 if(!is_user_logged_in()||!is_array($data)||!in_array($data['action']??'',array('apply','remove'),true)){throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('nodevora_invalid',esc_html__('Choose a valid loyalty action.','nodevora-customer-rewards-for-woocommerce'),403);}
 try { Nodevora_Commerce::select('remove'===$data['action']?'0':($data['points']??'')); }catch(Throwable$e){throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('nodevora_invalid_points',esc_html($e->getMessage()),400);}
}
}
