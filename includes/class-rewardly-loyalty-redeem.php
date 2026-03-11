<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Redeem {

	public static function init() {
		add_action( 'wp', array( __CLASS__, 'handle_form_requests' ) );
		add_filter( 'the_content', array( __CLASS__, 'inject_cart_notice_into_content' ), 20 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_checkout_box' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_discount' ), 20, 1 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_order_usage' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'consume_points_after_order' ), 20, 3 );
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
			wc_add_notice( 'Les points de fidélité ont été retirés de cette commande.', 'success' );

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
			wc_add_notice( 'Vous ne pouvez pas encore utiliser vos points de fidélité.', 'error' );
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

		wc_add_notice( 'Les points de fidélité ont été appliqués à votre commande.', 'success' );

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
			return new WP_Error( 'rewardly_invalid_points', 'Veuillez saisir un nombre de points valide.' );
		}

		$requested_points = (int) $value;

		if ( $requested_points <= 0 ) {
			return new WP_Error( 'rewardly_invalid_points', 'Veuillez saisir un nombre de points valide.' );
		}

		if ( $requested_points > (int) $user_points ) {
			return new WP_Error( 'rewardly_points_exceed_balance', 'Vous ne pouvez pas utiliser plus de points que votre solde disponible.' );
		}

		if ( $requested_points > (int) $max_usable ) {
			return new WP_Error( 'rewardly_points_exceed_order_max', 'Le nombre de points saisi dépasse le maximum utilisable pour cette commande.' );
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

	/**
	 * Injecter la notice fidélité dans le contenu du panier.
	 *
	 * @param string $content Contenu de la page.
	 * @return string
	 */
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

	/**
	 * Afficher le bloc fidélité au checkout.
	 *
	 * @return void
	 */
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
		$potential_points    = $subtotal > 0 ? Rewardly_Loyalty_Helpers::calculate_earned_points( $subtotal ) : 0;
		$min_points          = isset( $settings['min_points_to_redeem'] ) ? (int) $settings['min_points_to_redeem'] : 0;
		$max_usable_points   = self::get_max_redeemable_points_for_cart( $user_id, WC()->cart );
		$max_usable_amount   = Rewardly_Loyalty_Helpers::convert_points_to_amount( $max_usable_points );
		$requested_points    = (int) WC()->session->get( 'rewardly_requested_points' );
		$applied_points      = (int) WC()->session->get( 'rewardly_applied_points' );
		$applied_discount    = (float) WC()->session->get( 'rewardly_loyalty_discount_amount' );
		$can_redeem          = $points >= $min_points && $max_usable_points > 0;
		$display_input_value = $requested_points > 0 ? $requested_points : min( $points, $max_usable_points );


		/* Version simple si le client ne peut pas encore utiliser ses points. */
		if ( ! $can_redeem && $min_points > 0 ) {
			?>
			<div class="rewardly-loyalty-box rewardly-loyalty-box--connected">
				<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--top">
					<span class="rewardly-loyalty-box__icon">🏆</span>
					<div class="rewardly-loyalty-box__top-text">
						Cette commande peut vous faire gagner
						<strong><?php echo esc_html( $potential_points ); ?> points</strong>.
					</div>
				</div>

				<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--bottom">
					<div class="rewardly-loyalty-box__bottom-text">
						Vous avez actuellement <strong><?php echo esc_html( $points ); ?> points</strong>.
						Vous pourrez utiliser vos points dès <strong><?php echo esc_html( $min_points ); ?></strong> points cumulés.
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
					Cette commande peut vous faire gagner
					<strong><?php echo esc_html( $potential_points ); ?> points</strong>.
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
						Cette commande peut vous faire gagner
						<strong><?php echo esc_html( $potential_points ); ?> points</strong>.
					</span>
				</span>

				<span class="rewardly-loyalty-box__summary-hint">
					Utiliser mes points
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
			Vous avez <strong><?php echo esc_html( $points ); ?> points</strong>,
			soit <strong><?php echo wp_kses_post( wc_price( $total_amount ) ); ?></strong> de réduction disponible.
			<br>
			Vous pouvez utiliser jusqu’à <strong><?php echo esc_html( $max_usable_points ); ?> points</strong> sur cette commande,
			soit <strong><?php echo wp_kses_post( wc_price( $max_usable_amount ) ); ?></strong>.
			<?php if ( $is_active && $applied_points > 0 && $applied_discount > 0 ) : ?>
				<br>
				Points actuellement appliqués :
				<strong><?php echo esc_html( $applied_points ); ?></strong>
				(<?php echo wp_kses_post( wc_price( $applied_discount ) ); ?>).
			<?php endif; ?>
		</div>

		<div class="rewardly-loyalty-box__actions">
			<form class="rewardly-loyalty-box__form" method="post" action="<?php echo esc_url( self::get_current_page_url() ); ?>">
				<?php wp_nonce_field( 'rewardly_loyalty_apply', '_rewardly_nonce' ); ?>

				<label class="rewardly-loyalty-box__label" for="<?php echo esc_attr( $input_id ); ?>">
					Nombre de points à utiliser
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
						Appliquer mes points
					</button>

					<?php if ( $is_active ) : ?>
						<button type="submit" name="rewardly_loyalty_action" value="remove" class="button rewardly-loyalty-btn">
							Retirer mes points
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

		$cart->add_fee( 'Réduction fidélité', -$amount, false );

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
			'Points utilisés pour obtenir une réduction sur la commande.'
		);

		if ( $actual_spent !== $points_to_spend ) {
			/* (FR) Signaler l'anomalie sans marquer la dépense comme traitée. */
			$order->update_meta_data( '_rewardly_loyalty_spent_processed', 'failed' );
			$order->update_meta_data( '_rewardly_loyalty_spent_failed_points', $points_to_spend );
			$order->add_order_note( 'Rewardly : anomalie détectée lors du débit des points de fidélité. Vérification manuelle requise.' );
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
}