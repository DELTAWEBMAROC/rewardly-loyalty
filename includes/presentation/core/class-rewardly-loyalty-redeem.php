<?php
if (!defined("ABSPATH")) { exit; }
class Rewardly_Loyalty_Redeem {
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
				<?php esc_html_e( 'Complete your order and earn up to', 'nodevora-customer-rewards-for-woocommerce' ); ?>
				<strong><?php echo esc_html( $potential_points ); ?></strong> <?php esc_html_e( 'loyalty points!', 'nodevora-customer-rewards-for-woocommerce' ); ?>
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
		if ( $potential_points <= 0 && ! is_user_logged_in() ) {
			return '';
		}

		ob_start();

		if ( is_user_logged_in() ) {
			self::render_loyalty_box();
		} else {
			self::render_guest_loyalty_card( $potential_points, $context );
		}

		// Attribut purement visuel, partagé avec les cartes Blocks.
		$html=trim(ob_get_clean());$context='cart'===$context?'cart':'checkout';
		return preg_replace('/(class="rewardly-loyalty-box(?:[\s"]))/', 'data-nodevora-context="'.esc_attr($context).'" $1', $html);
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
		$context_label = 'cart' === $context ? __( 'your cart', 'nodevora-customer-rewards-for-woocommerce' ) : __( 'this order', 'nodevora-customer-rewards-for-woocommerce' );
		?>
		<div class="rewardly-checkout-guest-notice rewardly-checkout-guest-notice--<?php echo esc_attr( $context ); ?>">
			<span class="rewardly-checkout-guest-notice__icon">🏆</span>
			<div class="rewardly-checkout-guest-notice__text">
				<?php
				/* translators: 1: potential loyalty points, 2: cart or order context label. */
				echo esc_html( sprintf( __( 'Log in to use loyalty points and earn up to %1$s points with %2$s.', 'nodevora-customer-rewards-for-woocommerce' ), (int) $potential_points, $context_label ) );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Afficher le bloc de fidélité pour un client connecté.
	 *
	 * @return void
	 */
private static function render_remove_selection() {
		if ( Nodevora_Commerce::selected()<=0 ) { return; }
		echo '<form method="post" class="rewardly-loyalty-box" action="' . esc_url( self::get_current_page_url() ) . '">';
		wp_nonce_field( 'rewardly_loyalty_apply', '_rewardly_nonce' );
		echo '<button type="submit" class="rewardly-loyalty-btn" name="rewardly_loyalty_action" value="remove">' . esc_html__( 'Remove My Points', 'nodevora-customer-rewards-for-woocommerce' ) . '</button></form>';
	}
private static function render_loyalty_box() {
		if ( ! is_user_logged_in() || ! Rewardly_Loyalty_Helpers::is_enabled() || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		$user_id             = get_current_user_id();
		$points              = Rewardly_Loyalty_Helpers::get_user_points( $user_id );
		$settings            = Rewardly_Loyalty_Helpers::get_settings();
		$total_amount        = Rewardly_Loyalty_Helpers::convert_points_to_amount( $points );
		$is_active           = Nodevora_Commerce::selected()>0;
		$subtotal            = (float) WC()->cart->get_subtotal();
		$potential_points    = $subtotal > 0 ? Rewardly_Loyalty_Helpers::calculate_cart_earned_points( WC()->cart ) : 0;
		$min_points          = isset( $settings['min_points_to_redeem'] ) ? (int) $settings['min_points_to_redeem'] : 0;
		$max_usable_points   = self::get_max_redeemable_points_for_cart( $user_id, WC()->cart );
		$max_usable_amount   = Rewardly_Loyalty_Helpers::convert_points_to_amount( $max_usable_points );
		$requested_points    = (int) Nodevora_Commerce::selected();
		$applied_points      = (int) Nodevora_Commerce::selected();
		$applied_discount    = (float) Rewardly_Loyalty_Helpers::convert_points_to_amount(Nodevora_Commerce::selected());
		$can_redeem          = $points >= $min_points && $max_usable_points > 0;
		$display_input_value = $requested_points > 0 ? $requested_points : min( $points, $max_usable_points );


		/* (FR) Version simple si le client ne peut pas encore utiliser ses points. */
		if ( ! $can_redeem && $min_points > 0 ) {
			?>
			<div class="rewardly-loyalty-box rewardly-loyalty-box--connected">
				<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--top">
					<span class="rewardly-loyalty-box__icon">🏆</span>
					<div class="rewardly-loyalty-box__top-text">
						<?php esc_html_e( 'This order can earn you', 'nodevora-customer-rewards-for-woocommerce' ); ?>
						<strong><?php echo esc_html( $potential_points ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong>.
					</div>
				</div>

				<div class="rewardly-loyalty-box__row rewardly-loyalty-box__row--bottom">
					<div class="rewardly-loyalty-box__bottom-text">
						<?php esc_html_e( 'You currently have', 'nodevora-customer-rewards-for-woocommerce' ); ?> <strong><?php echo esc_html( $points ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong>.
						<?php esc_html_e( 'You can start using your points once you reach', 'nodevora-customer-rewards-for-woocommerce' ); ?> <strong><?php echo esc_html( $min_points ); ?></strong> <?php esc_html_e( 'total points.', 'nodevora-customer-rewards-for-woocommerce' ); ?>
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
					<?php esc_html_e( 'This order can earn you', 'nodevora-customer-rewards-for-woocommerce' ); ?>
					<strong><?php echo esc_html( $potential_points ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong>.
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
						<?php esc_html_e( 'This order can earn you', 'nodevora-customer-rewards-for-woocommerce' ); ?>
						<strong><?php echo esc_html( $potential_points ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong>.
					</span>
				</span>

				<span class="rewardly-loyalty-box__summary-hint">
					<?php esc_html_e( 'Use My Points', 'nodevora-customer-rewards-for-woocommerce' ); ?>
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
			<?php esc_html_e( 'You have', 'nodevora-customer-rewards-for-woocommerce' ); ?> <strong><?php echo esc_html( $points ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong>,
			<?php esc_html_e( 'which equals', 'nodevora-customer-rewards-for-woocommerce' ); ?> <strong><?php echo wp_kses_post( wc_price( $total_amount ) ); ?></strong> <?php esc_html_e( 'available discount.', 'nodevora-customer-rewards-for-woocommerce' ); ?>
			<br>
			<?php esc_html_e( 'You can use up to', 'nodevora-customer-rewards-for-woocommerce' ); ?> <strong><?php echo esc_html( $max_usable_points ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php esc_html_e( 'on this order, which equals', 'nodevora-customer-rewards-for-woocommerce' ); ?>
			<strong><?php echo wp_kses_post( wc_price( $max_usable_amount ) ); ?></strong>.
			<?php if ( $is_active && $applied_points > 0 && $applied_discount > 0 ) : ?>
				<br>
				<?php esc_html_e( 'Currently applied points:', 'nodevora-customer-rewards-for-woocommerce' ); ?>
				<strong><?php echo esc_html( $applied_points ); ?></strong>
				(<?php echo wp_kses_post( wc_price( $applied_discount ) ); ?>).
			<?php endif; ?>
		</div>

		<div class="rewardly-loyalty-box__actions">
			<form class="rewardly-loyalty-box__form" method="post" action="<?php echo esc_url( self::get_current_page_url() ); ?>">
				<?php wp_nonce_field( 'rewardly_loyalty_apply', '_rewardly_nonce' ); ?>

				<label class="rewardly-loyalty-box__label" for="<?php echo esc_attr( $input_id ); ?>">
					<?php esc_html_e( 'Number of points to use', 'nodevora-customer-rewards-for-woocommerce' ); ?>
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
						<?php esc_html_e( 'Apply My Points', 'nodevora-customer-rewards-for-woocommerce' ); ?>
					</button>

					<?php if ( $is_active ) : ?>
						<button type="submit" name="rewardly_loyalty_action" value="remove" class="button rewardly-loyalty-btn">
							<?php esc_html_e( 'Remove My Points', 'nodevora-customer-rewards-for-woocommerce' ); ?>
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
public static function get_max_redeemable_points_for_cart($user,$cart=null) { return Nodevora_Commerce::maximum(); }
private static function get_current_page_url() { return is_checkout()?wc_get_checkout_url():wc_get_cart_url(); }
}
