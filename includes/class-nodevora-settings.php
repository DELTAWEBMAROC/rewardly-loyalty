<?php
/** Settings and earning rules. @package Nodevora */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
class Nodevora_Settings {
	public static function defaults() {
		return array(
			'enabled'             => 1,
			'earn_rate'           => 1,
			'redeem_rate'         => 20,
			'minimum'             => 100,
			'maximum_discount'    => 0,
			'trigger'             => 'completed',
			'expiration_days'     => 365,
			'registration_bonus'  => 0,
			'first_order_bonus'   => 0,
			'review_bonus'        => 0,
			'display_cache'       => 1,
			'exclude_sale'        => 0,
			'excluded_products'   => '',
			'excluded_categories' => '',
			'category_rules'      => '',
			'primary_color'       => '#0b1638',
			'accent_color'        => '#12b8a6',
			'card_color'          => '#ffffff',
			'button_text_color'   => '#ffffff',
			'radius'              => 12,
			'front_styles'        => 1,
			'notice_mode'         => 'both',
			'managers'            => 1,
			'editor_view'         => 0,
			'levels'              => 0,
			'bronze'              => 100,
			'silver'              => 500,
			'gold'                => 1000,
		);
	}
	public static function get() { return Nodevora_Parity::core_settings(); }
	public static function init() {
		add_action(
			'admin_init',
			static function () {
				register_setting(
					'nodevora_v2',
					'nodevora_v2_settings',
					array(
						'type'              => 'array',
						'sanitize_callback' => array( __CLASS__, 'sanitize' ),
						'default'           => self::defaults(),
					)
				);
			}
		);
		add_filter(
			'option_page_capability_nodevora_v2',
			static function () {
				return 'manage_options';
			}
		);
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
	}
	public static function sanitize( $input ) {
		$clean = self::get();
		$input = is_array( $input ) ? $input : array();
		foreach ( self::defaults() as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue; }
			$v = is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
			if ( in_array( $key, array( 'enabled', 'display_cache', 'exclude_sale', 'front_styles', 'managers', 'editor_view', 'levels' ), true ) ) {
				$clean[ $key ] = '1' === $v ? 1 : 0; } elseif ( substr( $key, -6 ) === '_color' ) {
				$clean[ $key ] = sanitize_hex_color( $v ) ?: $default; } elseif ( in_array( $key, array( 'excluded_products', 'excluded_categories' ), true ) ) {
					$clean[ $key ] = implode( ',', wp_parse_id_list( $v ) ); } elseif ( 'category_rules' === $key ) {
					$rules = array();
					foreach ( explode( ',', $v ) as $pair ) {
						if ( preg_match( '/^\s*([0-9]{1,9})\s*:\s*([0-9]{1,6})\s*$/D', $pair, $m ) ) {
							$rules[] = absint( $m[1] ) . ':' . absint( $m[2] );
						}
					} $clean[ $key ] = implode( ',', $rules );
					} elseif ( 'trigger' === $key ) {
						$clean[ $key ] = in_array( $v, array( 'processing', 'completed' ), true ) ? $v : 'completed'; } elseif ( 'notice_mode' === $key ) {
						$clean[ $key ] = in_array( $v, array( 'both', 'cart', 'checkout', 'none' ), true ) ? $v : 'both'; } else {
							$clean[ $key ] = min( 1000000, max( 'redeem_rate' === $key ? 1 : 0, (int) $v ) ); }
		}
		$clean['expiration_days'] = min( 36500, $clean['expiration_days'] );
		$clean['radius']          = min( 40, $clean['radius'] );
		return $clean;
	}
	public static function rate($product) { return Rewardly_Loyalty_Helpers::get_effective_product_earning_rate($product); }
	public static function product_field() {
		if ( ! Nodevora_Admin::can_edit() ) {
			return; }
		woocommerce_wp_text_input(
			array(
				'id'                => '_nodevora_v2_rate',
				'label'             => __( 'Nodevora points per currency unit', 'nodevora-customer-rewards-for-woocommerce' ),
				'description'       => __( 'Leave empty to use category or global rules. Zero disables earning.', 'nodevora-customer-rewards-for-woocommerce' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => 0,
					'max'  => 1000000,
					'step' => 1,
				),
			)
		);
	}
	public static function save_product( $product ) {
		// WooCommerce verifies its product-save nonce before this hook; enforce our own capability too.
		if ( ! Nodevora_Admin::can_edit() || ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return; }
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return; }
		$v = isset( $_POST['_nodevora_v2_rate'] ) ? sanitize_text_field( wp_unslash( $_POST['_nodevora_v2_rate'] ) ) : '';
		if ( '' === $v ) {
			$product->delete_meta_data( '_nodevora_v2_rate' );
		} else {
			$product->update_meta_data( '_nodevora_v2_rate', min( 1000000, absint( $v ) ) ); }
	}
}
