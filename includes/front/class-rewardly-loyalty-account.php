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

	public static function add_endpoint() {
		add_rewrite_endpoint( 'rewardly-points', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'loyalty-points', EP_ROOT | EP_PAGES );
	}

	public static function add_query_vars( $vars ) {
		$vars[] = 'rewardly-points';
		$vars[] = 'loyalty-points';
		return array_unique( $vars );
	}

	public static function add_menu_item( $items ) {
		$new_items = array();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( 'dashboard' === $key ) {
				$new_items['rewardly-points'] = __( 'My Points', 'rewardly-loyalty' );
			}
		}

		if ( ! isset( $new_items['rewardly-points'] ) ) {
			$new_items['rewardly-points'] = __( 'My Points', 'rewardly-loyalty' );
		}

		return $new_items;
	}

	private static function get_log_type_label( $type ) {
		$labels = array(
			'earn'               => __( 'Earned', 'rewardly-loyalty' ),
			'spend'              => __( 'Redeemed', 'rewardly-loyalty' ),
			'revoke'             => __( 'Cancelled / Refunded', 'rewardly-loyalty' ),
			'adjust'             => __( 'Adjustment', 'rewardly-loyalty' ),
			'adjust_add'         => __( 'Adjustment', 'rewardly-loyalty' ),
			'adjust_remove'      => __( 'Manual Removal', 'rewardly-loyalty' ),
			'expire'             => __( 'Expired', 'rewardly-loyalty' ),
			'bonus_registration' => __( 'Registration Bonus', 'rewardly-loyalty' ),
			'bonus_first_order'  => __( 'First Order Bonus', 'rewardly-loyalty' ),
			'bonus_review'       => __( 'Review Bonus', 'rewardly-loyalty' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( $type );
	}

	private static function get_history_toggle_url( $show_all ) {
		$base_url = wc_get_account_endpoint_url( 'rewardly-points' );

		if ( $show_all ) {
			return add_query_arg( 'history', 'all', $base_url );
		}

		return remove_query_arg( 'history', $base_url );
	}

	/**
	 * Retourner les données de message de points en attente pour le client courant.
	 *
	 * @param int $user_id ID utilisateur.
	 * @return array|null
	 */
	private static function get_pending_points_notice_data( $user_id ) {
		if ( ! class_exists( 'Rewardly_Loyalty_Pro' ) || ! Rewardly_Loyalty_Pro::can_use_feature( 'pending_points_notice' ) ) {
			return null;
		}

		$settings = Rewardly_Loyalty_Helpers::get_settings();
		if ( 'yes' !== ( $settings['pro_pending_points_notice_enabled'] ?? 'no' ) ) {
			return null;
		}

		$statuses = Rewardly_Loyalty_Helpers::sanitize_status_list( $settings['pro_pending_points_statuses'] ?? array() );
		$orders   = wc_get_orders( array(
			'customer_id' => $user_id,
			'status'      => $statuses,
			'limit'       => 5,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'return'      => 'objects',
		) );

		if ( empty( $orders ) ) {
			return null;
		}

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			if ( 'yes' === $order->get_meta( '_rewardly_loyalty_processed' ) ) {
				continue;
			}

			$points = Rewardly_Loyalty_Helpers::calculate_order_earned_points( $order );
			if ( class_exists( 'Rewardly_Loyalty_Points' ) ) {
				$points += max( 0, (int) Rewardly_Loyalty_Points::get_potential_first_order_bonus( $order ) );
			}

			if ( $points <= 0 ) {
				continue;
			}

			$product_summary = self::get_order_product_summary( $order );

			return array(
				'points'        => $points,
				'order_id'      => $order->get_id(),
				'order_number'  => $order->get_order_number(),
				'status'        => wc_get_order_status_name( $order->get_status() ),
				'total'         => (float) $order->get_total(),
				'product_name'  => $product_summary['name'],
				'product_url'   => $product_summary['url'],
				'product_link'  => $product_summary['link'],
				'order_link'    => sprintf( '<a href="%1$s">%2$s</a>', esc_url( wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) ) ), esc_html( sprintf( __( 'Order #%s', 'rewardly-loyalty' ), $order->get_order_number() ) ) ),
				'order_summary' => $product_summary['summary'],
			);
		}

		return null;
	}

	/**
	 * Retourner un résumé produit pour une commande.
	 *
	 * @param WC_Order $order Commande.
	 * @return array
	 */
	private static function get_order_product_summary( $order ) {
		$items = $order->get_items( 'line_item' );
		if ( empty( $items ) ) {
			return array(
				'name'    => sprintf( __( 'Order #%s', 'rewardly-loyalty' ), $order->get_order_number() ),
				'url'     => '',
				'link'    => sprintf( __( 'Order #%s', 'rewardly-loyalty' ), $order->get_order_number() ),
				'summary' => sprintf( __( 'Order #%s', 'rewardly-loyalty' ), $order->get_order_number() ),
			);
		}

		$first_item = reset( $items );
		$name       = $first_item ? $first_item->get_name() : sprintf( __( 'Order #%s', 'rewardly-loyalty' ), $order->get_order_number() );
		$product    = $first_item && is_callable( array( $first_item, 'get_product' ) ) ? $first_item->get_product() : null;
		$url        = $product instanceof WC_Product ? get_permalink( $product->get_id() ) : '';
		$item_count = count( $items );

		if ( $item_count > 1 ) {
			$summary = sprintf(
				/* translators: 1: first product name, 2: number of items */
				__( '%1$s and %2$d more item(s)', 'rewardly-loyalty' ),
				$name,
				$item_count - 1
			);
		} else {
			$summary = $name;
		}

		$link = $url ? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $summary ) ) : esc_html( $summary );

		return array(
			'name'    => $name,
			'url'     => $url,
			'link'    => $link,
			'summary' => $summary,
		);
	}

	/**
	 * Formater le message de points en attente.
	 *
	 * @param array $notice_data Données calculées.
	 * @return string
	 */
	private static function get_pending_points_notice_text( $notice_data ) {
		$settings = Rewardly_Loyalty_Helpers::get_settings();
		$template = (string) ( $settings['pro_pending_points_notice_text'] ?? '' );
		if ( '' === $template ) {
			$template = __( 'You will earn {points} point(s) from your order for {product_name} once {order_link} is completed.', 'rewardly-loyalty' );
		}

		$replacements = array(
			'{points}'        => (string) (int) $notice_data['points'],
			'{order_number}'  => (string) $notice_data['order_number'],
			'{status}'        => (string) $notice_data['status'],
			'{total}'         => wp_strip_all_tags( Rewardly_Loyalty_Helpers::format_amount( (float) $notice_data['total'] ) ),
			'{currency}'      => Rewardly_Loyalty_Helpers::get_store_currency_code(),
			'{product_name}'  => (string) ( $notice_data['product_name'] ?? '' ),
			'{product_link}'  => (string) ( $notice_data['product_link'] ?? '' ),
			'{order_link}'    => (string) ( $notice_data['order_link'] ?? '' ),
			'{order_summary}' => (string) ( $notice_data['order_summary'] ?? '' ),
		);

		return strtr( $template, $replacements );
	}

	/**
	 * Retourner le lien de commande lié à un log si accessible.
	 *
	 * @param object $log Log fidélité.
	 * @param int    $user_id ID utilisateur.
	 * @return array|null
	 */
	private static function get_log_order_link_data( $log, $user_id ) {
		if ( ! class_exists( 'Rewardly_Loyalty_Pro' ) || ! Rewardly_Loyalty_Pro::can_use_feature( 'enhanced_history_links' ) ) {
			return null;
		}

		$order_id = isset( $log->order_id ) ? absint( $log->order_id ) : 0;
		if ( $order_id <= 0 ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || (int) $order->get_user_id() !== (int) $user_id ) {
			return null;
		}

		$product_summary = self::get_order_product_summary( $order );

		return array(
			'label' => sprintf(
				/* translators: 1: product summary, 2: order number */
				__( '%1$s (Order #%2$s)', 'rewardly-loyalty' ),
				$product_summary['summary'],
				$order->get_order_number()
			),
			'url'   => wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) ),
		);
	}

	public static function render_endpoint() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id        = get_current_user_id();
		$points         = Rewardly_Loyalty_Helpers::get_user_points( $user_id );
		$amount         = Rewardly_Loyalty_Helpers::convert_points_to_amount( $points );
		$earned         = Rewardly_Loyalty_Helpers::get_total_earned( $user_id );
		$spent          = Rewardly_Loyalty_Helpers::get_total_spent( $user_id );
		$level_data     = Rewardly_Loyalty_Helpers::get_user_level_data( $user_id );
		$pending_notice = self::get_pending_points_notice_data( $user_id );
		$show_all       = isset( $_GET['history'] ) && 'all' === sanitize_text_field( wp_unslash( $_GET['history'] ) );
		$logs_limit     = $show_all ? 200 : 10;
		$logs           = Rewardly_Loyalty_Helpers::get_user_logs( $user_id, $logs_limit );
		$all_logs_count = count( Rewardly_Loyalty_Helpers::get_user_logs( $user_id, 1000 ) );
		?>
		<div class="rewardly-loyalty-account">
			<h3><?php esc_html_e( 'My Loyalty Points', 'rewardly-loyalty' ); ?></h3>

			<div class="rewardly-loyalty-account__summary">
				<div class="rewardly-loyalty-card">
					<span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Current Balance', 'rewardly-loyalty' ); ?></span>
					<strong class="rewardly-loyalty-card__value"><?php echo esc_html( $points ); ?> <?php esc_html_e( 'points', 'rewardly-loyalty' ); ?></strong>
				</div>

				<div class="rewardly-loyalty-card">
					<span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Available Value', 'rewardly-loyalty' ); ?></span>
					<strong class="rewardly-loyalty-card__value"><?php echo wp_kses_post( wc_price( $amount ) ); ?></strong>
				</div>

				<div class="rewardly-loyalty-card">
					<span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Points Earned', 'rewardly-loyalty' ); ?></span>
					<strong class="rewardly-loyalty-card__value"><?php echo esc_html( $earned ); ?></strong>
				</div>

				<div class="rewardly-loyalty-card">
					<span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Points Redeemed', 'rewardly-loyalty' ); ?></span>
					<strong class="rewardly-loyalty-card__value"><?php echo esc_html( $spent ); ?></strong>
				</div>
			</div>

			<?php if ( ! empty( $level_data['enabled'] ) ) : ?>
				<div class="rewardly-loyalty-card rewardly-loyalty-card--level" style="margin:0 0 18px;">
					<span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Current Level', 'rewardly-loyalty' ); ?></span>
					<strong class="rewardly-loyalty-card__value"><?php echo esc_html( $level_data['current_level']['label'] ); ?></strong>
					<div class="rewardly-level-progress" style="height:10px;background:#eceff3;border-radius:999px;overflow:hidden;margin-top:10px;">
						<div class="rewardly-level-progress__bar" style="width:<?php echo esc_attr( (int) $level_data['progress'] ); ?>%;height:100%;background:var(--rewardly-accent, #f5a623);"></div>
					</div>
					<?php if ( ! empty( $level_data['next_level'] ) ) : ?>
						<p style="margin-top:10px;"><?php echo esc_html( sprintf( __( 'You need %d more point(s) to reach %s.', 'rewardly-loyalty' ), (int) $level_data['remaining'], $level_data['next_level']['label'] ) ); ?></p>
					<?php else : ?>
						<p style="margin-top:10px;"><?php esc_html_e( 'You already reached the highest available level.', 'rewardly-loyalty' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $pending_notice ) ) : ?>
				<div class="rewardly-loyalty-card rewardly-loyalty-card--pending" style="margin:0 0 18px;">
					<span class="rewardly-loyalty-card__label"><?php esc_html_e( 'Pending Loyalty Reward', 'rewardly-loyalty' ); ?></span>
					<p style="margin:8px 0 0;"><?php echo wp_kses_post( self::get_pending_points_notice_text( $pending_notice ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $logs ) ) : ?>
				<div class="rewardly-loyalty-history-head">
					<h4><?php esc_html_e( 'Recent History', 'rewardly-loyalty' ); ?></h4>
				</div>

				<div class="rewardly-log-table-head" aria-hidden="true">
					<div><?php esc_html_e( 'Date', 'rewardly-loyalty' ); ?></div>
					<div><?php esc_html_e( 'Type', 'rewardly-loyalty' ); ?></div>
					<div><?php esc_html_e( 'Points', 'rewardly-loyalty' ); ?></div>
					<div><?php esc_html_e( 'Amount', 'rewardly-loyalty' ); ?></div>
					<div><?php esc_html_e( 'Note', 'rewardly-loyalty' ); ?></div>
				</div>

				<table class="shop_table shop_table_responsive my_account_orders">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'rewardly-loyalty' ); ?></th>
							<th><?php esc_html_e( 'Type', 'rewardly-loyalty' ); ?></th>
							<th><?php esc_html_e( 'Points', 'rewardly-loyalty' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'rewardly-loyalty' ); ?></th>
							<th><?php esc_html_e( 'Note', 'rewardly-loyalty' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<?php
							$minus_types   = array( 'spend', 'adjust_remove', 'expire' );
							$points_prefix = in_array( $log->type, $minus_types, true ) ? '-' : '+';
							$amount_prefix = in_array( $log->type, $minus_types, true ) ? '-' : '+';
							$type_label      = self::get_log_type_label( $log->type );
							$order_link_data = self::get_log_order_link_data( $log, $user_id );

							$row_class    = 'rewardly-log-row rewardly-log-row--' . sanitize_html_class( $log->type );
							$amount_class = 'rewardly-log-amount rewardly-log-amount--' . sanitize_html_class( $log->type );
							$type_class   = 'rewardly-log-type rewardly-log-type--' . sanitize_html_class( $log->type );
							?>
							<tr class="<?php echo esc_attr( $row_class ); ?>">
								<td data-label="<?php echo esc_attr__( 'Date', 'rewardly-loyalty' ); ?>"><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $log->created_at ) ) ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Type', 'rewardly-loyalty' ); ?>"><span class="<?php echo esc_attr( $type_class ); ?>"><?php echo esc_html( $type_label ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Points', 'rewardly-loyalty' ); ?>"><?php echo esc_html( $points_prefix . abs( (int) $log->points ) ); ?></td>
								<td data-label="<?php echo esc_attr__( 'Amount', 'rewardly-loyalty' ); ?>"><span class="rewardly-log-label"><?php esc_html_e( 'Amount:', 'rewardly-loyalty' ); ?> </span><span class="<?php echo esc_attr( $amount_class ); ?>"><?php echo esc_html( $amount_prefix ) . ' ' . wp_kses_post( wc_price( abs( (float) $log->amount_dh ) ) ); ?></span></td>
								<td data-label="<?php echo esc_attr__( 'Note', 'rewardly-loyalty' ); ?>">
									<div><?php echo esc_html( $log->note ); ?></div>
									<?php if ( ! empty( $order_link_data ) ) : ?>
										<div style="margin-top:6px;"><a href="<?php echo esc_url( $order_link_data['url'] ); ?>"><?php echo esc_html( $order_link_data['label'] ); ?></a></div>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $all_logs_count > 10 ) : ?>
					<div class="rewardly-loyalty-history-actions rewardly-loyalty-history-actions--bottom">
						<?php if ( $show_all ) : ?>
							<a class="rewardly-history-toggle" href="<?php echo esc_url( self::get_history_toggle_url( false ) ); ?>"><?php esc_html_e( 'Show Less', 'rewardly-loyalty' ); ?></a>
						<?php else : ?>
							<a class="rewardly-history-toggle" href="<?php echo esc_url( self::get_history_toggle_url( true ) ); ?>"><?php esc_html_e( 'Show More', 'rewardly-loyalty' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			<?php else : ?>
				<p><?php esc_html_e( 'No loyalty activity yet.', 'rewardly-loyalty' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
