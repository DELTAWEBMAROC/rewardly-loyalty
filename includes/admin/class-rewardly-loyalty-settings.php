<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Settings {
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'option_page_capability_rewardly_loyalty_group', function() { return 'nodevora_edit'; } );
	}

	public static function register_settings() {
		register_setting( 'rewardly_loyalty_group', 'rewardly_loyalty_settings', array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ), 'default' => Rewardly_Loyalty_Helpers::get_default_settings() ) );
	}

	public static function sanitize_settings( $input ) {
		$d = wp_parse_args( get_option( 'rewardly_loyalty_settings', array() ), Rewardly_Loyalty_Helpers::get_default_settings() );
		if ( ! is_array( $input ) ) { return $d; }
		$sections = array(
			'general' => array( 'enabled', 'earn_points_per_dh', 'redeem_points_per_dh', 'min_points_to_redeem', 'max_discount_per_order', 'order_status_trigger', 'points_expiration_days', 'email_notifications', 'notice_display_mode', 'delete_data_on_uninstall' ),
			'design' => array( 'primary_color', 'accent_color', 'border_radius', 'enable_front_styles', 'design_template', 'icon_style', 'button_style', 'card_style', 'typography_preset', 'button_background', 'button_text', 'button_hover_background', 'button_hover_text', 'card_background', 'card_border', 'density' ),
			'rules' => array( 'pro_exclude_sale_products', 'pro_excluded_product_ids', 'pro_excluded_category_ids', 'pro_category_rule_term_id', 'pro_category_rule_rate' ),
			'bonuses' => array( 'pro_points_registration', 'pro_points_first_order', 'pro_points_review', 'pro_points_birthday', 'pro_referral_points' ),
			'pending' => array( 'pro_pending_points_notice_enabled', 'pro_pending_points_notice_text', 'pro_pending_points_statuses' ),
			'levels' => array( 'pro_levels_enabled', 'pro_level_bronze_threshold', 'pro_level_silver_threshold', 'pro_level_gold_threshold' ),
		);
		$scope = isset( $input['_section'] ) && is_string( $input['_section'] ) ? $input['_section'] : '';
		if ( isset( $input['_section'] ) && ! isset( $sections[$scope] ) ) { return $d; }
		if ( $scope ) { $input = array_intersect_key( $input, array_flip( $sections[$scope] ) ); }
		/* Les listes vides sont intentionnelles uniquement dans leur formulaire propriétaire. */
		foreach ( array( 'rules' => array( 'pro_excluded_product_ids', 'pro_excluded_category_ids', 'pro_category_rule_term_id', 'pro_category_rule_rate' ), 'pending' => array( 'pro_pending_points_statuses' ) ) as $owner => $fields ) {
			if ( $scope === $owner ) { foreach ( $fields as $field ) { if ( ! array_key_exists( $field, $input ) ) { $input[$field] = array(); } } }
		}
		$enums = array(
			'order_status_trigger' => array( 'completed', 'processing' ), 'design_template' => array( 'default', 'minimal', 'soft' ),
			'icon_style' => array( 'default', 'minimal', 'circle' ), 'button_style' => array( 'rounded', 'solid', 'outline' ),
			'card_style' => array( 'soft', 'outline', 'filled' ), 'typography_preset' => array( 'default', 'compact', 'modern' ),
			'notice_display_mode' => array( 'both', 'cart', 'checkout', 'shortcode' ), 'density' => array( 'comfortable', 'compact' ),
		);
		$booleans = array( 'enabled', 'email_notifications', 'enable_front_styles', 'delete_data_on_uninstall', 'pro_exclude_sale_products', 'pro_pending_points_notice_enabled', 'pro_levels_enabled' );
		$integer = array( 'earn_points_per_dh', 'redeem_points_per_dh', 'min_points_to_redeem', 'points_expiration_days', 'border_radius', 'pro_points_registration', 'pro_points_first_order', 'pro_points_review', 'pro_points_birthday', 'pro_referral_points', 'pro_level_bronze_threshold', 'pro_level_silver_threshold', 'pro_level_gold_threshold' );
		$colors = array( 'primary_color', 'accent_color', 'button_background', 'button_text', 'button_hover_background', 'button_hover_text', 'card_background', 'card_border' );
		foreach ( $input as $key => $value ) {
			if ( in_array( $key, $booleans, true ) && in_array( $value, array( 'yes', 'no' ), true ) ) { $d[$key] = $value; }
			elseif ( isset( $enums[$key] ) && in_array( $value, $enums[$key], true ) ) { $d[$key] = $value; }
			elseif ( in_array( $key, $integer, true ) && self::valid_integer( $value, in_array( $key, array( 'earn_points_per_dh', 'redeem_points_per_dh' ), true ) ? 1 : 0, 'border_radius' === $key ? 30 : ( 'points_expiration_days' === $key ? 36500 : 1000000000 ) ) ) { $d[$key] = (int) $value; }
			elseif ( in_array( $key, $colors, true ) && is_string( $value ) && ( sanitize_hex_color( $value ) || ( '' === $value && ! in_array( $key, array( 'primary_color', 'accent_color' ), true ) ) ) ) { $d[$key] = '' === $value ? '' : sanitize_hex_color( $value ); }
			elseif ( 'max_discount_per_order' === $key && is_scalar( $value ) && preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,6})?$/D', (string) $value ) ) { $d[$key] = wc_format_decimal( $value, wc_get_price_decimals() ); }
			elseif ( 'pro_pending_points_notice_text' === $key && is_string( $value ) ) { $d[$key] = sanitize_textarea_field( $value ); }
			elseif ( in_array( $key, array( 'pro_excluded_product_ids', 'pro_excluded_category_ids' ), true ) && is_array( $value ) ) {
				$valid = true; foreach ( $value as $id ) { if ( ! self::valid_integer( $id, 1, PHP_INT_MAX ) ) { $valid = false; } }
				if ( $valid ) { $d[$key] = array_values( array_unique( array_map( 'intval', $value ) ) ); }
			}
			elseif ( 'pro_pending_points_statuses' === $key && is_array( $value ) ) {
				$allowed = array_keys( wc_get_order_statuses() ); $allowed = array_map( function( $status ) { return substr( $status, 3 ); }, $allowed );
				if ( count( array_filter( $value, 'is_string' ) ) === count( $value ) && ! array_diff( $value, $allowed ) ) { $d[$key] = array_values( array_unique( $value ) ); }
			}
		}
		if ( isset( $input['pro_category_rule_term_id'], $input['pro_category_rule_rate'] ) && is_array( $input['pro_category_rule_term_id'] ) && is_array( $input['pro_category_rule_rate'] ) ) {
			$ids = array_values( $input['pro_category_rule_term_id'] ); $rates = array_values( $input['pro_category_rule_rate'] ); $rules = array(); $valid = count( $ids ) === count( $rates );
			foreach ( $ids as $i => $id ) {
				if ( '' === $id && '' === ( $rates[$i] ?? '' ) ) { continue; }
				if ( ! self::valid_integer( $id, 1, PHP_INT_MAX ) || ! self::valid_integer( $rates[$i] ?? null, 0, 1000000000 ) ) { $valid = false; break; }
				$rules[] = array( 'term_id' => (int) $id, 'rate' => (int) $rates[$i] );
			}
			if ( $valid ) { $d['pro_category_rules'] = $rules; }
		}
		return $d;
	}

	private static function valid_integer( $value, $min, $max ) {
		if ( ! is_int( $value ) && ! is_string( $value ) ) { return false; }
		if ( ! preg_match( '/^[0-9]+$/D', (string) $value ) ) { return false; }
		$digits = ltrim( (string) $value, '0' ); $limit = (string) $max;
		return ( strlen( $digits ) < strlen( $limit ) || ( strlen( $digits ) === strlen( $limit ) && strcmp( $digits, $limit ) <= 0 ) ) && (int) $value >= $min;
	}

	private static function get_currency_code() {
		return Rewardly_Loyalty_Helpers::get_store_currency_code();
	}

	public static function render_settings_tab() {
		$settings      = Rewardly_Loyalty_Helpers::get_settings();
		$currency_code = self::get_currency_code(); ?>
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?><input type="hidden" name="rewardly_loyalty_settings[_section]" value="general"><table class="form-table" role="presentation">
		<tr><th scope="row"><label for="nodevora-setting-enabled"><?php esc_html_e( 'Enable program', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-enabled" name="rewardly_loyalty_settings[enabled]"><option value="yes" <?php selected( $settings['enabled'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="no" <?php selected( $settings['enabled'], 'no' ); ?>><?php esc_html_e( 'No', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-earn_points_per_dh"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( '1 %s = X point(s)', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></label></th><td><input type="number" min="1" id="nodevora-setting-earn_points_per_dh" name="rewardly_loyalty_settings[earn_points_per_dh]" value="<?php echo esc_attr( $settings['earn_points_per_dh'] ); ?>"><p class="description"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( 'Define how many points are earned for each 1 %s spent.', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></p></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-redeem_points_per_dh"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( 'X point(s) = 1 %s', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></label></th><td><input type="number" min="1" id="nodevora-setting-redeem_points_per_dh" name="rewardly_loyalty_settings[redeem_points_per_dh]" value="<?php echo esc_attr( $settings['redeem_points_per_dh'] ); ?>"><p class="description"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( 'Define how many points are required to unlock 1 %s of discount.', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></p></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-min_points_to_redeem"><?php esc_html_e( 'Minimum points to redeem', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><input type="number" min="0" id="nodevora-setting-min_points_to_redeem" name="rewardly_loyalty_settings[min_points_to_redeem]" value="<?php echo esc_attr( $settings['min_points_to_redeem'] ); ?>"></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-max_discount_per_order"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( 'Maximum discount per order (%s)', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></label></th><td><input type="number" min="0" step="0.01" id="nodevora-setting-max_discount_per_order" name="rewardly_loyalty_settings[max_discount_per_order]" value="<?php echo esc_attr( $settings['max_discount_per_order'] ); ?>"><p class="description"><?php esc_html_e( 'Set 0 for no limit.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-order_status_trigger"><?php esc_html_e( 'Trigger order status', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-order_status_trigger" name="rewardly_loyalty_settings[order_status_trigger]"><option value="completed" <?php selected( $settings['order_status_trigger'], 'completed' ); ?>><?php esc_html_e( 'Completed', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="processing" <?php selected( $settings['order_status_trigger'], 'processing' ); ?>><?php esc_html_e( 'Processing', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-points_expiration_days"><?php esc_html_e( 'Points expiration (days)', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><input type="number" min="0" step="1" id="nodevora-setting-points_expiration_days" name="rewardly_loyalty_settings[points_expiration_days]" value="<?php echo esc_attr( $settings['points_expiration_days'] ); ?>"><p class="description"><?php esc_html_e( 'Set 0 to disable automatic expiration.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-email_notifications"><?php esc_html_e( 'Email notifications', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-email_notifications" name="rewardly_loyalty_settings[email_notifications]"><option value="yes" <?php selected( $settings['email_notifications'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="no" <?php selected( $settings['email_notifications'], 'no' ); ?>><?php esc_html_e( 'No', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td></tr>
		<tr><th scope="row"><label for="rewardly-notice-display-mode"><?php esc_html_e( 'Loyalty card display', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="rewardly-notice-display-mode" name="rewardly_loyalty_settings[notice_display_mode]"><option value="both" <?php selected( $settings['notice_display_mode'], 'both' ); ?>><?php esc_html_e( 'Cart and checkout', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="cart" <?php selected( $settings['notice_display_mode'], 'cart' ); ?>><?php esc_html_e( 'Cart only', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="checkout" <?php selected( $settings['notice_display_mode'], 'checkout' ); ?>><?php esc_html_e( 'Checkout only', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="shortcode" <?php selected( $settings['notice_display_mode'], 'shortcode' ); ?>><?php esc_html_e( 'Shortcode only', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select><p class="description"><?php esc_html_e( 'Choose where the loyalty card should appear automatically. The informational notice will be shown on the opposite page when only one card location is selected.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p><div id="rewardly-shortcode-only-help" style="margin-top:8px;<?php echo 'shortcode' === $settings['notice_display_mode'] ? '' : 'display:none;'; ?>"><p><strong><?php esc_html_e( 'Use this shortcode:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong></p><code>[rewardly_loyalty_card]</code><br><code>[rewardly_loyalty_card context="cart"]</code><br><code>[rewardly_loyalty_card context="checkout"]</code><br><br><strong><?php esc_html_e( 'Informational notice shortcodes:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><br><code>[rewardly_loyalty_notice]</code><br><code>[rewardly_loyalty_notice context="cart"]</code><br><code>[rewardly_loyalty_notice context="checkout"]</code></div></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-delete_data_on_uninstall"><?php esc_html_e( 'Delete plugin data on uninstall', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-delete_data_on_uninstall" name="rewardly_loyalty_settings[delete_data_on_uninstall]"><option value="no" <?php selected( $settings['delete_data_on_uninstall'] ?? 'no', 'no' ); ?>><?php esc_html_e( 'No', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="yes" <?php selected( $settings['delete_data_on_uninstall'] ?? 'no', 'yes' ); ?>><?php esc_html_e( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select><p class="description"><?php esc_html_e( 'If enabled, plugin preferences are removed on uninstall. Customer balances, history and financial evidence are always preserved.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
		</table><?php submit_button(); ?></form><?php
	}
}
