<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rewardly_Loyalty_Account {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
		add_action( 'woocommerce_account_rewardly-points_endpoint', array( __CLASS__, 'render_endpoint' ) );
		add_action( 'woocommerce_account_loyalty-points_endpoint', array( __CLASS__, 'render_endpoint' ) );
	}

	/**
	 * Ajouter l’endpoint.
	 *
	 * @return void
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( 'rewardly-points', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'loyalty-points', EP_ROOT | EP_PAGES );
	}

	/**
	 * Déclarer la query var.
	 *
	 * @param array $vars Variables.
	 * @return array
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'rewardly-points';
		$vars[] = 'loyalty-points';
		return array_unique( $vars );
	}

	/**
	 * Ajouter le lien dans Mon Compte.
	 *
	 * @param array $items Menus.
	 * @return array
	 */
	public static function add_menu_item( $items ) {
		$new_items = array();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( 'dashboard' === $key ) {
				$new_items['rewardly-points'] = 'Mes points';
			}
		}

		if ( ! isset( $new_items['rewardly-points'] ) ) {
			$new_items['rewardly-points'] = 'Mes points';
		}

		return $new_items;
	}

	/**
	 * Retourner le libellé français du type de mouvement.
	 *
	 * @param string $type Type brut.
	 * @return string
	 */
	private static function get_log_type_label( $type ) {
		$labels = array(
			'earn'          => 'Gain',
			'spend'         => 'Utilisation',
			'revoke'        => 'Annulation / remboursement',
			'adjust'        => 'Recrédit / ajustement',
			'adjust_add'    => 'Recrédit / ajustement',
			'adjust_remove' => 'Retrait manuel',
			'expire'        => 'Expiration',
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( $type );
	}

	/**
	 * Construire l’URL de la page fidélité avec ou sans historique complet.
	 *
	 * @param bool $show_all Afficher tout ou non.
	 * @return string
	 */
	private static function get_history_toggle_url( $show_all ) {
		$base_url = wc_get_account_endpoint_url( 'rewardly-points' );

		if ( $show_all ) {
			return add_query_arg( 'history', 'all', $base_url );
		}

		return remove_query_arg( 'history', $base_url );
	}

	/**
	 * Afficher la page "Mes points".
	 *
	 * @return void
	 */
	public static function render_endpoint() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id        = get_current_user_id();
		$points         = Rewardly_Loyalty_Helpers::get_user_points( $user_id );
		$amount         = Rewardly_Loyalty_Helpers::convert_points_to_amount( $points );
		$earned         = Rewardly_Loyalty_Helpers::get_total_earned( $user_id );
		$spent          = Rewardly_Loyalty_Helpers::get_total_spent( $user_id );
		$show_all       = isset( $_GET['history'] ) && 'all' === sanitize_text_field( wp_unslash( $_GET['history'] ) );
		$logs_limit     = $show_all ? 200 : 10;
		$logs           = Rewardly_Loyalty_Helpers::get_user_logs( $user_id, $logs_limit );
		$all_logs_count = count( Rewardly_Loyalty_Helpers::get_user_logs( $user_id, 1000 ) );
		?>
		<div class="rewardly-loyalty-account">
			<h3>Mes points de fidélité</h3>

			<div class="rewardly-loyalty-account__summary">
				<div class="rewardly-loyalty-card">
					<span class="rewardly-loyalty-card__label">Solde actuel</span>
					<strong class="rewardly-loyalty-card__value"><?php echo esc_html( $points ); ?> points</strong>
				</div>

				<div class="rewardly-loyalty-card">
					<span class="rewardly-loyalty-card__label">Valeur disponible</span>
					<strong class="rewardly-loyalty-card__value"><?php echo wp_kses_post( wc_price( $amount ) ); ?></strong>
				</div>

				<div class="rewardly-loyalty-card">
					<span class="rewardly-loyalty-card__label">Points gagnés</span>
					<strong class="rewardly-loyalty-card__value"><?php echo esc_html( $earned ); ?></strong>
				</div>

				<div class="rewardly-loyalty-card">
					<span class="rewardly-loyalty-card__label">Points utilisés</span>
					<strong class="rewardly-loyalty-card__value"><?php echo esc_html( $spent ); ?></strong>
				</div>
			</div>

			<?php if ( ! empty( $logs ) ) : ?>
				<div class="rewardly-loyalty-history-head">
					<h4>Historique récent</h4>
				</div>

				<div class="rewardly-log-table-head" aria-hidden="true">
					<div>Date</div>
					<div>Type</div>
					<div>Points</div>
					<div>Montant</div>
					<div>Note</div>
				</div>

				<table class="shop_table shop_table_responsive my_account_orders">
					<thead>
						<tr>
							<th>Date</th>
							<th>Type</th>
							<th>Points</th>
							<th>Montant</th>
							<th>Note</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							$minus_types   = array( 'spend', 'revoke', 'adjust_remove', 'expire' );
							$points_prefix = in_array( $log->type, $minus_types, true ) ? '-' : '+';
							$amount_prefix = in_array( $log->type, $minus_types, true ) ? '-' : '+';
							$type_label    = self::get_log_type_label( $log->type );

							$row_class    = 'rewardly-log-row rewardly-log-row--' . sanitize_html_class( $log->type );
							$amount_class = 'rewardly-log-amount rewardly-log-amount--' . sanitize_html_class( $log->type );
							$type_class   = 'rewardly-log-type rewardly-log-type--' . sanitize_html_class( $log->type );
							?>
							<tr class="<?php echo esc_attr( $row_class ); ?>">
								<td data-label="Date"><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $log->created_at ) ) ); ?></td>
								<td data-label="Type">
									<span class="<?php echo esc_attr( $type_class ); ?>">
										<?php echo esc_html( $type_label ); ?>
									</span>
								</td>
								<td data-label="Points"><?php echo esc_html( $points_prefix . abs( (int) $log->points ) ); ?></td>
								<td data-label="Montant">
									<span class="rewardly-log-label">Montant : </span>
									<span class="<?php echo esc_attr( $amount_class ); ?>">
										<?php echo esc_html( $amount_prefix ) . ' ' . wp_kses_post( wc_price( abs( (float) $log->amount_dh ) ) ); ?>
									</span>
								</td>
								<td data-label="Note"><?php echo esc_html( $log->note ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $all_logs_count > 10 ) : ?>
					<div class="rewardly-loyalty-history-actions rewardly-loyalty-history-actions--bottom">
						<?php if ( $show_all ) : ?>
							<a class="rewardly-history-toggle" href="<?php echo esc_url( self::get_history_toggle_url( false ) ); ?>">
								Voir moins
							</a>
						<?php else : ?>
							<a class="rewardly-history-toggle" href="<?php echo esc_url( self::get_history_toggle_url( true ) ); ?>">
								Voir plus
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			<?php else : ?>
				<p>Aucun mouvement de fidélité pour le moment.</p>
			<?php endif; ?>
		</div>
		<?php
	}
}