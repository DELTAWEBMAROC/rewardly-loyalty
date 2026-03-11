<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Redeem {

	public static function init() {
		add_action( 'wp', array( __CLASS__, 'handle_toggle_request' ) );
		add_filter( 'the_content', array( __CLASS__, 'inject_cart_notice_into_content' ), 20 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_checkout_box' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_discount' ), 20, 1 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_order_usage' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'consume_points_after_order' ), 20, 3 );
	}

	public static function handle_toggle_request() {
		if ( ! is_user_logged_in() || ! Rewardly_Loyalty_Helpers::is_enabled() ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		if ( empty( $_GET['rewardly_loyalty_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['rewardly_loyalty_action'] ) );
		$nonce  = isset( $_GET['_rewardly_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_rewardly_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'rewardly_loyalty_toggle' ) ) {
			return;
		}

		if ( 'apply' === $action ) {
			WC()->session->set( 'rewardly_use_points', 'yes' );
			wc_add_notice( 'Les points de fidélité ont été appliqués à votre commande.', 'success' );
		}

		if ( 'remove' === $action ) {
			WC()->session->set( 'rewardly_use_points', 'no' );
			wc_add_notice( 'Les points de fidélité ont été retirés de cette commande.', 'success' );
		}

		WC()->session->set( 'rewardly_loyalty_discount_amount', 0 );
		WC()->cart->calculate_totals();

		wp_safe_redirect( self::get_current_page_url() );
		exit;
	}

	private static function get_current_page_url() {
		$current_url = home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );

		if ( is_cart() ) {
			$current_url = wc_get_cart_url();
		}

		if ( is_checkout() ) {
			$current_url = wc_get_checkout_url();
		}

		return remove_query_arg( array( 'rewardly_loyalty_action', '_rewardly_nonce' ), $current_url );
	}

	private static function get_cart_notice_html() {
		if ( ! Rewardly_Loyalty_Helpers::is_enabled() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$subtotal = (float) WC()->cart->get_subtotal();
		if ( $subtotal <= 0 ) {
			return '';
		}

		$potential_points = Rewardly_Loyalty_Helpers::calculate_earned_points( $subtotal );
		if ( $potential_points <= 0 ) {
			return '';
		}

		ob_start();
		?>
		<div class="rewardly-cart-points-notice">
			<span class="rewardly-cart-points-notice__icon">🏆</span>
			<div class="rewardly-cart-points-notice__text">
				Finalisez votre commande et gagnez jusqu’à
				<strong><?php echo esc_html( $potential_points ); ?></strong> points de fidélité !
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function inject_cart_notice_into_content( $content ) {
		if ( is_admin() || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return $content;
		}

		$notice_html = self::get_cart_notice_html();
		if ( empty( $notice_html ) ) {
			return $content;
		}

		if ( false !== strpos( $content, 'rewardly-cart-points-notice' ) ) {
			return $content;
		}

		return $notice_html . $content;
	}

	public static function render_checkout_box() {
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		if ( ! Rewardly_Loyalty_Helpers::is_enabled() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$subtotal = (float) WC()->cart->get_subtotal();
		if ( $subtotal <= 0 ) {
			return;
		}

		$potential_points = Rewardly_Loyalty_Helpers::calculate_earned_points( $subtotal );
		if ( $potential_points <= 0 ) {
			return;
		}

		if ( is_user_logged_in() ) {
			self::render_loyalty_box();
			return;
		}
		?>
		<div class="rewardly-checkout-guest-notice">
			<span class="rewardly-checkout-guest-notice__icon">🏆</span>
			<div class="rewardly-checkout-guest-notice__text">
				Cette commande peut vous faire gagner jusqu’à
				<strong><?php echo esc_html( $potential_points ); ?></strong>
				points de fidélité.
			</div>
		</div>
		<?php
	}

	private static function render_loyalty_box() {
		if ( ! is_user_logged_in() || ! Rewardly_Loyalty_Helpers::is_enabled() || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		$user_id          = get_current_user_id();
		$points           = Rewardly_Loyalty_Helpers::get_user_points( $user_id );
		$settings         = Rewardly_Loyalty_Helpers::get_settings();
		$amount           = Rewardly_Loyalty_Helpers::convert_points_to_amount( $points );
		$is_active        = 'yes' === WC()->session->get( 'rewardly_use_points' );
		$subtotal         = (float) WC()->cart->get_subtotal();
		$potential_points = $subtotal > 0 ? Rewardly_Loyalty_Helpers::calculate_earned_points( $subtotal ) : 0;
		$min_points       = isset( $settings['min_points_to_redeem'] ) ? (int) $settings['min_points_to_redeem'] : 0;
		$can_redeem       = $points >= $min_points;

		$apply_url = wp_nonce_url( add_query_arg( array( 'rewardly_loyalty_action' => 'apply' ), self::get_current_page_url() ), 'rewardly_loyalty_toggle', '_rewardly_nonce' );
		$remove_url = wp_nonce_url( add_query_arg( array( 'rewardly_loyalty_action' => 'remove' ), self::get_current_page_url() ), 'rewardly_loyalty_toggle', '_rewardly_nonce' );
		?>
		<div class="rewardly-loyalty-box rewardly-loyalty-box--connected">
			<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--top">
				<span class="rewardly-loyalty-box__icon">🏆</span>
				<div class="rewardly-loyalty-box__top-text">
					Cette commande peut vous faire gagner
					<strong><?php echo esc_html( $potential_points ); ?> points</strong>.
				</div>
			</div>

			<?php if ( $can_redeem ) : ?>
				<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--bottom">
					<div class="rewardly-loyalty-box__bottom-text">
						Vous avez <strong><?php echo esc_html( $points ); ?> points</strong>,
						soit <strong><?php echo wp_kses_post( wc_price( $amount ) ); ?></strong> de réduction disponible.
					</div>
					<div class="rewardly-loyalty-box__actions">
						<?php if ( $is_active ) : ?>
							<a class="button rewardly-loyalty-btn" href="<?php echo esc_url( $remove_url ); ?>">Retirer mes points</a>
						<?php else : ?>
							<a class="button rewardly-loyalty-btn rewardly-loyalty-btn--primary" href="<?php echo esc_url( $apply_url ); ?>">Appliquer mes points</a>
						<?php endif; ?>
					</div>
				</div>
			<?php elseif ( $min_points > 0 ) : ?>
				<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--bottom">
					<div class="rewardly-loyalty-box__bottom-text">
						Vous avez actuellement <strong><?php echo esc_html( $points ); ?> points</strong>.
						Vous pourrez utiliser vos points dès <strong><?php echo esc_html( $min_points ); ?></strong> points cumulés.
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function apply_discount( $cart ) {
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! is_user_logged_in() || ! Rewardly_Loyalty_Helpers::is_enabled() || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( 'rewardly_loyalty_discount_amount', 0 );
		if ( 'yes' !== WC()->session->get( 'rewardly_use_points' ) ) {
			return;
		}

		$user_id  = get_current_user_id();
		$points   = Rewardly_Loyalty_Helpers::get_user_points( $user_id );
		$settings = Rewardly_Loyalty_Helpers::get_settings();

		if ( $points < (int) $settings['min_points_to_redeem'] ) {
			return;
		}

		$amount   = Rewardly_Loyalty_Helpers::convert_points_to_amount( $points );
		$subtotal = (float) $cart->get_subtotal();
		if ( $amount <= 0 || $subtotal <= 0 ) {
			return;
		}

		if ( (float) $settings['max_discount_per_order'] > 0 ) {
			$amount = min( $amount, (float) $settings['max_discount_per_order'] );
		}

		$amount = min( $amount, $subtotal );
		if ( $amount <= 0 ) {
			return;
		}

		$cart->add_fee( 'Réduction fidélité', -$amount, false );
		WC()->session->set( 'rewardly_loyalty_discount_amount', $amount );
	}

	public static function save_order_usage( $order, $data ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$discount = (float) WC()->session->get( 'rewardly_loyalty_discount_amount' );
		if ( $discount <= 0 || ! is_user_logged_in() ) {
			return;
		}

		$points_to_spend = Rewardly_Loyalty_Helpers::convert_amount_to_points( $discount );
		if ( $points_to_spend <= 0 ) {
			return;
		}

		$order->update_meta_data( '_lavap_loyalty_points_spent', $points_to_spend );
		$order->update_meta_data( '_rewardly_loyalty_discount_amount', $discount );
	}

	
	public static function consume_points_after_order( $order_id, $posted_data, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_lavap_loyalty_spent_processed' ) ) {
			return;
		}

		$points_to_spend = (int) $order->get_meta( '_lavap_loyalty_points_spent' );
		if ( $points_to_spend <= 0 ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		Rewardly_Loyalty_Helpers::subtract_points(
			$user_id,
			$points_to_spend,
			$order_id,
			'spend',
			'Points utilisés pour obtenir une réduction sur la commande.'
		);

		$order->update_meta_data( '_lavap_loyalty_spent_processed', 'yes' );
		$order->save();

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( 'rewardly_use_points' );
			WC()->session->__unset( 'rewardly_loyalty_discount_amount' );
		}
	}

}
