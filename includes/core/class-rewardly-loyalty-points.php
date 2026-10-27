<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Points {

	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_reward_points' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_reward_points' ) );

		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'maybe_handle_order_reversal' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'maybe_handle_order_reversal' ) );

		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_admin_order_info' ) );

		add_action( 'user_register', array( __CLASS__, 'maybe_award_registration_bonus' ) );
		add_action( 'comment_post', array( __CLASS__, 'maybe_award_review_bonus' ), 20, 3 );
	}

	public static function maybe_reward_points( $order_id ) {
		// Ne jamais reprendre les écritures historiques avant une bascule vérifiée.
		if(Nodevora_Financial_Compatibility::selected()){return Nodevora_Financial_Lifecycle::earn($order_id);}
		$order=wc_get_order($order_id);if($order){$order->update_meta_data('_nodevora_earning_pending','yes');$order->save();}
	}

	/**
	 * Accorder le bonus d'inscription.
	 *
	 * @param int $user_id ID utilisateur.
	 * @return void
	 */
	public static function maybe_award_registration_bonus( $user_id ) {
		// Ne jamais reprendre les écritures historiques avant une bascule vérifiée.
		if(Nodevora_Financial_Compatibility::selected()){return Nodevora_Financial_Lifecycle::registration($user_id);}
		update_user_meta($user_id,'_nodevora_registration_pending','yes');
	}

	/**
	 * Accorder le bonus première commande.
	 *
	 * @param WC_Order $order Commande.
	 * @return void
	 */
	/**
	 * Retourner le bonus première commande potentiel pour une commande donnée.
	 *
	 * @param WC_Order $order Commande.
	 * @return int
	 */
	public static function get_potential_first_order_bonus( $order ) {
		if ( ! $order || ! class_exists( 'Rewardly_Loyalty_Pro' ) || ! Rewardly_Loyalty_Pro::can_use_feature( 'extra_points' ) ) {
			return 0;
		}

		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$points   = max( 0, absint( $settings['pro_points_first_order'] ?? 0 ) );
		$user_id  = $order->get_user_id();

		if ( $points <= 0 || ! $user_id ) {
			return 0;
		}

		if(Nodevora_Financial_Compatibility::selected()&&Nodevora_Financial_Lifecycle::event('user:'.$user_id.':first_order:v1')){return 0;}
		if ( 'yes' === get_user_meta( $user_id, '_rewardly_bonus_first_order_awarded', true ) ) {
			return 0;
		}

		return $points;
	}

	private static function maybe_award_first_order_bonus( $order ) {
		throw new Nodevora_Financial_Error('Bonus claims require the financial lifecycle service.');
	}

	/**
	 * Accorder le bonus review approuvée.
	 *
	 * @param int        $comment_id        ID commentaire.
	 * @param int|string $comment_approved  Statut.
	 * @param array      $commentdata       Données.
	 * @return void
	 */
	public static function maybe_award_review_bonus( $comment_id, $comment_approved, $commentdata ) {
		// Ne jamais reprendre les écritures historiques avant une bascule vérifiée.
		if(Nodevora_Financial_Compatibility::selected()){return Nodevora_Financial_Lifecycle::review($comment_id,$comment_approved,$commentdata);}
		update_comment_meta($comment_id,'_nodevora_review_pending','yes');
	}

	public static function maybe_handle_order_reversal( $order_id ) {
		// Ne jamais reprendre les écritures historiques avant une bascule vérifiée.
		if(Nodevora_Financial_Compatibility::selected()){return Nodevora_Financial_Lifecycle::reverse($order_id);}
		$order=wc_get_order($order_id);if($order){$order->update_meta_data('_nodevora_financial_recovery','setup_required');$order->save();}
	}

	public static function render_admin_order_info( $order ) {
		$earned         = (int) $order->get_meta( '_rewardly_loyalty_points_earned' );
		$spent          = (int) $order->get_meta( '_rewardly_loyalty_points_spent' );
		$discount       = (float) $order->get_meta( '_rewardly_loyalty_discount_amount' );
		$bonus_first    = (int) $order->get_meta( '_rewardly_bonus_first_order_points' );
		$revoked        = $order->get_meta( '_rewardly_loyalty_revoked' );
		$spent_restored = $order->get_meta( '_rewardly_loyalty_spent_restored' );
		?>
		<div class="order_data_column">
			<h4><?php esc_html_e( 'Nodevora Customer Rewards', 'nodevora-customer-rewards-for-woocommerce' ); ?></h4>
			<p><strong><?php esc_html_e( 'Points earned:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( $earned ); ?></p>
			<p><strong><?php esc_html_e( 'Points redeemed:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( $spent ); ?></p>
			<p><strong><?php esc_html_e( 'Loyalty discount:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo wp_kses_post( wc_price( $discount ) ); ?></p>
			<p><strong><?php esc_html_e( 'First order bonus:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( $bonus_first ); ?></p>
			<p><strong><?php esc_html_e( 'Earned points removed:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( 'yes' === $revoked ? __( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ) : __( 'No', 'nodevora-customer-rewards-for-woocommerce' ) ); ?></p>
			<p><strong><?php esc_html_e( 'Redeemed points restored:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( 'yes' === $spent_restored ? __( 'Yes', 'nodevora-customer-rewards-for-woocommerce' ) : __( 'No', 'nodevora-customer-rewards-for-woocommerce' ) ); ?></p>
		</div>
		<?php
	}
}
