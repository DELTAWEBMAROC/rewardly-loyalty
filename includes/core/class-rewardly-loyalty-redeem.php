<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Redeem {

	public static function init() {
		add_action( 'wp', array( __CLASS__, 'handle_form_requests' ) );
		add_filter( 'the_content', array( __CLASS__, 'inject_cart_notice_into_content' ), 20 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'render_cart_notice' ), 5 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'render_cart_box' ), 10 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_checkout_notice' ), 5 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_checkout_box' ), 10 );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_discount' ), 20, 1 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_order_usage' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'save_order_usage_store_api' ), 20, 1 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'consume_points_after_order' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'consume_points_after_order_store_api' ), 20, 1 );
	}

	/**
	 * Retourner le mode d'affichage de la carte fidélité.
	 *
	 * @return string
	 */
	public static function get_notice_display_mode() {
		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$mode     = isset( $settings['notice_display_mode'] ) ? sanitize_key( $settings['notice_display_mode'] ) : 'both';

		if ( ! in_array( $mode, array( 'both', 'cart', 'checkout', 'shortcode' ), true ) ) {
			$mode = 'both';
		}

		return $mode;
	}

	/**
	 * Déterminer si la carte doit s'afficher automatiquement sur le panier.
	 *
	 * @return bool
	 */
	public static function should_display_notice_in_cart() {
		return in_array( self::get_notice_display_mode(), array( 'both', 'cart' ), true );
	}

	/**
	 * Déterminer si la carte doit s'afficher automatiquement au checkout.
	 *
	 * @return bool
	 */
	public static function should_display_notice_in_checkout() {
		return in_array( self::get_notice_display_mode(), array( 'both', 'checkout' ), true );
	}

	/**
	 * Déterminer si la notice simple doit s'afficher automatiquement sur le panier.
	 *
	 * @return bool
	 */
	public static function should_display_auto_notice_in_cart() {
		return 'checkout' === self::get_notice_display_mode();
	}

	/**
	 * Déterminer si la notice simple doit s'afficher automatiquement au checkout.
	 *
	 * @return bool
	 */
	public static function should_display_auto_notice_in_checkout() {
		return 'cart' === self::get_notice_display_mode();
	}

	/**
	 * Retourner le HTML d'affichage via shortcode.
	 *
	 * @param array $atts Attributs du shortcode.
	 * @return string
	 */
	public static function render_notice_shortcode( $atts = array() ) {
		$atts    = shortcode_atts( array( 'context' => 'auto' ), $atts, 'rewardly_loyalty_notice' );
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

		if ( 'checkout' === $context ) {
			return self::get_checkout_notice_html();
		}

		return self::get_cart_notice_html();
	}

	/**
	 * Gérer l’application ou le retrait manuel des points.
	 *
	 * @return void
	 */
	public static function handle_form_requests() {
		if ( ! is_user_logged_in() || ! Rewardly_Loyalty_Helpers::is_enabled() ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		/* Application manuelle via formulaire POST. */
		if (
			'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' )
			&& isset( $_POST['rewardly_loyalty_action'] )
			&& 'apply' === sanitize_text_field( wp_unslash( $_POST['rewardly_loyalty_action'] ) )
		) {
			$nonce = isset( $_POST['_rewardly_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_rewardly_nonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'rewardly_loyalty_apply' ) ) {
				return;
			}

			self::handle_apply_request();
			return;
		}

		/* Retrait via formulaire POST pour éviter une mutation d'état via GET. */
		if (
			'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' )
			&& isset( $_POST['rewardly_loyalty_action'] )
			&& 'remove' === sanitize_text_field( wp_unslash( $_POST['rewardly_loyalty_action'] ) )
		) {
			$nonce = isset( $_POST['_rewardly_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_rewardly_nonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'rewardly_loyalty_remove' ) && ! wp_verify_nonce( $nonce, 'rewardly_loyalty_apply' ) ) {
				return;
			}

			self::clear_usage_session();
			wc_add_notice( __( 'Loyalty points were removed from this order.', 'rewardly-loyalty' ), 'success' );

			WC()->cart->calculate_totals();
			wp_safe_redirect( self::get_current_page_url() );
			exit;
		}
	}

	/**
	 * Traiter la demande d’application manuelle des points.
	 *
	 * @return void
	 */
	private static function handle_apply_request() {
		$user_id     = get_current_user_id();
		$settings    = Rewardly_Loyalty_Helpers::get_settings();
		$user_points = Rewardly_Loyalty_Helpers::get_user_points( $user_id );
		$min_points  = isset( $settings['min_points_to_redeem'] ) ? (int) $settings['min_points_to_redeem'] : 0;
		$max_usable  = self::get_max_redeemable_points_for_cart( $user_id, WC()->cart );
		$raw_value   = isset( $_POST['rewardly_points_to_use'] ) ? wp_unslash( $_POST['rewardly_points_to_use'] ) : '';

		if ( $user_points < $min_points ) {
			wc_add_notice( __( 'You cannot use your loyalty points yet.', 'rewardly-loyalty' ), 'error' );
			wp_safe_redirect( self::get_current_page_url() );
			exit;
		}

		$validation = self::validate_requested_points( $raw_value, $user_points, $max_usable );

		if ( is_wp_error( $validation ) ) {
			wc_add_notice( $validation->get_error_message(), 'error' );
			wp_safe_redirect( self::get_current_page_url() );
			exit;
		}

		$requested_points = (int) $validation;

		WC()->session->set( 'rewardly_use_points', 'yes' );
		WC()->session->set( 'rewardly_requested_points', $requested_points );
		WC()->session->set( 'rewardly_applied_points', 0 );
		WC()->session->set( 'rewardly_loyalty_discount_amount', 0 );

		WC()->cart->calculate_totals();

		wc_add_notice( __( 'Loyalty points were applied to your order.', 'rewardly-loyalty' ), 'success' );

		wp_safe_redirect( self::get_current_page_url() );
		exit;
	}

	/**
	 * Valider le nombre de points saisi par le client.
	 *
	 * @param mixed $raw_value Valeur brute saisie.
	 * @param int   $user_points Solde du client.
	 * @param int   $max_usable Maximum utilisable sur la commande.
	 * @return int|WP_Error
	 */
	private static function validate_requested_points( $raw_value, $user_points, $max_usable ) {
		$value = is_scalar( $raw_value ) ? trim( (string) $raw_value ) : '';

		if ( '' === $value || ! preg_match( '/^\d+$/', $value ) ) {
			return new WP_Error( 'rewardly_invalid_points', __( 'Please enter a valid number of points.', 'rewardly-loyalty' ) );
		}

		$requested_points = (int) $value;

		if ( $requested_points <= 0 ) {
			return new WP_Error( 'rewardly_invalid_points', __( 'Please enter a valid number of points.', 'rewardly-loyalty' ) );
		}

		if ( $requested_points > (int) $user_points ) {
			return new WP_Error( 'rewardly_points_exceed_balance', __( 'You cannot use more points than your available balance.', 'rewardly-loyalty' ) );
		}

		if ( $requested_points > (int) $max_usable ) {
			return new WP_Error( 'rewardly_points_exceed_order_max', __( 'The number of points entered exceeds the maximum allowed for this order.', 'rewardly-loyalty' ) );
		}

		return $requested_points;
	}

	/**
	 * Calculer le maximum réel de points utilisables sur la commande.
	 *
	 * @param int          $user_id Identifiant utilisateur.
	 * @param WC_Cart|null $cart Panier courant.
	 * @return int
	 */
	private static function get_max_redeemable_points_for_cart( $user_id, $cart = null ) {
		if ( ! $cart instanceof WC_Cart ) {
			return 0;
		}

		$user_points = Rewardly_Loyalty_Helpers::get_user_points( $user_id );
		if ( $user_points <= 0 ) {
			return 0;
		}

		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$subtotal = (float) $cart->get_subtotal();

		if ( $subtotal <= 0 ) {
			return 0;
		}

		$max_discount_amount = $subtotal;

		if ( isset( $settings['max_discount_per_order'] ) && (float) $settings['max_discount_per_order'] > 0 ) {
			$max_discount_amount = min( $max_discount_amount, (float) $settings['max_discount_per_order'] );
		}

		if ( $max_discount_amount <= 0 ) {
			return 0;
		}

		$max_points_by_order = Rewardly_Loyalty_Helpers::convert_amount_to_points( $max_discount_amount );

		if ( $max_points_by_order <= 0 ) {
			return 0;
		}

		return min( (int) $user_points, (int) $max_points_by_order );
	}

	/**
	 * Vider les données de session liées à l’usage des points.
	 *
	 * @return void
	 */
	private static function clear_usage_session() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( 'rewardly_use_points', 'no' );
		WC()->session->__unset( 'rewardly_requested_points' );
		WC()->session->__unset( 'rewardly_applied_points' );
		WC()->session->__unset( 'rewardly_loyalty_discount_amount' );
	}

	/**
	 * Retourner l’URL de la page courante.
	 *
	 * @return string
	 */
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

	/**
	 * Générer le HTML de la notice panier.
	 *
	 * @return string
	 */
	private static function get_cart_notice_html() {
		if ( ! Rewardly_Loyalty_Helpers::is_enabled() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$subtotal = (float) WC()->cart->get_subtotal();
		if ( $subtotal <= 0 ) {
			return '';
		}

		$potential_points = Rewardly_Loyalty_Helpers::calculate_cart_earned_points( WC()->cart );
		if ( $potential_points <= 0 ) {
			return '';
		}

		ob_start();
		?>
		<div class="rewardly-cart-points-notice">
			<span class="rewardly-cart-points-notice__icon">🏆</span>
			<div class="rewardly-cart-points-notice__text">
				<?php esc_html_e( 'Complete your order and earn up to', 'rewardly-loyalty' ); ?>
				<strong><?php echo esc_html( $potential_points ); ?></strong> <?php esc_html_e( 'loyalty points!', 'rewardly-loyalty' ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Injecter la notice fidélité dans le contenu du panier.
	 *
	 * @param string $content Contenu de la page.
	 * @return string
	 */
	public static function inject_cart_notice_into_content( $content ) {
		return $content;
	}

	/**
	 * Afficher la notice simple au panier.
	 *
	 * @return void
	 */
	public static function render_cart_notice() {
		if ( ! self::should_display_auto_notice_in_cart() ) {
			return;
		}

		echo self::get_cart_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Afficher la notice simple au checkout.
	 *
	 * @return void
	 */
	public static function render_checkout_notice() {
		if ( ! self::should_display_auto_notice_in_checkout() ) {
			return;
		}

		echo self::get_checkout_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Afficher la carte fidélité au panier.
	 *
	 * @return void
	 */
	public static function render_cart_box() {
		if ( ! self::should_display_notice_in_cart() ) {
			return;
		}

		echo self::get_loyalty_card_html( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Afficher le bloc fidélité au checkout.
	 *
	 * @return void
	 */
	public static function render_checkout_box() {
		if ( ! self::should_display_notice_in_checkout() ) {
			return;
		}

		echo self::get_loyalty_card_html( 'checkout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Retourner le HTML complet de la carte fidélité.
	 *
	 * @param string $context Contexte d'affichage.
	 * @return string
	 */
	public static function get_loyalty_card_html( $context = 'checkout' ) {
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			return '';
		}

		if ( ! Rewardly_Loyalty_Helpers::is_enabled() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$subtotal = (float) WC()->cart->get_subtotal();
		if ( $subtotal <= 0 ) {
			return '';
		}

		$potential_points = Rewardly_Loyalty_Helpers::calculate_cart_earned_points( WC()->cart );
		if ( $potential_points <= 0 ) {
			return '';
		}

		ob_start();

		if ( is_user_logged_in() ) {
			self::render_loyalty_box();
		} else {
			self::render_guest_loyalty_card( $potential_points, $context );
		}

		return trim( ob_get_clean() );
	}

	/**
	 * Afficher la carte invité.
	 *
	 * @param int    $potential_points Points potentiels.
	 * @param string $context Contexte courant.
	 * @return void
	 */
	private static function render_guest_loyalty_card( $potential_points, $context = 'checkout' ) {
		$context = sanitize_key( $context );
		$context_label = 'cart' === $context ? __( 'your cart', 'rewardly-loyalty' ) : __( 'this order', 'rewardly-loyalty' );
		?>
		<div class="rewardly-checkout-guest-notice rewardly-checkout-guest-notice--<?php echo esc_attr( $context ); ?>">
			<span class="rewardly-checkout-guest-notice__icon">🏆</span>
			<div class="rewardly-checkout-guest-notice__text">
				<?php echo esc_html( sprintf( __( 'Log in to use loyalty points and earn up to %1$s points with %2$s.', 'rewardly-loyalty' ), (int) $potential_points, $context_label ) ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Afficher le bloc de fidélité pour un client connecté.
	 *
	 * @return void
	 */
	private static function render_loyalty_box() {
		if ( ! is_user_logged_in() || ! Rewardly_Loyalty_Helpers::is_enabled() || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		$user_id             = get_current_user_id();
		$points              = Rewardly_Loyalty_Helpers::get_user_points( $user_id );
		$settings            = Rewardly_Loyalty_Helpers::get_settings();
		$total_amount        = Rewardly_Loyalty_Helpers::convert_points_to_amount( $points );
		$is_active           = 'yes' === WC()->session->get( 'rewardly_use_points' );
		$subtotal            = (float) WC()->cart->get_subtotal();
		$potential_points    = $subtotal > 0 ? Rewardly_Loyalty_Helpers::calculate_cart_earned_points( WC()->cart ) : 0;
		$min_points          = isset( $settings['min_points_to_redeem'] ) ? (int) $settings['min_points_to_redeem'] : 0;
		$max_usable_points   = self::get_max_redeemable_points_for_cart( $user_id, WC()->cart );
		$max_usable_amount   = Rewardly_Loyalty_Helpers::convert_points_to_amount( $max_usable_points );
		$requested_points    = (int) WC()->session->get( 'rewardly_requested_points' );
		$applied_points      = (int) WC()->session->get( 'rewardly_applied_points' );
		$applied_discount    = (float) WC()->session->get( 'rewardly_loyalty_discount_amount' );
		$can_redeem          = $points >= $min_points && $max_usable_points > 0;
		$display_input_value = $requested_points > 0 ? $requested_points : min( $points, $max_usable_points );


		/* (FR) Version simple si le client ne peut pas encore utiliser ses points. */
		if ( ! $can_redeem && $min_points > 0 ) {
			?>
			<div class="rewardly-loyalty-box rewardly-loyalty-box--connected">
				<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--top">
					<span class="rewardly-loyalty-box__icon">🏆</span>
					<div class="rewardly-loyalty-box__top-text">
						<?php esc_html_e( 'This order can earn you', 'rewardly-loyalty' ); ?>
						<strong><?php echo esc_html( $potential_points ); ?> <?php esc_html_e( 'points', 'rewardly-loyalty' ); ?></strong>.
					</div>
				</div>

				<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--bottom">
					<div class="rewardly-loyalty-box__bottom-text">
						<?php esc_html_e( 'You currently have', 'rewardly-loyalty' ); ?> <strong><?php echo esc_html( $points ); ?> <?php esc_html_e( 'points', 'rewardly-loyalty' ); ?></strong>.
						<?php esc_html_e( 'You can start using your points once you reach', 'rewardly-loyalty' ); ?> <strong><?php echo esc_html( $min_points ); ?></strong> <?php esc_html_e( 'total points.', 'rewardly-loyalty' ); ?>
					</div>
				</div>
			</div>
			<?php
			return;
		}

		/* Desktop : bloc ouvert normal. */
		?>
		<div class="rewardly-loyalty-box rewardly-loyalty-box--connected rewardly-loyalty-box--desktop">
			<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--top">
				<span class="rewardly-loyalty-box__icon">🏆</span>
				<div class="rewardly-loyalty-box__top-text">
					<?php esc_html_e( 'This order can earn you', 'rewardly-loyalty' ); ?>
					<strong><?php echo esc_html( $potential_points ); ?> <?php esc_html_e( 'points', 'rewardly-loyalty' ); ?></strong>.
				</div>
			</div>

			<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--bottom">
				<?php
				self::render_loyalty_box_content(
					array(
						'points'              => $points,
						'total_amount'        => $total_amount,
						'is_active'           => $is_active,
						'max_usable_points'   => $max_usable_points,
						'max_usable_amount'   => $max_usable_amount,
						'applied_points'      => $applied_points,
						'applied_discount'    => $applied_discount,
						'display_input_value' => $display_input_value,
						'input_suffix'        => 'desktop',
					)
				);
				?>
			</div>
		</div>

		<?php
		/* Mobile : bloc repliable. */
		?>
		<details class="rewardly-loyalty-box rewardly-loyalty-box--connected rewardly-loyalty-box--mobile-collapsible">
			<summary class="rewardly-loyalty-box__summary">
				<span class="rewardly-loyalty-box__summary-main">
					<span class="rewardly-loyalty-box__icon">🏆</span>
					<span class="rewardly-loyalty-box__summary-text">
						<?php esc_html_e( 'This order can earn you', 'rewardly-loyalty' ); ?>
						<strong><?php echo esc_html( $potential_points ); ?> <?php esc_html_e( 'points', 'rewardly-loyalty' ); ?></strong>.
					</span>
				</span>

				<span class="rewardly-loyalty-box__summary-hint">
					<?php esc_html_e( 'Use My Points', 'rewardly-loyalty' ); ?>
				</span>
			</summary>

			<div class="rewardly-loyalty-box__mobile-content">
				<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--bottom">
					<?php
					self::render_loyalty_box_content(
						array(
							'points'              => $points,
							'total_amount'        => $total_amount,
							'is_active'           => $is_active,
							'max_usable_points'   => $max_usable_points,
							'max_usable_amount'   => $max_usable_amount,
							'applied_points'      => $applied_points,
							'applied_discount'    => $applied_discount,
							'display_input_value' => $display_input_value,
							'input_suffix'        => 'mobile',
						)
					);
					?>
				</div>
			</div>
		</details>
		<?php
	}

	/**
	 * Afficher le contenu interne du bloc fidélité.
	 *
	 * @param array $args Données d'affichage.
	 * @return void
	 */
	private static function render_loyalty_box_content( $args ) {
		$points              = isset( $args['points'] ) ? (int) $args['points'] : 0;
		$total_amount        = isset( $args['total_amount'] ) ? (float) $args['total_amount'] : 0;
		$is_active           = ! empty( $args['is_active'] );
		$max_usable_points   = isset( $args['max_usable_points'] ) ? (int) $args['max_usable_points'] : 0;
		$max_usable_amount   = isset( $args['max_usable_amount'] ) ? (float) $args['max_usable_amount'] : 0;
		$applied_points      = isset( $args['applied_points'] ) ? (int) $args['applied_points'] : 0;
		$applied_discount    = isset( $args['applied_discount'] ) ? (float) $args['applied_discount'] : 0;
		$display_input_value = isset( $args['display_input_value'] ) ? (int) $args['display_input_value'] : 0;
		$input_suffix        = isset( $args['input_suffix'] ) ? sanitize_key( $args['input_suffix'] ) : 'default';
		$input_id            = 'rewardly_points_to_use_' . $input_suffix;
		?>
		<div class="rewardly-loyalty-box__bottom-text">
			<?php esc_html_e( 'You have', 'rewardly-loyalty' ); ?> <strong><?php echo esc_html( $points ); ?> <?php esc_html_e( 'points', 'rewardly-loyalty' ); ?></strong>,
			<?php esc_html_e( 'which equals', 'rewardly-loyalty' ); ?> <strong><?php echo wp_kses_post( wc_price( $total_amount ) ); ?></strong> <?php esc_html_e( 'available discount.', 'rewardly-loyalty' ); ?>
			<br>
			<?php esc_html_e( 'You can use up to', 'rewardly-loyalty' ); ?> <strong><?php echo esc_html( $max_usable_points ); ?> <?php esc_html_e( 'points', 'rewardly-loyalty' ); ?></strong> <?php esc_html_e( 'on this order, which equals', 'rewardly-loyalty' ); ?>
			<strong><?php echo wp_kses_post( wc_price( $max_usable_amount ) ); ?></strong>.
			<?php if ( $is_active && $applied_points > 0 && $applied_discount > 0 ) : ?>
				<br>
				<?php esc_html_e( 'Currently applied points:', 'rewardly-loyalty' ); ?>
				<strong><?php echo esc_html( $applied_points ); ?></strong>
				(<?php echo wp_kses_post( wc_price( $applied_discount ) ); ?>).
			<?php endif; ?>
		</div>

		<div class="rewardly-loyalty-box__actions">
			<form class="rewardly-loyalty-box__form" method="post" action="<?php echo esc_url( self::get_current_page_url() ); ?>">
				<?php wp_nonce_field( 'rewardly_loyalty_apply', '_rewardly_nonce' ); ?>

				<label class="rewardly-loyalty-box__label" for="<?php echo esc_attr( $input_id ); ?>">
					<?php esc_html_e( 'Number of points to use', 'rewardly-loyalty' ); ?>
				</label>

				<input
					type="number"
					id="<?php echo esc_attr( $input_id ); ?>"
					name="rewardly_points_to_use"
					class="rewardly-loyalty-box__input"
					min="1"
					max="<?php echo esc_attr( $max_usable_points ); ?>"
					step="1"
					value="<?php echo esc_attr( $display_input_value ); ?>"
				>

				<div class="rewardly-loyalty-box__buttons">
					<button type="submit" name="rewardly_loyalty_action" value="apply" class="button rewardly-loyalty-btn rewardly-loyalty-btn--primary">
						<?php esc_html_e( 'Apply My Points', 'rewardly-loyalty' ); ?>
					</button>

					<?php if ( $is_active ) : ?>
						<button type="submit" name="rewardly_loyalty_action" value="remove" class="button rewardly-loyalty-btn">
							<?php esc_html_e( 'Remove My Points', 'rewardly-loyalty' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Appliquer la réduction fidélité au panier.
	 *
	 * @param WC_Cart $cart Panier courant.
	 * @return void
	 */
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
		WC()->session->set( 'rewardly_applied_points', 0 );

		if ( 'yes' !== WC()->session->get( 'rewardly_use_points' ) ) {
			return;
		}

		$user_id          = get_current_user_id();
		$user_points      = Rewardly_Loyalty_Helpers::get_user_points( $user_id );
		$settings         = Rewardly_Loyalty_Helpers::get_settings();
		$requested_points = (int) WC()->session->get( 'rewardly_requested_points' );
		$min_points       = isset( $settings['min_points_to_redeem'] ) ? (int) $settings['min_points_to_redeem'] : 0;

		if ( $user_points < $min_points ) {
			self::clear_usage_session();
			return;
		}

		if ( $requested_points <= 0 ) {
			self::clear_usage_session();
			return;
		}

		$max_usable_points = self::get_max_redeemable_points_for_cart( $user_id, $cart );

		if ( $requested_points > $user_points || $requested_points > $max_usable_points ) {
			self::clear_usage_session();
			return;
		}

		$amount = Rewardly_Loyalty_Helpers::convert_points_to_amount( $requested_points );

		if ( $amount <= 0 ) {
			self::clear_usage_session();
			return;
		}

		$subtotal = (float) $cart->get_subtotal();
		if ( $subtotal <= 0 ) {
			self::clear_usage_session();
			return;
		}

		$amount = min( $amount, $subtotal );

		if ( isset( $settings['max_discount_per_order'] ) && (float) $settings['max_discount_per_order'] > 0 ) {
			$amount = min( $amount, (float) $settings['max_discount_per_order'] );
		}

		if ( $amount <= 0 ) {
			self::clear_usage_session();
			return;
		}

		$cart->add_fee( __( 'Loyalty Discount', 'rewardly-loyalty' ), -$amount, false );

		WC()->session->set( 'rewardly_applied_points', $requested_points );
		WC()->session->set( 'rewardly_loyalty_discount_amount', $amount );
	}

	/**
	 * Sauvegarder l’usage des points sur la commande.
	 *
	 * @param WC_Order $order Objet commande.
	 * @param array    $data Données checkout.
	 * @return void
	 */
	public static function save_order_usage( $order, $data ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$discount        = (float) WC()->session->get( 'rewardly_loyalty_discount_amount' );
		$points_to_spend = (int) WC()->session->get( 'rewardly_applied_points' );

		if ( $discount <= 0 || $points_to_spend <= 0 || ! is_user_logged_in() ) {
			return;
		}

		$order->update_meta_data( '_rewardly_loyalty_points_spent', $points_to_spend );
		$order->update_meta_data( '_rewardly_loyalty_discount_amount', $discount );
	}


	/**
	 * Sauvegarder l’usage des points pour le checkout Blocks / Store API.
	 *
	 * @param WC_Order $order Objet commande.
	 * @return void
	 */
	public static function save_order_usage_store_api( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		self::save_order_usage( $order, array() );
	}

	/**
	 * Consommer les points après la création de la commande.
	 *
	 * @param int      $order_id Identifiant commande.
	 * @param mixed    $posted_data Données soumises.
	 * @param WC_Order $order Objet commande.
	 * @return void
	 */
	public static function consume_points_after_order( $order_id, $posted_data, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_rewardly_loyalty_spent_processed' ) ) {
			return;
		}

		$points_to_spend = (int) $order->get_meta( '_rewardly_loyalty_points_spent' );
		if ( $points_to_spend <= 0 ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$actual_spent = Rewardly_Loyalty_Helpers::subtract_points_exact(
			$user_id,
			$points_to_spend,
			$order_id,
			'spend',
			__( 'Points used to get a discount on the order.', 'rewardly-loyalty' )
		);

		if ( $actual_spent !== $points_to_spend ) {
			/* (FR) Signaler l'anomalie sans marquer la dépense comme traitée. */
			$order->update_meta_data( '_rewardly_loyalty_spent_processed', 'failed' );
			$order->update_meta_data( '_rewardly_loyalty_spent_failed_points', $points_to_spend );
			$order->add_order_note( __( 'Rewardly: an issue was detected while debiting loyalty points. Manual verification is required.', 'rewardly-loyalty' ) );
			$order->save();

			if ( function_exists( 'WC' ) && WC()->session ) {
				self::clear_usage_session();
			}

			return;
		}

		$order->update_meta_data( '_rewardly_loyalty_spent_processed', 'yes' );
		$order->delete_meta_data( '_rewardly_loyalty_spent_failed_points' );
		$order->save();

		if ( function_exists( 'WC' ) && WC()->session ) {
			self::clear_usage_session();
		}
	}


	/**
	 * Consommer les points après la création d'une commande Blocks / Store API.
	 *
	 * @param WC_Order $order Objet commande.
	 * @return void
	 */
	public static function consume_points_after_order_store_api( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		self::consume_points_after_order( $order->get_id(), array(), $order );
	}

	/**
	 * Déterminer s'il faut charger le script de compatibilité Blocks.
	 *
	 * @return bool
	 */
	public static function should_enqueue_blocks_script() {
		if ( is_admin() || ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
			return false;
		}

		if ( ! is_cart() && ! is_checkout() ) {
			return false;
		}

		if ( is_cart() && ! self::should_display_notice_in_cart() && ! self::should_display_auto_notice_in_cart() ) {
			return false;
		}

		if ( is_checkout() && ! self::should_display_notice_in_checkout() && ! self::should_display_auto_notice_in_checkout() ) {
			return false;
		}

		return self::is_cart_or_checkout_blocks_page();
	}

	/**
	 * Vérifier si la page panier/commande utilise les blocs WooCommerce.
	 *
	 * @return bool
	 */
	private static function is_cart_or_checkout_blocks_page() {
		$page_id = 0;

		if ( function_exists( 'is_cart' ) && is_cart() && function_exists( 'wc_get_page_id' ) ) {
			$page_id = (int) wc_get_page_id( 'cart' );
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && function_exists( 'wc_get_page_id' ) ) {
			$page_id = (int) wc_get_page_id( 'checkout' );
		}

		if ( $page_id <= 0 ) {
			return false;
		}

		$post = get_post( $page_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$content = (string) $post->post_content;

		return has_block( 'woocommerce/cart', $content ) || has_block( 'woocommerce/checkout', $content );
	}

	/**
	 * Retourner le HTML de la notice panier pour les blocs.
	 *
	 * @return string
	 */
	public static function get_block_cart_notice_html() {
		if ( self::should_display_notice_in_cart() ) {
			return self::get_loyalty_card_html( 'cart' );
		}

		if ( self::should_display_auto_notice_in_cart() ) {
			return self::get_cart_notice_html();
		}

		return '';
	}

	/**
	 * Retourner le HTML de la carte fidélité checkout pour les blocs.
	 *
	 * @return string
	 */
	public static function get_block_checkout_box_html() {
		if ( self::should_display_notice_in_checkout() ) {
			return self::get_loyalty_card_html( 'checkout' );
		}

		if ( self::should_display_auto_notice_in_checkout() ) {
			return self::get_checkout_notice_html();
		}

		return '';
	}

	/**
	 * Retourner la notice informative de checkout.
	 *
	 * @return string
	 */
	public static function get_checkout_notice_html() {
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			return '';
		}
		if ( ! Rewardly_Loyalty_Helpers::is_enabled() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		$subtotal = (float) WC()->cart->get_subtotal();
		if ( $subtotal <= 0 ) {
			return '';
		}
		$potential_points = Rewardly_Loyalty_Helpers::calculate_cart_earned_points( WC()->cart );
		if ( $potential_points <= 0 ) {
			return '';
		}
		return '<div class="rewardly-checkout-guest-notice rewardly-notice-only"><span class="rewardly-checkout-guest-notice__icon">🏆</span><div class="rewardly-checkout-guest-notice__text">' . esc_html__( 'This order can earn you up to', 'rewardly-loyalty' ) . ' <strong>' . esc_html( $potential_points ) . '</strong> ' . esc_html__( 'loyalty points.', 'rewardly-loyalty' ) . '</div></div>';
	}

}
