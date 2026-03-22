<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Settings {
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings() {
		register_setting( 'rewardly_loyalty_group', 'rewardly_loyalty_settings', array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ), 'default' => Rewardly_Loyalty_Helpers::get_default_settings() ) );
	}

	public static function sanitize_settings( $input ) {
		$d = wp_parse_args( get_option( 'rewardly_loyalty_settings', array() ), Rewardly_Loyalty_Helpers::get_default_settings() );
		$allowed_templates     = array( 'default', 'minimal', 'soft' );
		$allowed_icon_styles   = array( 'default', 'minimal', 'circle' );
		$allowed_button_styles = array( 'rounded', 'solid', 'outline' );
		$allowed_card_styles   = array( 'soft', 'outline', 'filled' );
		$allowed_typography    = array( 'default', 'compact', 'modern' );
		$allowed_notice_display = array( 'both', 'cart', 'checkout', 'shortcode' );

		/* (FR) Résoudre les valeurs de sélection avant le tableau final pour éviter d'écraser des réglages avec null quand un onglet partiel est enregistré. */
		$order_status_trigger  = $input['order_status_trigger'] ?? $d['order_status_trigger'];
		if ( ! in_array( $order_status_trigger, array( 'completed', 'processing' ), true ) ) {
			$order_status_trigger = $d['order_status_trigger'];
		}

		$design_template = $input['design_template'] ?? $d['design_template'];
		if ( ! in_array( $design_template, $allowed_templates, true ) ) {
			$design_template = $d['design_template'];
		}

		$icon_style = $input['icon_style'] ?? $d['icon_style'];
		if ( ! in_array( $icon_style, $allowed_icon_styles, true ) ) {
			$icon_style = $d['icon_style'];
		}

		$button_style = $input['button_style'] ?? $d['button_style'];
		if ( ! in_array( $button_style, $allowed_button_styles, true ) ) {
			$button_style = $d['button_style'];
		}

		$card_style = $input['card_style'] ?? $d['card_style'];
		if ( ! in_array( $card_style, $allowed_card_styles, true ) ) {
			$card_style = $d['card_style'];
		}

		$typography_preset = $input['typography_preset'] ?? $d['typography_preset'];
		if ( ! in_array( $typography_preset, $allowed_typography, true ) ) {
			$typography_preset = $d['typography_preset'];
		}

		$license_status = Rewardly_Loyalty_Helpers::sanitize_license_status( $input['license_status'] ?? $d['license_status'] );
		$license_plan   = Rewardly_Loyalty_Helpers::sanitize_license_plan( $input['license_plan'] ?? $d['license_plan'] );

		$notice_display_mode = $input['notice_display_mode'] ?? $d['notice_display_mode'];
		if ( ! in_array( $notice_display_mode, $allowed_notice_display, true ) ) {
			$notice_display_mode = $d['notice_display_mode'];
		}

		$raw_category_rules = array();
		$term_ids           = isset( $input['pro_category_rule_term_id'] ) && is_array( $input['pro_category_rule_term_id'] ) ? $input['pro_category_rule_term_id'] : array();
		$rates              = isset( $input['pro_category_rule_rate'] ) && is_array( $input['pro_category_rule_rate'] ) ? $input['pro_category_rule_rate'] : array();
		$max_index          = max( count( $term_ids ), count( $rates ) );
		for ( $i = 0; $i < $max_index; $i++ ) {
			$raw_category_rules[] = array(
				'term_id' => absint( $term_ids[ $i ] ?? 0 ),
				'rate'    => absint( $rates[ $i ] ?? 0 ),
			);
		}


		$can_use_advanced_rules = class_exists( 'Rewardly_Loyalty_Pro' ) && Rewardly_Loyalty_Pro::can_use_feature( 'advanced_rules' );
		$can_use_extra_points   = class_exists( 'Rewardly_Loyalty_Pro' ) && Rewardly_Loyalty_Pro::can_use_feature( 'extra_points' );
		$can_use_pending_notice = class_exists( 'Rewardly_Loyalty_Pro' ) && Rewardly_Loyalty_Pro::can_use_feature( 'pending_points_notice' );
		$can_use_levels         = class_exists( 'Rewardly_Loyalty_Pro' ) && Rewardly_Loyalty_Pro::can_use_feature( 'levels_badges' );

		$pending_points_notice_enabled = ( isset( $input['pro_pending_points_notice_enabled'] ) && 'yes' === $input['pro_pending_points_notice_enabled'] ) ? 'yes' : 'no';
		$pending_points_notice_text    = sanitize_textarea_field( $input['pro_pending_points_notice_text'] ?? $d['pro_pending_points_notice_text'] );
		$pending_points_statuses       = Rewardly_Loyalty_Helpers::sanitize_status_list( $input['pro_pending_points_statuses'] ?? $d['pro_pending_points_statuses'] );

		return array(
			'enabled'                    => ( isset( $input['enabled'] ) && 'no' === $input['enabled'] ) ? 'no' : 'yes',
			'earn_points_per_dh'         => max( 1, absint( $input['earn_points_per_dh'] ?? $d['earn_points_per_dh'] ) ),
			'redeem_points_per_dh'       => max( 1, absint( $input['redeem_points_per_dh'] ?? $d['redeem_points_per_dh'] ) ),
			'min_points_to_redeem'       => max( 0, absint( $input['min_points_to_redeem'] ?? $d['min_points_to_redeem'] ) ),
			'max_discount_per_order'     => max( 0, absint( $input['max_discount_per_order'] ?? $d['max_discount_per_order'] ) ),
			'order_status_trigger'       => $order_status_trigger,
			'points_expiration_days'     => max( 0, absint( $input['points_expiration_days'] ?? $d['points_expiration_days'] ) ),
			'email_notifications'        => ( isset( $input['email_notifications'] ) && 'no' === $input['email_notifications'] ) ? 'no' : 'yes',
			'notice_display_mode'        => $notice_display_mode,
			'primary_color'              => sanitize_hex_color( $input['primary_color'] ?? $d['primary_color'] ) ?: $d['primary_color'],
			'accent_color'               => sanitize_hex_color( $input['accent_color'] ?? $d['accent_color'] ) ?: $d['accent_color'],
			'border_radius'              => min( 30, max( 0, absint( $input['border_radius'] ?? $d['border_radius'] ) ) ),
			'enable_front_styles'        => ( isset( $input['enable_front_styles'] ) && 'no' === $input['enable_front_styles'] ) ? 'no' : 'yes',
			'delete_data_on_uninstall'   => ( isset( $input['delete_data_on_uninstall'] ) && 'yes' === $input['delete_data_on_uninstall'] ) ? 'yes' : 'no',
			'design_template'            => $design_template,
			'icon_style'                 => $icon_style,
			'button_style'               => $button_style,
			'card_style'                 => $card_style,
			'typography_preset'          => $typography_preset,
			'license_key'                => Rewardly_Loyalty_Helpers::sanitize_envato_purchase_code( $input['license_key'] ?? $d['license_key'] ),
			'license_status'             => $license_status,
			'license_plan'               => $license_plan,
			'license_instance_id'        => sanitize_key( $input['license_instance_id'] ?? $d['license_instance_id'] ),
			'license_last_check'         => sanitize_text_field( $input['license_last_check'] ?? $d['license_last_check'] ),
			'license_expires_at'         => sanitize_text_field( $input['license_expires_at'] ?? $d['license_expires_at'] ),
			'license_grace_until'        => sanitize_text_field( $input['license_grace_until'] ?? $d['license_grace_until'] ),
			'license_activated_domain'   => sanitize_text_field( $input['license_activated_domain'] ?? $d['license_activated_domain'] ),
			'pro_exclude_sale_products'  => $can_use_advanced_rules ? ( ( isset( $input['pro_exclude_sale_products'] ) && 'yes' === $input['pro_exclude_sale_products'] ) ? 'yes' : 'no' ) : $d['pro_exclude_sale_products'],
			'pro_excluded_product_ids'   => $can_use_advanced_rules ? Rewardly_Loyalty_Helpers::sanitize_id_list( $input['pro_excluded_product_ids'] ?? array() ) : $d['pro_excluded_product_ids'],
			'pro_excluded_category_ids'  => $can_use_advanced_rules ? Rewardly_Loyalty_Helpers::sanitize_id_list( $input['pro_excluded_category_ids'] ?? array() ) : $d['pro_excluded_category_ids'],
			'pro_category_rules'         => $can_use_advanced_rules ? Rewardly_Loyalty_Helpers::sanitize_category_rules_array( $raw_category_rules ) : $d['pro_category_rules'],
			'pro_points_registration'    => $can_use_extra_points ? max( 0, absint( $input['pro_points_registration'] ?? $d['pro_points_registration'] ) ) : $d['pro_points_registration'],
			'pro_points_first_order'     => $can_use_extra_points ? max( 0, absint( $input['pro_points_first_order'] ?? $d['pro_points_first_order'] ) ) : $d['pro_points_first_order'],
			'pro_points_review'          => $can_use_extra_points ? max( 0, absint( $input['pro_points_review'] ?? $d['pro_points_review'] ) ) : $d['pro_points_review'],
			'pro_points_birthday'        => $can_use_extra_points ? max( 0, absint( $input['pro_points_birthday'] ?? $d['pro_points_birthday'] ) ) : $d['pro_points_birthday'],
			'pro_referral_points'        => $can_use_extra_points ? max( 0, absint( $input['pro_referral_points'] ?? $d['pro_referral_points'] ) ) : $d['pro_referral_points'],
			'pro_pending_points_notice_enabled' => $can_use_pending_notice ? $pending_points_notice_enabled : $d['pro_pending_points_notice_enabled'],
			'pro_pending_points_notice_text'    => $can_use_pending_notice ? ( '' !== $pending_points_notice_text ? $pending_points_notice_text : $d['pro_pending_points_notice_text'] ) : $d['pro_pending_points_notice_text'],
			'pro_pending_points_statuses'       => $can_use_pending_notice ? $pending_points_statuses : $d['pro_pending_points_statuses'],
			'pro_levels_enabled'         => $can_use_levels ? ( ( isset( $input['pro_levels_enabled'] ) && 'yes' === $input['pro_levels_enabled'] ) ? 'yes' : 'no' ) : $d['pro_levels_enabled'],
			'pro_level_bronze_threshold' => $can_use_levels ? max( 0, absint( $input['pro_level_bronze_threshold'] ?? $d['pro_level_bronze_threshold'] ) ) : $d['pro_level_bronze_threshold'],
			'pro_level_silver_threshold' => $can_use_levels ? max( 0, absint( $input['pro_level_silver_threshold'] ?? $d['pro_level_silver_threshold'] ) ) : $d['pro_level_silver_threshold'],
			'pro_level_gold_threshold'   => $can_use_levels ? max( 0, absint( $input['pro_level_gold_threshold'] ?? $d['pro_level_gold_threshold'] ) ) : $d['pro_level_gold_threshold'],
		);
	}

	private static function get_currency_code() {
		return Rewardly_Loyalty_Helpers::get_store_currency_code();
	}

	public static function render_settings_tab() {
		$settings      = Rewardly_Loyalty_Helpers::get_settings();
		$currency_code = self::get_currency_code(); ?>
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?><table class="form-table" role="presentation">
		<tr><th scope="row"><?php esc_html_e( 'Enable program', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[enabled]"><option value="yes" <?php selected( $settings['enabled'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'rewardly-loyalty' ); ?></option><option value="no" <?php selected( $settings['enabled'], 'no' ); ?>><?php esc_html_e( 'No', 'rewardly-loyalty' ); ?></option></select></td></tr>
		<tr><th scope="row"><?php echo esc_html( sprintf( __( '1 %s = X point(s)', 'rewardly-loyalty' ), $currency_code ) ); ?></th><td><input type="number" min="1" name="rewardly_loyalty_settings[earn_points_per_dh]" value="<?php echo esc_attr( $settings['earn_points_per_dh'] ); ?>"><p class="description"><?php echo esc_html( sprintf( __( 'Define how many points are earned for each 1 %s spent.', 'rewardly-loyalty' ), $currency_code ) ); ?></p></td></tr>
		<tr><th scope="row"><?php echo esc_html( sprintf( __( 'X point(s) = 1 %s', 'rewardly-loyalty' ), $currency_code ) ); ?></th><td><input type="number" min="1" name="rewardly_loyalty_settings[redeem_points_per_dh]" value="<?php echo esc_attr( $settings['redeem_points_per_dh'] ); ?>"><p class="description"><?php echo esc_html( sprintf( __( 'Define how many points are required to unlock 1 %s of discount.', 'rewardly-loyalty' ), $currency_code ) ); ?></p></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Minimum points to redeem', 'rewardly-loyalty' ); ?></th><td><input type="number" min="0" name="rewardly_loyalty_settings[min_points_to_redeem]" value="<?php echo esc_attr( $settings['min_points_to_redeem'] ); ?>"></td></tr>
		<tr><th scope="row"><?php echo esc_html( sprintf( __( 'Maximum discount per order (%s)', 'rewardly-loyalty' ), $currency_code ) ); ?></th><td><input type="number" min="0" step="1" name="rewardly_loyalty_settings[max_discount_per_order]" value="<?php echo esc_attr( $settings['max_discount_per_order'] ); ?>"><p class="description"><?php esc_html_e( 'Set 0 for no limit.', 'rewardly-loyalty' ); ?></p></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Trigger order status', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[order_status_trigger]"><option value="completed" <?php selected( $settings['order_status_trigger'], 'completed' ); ?>><?php esc_html_e( 'Completed', 'rewardly-loyalty' ); ?></option><option value="processing" <?php selected( $settings['order_status_trigger'], 'processing' ); ?>><?php esc_html_e( 'Processing', 'rewardly-loyalty' ); ?></option></select></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Points expiration (days)', 'rewardly-loyalty' ); ?></th><td><input type="number" min="0" step="1" name="rewardly_loyalty_settings[points_expiration_days]" value="<?php echo esc_attr( $settings['points_expiration_days'] ); ?>"><p class="description"><?php esc_html_e( 'Set 0 to disable automatic expiration.', 'rewardly-loyalty' ); ?></p></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Email notifications', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[email_notifications]"><option value="yes" <?php selected( $settings['email_notifications'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'rewardly-loyalty' ); ?></option><option value="no" <?php selected( $settings['email_notifications'], 'no' ); ?>><?php esc_html_e( 'No', 'rewardly-loyalty' ); ?></option></select></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Loyalty card display', 'rewardly-loyalty' ); ?></th><td><select id="rewardly-notice-display-mode" name="rewardly_loyalty_settings[notice_display_mode]"><option value="both" <?php selected( $settings['notice_display_mode'], 'both' ); ?>><?php esc_html_e( 'Cart and checkout', 'rewardly-loyalty' ); ?></option><option value="cart" <?php selected( $settings['notice_display_mode'], 'cart' ); ?>><?php esc_html_e( 'Cart only', 'rewardly-loyalty' ); ?></option><option value="checkout" <?php selected( $settings['notice_display_mode'], 'checkout' ); ?>><?php esc_html_e( 'Checkout only', 'rewardly-loyalty' ); ?></option><option value="shortcode" <?php selected( $settings['notice_display_mode'], 'shortcode' ); ?>><?php esc_html_e( 'Shortcode only', 'rewardly-loyalty' ); ?></option></select><p class="description"><?php esc_html_e( 'Choose where the loyalty card should appear automatically. The informational notice will be shown on the opposite page when only one card location is selected.', 'rewardly-loyalty' ); ?></p><div id="rewardly-shortcode-only-help" style="margin-top:8px;<?php echo 'shortcode' === $settings['notice_display_mode'] ? '' : 'display:none;'; ?>"><p><strong><?php esc_html_e( 'Use this shortcode:', 'rewardly-loyalty' ); ?></strong></p><code>[rewardly_loyalty_card]</code><br><code>[rewardly_loyalty_card context="cart"]</code><br><code>[rewardly_loyalty_card context="checkout"]</code><br><br><strong><?php esc_html_e( 'Informational notice shortcodes:', 'rewardly-loyalty' ); ?></strong><br><code>[rewardly_loyalty_notice]</code><br><code>[rewardly_loyalty_notice context="cart"]</code><br><code>[rewardly_loyalty_notice context="checkout"]</code></div></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Delete plugin data on uninstall', 'rewardly-loyalty' ); ?></th><td><select name="rewardly_loyalty_settings[delete_data_on_uninstall]"><option value="no" <?php selected( $settings['delete_data_on_uninstall'] ?? 'no', 'no' ); ?>><?php esc_html_e( 'No', 'rewardly-loyalty' ); ?></option><option value="yes" <?php selected( $settings['delete_data_on_uninstall'] ?? 'no', 'yes' ); ?>><?php esc_html_e( 'Yes', 'rewardly-loyalty' ); ?></option></select><p class="description"><?php esc_html_e( 'If enabled, all Rewardly settings, points balances and logs will be deleted when the plugin is uninstalled.', 'rewardly-loyalty' ); ?></p></td></tr>
		</table><?php submit_button(); ?></form><?php
	}
}
