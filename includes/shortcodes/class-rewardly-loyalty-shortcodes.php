<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Shortcodes {
	public static function init() {
		add_shortcode( 'rewardly_points_balance', array( __CLASS__, 'render_points_balance' ) );
		add_shortcode( 'rewardly_points_value', array( __CLASS__, 'render_points_value' ) );
		add_shortcode( 'rewardly_points_history', array( __CLASS__, 'render_points_history' ) );
		add_shortcode( 'rewardly_account_block', array( __CLASS__, 'render_account_block' ) );
		add_shortcode( 'rewardly_loyalty_notice', array( __CLASS__, 'render_loyalty_notice' ) );
		add_shortcode( 'rewardly_loyalty_card', array( __CLASS__, 'render_loyalty_card' ) );
	}
	private static function uid() { return is_user_logged_in() ? get_current_user_id() : 0; }
	private static function guest() {
		return '<div class="rewardly-shortcode-guest">' . esc_html__( 'Please log in to view your loyalty information.', 'nodevora-customer-rewards-for-woocommerce' ) . '</div>';
	}
	private static function get_history_type_label( $type ) {
		$labels = array(
			'earn'          => __( 'Earned', 'nodevora-customer-rewards-for-woocommerce' ),
			'spend'         => __( 'Redeemed', 'nodevora-customer-rewards-for-woocommerce' ),
			'revoke'        => __( 'Cancelled / Refunded', 'nodevora-customer-rewards-for-woocommerce' ),
			'adjust'        => __( 'Adjustment', 'nodevora-customer-rewards-for-woocommerce' ),
			'adjust_add'    => __( 'Adjustment', 'nodevora-customer-rewards-for-woocommerce' ),
			'adjust_remove' => __( 'Manual Removal', 'nodevora-customer-rewards-for-woocommerce' ),
			'expire'             => __( 'Expired', 'nodevora-customer-rewards-for-woocommerce' ),
			'bonus_registration' => __( 'Registration Bonus', 'nodevora-customer-rewards-for-woocommerce' ),
			'bonus_first_order'  => __( 'First Order Bonus', 'nodevora-customer-rewards-for-woocommerce' ),
			'bonus_review'       => __( 'Review Bonus', 'nodevora-customer-rewards-for-woocommerce' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( (string) $type );
	}
	public static function render_points_balance() {
		$u = self::uid();
		if ( ! $u ) { return self::guest(); }
		return '<span class="rewardly-shortcode-balance">' . esc_html( Rewardly_Loyalty_Helpers::get_user_points( $u ) ) . ' ' . esc_html__( 'points', 'nodevora-customer-rewards-for-woocommerce' ) . '</span>';
	}
	public static function render_points_value() {
		$u = self::uid();
		if ( ! $u ) { return self::guest(); }
		return '<span class="rewardly-shortcode-value">' . wp_kses_post( wc_price( Rewardly_Loyalty_Helpers::convert_points_to_amount( Rewardly_Loyalty_Helpers::get_user_points( $u ) ) ) ) . '</span>';
	}
	public static function render_points_history( $atts ) {
		$u = self::uid();
		if ( ! $u ) { return self::guest(); }
		$atts = shortcode_atts( array( 'limit' => 10 ), $atts, 'rewardly_points_history' );
		$logs = Rewardly_Loyalty_Helpers::get_user_logs( $u, max( 1, min( 50, absint( $atts['limit'] ) ) ) );
		ob_start();
		?>
		<div class="rewardly-shortcode-history">
			<h3><?php esc_html_e( 'My Points History', 'nodevora-customer-rewards-for-woocommerce' ); ?></h3>
			<?php if ( empty( $logs ) ) : ?>
				<p><?php esc_html_e( 'No loyalty activity yet.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $logs as $log ) : ?>
						<li>
							<strong><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $log->created_at ) ) ); ?></strong>
							— <?php echo esc_html( self::get_history_type_label( $log->type ) ); ?>
							— <?php echo esc_html( (int) $log->points ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?>
							<?php if ( ! empty( $log->note ) ) : ?>
								<br><span><?php echo esc_html( $log->note ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
	public static function render_account_block() {
		$u = self::uid();
		if ( ! $u ) { return self::guest(); }
		$points = Rewardly_Loyalty_Helpers::get_user_points( $u );
		$amount = Rewardly_Loyalty_Helpers::convert_points_to_amount( $points );
		$earned = Rewardly_Loyalty_Helpers::get_total_earned( $u );
		$spent  = Rewardly_Loyalty_Helpers::get_total_spent( $u );
		ob_start();
		?>
		<div class="rewardly-shortcode-account rewardly-loyalty-account">
			<div class="rewardly-loyalty-account__summary">
				<div class="rewardly-loyalty-card"><span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Current Balance', 'nodevora-customer-rewards-for-woocommerce' ); ?></span><strong class="rewardly-loyalty-card__value"><?php echo esc_html( $points ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong></div>
				<div class="rewardly-loyalty-card"><span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Available Value', 'nodevora-customer-rewards-for-woocommerce' ); ?></span><strong class="rewardly-loyalty-card__value"><?php echo wp_kses_post( wc_price( $amount ) ); ?></strong></div>
				<div class="rewardly-loyalty-card"><span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Points Earned', 'nodevora-customer-rewards-for-woocommerce' ); ?></span><strong class="rewardly-loyalty-card__value"><?php echo esc_html( $earned ); ?></strong></div>
				<div class="rewardly-loyalty-card"><span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Points Redeemed', 'nodevora-customer-rewards-for-woocommerce' ); ?></span><strong class="rewardly-loyalty-card__value"><?php echo esc_html( $spent ); ?></strong></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_loyalty_notice( $atts ) {
		if ( ! class_exists( 'Rewardly_Loyalty_Redeem' ) ) {
			return '';
		}

		return Rewardly_Loyalty_Redeem::render_notice_shortcode( $atts );
	}

	public static function render_loyalty_card( $atts ) {
		if ( ! class_exists( 'Rewardly_Loyalty_Redeem' ) ) {
			return '';
		}

		$atts    = shortcode_atts( array( 'context' => 'auto' ), $atts, 'rewardly_loyalty_card' );
		$context = sanitize_key( $atts['context'] );
		if ( ! in_array( $context, array( 'auto', 'cart', 'checkout' ), true ) ) {
			$context = 'auto';
		}
		if ( 'auto' === $context ) {
			if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
				$context = 'checkout';
			} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
				$context = 'cart';
			} else {
				$context = 'cart';
			}
		}

		return Rewardly_Loyalty_Redeem::get_loyalty_card_html( $context );
	}
}

