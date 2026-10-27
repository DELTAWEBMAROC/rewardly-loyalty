<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Données réactives du panier ; aucune écriture financière ne dépend du navigateur. */
class Nodevora_Loyalty_Blocks {
	const NAMESPACE_KEY = 'nodevora-loyalty';
	public static function init() {
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register' ) );
	}
	public static function register() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) { return; }
		woocommerce_store_api_register_endpoint_data( array(
			'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
			'namespace' => self::NAMESPACE_KEY, 'data_callback' => array( __CLASS__, 'data' ), 'schema_callback' => array( __CLASS__, 'schema' ), 'schema_type' => ARRAY_A,
		) );
		woocommerce_store_api_register_update_callback( array( 'namespace' => self::NAMESPACE_KEY, 'callback' => array( __CLASS__, 'update' ) ) );
	}
	public static function schema() {
		$schema = array();
		foreach ( array( 'enabled', 'logged_in', 'ready', 'balance_known' ) as $key ) { $schema[$key] = array( 'type' => 'boolean', 'readonly' => true ); }
		foreach ( array( 'balance', 'potential', 'maximum', 'applied' ) as $key ) { $schema[$key] = array( 'type' => 'integer', 'readonly' => true ); }
		foreach ( array( 'message', 'display', 'discount', 'login_url' ) as $key ) { $schema[$key] = array( 'type' => 'string', 'readonly' => true ); }
		return $schema;
	}
	public static function data() {
		$s = Rewardly_Loyalty_Helpers::get_settings();
		$data = array( 'enabled' => Rewardly_Loyalty_Helpers::is_enabled(), 'logged_in' => is_user_logged_in(), 'ready' => false, 'balance_known' => false, 'balance' => 0, 'potential' => 0, 'maximum' => 0, 'applied' => 0, 'message' => '', 'display' => $s['notice_display_mode'], 'discount' => '', 'login_url' => wc_get_page_permalink( 'myaccount' ) );
		if ( ! $data['enabled'] || ! WC()->cart || WC()->cart->is_empty() ) { $data['enabled'] = false; return $data; }
		$data['potential'] = Rewardly_Loyalty_Helpers::calculate_cart_earned_points( WC()->cart );
		if ( ! $data['logged_in'] ) { return $data; }
		try {
			$data['balance'] = Nodevora_Financial_Compatibility::selected() ? Nodevora_Financial_Compatibility::balance( get_current_user_id() ) : Rewardly_Loyalty_Helpers::get_user_points( get_current_user_id() );
			$data['balance_known'] = true;
			Nodevora_Financial_Checkout::require_enabled();
			$data['maximum'] = $data['balance'] >= (int) $s['min_points_to_redeem'] ? Rewardly_Loyalty_Redeem::get_max_redeemable_points_for_cart( get_current_user_id(), WC()->cart ) : 0;
			$data['ready'] = $data['maximum'] > 0;
			$data['applied'] = (int) WC()->session->get( 'rewardly_applied_points', 0 );
			$data['discount'] = html_entity_decode( wp_strip_all_tags( wc_price( WC()->session->get( 'rewardly_loyalty_discount_amount', 0 ) ) ), ENT_QUOTES, 'UTF-8' );
		} catch ( Nodevora_Financial_Error $e ) { $data['message'] = __( 'Your points are preserved. Redemption is temporarily unavailable while the store completes financial setup.', 'nodevora-customer-rewards-for-woocommerce' ); }
		return $data;
	}
	public static function update( $data ) {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) || ! is_user_logged_in() || ! WC()->session || ! WC()->cart ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'nodevora_unauthorized', __( 'Log in before using loyalty points.', 'nodevora-customer-rewards-for-woocommerce' ), 403 );
		}
		if ( ! is_array( $data ) || ! isset( $data['action'] ) || ! in_array( $data['action'], array( 'apply', 'remove' ), true ) ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'nodevora_invalid', __( 'Choose a valid loyalty action.', 'nodevora-customer-rewards-for-woocommerce' ), 400 ); }
		if ( 'remove' === $data['action'] ) { Rewardly_Loyalty_Redeem::clear_usage_session(); return; }
		try { Nodevora_Financial_Checkout::require_enabled(); } catch ( Nodevora_Financial_Error $e ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'nodevora_unavailable', __( 'Redemption is temporarily unavailable. Your points are preserved.', 'nodevora-customer-rewards-for-woocommerce' ), 409 ); }
		$s = Rewardly_Loyalty_Helpers::get_settings(); $balance = Rewardly_Loyalty_Helpers::get_user_points( get_current_user_id() );
		if ( ! Rewardly_Loyalty_Helpers::is_enabled() || $balance < (int) $s['min_points_to_redeem'] ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'nodevora_ineligible', __( 'You cannot use your loyalty points yet.', 'nodevora-customer-rewards-for-woocommerce' ), 400 ); }
		$points = Rewardly_Loyalty_Redeem::validate_requested_points( $data['points'] ?? '', $balance, Rewardly_Loyalty_Redeem::get_max_redeemable_points_for_cart( get_current_user_id(), WC()->cart ) );
		if ( is_wp_error( $points ) ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'nodevora_invalid_points', $points->get_error_message(), 400 ); }
		WC()->session->set( 'rewardly_use_points', 'yes' ); WC()->session->set( 'rewardly_requested_points', $points );
	}
}
