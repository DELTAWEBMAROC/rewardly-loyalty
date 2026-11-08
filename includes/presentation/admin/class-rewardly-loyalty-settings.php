<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Settings {
	private static $save_expected = null;
	private static $save_before = null;
	private static $save_invalid = false;
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'wp_redirect', array( __CLASS__, 'verified_save_redirect' ), 10, 2 );
		add_filter( 'option_page_capability_rewardly_loyalty_group', function() { return 'nodevora_edit'; } );
	}

	public static function register_settings() {
		register_setting( 'rewardly_loyalty_group', 'nodevora_v2_preferences', array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ), 'default' => Rewardly_Loyalty_Helpers::get_default_settings() ) );
	}

	private static function settings_post() {
		global $pagenow;
		return 'options.php' === $pagenow && 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' )
			&& 'rewardly_loyalty_group' === ( isset( $_POST['option_page'] ) && is_string( $_POST['option_page'] ) ? sanitize_key( wp_unslash( $_POST['option_page'] ) ) : '' ) && 'update' === ( isset( $_POST['action'] ) && is_string( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '' )
			&& current_user_can( 'nodevora_edit' ) && is_string( $_POST['_wpnonce'] ?? null )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'rewardly_loyalty_group-options' );
	}
	private static function stored_settings() {
		global $wpdb;
		// Le cache d’option ne constitue pas une preuve de persistance après un refus SQL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh prepared read verifies SQL persistence; the options cache cannot prove a successful save.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'nodevora_v2_preferences' ), ARRAY_A );
		return $row ? $row['option_value'] : null;
	}
	private static function sanitized_result( $value, $invalid = false ) {
		if ( self::settings_post() ) {
			self::$save_expected = maybe_serialize( $value );
			self::$save_invalid = self::$save_invalid || $invalid;
		}
		return $value;
	}
	public static function verified_save_redirect( $location, $status ) {
		if ( null === self::$save_expected || ! self::settings_post() ) { return $location; }
		$stored = self::stored_settings();
		$result = $stored !== self::$save_expected ? 'failed' : ( self::$save_invalid ? 'invalid' : ( self::$save_before === $stored ? 'unchanged' : 'saved' ) );
		// Aucun réglage ni diagnostic dans l’URL ; le résultat est lié à cette session.
		return add_query_arg( array( 'nodevora_settings_result' => $result, 'nodevora_settings_nonce' => wp_create_nonce( 'nodevora-settings-' . $result ) ), remove_query_arg( 'settings-updated', $location ) );
	}
	public static function render_save_notice() {
		$result = isset( $_GET['nodevora_settings_result'] ) && is_string( $_GET['nodevora_settings_result'] ) ? sanitize_key( wp_unslash( $_GET['nodevora_settings_result'] ) ) : '';
		$nonce = isset( $_GET['nodevora_settings_nonce'] ) && is_string( $_GET['nodevora_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nodevora_settings_nonce'] ) ) : '';
		if ( ! is_string( $result ) || ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'nodevora-settings-' . $result ) ) { return; }
		$messages = array(
			'saved' => __( 'Your settings have been saved.', 'nodevora-customer-rewards-for-woocommerce' ),
			'unchanged' => __( 'These settings are already saved. No changes were needed.', 'nodevora-customer-rewards-for-woocommerce' ),
			'failed' => __( 'Your settings could not be saved. Reload this page and try again, or contact your site administrator.', 'nodevora-customer-rewards-for-woocommerce' ),
			'invalid' => __( 'Some submitted settings are invalid and were not saved. Check the values and try again.', 'nodevora-customer-rewards-for-woocommerce' ),
		);
		if ( ! isset( $messages[$result] ) ) { return; }
		$error = in_array( $result, array( 'failed', 'invalid' ), true );
		echo '<div class="notice ' . ( $error ? 'notice-error' : 'notice-success' ) . ' nodevora-settings-notice" role="' . ( $error ? 'alert' : 'status' ) . '"><p>' . esc_html( $messages[$result] ) . '</p></div>';
	}

	public static function sanitize_settings( $input ) {
		if ( self::settings_post() && null === self::$save_expected ) { self::$save_before = self::stored_settings(); }
		$d = wp_parse_args( get_option( 'nodevora_v2_preferences', array() ), Rewardly_Loyalty_Helpers::get_default_settings() );
		if ( ! is_array( $input ) ) { return self::sanitized_result( $d, true ); }
		$sections = array(
			'general' => array( 'enabled', 'earn_points_per_dh', 'redeem_points_per_dh', 'min_points_to_redeem', 'max_discount_per_order', 'order_status_trigger', 'points_expiration_days', 'email_notifications', 'notice_display_mode', 'delete_data_on_uninstall' ),
			'design' => array( 'primary_color', 'accent_color', 'border_radius', 'enable_front_styles', 'design_template', 'icon_style', 'button_style', 'card_style', 'typography_preset', 'button_background', 'button_text', 'button_hover_background', 'button_hover_text', 'card_background', 'card_border', 'density' ),
			'rules' => array( 'pro_exclude_sale_products', 'pro_excluded_product_ids', 'pro_excluded_category_ids', 'pro_category_rule_term_id', 'pro_category_rule_rate' ),
			'bonuses' => array( 'pro_points_registration', 'pro_points_first_order', 'pro_points_review', 'pro_points_birthday', 'pro_referral_points' ),
			'pending' => array( 'pro_pending_points_notice_enabled', 'pro_pending_points_notice_text', 'pro_pending_points_statuses' ),
			'levels' => array( 'pro_levels_enabled', 'pro_level_bronze_threshold', 'pro_level_silver_threshold', 'pro_level_gold_threshold' ),
		);
		$sections['design']=array_merge($sections['design'],Rewardly_Loyalty_Design::additional_colors());
		$reset_design=!empty($input['_reset_design']);
		$scope = isset( $input['_section'] ) && is_string( $input['_section'] ) ? $input['_section'] : '';
		if('design'===$scope&&$reset_design){$defaults=Rewardly_Loyalty_Helpers::get_default_settings();foreach($sections['design']as$field){$d[$field]=$defaults[$field];}}
		if ( isset( $input['_section'] ) && ! isset( $sections[$scope] ) ) { return self::sanitized_result( $d, true ); }
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
		$colors=array_merge($colors,Rewardly_Loyalty_Design::additional_colors());
		$invalid = false;
		foreach ( $input as $key => $value ) {
			if ( in_array( $key, $booleans, true ) && in_array( $value, array( 'yes', 'no' ), true ) ) { $d[$key] = $value; }
			elseif ( isset( $enums[$key] ) && in_array( $value, $enums[$key], true ) ) { $d[$key] = $value; }
			elseif ( in_array( $key, $integer, true ) && self::valid_integer( $value, in_array( $key, array( 'earn_points_per_dh', 'redeem_points_per_dh' ), true ) ? 1 : 0, 'border_radius' === $key ? 30 : ( 'points_expiration_days' === $key ? 36500 : 1000000000 ) ) ) { $d[$key] = (int) $value; }
			elseif ( in_array( $key, $colors, true ) && is_string( $value ) && ( sanitize_hex_color( $value ) || ( '' === $value && ! in_array( $key, array( 'primary_color', 'accent_color' ), true ) ) ) ) { $d[$key] = '' === $value ? '' : sanitize_hex_color( $value ); }
			elseif ( 'max_discount_per_order' === $key && is_scalar( $value ) && preg_match( '/^[0-9]{1,9}(?:\.[0-9]{1,6})?$/D', (string) $value ) ) { $d[$key] = wc_format_decimal( $value, wc_get_price_decimals() ); }
			elseif ( 'pro_pending_points_notice_text' === $key && is_string( $value ) ) { $d[$key] = sanitize_textarea_field( $value ); }
			elseif ( in_array( $key, array( 'pro_excluded_product_ids', 'pro_excluded_category_ids' ), true ) && is_array( $value ) ) {
				$valid = true; foreach ( $value as $id ) { if ( ! self::valid_integer( $id, 1, PHP_INT_MAX ) ) { $valid = false; } }
				if ( $valid ) { $d[$key] = array_values( array_unique( array_map( 'intval', $value ) ) ); } else { $invalid = true; }
			}
			elseif ( 'pro_pending_points_statuses' === $key && is_array( $value ) ) {
				$allowed = array_keys( wc_get_order_statuses() ); $allowed = array_map( function( $status ) { return substr( $status, 3 ); }, $allowed );
				if ( count( array_filter( $value, 'is_string' ) ) === count( $value ) && ! array_diff( $value, $allowed ) ) { $d[$key] = array_values( array_unique( $value ) ); } else { $invalid = true; }
			}
			elseif ( isset( $enums[$key] ) || in_array( $key, array_merge( $booleans, $integer, $colors, array( 'max_discount_per_order', 'pro_pending_points_notice_text', 'pro_excluded_product_ids', 'pro_excluded_category_ids', 'pro_pending_points_statuses' ) ), true ) ) { $invalid = true; }
		}
		if ( isset( $input['pro_category_rule_term_id'], $input['pro_category_rule_rate'] ) && is_array( $input['pro_category_rule_term_id'] ) && is_array( $input['pro_category_rule_rate'] ) ) {
			$ids = array_values( $input['pro_category_rule_term_id'] ); $rates = array_values( $input['pro_category_rule_rate'] ); $rules = array(); $valid = count( $ids ) === count( $rates );
			foreach ( $ids as $i => $id ) {
				if ( '' === $id && '' === ( $rates[$i] ?? '' ) ) { continue; }
				if ( ! self::valid_integer( $id, 1, PHP_INT_MAX ) || ! self::valid_integer( $rates[$i] ?? null, 0, 1000000000 ) ) { $valid = false; break; }
				$rules[] = array( 'term_id' => (int) $id, 'rate' => (int) $rates[$i] );
			}
			if ( $valid ) { $d['pro_category_rules'] = $rules; } else { $invalid = true; }
		}
		return self::sanitized_result( $d, $invalid );
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
		<form method="post" action="options.php" class="rewardly-admin-card"><?php settings_fields( 'rewardly_loyalty_group' ); ?><input type="hidden" name="nodevora_v2_preferences[_section]" value="general"><table class="form-table" role="presentation">
		<tr><th scope="row"><label for="nodevora-setting-enabled"><?php esc_html_e( 'Enable program', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-enabled" name="nodevora_v2_preferences[enabled]"><option value="yes" <?php selected( $settings['enabled'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="no" <?php selected( $settings['enabled'], 'no' ); ?>><?php esc_html_e( 'No', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-earn_points_per_dh"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( '1 %s = X point(s)', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></label></th><td><input type="number" min="1" id="nodevora-setting-earn_points_per_dh" name="nodevora_v2_preferences[earn_points_per_dh]" value="<?php echo esc_attr( $settings['earn_points_per_dh'] ); ?>"><p class="description"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( 'Define how many points are earned for each 1 %s spent.', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></p></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-redeem_points_per_dh"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( 'X point(s) = 1 %s', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></label></th><td><input type="number" min="1" id="nodevora-setting-redeem_points_per_dh" name="nodevora_v2_preferences[redeem_points_per_dh]" value="<?php echo esc_attr( $settings['redeem_points_per_dh'] ); ?>"><p class="description"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( 'Define how many points are required to unlock 1 %s of discount.', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></p></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-min_points_to_redeem"><?php esc_html_e( 'Minimum points to redeem', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><input type="number" min="0" id="nodevora-setting-min_points_to_redeem" name="nodevora_v2_preferences[min_points_to_redeem]" value="<?php echo esc_attr( $settings['min_points_to_redeem'] ); ?>"></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-max_discount_per_order"><?php
			/* translators: %s: store currency code. */
			echo esc_html( sprintf( __( 'Maximum discount per order (%s)', 'nodevora-customer-rewards-for-woocommerce' ), $currency_code ) );
		?></label></th><td><input type="number" min="0" step="0.01" id="nodevora-setting-max_discount_per_order" name="nodevora_v2_preferences[max_discount_per_order]" value="<?php echo esc_attr( $settings['max_discount_per_order'] ); ?>"><p class="description"><?php esc_html_e( 'Set 0 for no limit.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
		<tr class="nodevora-advanced"><th scope="row"><label for="nodevora-setting-order_status_trigger"><?php esc_html_e( 'Trigger order status', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-order_status_trigger" name="nodevora_v2_preferences[order_status_trigger]" <?php disabled(!Nodevora_Access::advanced()); ?>><option value="completed" <?php selected( $settings['order_status_trigger'], 'completed' ); ?>><?php esc_html_e( 'Completed', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="processing" <?php selected( $settings['order_status_trigger'], 'processing' ); ?>><?php esc_html_e( 'Processing', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td></tr>
		<tr class="nodevora-advanced"><th scope="row"><label for="nodevora-setting-points_expiration_days"><?php esc_html_e( 'Points expiration (days)', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><input type="number" min="0" step="1" id="nodevora-setting-points_expiration_days" name="nodevora_v2_preferences[points_expiration_days]" <?php disabled(!Nodevora_Access::advanced()); ?> value="<?php echo esc_attr( $settings['points_expiration_days'] ); ?>"><p class="description"><?php esc_html_e( 'Set 0 to disable automatic expiration.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
		<tr><th scope="row"><label for="nodevora-setting-email_notifications"><?php esc_html_e( 'Email notifications', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-email_notifications" name="nodevora_v2_preferences[email_notifications]"><option value="yes" <?php selected( $settings['email_notifications'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="no" <?php selected( $settings['email_notifications'], 'no' ); ?>><?php esc_html_e( 'No', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td></tr>
		<tr><th scope="row"><label for="rewardly-notice-display-mode"><?php esc_html_e( 'Loyalty card display', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="rewardly-notice-display-mode" name="nodevora_v2_preferences[notice_display_mode]"><option value="both" <?php selected( $settings['notice_display_mode'], 'both' ); ?>><?php esc_html_e( 'Cart and checkout', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="cart" <?php selected( $settings['notice_display_mode'], 'cart' ); ?>><?php esc_html_e( 'Cart only', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="checkout" <?php selected( $settings['notice_display_mode'], 'checkout' ); ?>><?php esc_html_e( 'Checkout only', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="shortcode" <?php selected( $settings['notice_display_mode'], 'shortcode' ); ?>><?php esc_html_e( 'Shortcode only', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select><p class="description"><?php esc_html_e( 'Choose where the loyalty card should appear automatically. The informational notice will be shown on the opposite page when only one card location is selected.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p><div id="rewardly-shortcode-only-help" style="margin-top:8px;<?php echo 'shortcode' === $settings['notice_display_mode'] ? '' : 'display:none;'; ?>"><p><strong><?php esc_html_e( 'Use this shortcode:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong></p><code>[rewardly_loyalty_card]</code><br><code>[rewardly_loyalty_card context="cart"]</code><br><code>[rewardly_loyalty_card context="checkout"]</code><br><br><strong><?php esc_html_e( 'Informational notice shortcodes:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong><br><code>[rewardly_loyalty_notice]</code><br><code>[rewardly_loyalty_notice context="cart"]</code><br><code>[rewardly_loyalty_notice context="checkout"]</code></div></td></tr>
		<tr class="nodevora-advanced"><th scope="row"><label for="nodevora-setting-delete_data_on_uninstall"><?php esc_html_e( 'Delete plugin data on uninstall', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-setting-delete_data_on_uninstall" name="nodevora_v2_preferences[delete_data_on_uninstall]" <?php disabled(!Nodevora_Access::advanced()); ?>><option value="no" <?php selected( $settings['delete_data_on_uninstall'] ?? 'no', 'no' ); ?>><?php esc_html_e( 'No', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="yes" <?php selected( $settings['delete_data_on_uninstall'] ?? 'no', 'yes' ); ?>><?php esc_html_e( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select><p class="description"><?php esc_html_e( 'If enabled, plugin preferences are removed on uninstall. Customer balances, history and financial evidence are always preserved.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></td></tr>
		</table><?php submit_button(); ?></form><?php
	}
}
