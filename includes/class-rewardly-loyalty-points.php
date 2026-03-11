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
	}

	public static function maybe_reward_points( $order_id ) {
		if ( ! Rewardly_Loyalty_Helpers::is_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$status   = $order->get_status();
		if ( $status !== $settings['order_status_trigger'] ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_rewardly_loyalty_processed' ) ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		$subtotal = (float) $order->get_subtotal();
		$points   = Rewardly_Loyalty_Helpers::calculate_earned_points( $subtotal );

		if ( $points <= 0 ) {
			return;
		}

		Rewardly_Loyalty_Helpers::add_points(
			$user_id,
			$points,
			$order_id,
			'earn',
			'Points gagnés après validation de la commande.'
		);

		$order->update_meta_data( '_rewardly_loyalty_points_earned', $points );
		$order->update_meta_data( '_rewardly_loyalty_processed', 'yes' );
		$order->update_meta_data( '_rewardly_loyalty_revoked', 'no' );
		$order->update_meta_data( '_rewardly_loyalty_spent_restored', 'no' );
		$order->save();
	}

	public static function maybe_handle_order_reversal( $order_id ) {
		if ( ! Rewardly_Loyalty_Helpers::is_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		$status = $order->get_status();

		if ( 'yes' === $order->get_meta( '_rewardly_loyalty_processed' ) && 'yes' !== $order->get_meta( '_rewardly_loyalty_revoked' ) ) {
			$earned_points = (int) $order->get_meta( '_rewardly_loyalty_points_earned' );
			if ( $earned_points > 0 ) {
				$note           = 'cancelled' === $status ? 'Points retirés après annulation de la commande.' : 'Points retirés après remboursement de la commande.';
				$actual_revoked = Rewardly_Loyalty_Helpers::subtract_points_exact( $user_id, $earned_points, $order_id, 'revoke', $note );

				if ( $actual_revoked === $earned_points ) {
					$order->update_meta_data( '_rewardly_loyalty_revoked', 'yes' );
					$order->delete_meta_data( '_rewardly_loyalty_revoke_pending_points' );
				} else {
					/* (FR) Conserver la révocation en attente si le retrait exact échoue. */
					$order->update_meta_data( '_rewardly_loyalty_revoked', 'no' );
					$order->update_meta_data( '_rewardly_loyalty_revoke_pending_points', $earned_points );
					$order->add_order_note( 'Rewardly : révocation partielle bloquée car le solde actuel ne permet pas de retirer tous les points gagnés.' );
				}
			}
		}

		$spent_processed = $order->get_meta( '_rewardly_loyalty_spent_processed' );

		if ( 'yes' === $spent_processed && 'yes' !== $order->get_meta( '_rewardly_loyalty_spent_restored' ) ) {
			$spent_points = (int) $order->get_meta( '_rewardly_loyalty_points_spent' );
			if ( $spent_points > 0 ) {
				$note = 'cancelled' === $status ? 'Points recrédités après annulation de la commande.' : 'Points recrédités après remboursement de la commande.';
				Rewardly_Loyalty_Helpers::add_points( $user_id, $spent_points, $order_id, 'adjust', $note );
				$order->update_meta_data( '_rewardly_loyalty_spent_restored', 'yes' );
			}
		}

		$order->save();
	}

	public static function render_admin_order_info( $order ) {
		$earned         = (int) $order->get_meta( '_rewardly_loyalty_points_earned' );
		$spent          = (int) $order->get_meta( '_rewardly_loyalty_points_spent' );
		$discount       = (float) $order->get_meta( '_rewardly_loyalty_discount_amount' );
		$revoked        = $order->get_meta( '_rewardly_loyalty_revoked' );
		$spent_restored = $order->get_meta( '_rewardly_loyalty_spent_restored' );
		?>
		<div class="order_data_column">
			<h4>Fidélité Rewardly</h4>
			<p><strong>Points gagnés :</strong> <?php echo esc_html( $earned ); ?></p>
			<p><strong>Points utilisés :</strong> <?php echo esc_html( $spent ); ?></p>
			<p><strong>Réduction fidélité :</strong> <?php echo wp_kses_post( wc_price( $discount ) ); ?></p>
			<p><strong>Points gagnés retirés :</strong> <?php echo esc_html( 'yes' === $revoked ? 'Oui' : 'Non' ); ?></p>
			<p><strong>Points utilisés recrédités :</strong> <?php echo esc_html( 'yes' === $spent_restored ? 'Oui' : 'Non' ); ?></p>
		</div>
		<?php
	}
}
