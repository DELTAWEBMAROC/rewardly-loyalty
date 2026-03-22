<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Pro {

	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_data_panel' ) );
	}

	public static function get_license_state() {
		return array(
			'key'              => '',
			'status'           => 'not_required',
			'plan'             => 'free',
			'instance_id'      => '',
			'last_check'       => '',
			'expires_at'       => '',
			'grace_until'      => '',
			'activated_domain' => '',
			'site_domain'      => '',
			'is_pro'           => false,
		);
	}

	public static function is_dev_mode() {
		return false;
	}

	public static function is_license_active() {
		return true;
	}

	public static function is_pro_enabled() {
		return true;
	}

	public static function can_use_feature( $feature ) {
		return true;
	}

	public static function get_feature_access_map() {
		return array(
			'advanced_rules'         => 'free',
			'product_rules'          => 'free',
			'category_rules'         => 'free',
			'exclusions'             => 'free',
			'extra_points'           => 'free',
			'pending_points_notice'  => 'free',
			'levels_badges'          => 'free',
			'enhanced_history_links' => 'free',
		);
	}

	public static function get_upgrade_url() {
		return '';
	}

	public static function get_license_status_label() {
		return __( 'Not required', 'rewardly-loyalty' );
	}

	public static function render_product_data_panel() {
		global $post;
		if ( ! $post ) {
			return;
		}

		$value = get_post_meta( $post->ID, '_rewardly_loyalty_product_points_rate', true );
		woocommerce_wp_text_input(
			array(
				'id'                => '_rewardly_loyalty_product_points_rate',
				'label'             => __( 'Rewardly points per currency unit', 'rewardly-loyalty' ),
				'description'       => __( 'Override the global earn rate for this product only. Leave empty to use the global rule.', 'rewardly-loyalty' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'value'             => '' !== $value ? (string) absint( $value ) : '',
			)
		);
	}

	public static function save_product_data_panel( $product_id ) {
		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		$raw_value = isset( $_POST['_rewardly_loyalty_product_points_rate'] ) ? wp_unslash( $_POST['_rewardly_loyalty_product_points_rate'] ) : '';
		$value     = is_scalar( $raw_value ) ? trim( (string) $raw_value ) : '';

		if ( '' === $value ) {
			delete_post_meta( $product_id, '_rewardly_loyalty_product_points_rate' );
			return;
		}

		update_post_meta( $product_id, '_rewardly_loyalty_product_points_rate', max( 0, absint( $value ) ) );
	}
}
