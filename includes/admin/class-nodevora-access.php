<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Les rôles contrôlent la visibilité, jamais l’autorité des opérations financières. */
class Nodevora_Access {
	public static function can_view( $user = null ) {
		$user = $user ?: wp_get_current_user();
		if ( user_can( $user, 'manage_options' ) ) { return true; }
		if ( ! $user->ID || array_intersect( array( 'customer', 'subscriber' ), $user->roles ) ) { return false; }
		$policy = (array) get_option( 'nodevora_access_policy', array() );
		if ( ! empty( $policy['administrators_only'] ) ) { return false; }
		$roles = isset( $policy['roles'] ) ? (array) $policy['roles'] : array( 'shop_manager' );
		return (bool) array_intersect( $roles, $user->roles ) || ( ! isset( $policy['roles'] ) && user_can( $user, 'manage_woocommerce' ) );
	}
	public static function can_edit() { return self::can_view() && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ); }
	public static function init() {
		add_filter( 'user_has_cap', static function( $all, $caps, $args, $user ) {
			// Ne traiter que nos capacités virtuelles pour éviter toute récursion.
			if ( in_array( 'nodevora_view', $caps, true ) ) { $all['nodevora_view'] = self::can_view( $user ); }
			if ( in_array( 'nodevora_edit', $caps, true ) ) { $all['nodevora_edit'] = self::can_view( $user ) && ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ) ); }
			return $all;
		}, 10, 4 );
		// Product overrides are settings too: hiding their panel must not leave REST/meta bypasses.
		foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $hook ) {
			add_filter( $hook, static function( $check, $id, $key ) {
				if ( '_rewardly_loyalty_product_points_rate' !== $key || ( defined('WP_CLI') && WP_CLI && !defined('REST_REQUEST') ) ) { return $check; }
				return self::can_edit() && current_user_can('edit_post', $id) ? $check : false;
			}, 10, 3 );
		}
		foreach ( array('product','product_variation') as $type ) {
			add_filter( 'woocommerce_rest_pre_insert_'.$type.'_object', static function($object, $request) {
				foreach ( (array)$request->get_param('meta_data') as $meta ) {
					if ( is_array($meta) && '_rewardly_loyalty_product_points_rate' === ($meta['key'] ?? '') && !self::can_edit() ) { return new WP_Error('nodevora_forbidden', __('You are not allowed to change Nodevora settings.','nodevora-customer-rewards-for-woocommerce'), array('status'=>403)); }
				}
				return $object;
			},10,2 );
		}
		add_action( 'admin_init', static function() {
			$owner = get_transient( 'nodevora_activation_redirect' );
			if ( $owner && (int) $owner === get_current_user_id() && ! wp_doing_ajax() && ! isset( $_GET['activate-multi'] ) ) { delete_transient( 'nodevora_activation_redirect' ); wp_safe_redirect( admin_url( 'admin.php?page=rewardly-loyalty-settings' . ('synchronization_required'===get_option('nodevora_installation_status')?'&tab=data':'') ) ); exit; }
		} );
		add_action( 'admin_post_nodevora_preferences', array( __CLASS__, 'save' ) );
	}
	public static function advanced() { return 'advanced' === get_user_meta( get_current_user_id(), 'nodevora_admin_mode', true ); }
	public static function save() {
		if ( ! self::can_view() ) { wp_die( '', '', array( 'response' => 403 ) ); }
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( '', '', array( 'response' => 405 ) ); }
		check_admin_referer( 'nodevora_preferences' );
		if ( isset( $_POST['policy'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) { wp_die( '', '', array( 'response' => 403 ) ); }
			$roles = isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) ? array_values( array_intersect( array( 'shop_manager', 'editor' ), array_map( 'sanitize_key', wp_unslash( $_POST['roles'] ) ) ) ) : array();
			update_option( 'nodevora_access_policy', array( 'administrators_only' => ! empty( $_POST['administrators_only'] ), 'roles' => $roles ), false );
		} else { update_user_meta( get_current_user_id(), 'nodevora_admin_mode', ! empty( $_POST['advanced'] ) ? 'advanced' : 'simple' ); }
		wp_safe_redirect( admin_url( 'admin.php?page=rewardly-loyalty-settings' ) ); exit;
	}
	public static function policy_form() {
		if(!current_user_can('manage_options')){return;}
		$p=(array)get_option('nodevora_access_policy',array('roles'=>array('shop_manager')));
		echo '<section class="rewardly-admin-card nodevora-advanced"><h2>'.esc_html__('Nodevora access','nodevora-customer-rewards-for-woocommerce').'</h2><p>'.esc_html__('Administrators always retain access. Shop managers retain their existing editing and adjustment capabilities. Authorized editors have read-only access; panel visibility never grants financial authority.','nodevora-customer-rewards-for-woocommerce').'</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('nodevora_preferences');
		echo '<input type="hidden" name="action" value="nodevora_preferences"><input type="hidden" name="policy" value="1"><label><input type="checkbox" name="administrators_only" value="1" '.checked(!empty($p['administrators_only']),true,false).'> '.esc_html__('Restrict Nodevora access to administrators','nodevora-customer-rewards-for-woocommerce').'</label>';
		foreach(array('shop_manager'=>__('Shop Manager','nodevora-customer-rewards-for-woocommerce'),'editor'=>__('Editor (read only)','nodevora-customer-rewards-for-woocommerce'))as$role=>$label){echo '<label><input type="checkbox" name="roles[]" value="'.esc_attr($role).'" '.checked(in_array($role,$p['roles']??array('shop_manager'),true),true,false).'> '.esc_html($label).'</label>';}
		echo '<button class="button">'.esc_html__('Save access policy','nodevora-customer-rewards-for-woocommerce').'</button></form></section>';
	}

	public static function mode_control() {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nodevora-mode">'; wp_nonce_field( 'nodevora_preferences' );
		echo '<input type="hidden" name="action" value="nodevora_preferences"><label><input type="checkbox" name="advanced" value="1" ' . checked( self::advanced(), true, false ) . '> ' . esc_html__( 'Advanced mode', 'nodevora-customer-rewards-for-woocommerce' ) . '</label> <button class="button">' . esc_html__( 'Apply display mode', 'nodevora-customer-rewards-for-woocommerce' ) . '</button></form>';
	}
}
