<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Emails {

	public static function init() {
		// Réservé pour extensions futures.
	}

	public static function maybe_send_points_notification( $user_id, $type, $points, $amount, $note ) {
		if ( ! Rewardly_Loyalty_Helpers::email_notifications_enabled() ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return;
		}

		$subject = self::get_subject( $type );
		$message = self::get_message( $user, $type, $points, $amount, $note );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $user->user_email, $subject, $message, $headers );
	}

	
	private static function get_subject( $type ) {
		$subjects = array(
			'earn'          => 'Vous avez gagné des points de fidélité',
			'spend'         => 'Vous avez utilisé des points de fidélité',
			'revoke'        => 'Des points ont été retirés de votre solde',
			'adjust'        => 'Votre solde de fidélité a été ajusté',
			'adjust_add'    => 'Des points ont été ajoutés à votre solde',
			'adjust_remove' => 'Des points ont été retirés manuellement de votre solde',
			'restore'       => 'Vos points ont été recrédités',
			'expire'        => 'Une partie de vos points a expiré',
		);

		return isset( $subjects[ $type ] ) ? $subjects[ $type ] : 'Mise à jour de vos points de fidélité';
	}

	private static function get_message( $user, $type, $points, $amount, $note ) {
		$balance = Rewardly_Loyalty_Helpers::get_user_points( $user->ID );
		$title   = self::get_subject( $type );

		ob_start();
		?>
		<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#111;max-width:620px;">
			<h2 style="margin:0 0 14px;color:#111;"><?php echo esc_html( $title ); ?></h2>
			<p>Bonjour <?php echo esc_html( $user->display_name ?: $user->user_login ); ?>,</p>
			<p><?php echo esc_html( $note ); ?></p>
			<p><strong>Points concernés :</strong> <?php echo esc_html( $points ); ?><br>
			<strong>Valeur estimée :</strong> <?php echo wp_kses_post( wc_price( $amount ) ); ?><br>
			<strong>Solde actuel :</strong> <?php echo esc_html( $balance ); ?> points</p>
			<p>Merci pour votre confiance.</p>
		</div>
		<?php
		return ob_get_clean();
	}
}
