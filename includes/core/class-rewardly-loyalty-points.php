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
		if(Nodevora_Financial_Compatibility::selected()){return Nodevora_Financial_Lifecycle::earn($order_id);}
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

		$points = Rewardly_Loyalty_Helpers::calculate_order_earned_points( $order );

		/* (FR) Toujours tenter le bonus de première commande même si les points de commande sont nuls. */
		if ( $points > 0 ) {
			Rewardly_Loyalty_Helpers::add_points(
				$user_id,
				$points,
				$order_id,
				'earn',
				__( 'Points earned after order validation.', 'nodevora-customer-rewards-for-woocommerce' )
			);

			$order->update_meta_data( '_rewardly_loyalty_points_earned', $points );
			$order->update_meta_data( '_rewardly_loyalty_revoked', 'no' );
			$order->update_meta_data( '_rewardly_loyalty_spent_restored', 'no' );
		} else {
			$order->update_meta_data( '_rewardly_loyalty_points_earned', 0 );
		}

		self::maybe_award_first_order_bonus( $order );

		/* (FR) Marquer la commande comme traitée pour éviter tout double crédit. */
		$order->update_meta_data( '_rewardly_loyalty_processed', 'yes' );
		if ( ! $order->meta_exists( '_rewardly_loyalty_revoked' ) ) {
			$order->update_meta_data( '_rewardly_loyalty_revoked', 'no' );
		}
		if ( ! $order->meta_exists( '_rewardly_loyalty_spent_restored' ) ) {
			$order->update_meta_data( '_rewardly_loyalty_spent_restored', 'no' );
		}

		$order->save();
	}

	/**
	 * Accorder le bonus d'inscription.
	 *
	 * @param int $user_id ID utilisateur.
	 * @return void
	 */
	public static function maybe_award_registration_bonus( $user_id ) {
		if(Nodevora_Financial_Compatibility::selected()){return Nodevora_Financial_Lifecycle::registration($user_id);}
		if ( ! class_exists( 'Rewardly_Loyalty_Pro' ) || ! Rewardly_Loyalty_Pro::can_use_feature( 'extra_points' ) ) {
			return;
		}

		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$points   = max( 0, absint( $settings['pro_points_registration'] ?? 0 ) );
		if ( $points <= 0 || 'yes' === get_user_meta( $user_id, '_rewardly_bonus_registration_awarded', true ) ) {
			return;
		}

		Rewardly_Loyalty_Helpers::add_points( $user_id, $points, 0, 'bonus_registration', __( 'Registration bonus.', 'nodevora-customer-rewards-for-woocommerce' ) );
		update_user_meta( $user_id, '_rewardly_bonus_registration_awarded', 'yes' );
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
		if ( ! class_exists( 'Rewardly_Loyalty_Pro' ) || ! Rewardly_Loyalty_Pro::can_use_feature( 'extra_points' ) ) {
			return;
		}

		$points  = self::get_potential_first_order_bonus( $order );
		$user_id = $order->get_user_id();
		if ( $points <= 0 || ! $user_id ) {
			return;
		}

		Rewardly_Loyalty_Helpers::add_points( $user_id, $points, $order->get_id(), 'bonus_first_order', __( 'First order bonus.', 'nodevora-customer-rewards-for-woocommerce' ) );
		update_user_meta( $user_id, '_rewardly_bonus_first_order_awarded', 'yes' );
		update_user_meta( $user_id, '_rewardly_bonus_first_order_order_id', (int) $order->get_id() );
		$order->update_meta_data( '_rewardly_bonus_first_order_points', $points );
		$order->update_meta_data( '_rewardly_bonus_first_order_revoked', 'no' );
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
		if(Nodevora_Financial_Compatibility::selected()){return Nodevora_Financial_Lifecycle::review($comment_id,$comment_approved,$commentdata);}
		if ( ! class_exists( 'Rewardly_Loyalty_Pro' ) || ! Rewardly_Loyalty_Pro::can_use_feature( 'extra_points' ) ) {
			return;
		}

		if ( 1 !== (int) $comment_approved ) {
			return;
		}

		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$points   = max( 0, absint( $settings['pro_points_review'] ?? 0 ) );
		if ( $points <= 0 ) {
			return;
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment || 'product' !== get_post_type( $comment->comment_post_ID ) ) {
			return;
		}

		$user_id = (int) $comment->user_id;
		if ( $user_id <= 0 || 'yes' === get_comment_meta( $comment_id, '_rewardly_bonus_review_awarded', true ) ) {
			return;
		}

		Rewardly_Loyalty_Helpers::add_points( $user_id, $points, 0, 'bonus_review', __( 'Approved product review bonus.', 'nodevora-customer-rewards-for-woocommerce' ) );
		update_comment_meta( $comment_id, '_rewardly_bonus_review_awarded', 'yes' );
	}

	public static function maybe_handle_order_reversal( $order_id ) {
		if(Nodevora_Financial_Compatibility::selected()){return Nodevora_Financial_Lifecycle::reverse($order_id);}
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
				$note           = 'cancelled' === $status ? __( 'Points removed after order cancellation.', 'nodevora-customer-rewards-for-woocommerce' ) : __( 'Points removed after order refund.', 'nodevora-customer-rewards-for-woocommerce' );
				$actual_revoked = Rewardly_Loyalty_Helpers::subtract_points_exact( $user_id, $earned_points, $order_id, 'revoke', $note );

				if ( $actual_revoked === $earned_points ) {
					$order->update_meta_data( '_rewardly_loyalty_revoked', 'yes' );
					$order->delete_meta_data( '_rewardly_loyalty_revoke_pending_points' );
				} else {
					/* (FR) Conserver la révocation en attente si le retrait exact échoue. */
					$order->update_meta_data( '_rewardly_loyalty_revoked', 'no' );
					$order->update_meta_data( '_rewardly_loyalty_revoke_pending_points', $earned_points );
					$order->add_order_note( __( 'Nodevora: partial reversal was blocked because the current balance does not allow all earned points to be removed.', 'nodevora-customer-rewards-for-woocommerce' ) );
				}
			}
		}

		/* (FR) Révoquer aussi le bonus de première commande si la commande n'est plus valide. */
		if ( 'yes' === $order->get_meta( '_rewardly_loyalty_processed' ) && 'yes' !== $order->get_meta( '_rewardly_bonus_first_order_revoked' ) ) {
			$bonus_first_points = (int) $order->get_meta( '_rewardly_bonus_first_order_points' );
			if ( $bonus_first_points > 0 ) {
				$note                = 'cancelled' === $status ? __( 'First order bonus removed after order cancellation.', 'nodevora-customer-rewards-for-woocommerce' ) : __( 'First order bonus removed after order refund.', 'nodevora-customer-rewards-for-woocommerce' );
				$actual_bonus_revoked = Rewardly_Loyalty_Helpers::subtract_points_exact( $user_id, $bonus_first_points, $order_id, 'revoke', $note );

				if ( $actual_bonus_revoked === $bonus_first_points ) {
					$order->update_meta_data( '_rewardly_bonus_first_order_revoked', 'yes' );
					$bonus_order_id = (int) get_user_meta( $user_id, '_rewardly_bonus_first_order_order_id', true );
					if ( $bonus_order_id === (int) $order_id ) {
						delete_user_meta( $user_id, '_rewardly_bonus_first_order_awarded' );
						delete_user_meta( $user_id, '_rewardly_bonus_first_order_order_id' );
					}
				} else {
					/* (FR) Conserver l'état en attente si le solde actuel ne permet pas un retrait exact. */
					$order->update_meta_data( '_rewardly_bonus_first_order_revoked', 'no' );
					$order->add_order_note( __( 'Nodevora: first order bonus reversal was blocked because the current balance does not allow all bonus points to be removed.', 'nodevora-customer-rewards-for-woocommerce' ) );
				}
			}
		}

		$spent_processed = $order->get_meta( '_rewardly_loyalty_spent_processed' );

		if ( 'yes' === $spent_processed && 'yes' !== $order->get_meta( '_rewardly_loyalty_spent_restored' ) ) {
			$spent_points = (int) $order->get_meta( '_rewardly_loyalty_points_spent' );
			if ( $spent_points > 0 ) {
				$note = 'cancelled' === $status ? __( 'Points restored after order cancellation.', 'nodevora-customer-rewards-for-woocommerce' ) : __( 'Points restored after order refund.', 'nodevora-customer-rewards-for-woocommerce' );
				Rewardly_Loyalty_Helpers::add_points( $user_id, $spent_points, $order_id, 'restore', $note );
				$order->update_meta_data( '_rewardly_loyalty_spent_restored', 'yes' );
			}
		}

		$order->save();
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
