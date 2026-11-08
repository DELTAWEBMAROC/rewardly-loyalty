<?php
/** Native settings, permissions, adjustments and explicit refund review. @package Nodevora */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
class Nodevora_Admin {
	public static function can_edit() {
		return Nodevora_Access::can_edit(); }
	public static function can_view() {
		return Nodevora_Access::can_view(); }
	public static function init() {
		add_action(
			'admin_menu',
			static function () {
				if ( self::can_view() ) {
					add_submenu_page( 'woocommerce', 'Nodevora', 'Nodevora', 'read', 'nodevora-v2', array( __CLASS__, 'page' ) );
				} }
		);
		add_action( 'admin_post_nodevora_v2_adjust', array( __CLASS__, 'adjust' ) );
		add_action( 'admin_post_nodevora_v2_refund', array( __CLASS__, 'refund' ) );
		add_action( 'admin_post_nodevora_v2_retry', array( __CLASS__, 'retry' ) );
		add_action( 'admin_post_nodevora_v2_mode', array( __CLASS__, 'mode' ) );
		add_action(
			'woocommerce_admin_order_data_after_order_details',
			static function ( $order ) {
				if ( $order->get_meta( '_nodevora_v2_issue' ) ) {
					echo '<p><strong>Nodevora:</strong> ' . esc_html( $order->get_meta( '_nodevora_v2_issue' ) ) . '</p>';
				} }
		);
	}
	public static function authorize( $nonce, $admin = false ) {
		if ( ( $admin ? ! current_user_can( 'manage_options' ) : ! self::can_edit() ) ) {
			wp_die( '', '', array( 'response' => 403 ) ); }
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			wp_die( '', '', array( 'response' => 405 ) ); }
	}
	private static function finish() {
		wp_safe_redirect( admin_url( 'admin.php?page=rewardly-loyalty-settings&tab=pro_features&updated=1' ) );
		exit; }
	public static function mode() {
		self::authorize( 'nodevora_v2_mode' );
		check_admin_referer( 'nodevora_v2_mode' );
		update_user_meta( get_current_user_id(), 'nodevora_v2_advanced', empty( $_POST['advanced'] ) ? 0 : 1 );
		self::finish(); }
	public static function adjust() {
		self::authorize( 'nodevora_v2_adjust' );
		check_admin_referer( 'nodevora_v2_adjust' );
		try {
			$uid  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
			$raw  = isset( $_POST['points'] ) ? sanitize_text_field( wp_unslash( $_POST['points'] ) ) : '';
			$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
			$key  = isset( $_POST['operation'] ) ? sanitize_text_field( wp_unslash( $_POST['operation'] ) ) : '';
			if ( ! preg_match( '/^-?[0-9]{1,9}$/D', $raw ) || strlen( trim( $note ) ) < 5 || strlen( $note ) > 1000 || ! wp_is_uuid( $key ) ) {
				throw new RuntimeException( esc_html__( 'A valid amount, reason and operation identifier are required.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$n = (int) $raw;
			Nodevora_Engine::apply(
				$uid,
				'admin:' . get_current_user_id() . ':' . $key,
				$n < 0 ? 'debit' : 'credit',
				abs( $n ),
				array(
					'note'  => $note,
					'actor' => get_current_user_id(),
				)
			);
			self::finish();
		} catch ( Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ), '', array( 'response' => 400 ) ); }
	}
	public static function refund() {
		self::authorize( 'nodevora_v2_refund', true );
		check_admin_referer( 'nodevora_v2_refund' );
		try {
			$id   = isset( $_POST['refund_id'] ) ? absint( $_POST['refund_id'] ) : 0;
			$r    = wc_get_order( $id );
			$raw  = isset( $_POST['points'] ) ? sanitize_text_field( wp_unslash( $_POST['points'] ) ) : '';
			$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
			if ( ! $r instanceof WC_Order_Refund || ! preg_match( '/^-?[0-9]{1,9}$/D', $raw ) || strlen( trim( $note ) ) < 10 || strlen( $note ) > 1000 ) {
				throw new RuntimeException( esc_html__( 'Refund, points decision and detailed reason required.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$o = wc_get_order( $r->get_parent_id() );
			if ( ! $o || ! $o->get_user_id() || ! Nodevora_Store::event( 'refund:' . $id . ':review' ) ) {
				throw new RuntimeException( esc_html__( 'Pending refund review not found.', 'nodevora-customer-rewards-for-woocommerce' ) ); }
			$n = (int) $raw;
			Nodevora_Engine::apply(
				(int) $o->get_user_id(),
				'refund:' . $id . ':reviewed',
				$n < 0 ? 'debit' : ( $n > 0 ? 'credit' : 'control' ),
				abs( $n ),
				array(
					'order_id' => $o->get_id(),
					'note'     => $note,
					'actor'    => get_current_user_id(),
				)
			);
			$o->delete_meta_data( '_nodevora_v2_issue' );
			$o->save();
			Nodevora_Commerce::reconcile( $o->get_id() );
			self::finish();
		} catch ( Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ), '', array( 'response' => 400 ) ); }
	}
	public static function retry() {
		self::authorize( 'nodevora_v2_retry' );
		check_admin_referer( 'nodevora_v2_retry' );
		$id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		Nodevora_Commerce::reconcile( $id );
		self::finish(); }
	private static function open_form( $action, $upload = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '><input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
	}
	public static function page() {
		if ( ! self::can_view() ) {
			wp_die( '', '', array( 'response' => 403 ) ); }
		$s        = Nodevora_Settings::get();
		$advanced = (bool) get_user_meta( get_current_user_id(), 'nodevora_v2_advanced', true );
		echo '<div class="wrap"><h1>Nodevora <small>' . esc_html( NODEVORA_VERSION ) . '</small></h1>';
		settings_errors();
		if ( self::can_edit() ) {
			self::open_form( 'nodevora_v2_mode' );
			echo '<label><input type="checkbox" name="advanced" value="1" ' . checked( $advanced, true, false ) . '> ' . esc_html__( 'Advanced mode', 'nodevora-customer-rewards-for-woocommerce' ) . '</label> ';
			submit_button( __( 'Apply display mode', 'nodevora-customer-rewards-for-woocommerce' ), 'secondary', 'submit', false );
			echo '</form>'; }
		if ( current_user_can( 'manage_options' ) ) {
			echo '<h2>' . esc_html__( 'Settings', 'nodevora-customer-rewards-for-woocommerce' ) . '</h2><form method="post" action="options.php">';
			settings_fields( 'nodevora_v2' );
			$labels = array(
				'enabled'             => __( 'Enable loyalty program', 'nodevora-customer-rewards-for-woocommerce' ),
				'earn_rate'           => __( 'Points earned per currency unit', 'nodevora-customer-rewards-for-woocommerce' ),
				'redeem_rate'         => __( 'Points needed per currency unit', 'nodevora-customer-rewards-for-woocommerce' ),
				'minimum'             => __( 'Minimum points to redeem', 'nodevora-customer-rewards-for-woocommerce' ),
				'maximum_discount'    => __( 'Maximum discount per order (0 = unlimited)', 'nodevora-customer-rewards-for-woocommerce' ),
				'trigger'             => __( 'Earning order status', 'nodevora-customer-rewards-for-woocommerce' ),
				'expiration_days'     => __( 'Point validity in days (0 = no expiry)', 'nodevora-customer-rewards-for-woocommerce' ),
				'registration_bonus'  => __( 'Registration Bonus', 'nodevora-customer-rewards-for-woocommerce' ),
				'first_order_bonus'   => __( 'First Order Bonus', 'nodevora-customer-rewards-for-woocommerce' ),
				'review_bonus'        => __( 'Review Bonus', 'nodevora-customer-rewards-for-woocommerce' ),
				'display_cache'       => __( 'Cache history display', 'nodevora-customer-rewards-for-woocommerce' ),
				'exclude_sale'        => __( 'Exclude sale products from earning', 'nodevora-customer-rewards-for-woocommerce' ),
				'excluded_products'   => __( 'Excluded product IDs, comma separated', 'nodevora-customer-rewards-for-woocommerce' ),
				'excluded_categories' => __( 'Excluded category IDs, comma separated', 'nodevora-customer-rewards-for-woocommerce' ),
				'category_rules'      => __( 'Category rates: category_id:rate, category_id:rate', 'nodevora-customer-rewards-for-woocommerce' ),
				'primary_color'       => __( 'Primary color', 'nodevora-customer-rewards-for-woocommerce' ),
				'accent_color'        => __( 'Accent color', 'nodevora-customer-rewards-for-woocommerce' ),
				'card_color'          => __( 'Card background', 'nodevora-customer-rewards-for-woocommerce' ),
				'button_text_color'   => __( 'Button text color', 'nodevora-customer-rewards-for-woocommerce' ),
				'radius'              => __( 'Border radius', 'nodevora-customer-rewards-for-woocommerce' ),
				'front_styles'        => __( 'Enable frontend styles', 'nodevora-customer-rewards-for-woocommerce' ),
				'notice_mode'         => __( 'Rewards form placement', 'nodevora-customer-rewards-for-woocommerce' ),
				'managers'            => __( 'Allow shop managers to adjust points', 'nodevora-customer-rewards-for-woocommerce' ),
				'editor_view'         => __( 'Allow editors to view this page', 'nodevora-customer-rewards-for-woocommerce' ),
				'levels'              => __( 'Display loyalty levels', 'nodevora-customer-rewards-for-woocommerce' ),
				'bronze'              => __( 'Bronze threshold', 'nodevora-customer-rewards-for-woocommerce' ),
				'silver'              => __( 'Silver threshold', 'nodevora-customer-rewards-for-woocommerce' ),
				'gold'                => __( 'Gold threshold', 'nodevora-customer-rewards-for-woocommerce' ),
			);
			echo '<table class="form-table"><tbody>';
			foreach ( $labels as $key => $label ) {
				if ( ! $advanced && ! in_array( $key, array( 'enabled', 'earn_rate', 'redeem_rate', 'minimum', 'trigger', 'expiration_days', 'primary_color', 'accent_color' ), true ) ) {
					continue; }
				echo '<tr><th><label for="nv-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
				$name = 'nodevora_v2_settings[' . $key . ']';
				if ( in_array( $key, array( 'enabled', 'display_cache', 'exclude_sale', 'front_styles', 'managers', 'editor_view', 'levels' ), true ) ) {
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input id="nv-' . esc_attr( $key ) . '" type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $s[ $key ], 1, false ) . '>'; } elseif ( in_array( $key, array( 'trigger', 'notice_mode' ), true ) ) {
					echo '<select id="nv-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( 'trigger' === $key ? array( 'completed', 'processing' ) : array( 'both', 'cart', 'checkout', 'none' ) as $choice ) {
						echo '<option value="' . esc_attr( $choice ) . '" ' . selected( $s[ $key ], $choice, false ) . '>' . esc_html( $choice ) . '</option>';
					} echo '</select>'; } else {
						$type = substr( $key, -6 ) === '_color' ? 'color' : ( is_int( Nodevora_Settings::defaults()[ $key ] ) ? 'number' : 'text' );
						echo '<input id="nv-' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $s[ $key ] ) . '" class="regular-text">'; }
					echo '</td></tr>';
			}
			echo '</tbody></table>';
			submit_button();
			echo '</form>';
		}
		if ( self::can_edit() ) {
			echo '<hr><h2>' . esc_html__( 'Adjust points', 'nodevora-customer-rewards-for-woocommerce' ) . '</h2>';
			self::open_form( 'nodevora_v2_adjust' );
			echo '<input type="hidden" name="operation" value="' . esc_attr( wp_generate_uuid4() ) . '">';
			self::input( 'user_id', __( 'Customer user ID', 'nodevora-customer-rewards-for-woocommerce' ), 'number' );
			self::input( 'points', __( 'Points change (negative removes points)', 'nodevora-customer-rewards-for-woocommerce' ), 'number' );
			self::input( 'note', __( 'Reason', 'nodevora-customer-rewards-for-woocommerce' ) );
			submit_button( __( 'Apply adjustment', 'nodevora-customer-rewards-for-woocommerce' ) );
			echo '</form>';
			echo '<h2>' . esc_html__( 'Retry order reconciliation', 'nodevora-customer-rewards-for-woocommerce' ) . '</h2>';
			self::open_form( 'nodevora_v2_retry' );
			self::input( 'order_id', __( 'Order ID', 'nodevora-customer-rewards-for-woocommerce' ), 'number' );
			submit_button( __( 'Retry', 'nodevora-customer-rewards-for-woocommerce' ) );
			echo '</form>';
		}
		if ( current_user_can( 'manage_options' ) ) {
			echo '<hr><h2>' . esc_html__( 'Review a partial refund', 'nodevora-customer-rewards-for-woocommerce' ) . '</h2><p>' . esc_html__( 'Record the points adjustment and reason. Use zero to keep points unchanged. Restore the order status manually after review.', 'nodevora-customer-rewards-for-woocommerce' ) . '</p>';
			self::open_form( 'nodevora_v2_refund' );
			self::input( 'refund_id', __( 'Refund ID', 'nodevora-customer-rewards-for-woocommerce' ), 'number' );
			self::input( 'points', __( 'Points change (negative removes points)', 'nodevora-customer-rewards-for-woocommerce' ), 'number' );
			self::input( 'note', __( 'Reason', 'nodevora-customer-rewards-for-woocommerce' ) );
			submit_button( __( 'Record review', 'nodevora-customer-rewards-for-woocommerce' ) );
			echo '</form>';
			echo '<hr><h2>' . esc_html__( 'Backup / Restore', 'nodevora-customer-rewards-for-woocommerce' ) . '</h2><p>' . esc_html__( 'V2 backups are private, limited to 8 MiB and 20,000 rows per table, and restore only to an empty V2 store on the same site. Orders and users must still exist. Legacy backups are not imported.', 'nodevora-customer-rewards-for-woocommerce' ) . '</p>';
			self::open_form( 'nodevora_v2_export' );
			submit_button( __( 'Export backup', 'nodevora-customer-rewards-for-woocommerce' ) );
			echo '</form>';
			self::open_form( 'nodevora_v2_import', true );
			echo '<input type="file" name="backup" accept="application/json,.json" required><p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html__( 'Restore this backup into an empty V2 store.', 'nodevora-customer-rewards-for-woocommerce' ) . '</label></p>';
			submit_button( __( 'Import backup', 'nodevora-customer-rewards-for-woocommerce' ) );
			echo '</form>';
		}
		echo '</div>';
	}
public static function reviews() {
 echo '<section class="rewardly-admin-card">';
 self::open_form('nodevora_v2_retry');self::input('order_id',__('Order ID','nodevora-customer-rewards-for-woocommerce'),'number');submit_button(__('Retry','nodevora-customer-rewards-for-woocommerce'));echo '</form>';
 self::open_form('nodevora_v2_refund');self::input('refund_id',__('Refund ID','nodevora-customer-rewards-for-woocommerce'),'number');self::input('points',__('Points','nodevora-customer-rewards-for-woocommerce'),'number');self::input('note',__('Reason','nodevora-customer-rewards-for-woocommerce'));submit_button(__('Record review','nodevora-customer-rewards-for-woocommerce'));echo '</form></section>';
}
	private static function input( $name, $label, $type = 'text' ) {
		echo '<p><label>' . esc_html( $label ) . '<br><input class="regular-text" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" required></label></p>'; }
}
