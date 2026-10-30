<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Emails {

	public static function init() {
		// Réservé pour extensions futures.
	}

	public static function maybe_send_points_notification( $user_id, $type, $points, $amount, $note, $identity = '' ) {
		if ( ! Rewardly_Loyalty_Helpers::email_notifications_enabled() ) {
			return true;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return false;
		}

		$subject = self::get_subject( $type );
		$message = self::get_message( $user, $type, $points, $amount, $note );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if (preg_match('/^nodevora-event-[0-9]+$/D',$identity)) { $headers[] = 'X-Nodevora-Event: '.$identity; }
		return wp_mail( $user->user_email, $subject, $message, $headers );
	}

	private static function get_subject( $type ) {
		$subjects = array(
			'earn'          => __( 'You earned loyalty points', 'nodevora-customer-rewards-for-woocommerce' ),
			'spend'         => __( 'You used loyalty points', 'nodevora-customer-rewards-for-woocommerce' ),
			'revoke'        => __( 'Points were removed from your balance', 'nodevora-customer-rewards-for-woocommerce' ),
			'adjust'        => __( 'Your loyalty balance was adjusted', 'nodevora-customer-rewards-for-woocommerce' ),
			'adjust_add'    => __( 'Points were added to your balance', 'nodevora-customer-rewards-for-woocommerce' ),
			'adjust_remove' => __( 'Points were manually removed from your balance', 'nodevora-customer-rewards-for-woocommerce' ),
			'restore'       => __( 'Your points were restored', 'nodevora-customer-rewards-for-woocommerce' ),
			'expire'        => __( 'Some of your points expired', 'nodevora-customer-rewards-for-woocommerce' ),
		);

		return isset( $subjects[ $type ] ) ? $subjects[ $type ] : __( 'Update on your loyalty points', 'nodevora-customer-rewards-for-woocommerce' );
	}

	private static function get_message( $user, $type, $points, $amount, $note ) {
		$balance = Rewardly_Loyalty_Helpers::get_user_points( $user->ID );
		$title   = self::get_subject( $type );

		ob_start();
		?>
		<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#111;max-width:620px;">
			<h2 style="margin:0 0 14px;color:#111;"><?php echo esc_html( $title ); ?></h2>
			<p><?php
			/* translators: %s: customer display name. */
			printf( esc_html__( 'Hello %s,', 'nodevora-customer-rewards-for-woocommerce' ), esc_html( $user->display_name ?: $user->user_login ) );
			?></p>
			<p><?php echo esc_html( $note ); ?></p>
			<p><strong><?php esc_html_e( 'Points affected:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( $points ); ?><br>
			<strong><?php esc_html_e( 'Estimated value:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo wp_kses_post( wc_price( $amount ) ); ?><br>
			<strong><?php esc_html_e( 'Current balance:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( $balance ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
			<p><?php esc_html_e( 'Thank you for your trust.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
