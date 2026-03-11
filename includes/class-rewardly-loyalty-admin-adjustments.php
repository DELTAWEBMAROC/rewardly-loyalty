<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Admin_Adjustments {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_rewardly_loyalty_adjust_points', array( __CLASS__, 'handle_form' ) );
	}

	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			'Ajustements fidélité',
			'Ajustements points',
			'manage_woocommerce',
			'rewardly-loyalty-adjustments',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Accès refusé.' );
		}

		$current_user = null;
		$query        = isset( $_GET['rewardly_user'] ) ? sanitize_text_field( wp_unslash( $_GET['rewardly_user'] ) ) : '';

		if ( '' !== $query ) {
			if ( is_numeric( $query ) ) {
				$current_user = get_user_by( 'id', (int) $query );
			}

			if ( ! $current_user ) {
				$current_user = get_user_by( 'email', $query );
			}

			if ( ! $current_user ) {
				$current_user = get_user_by( 'login', $query );
			}
		}
		?>
		<div class="wrap">
			<h1>Ajustements manuels des points</h1>

			<form method="get" style="margin:16px 0;">
				<input type="hidden" name="page" value="rewardly-loyalty-adjustments">
				<input type="text" name="rewardly_user" value="<?php echo esc_attr( $query ); ?>" placeholder="ID, email ou login utilisateur" style="min-width:320px;">
				<?php submit_button( 'Rechercher', 'secondary', '', false ); ?>
			</form>

			<?php if ( $current_user ) : ?>
				<?php $balance = Rewardly_Loyalty_Helpers::get_user_points( $current_user->ID ); ?>
				<div style="background:#fff;padding:16px;border:1px solid #ddd;border-radius:8px;max-width:760px;">
					<p><strong>Utilisateur :</strong> <?php echo esc_html( $current_user->display_name ); ?> (<?php echo esc_html( $current_user->user_email ); ?>)</p>
					<p><strong>Solde actuel :</strong> <?php echo esc_html( $balance ); ?> points</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'rewardly_loyalty_adjust_points' ); ?>
						<input type="hidden" name="action" value="rewardly_loyalty_adjust_points">
						<input type="hidden" name="user_id" value="<?php echo esc_attr( $current_user->ID ); ?>">

						<table class="form-table">
							<tr>
								<th scope="row">Action</th>
								<td>
									<select name="adjustment_action">
										<option value="add">Ajouter des points</option>
										<option value="remove">Retirer des points</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">Points</th>
								<td>
									<input type="number" min="1" name="points" required>
								</td>
							</tr>
							<tr>
								<th scope="row">Note</th>
								<td>
									<textarea name="note" rows="3" cols="50" placeholder="Ex : geste commercial, correction manuelle..."></textarea>
								</td>
							</tr>
						</table>

						<?php submit_button( 'Enregistrer l’ajustement' ); ?>
					</form>
				</div>
			<?php elseif ( '' !== $query ) : ?>
				<div class="notice notice-warning"><p>Aucun utilisateur trouvé.</p></div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_form() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Accès refusé.' );
		}

		check_admin_referer( 'rewardly_loyalty_adjust_points' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$action  = isset( $_POST['adjustment_action'] ) ? sanitize_text_field( wp_unslash( $_POST['adjustment_action'] ) ) : '';
		$points  = isset( $_POST['points'] ) ? absint( $_POST['points'] ) : 0;
		$note    = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( $user_id <= 0 || $points <= 0 || ! in_array( $action, array( 'add', 'remove' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rewardly-loyalty-adjustments&rewardly_user=' . $user_id ) );
			exit;
		}

		if ( 'add' === $action ) {
			$final_note = $note ? $note : 'Ajustement manuel du solde de fidélité.';
			Rewardly_Loyalty_Helpers::add_points( $user_id, $points, 0, 'adjust_add', $final_note );
		} else {
			$final_note = $note ? $note : 'Retrait manuel du solde de fidélité.';
			Rewardly_Loyalty_Helpers::subtract_points( $user_id, $points, 0, 'adjust_remove', $final_note );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rewardly-loyalty-adjustments&rewardly_user=' . $user_id ) );
		exit;
	}
}