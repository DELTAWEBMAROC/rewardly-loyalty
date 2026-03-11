<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			'Rewardly Loyalty Program',
			'Rewardly Loyalty',
			'manage_woocommerce',
			'rewardly-loyalty-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'rewardly_loyalty_group',
			'rewardly_loyalty_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => Rewardly_Loyalty_Helpers::get_settings(),
			)
		);
	}

	public static function sanitize_settings( $input ) {
		return array(
			'enabled'                => ( isset( $input['enabled'] ) && 'no' === $input['enabled'] ) ? 'no' : 'yes',
			'earn_points_per_dh'     => max( 1, absint( $input['earn_points_per_dh'] ?? 1 ) ),
			'redeem_points_per_dh'   => max( 1, absint( $input['redeem_points_per_dh'] ?? 20 ) ),
			'min_points_to_redeem'   => max( 0, absint( $input['min_points_to_redeem'] ?? 100 ) ),
			'max_discount_per_order' => max( 0, absint( $input['max_discount_per_order'] ?? 0 ) ),
			'order_status_trigger'   => in_array( ( $input['order_status_trigger'] ?? 'completed' ), array( 'completed', 'processing' ), true ) ? $input['order_status_trigger'] : 'completed',
			'points_expiration_days' => max( 0, absint( $input['points_expiration_days'] ?? 365 ) ),
			'email_notifications'    => ( isset( $input['email_notifications'] ) && 'no' === $input['email_notifications'] ) ? 'no' : 'yes',
		);
	}

	public static function render_page() {
		$settings = Rewardly_Loyalty_Helpers::get_settings();
		?>
		<div class="wrap">
			<h1>Rewardly Loyalty Program</h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'rewardly_loyalty_group' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">Activer le programme</th>
						<td>
							<select name="rewardly_loyalty_settings[enabled]">
								<option value="yes" <?php selected( $settings['enabled'], 'yes' ); ?>>Oui</option>
								<option value="no" <?php selected( $settings['enabled'], 'no' ); ?>>Non</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">1 DH = X point(s)</th>
						<td><input type="number" min="1" name="rewardly_loyalty_settings[earn_points_per_dh]" value="<?php echo esc_attr( $settings['earn_points_per_dh'] ); ?>"></td>
					</tr>

					<tr>
						<th scope="row">X point(s) = 1 DH</th>
						<td><input type="number" min="1" name="rewardly_loyalty_settings[redeem_points_per_dh]" value="<?php echo esc_attr( $settings['redeem_points_per_dh'] ); ?>"></td>
					</tr>

					<tr>
						<th scope="row">Minimum de points pour utilisation</th>
						<td><input type="number" min="0" name="rewardly_loyalty_settings[min_points_to_redeem]" value="<?php echo esc_attr( $settings['min_points_to_redeem'] ); ?>"></td>
					</tr>

					<tr>
						<th scope="row">Réduction maximale par commande (DH)</th>
						<td>
							<input type="number" min="0" step="1" name="rewardly_loyalty_settings[max_discount_per_order]" value="<?php echo esc_attr( $settings['max_discount_per_order'] ); ?>">
							<p class="description">Mettre 0 pour aucune limite.</p>
						</td>
					</tr>

					<tr>
						<th scope="row">Statut déclencheur</th>
						<td>
							<select name="rewardly_loyalty_settings[order_status_trigger]">
								<option value="completed" <?php selected( $settings['order_status_trigger'], 'completed' ); ?>>Terminée</option>
								<option value="processing" <?php selected( $settings['order_status_trigger'], 'processing' ); ?>>En cours</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">Expiration des points (jours)</th>
						<td>
							<input type="number" min="0" step="1" name="rewardly_loyalty_settings[points_expiration_days]" value="<?php echo esc_attr( $settings['points_expiration_days'] ); ?>">
							<p class="description">Mettre 0 pour désactiver l’expiration automatique.</p>
						</td>
					</tr>

					<tr>
						<th scope="row">Notifications e-mail</th>
						<td>
							<select name="rewardly_loyalty_settings[email_notifications]">
								<option value="yes" <?php selected( $settings['email_notifications'], 'yes' ); ?>>Oui</option>
								<option value="no" <?php selected( $settings['email_notifications'], 'no' ); ?>>Non</option>
							</select>
							<p class="description">Envoyer un e-mail lors des gains, utilisations, recrédits et expirations.</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
