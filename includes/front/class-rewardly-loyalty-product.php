<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Product {

	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_product_notice' ), 25 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	public static function enqueue_scripts() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_script(
			'rewardly-loyalty-product',
			REWARDLY_LOYALTY_URL . 'assets/js/loyalty-product.js',
			array( 'jquery' ),
			filemtime( REWARDLY_LOYALTY_PATH . 'assets/js/loyalty-product.js' ),
			true
		);

		wp_localize_script(
			'rewardly-loyalty-product',
			'rewardlyProductData',
			array(
				'locale'   => str_replace( '_', '-', determine_locale() ),
				'currency' => Rewardly_Loyalty_Helpers::get_store_currency_code(),
			)
		);
	}

	public static function render_product_notice() {
		if ( ! Rewardly_Loyalty_Helpers::is_enabled() ) {
			return;
		}

		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$price = (float) $product->get_price();
		if ( $price <= 0 && $product->is_type( 'variable' ) ) {
			$variation_prices = $product->get_variation_prices( true );
			if ( ! empty( $variation_prices['price'] ) ) {
				$price = (float) current( $variation_prices['price'] );
			}
		}

		if ( $price <= 0 ) {
			return;
		}

		$points_rate = Rewardly_Loyalty_Helpers::get_effective_product_earning_rate( $product );
		$points      = Rewardly_Loyalty_Helpers::calculate_product_earned_points( $product, $price );
		if ( $points <= 0 ) {
			return;
		}

		$amount = Rewardly_Loyalty_Helpers::convert_points_to_amount( $points );
		?>
		<div
			class="rewardly-product-points-notice"
			data-default-price="<?php echo esc_attr( wc_format_decimal( $price ) ); ?>"
			data-points-rate="<?php echo esc_attr( (int) $points_rate ); ?>"
			data-redeem-points-per-dh="<?php echo esc_attr( (int) Rewardly_Loyalty_Helpers::get_settings()['redeem_points_per_dh'] ); ?>"
		>
			<span class="rewardly-product-points-notice__icon">🏆</span>
			<div class="rewardly-product-points-notice__text">
				<?php esc_html_e( 'Buy this product and earn up to', 'rewardly-loyalty' ); ?>
				<strong class="rewardly-product-points-notice__points"><?php echo esc_html( $points ); ?></strong>
				<?php esc_html_e( 'loyalty points', 'rewardly-loyalty' ); ?>
				<strong class="rewardly-product-points-notice__amount">(<?php echo wp_kses_post( wc_price( $amount ) ); ?>)</strong>
			</div>
		</div>
		<?php
	}
}
