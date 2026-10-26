<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Rewardly_Loyalty_Admin_Adjustments {
	public static function init() { add_action( 'admin_post_rewardly_loyalty_adjust_points', array( __CLASS__, 'handle_form' ) ); }

	public static function render_tab() {
		$current_user = null;
		$query        = filter_input( INPUT_GET, 'rewardly_user', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$query        = is_string( $query ) ? trim( wp_unslash( $query ) ) : '';

		if ( '' !== $query ) {
			if ( is_numeric( $query ) ) {
				$current_user = get_user_by( 'id', (int) $query );
			}
			if ( ! $current_user ) {
				$current_user = get_user_by( 'email', sanitize_email( $query ) );
			}
			if ( ! $current_user ) {
				$current_user = get_user_by( 'login', sanitize_user( $query, true ) );
			}
		}
		?><div class="rewardly-admin-card"><h2><?php esc_html_e( 'Manual points adjustments', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2><form method="get" style="margin:16px 0;"><input type="hidden" name="page" value="rewardly-loyalty-settings"><input type="hidden" name="tab" value="adjustments"><input type="text" name="rewardly_user" value="<?php echo esc_attr( $query ); ?>" placeholder="<?php echo esc_attr__( 'User ID, email or username', 'nodevora-customer-rewards-for-woocommerce' ); ?>" style="min-width:320px;"><?php submit_button( __( 'Search', 'nodevora-customer-rewards-for-woocommerce' ), 'secondary', '', false ); ?></form><?php if ( $current_user ) : $balance = Rewardly_Loyalty_Helpers::get_user_points( $current_user->ID ); ?><div style="background:#fff;padding:16px;border:1px solid #ddd;border-radius:8px;max-width:760px;"><p><strong><?php esc_html_e( 'User:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( $current_user->display_name ); ?> (<?php echo esc_html( $current_user->user_email ); ?>)</p><p><strong><?php esc_html_e( 'Current balance:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( $balance ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'rewardly_loyalty_adjust_points' ); ?><input type="hidden" name="action" value="rewardly_loyalty_adjust_points"><input type="hidden" name="user_id" value="<?php echo esc_attr( $current_user->ID ); ?>"><table class="form-table"><tr><th scope="row"><?php esc_html_e( 'Action', 'nodevora-customer-rewards-for-woocommerce' ); ?></th><td><select name="adjustment_action"><option value="add"><?php esc_html_e( 'Add points', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="remove"><?php esc_html_e( 'Remove points', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td></tr><tr><th scope="row"><?php esc_html_e( 'Points', 'nodevora-customer-rewards-for-woocommerce' ); ?></th><td><input type="number" min="1" name="points" required></td></tr><tr><th scope="row"><?php esc_html_e( 'Note', 'nodevora-customer-rewards-for-woocommerce' ); ?></th><td><textarea name="note" rows="3" cols="50"></textarea></td></tr></table><?php submit_button( __( 'Save adjustment', 'nodevora-customer-rewards-for-woocommerce' ) ); ?></form></div><?php elseif ( '' !== $query ) : ?><div class="notice notice-warning"><p><?php esc_html_e( 'No user found.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></div><?php endif; ?></div><?php
	}

	public static function handle_form() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage loyalty points.', 'nodevora-customer-rewards-for-woocommerce' ) );
		}

		check_admin_referer( 'rewardly_loyalty_adjust_points' );

		$user_id = absint( filter_input( INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT ) );
		$action  = filter_input( INPUT_POST, 'adjustment_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$points  = absint( filter_input( INPUT_POST, 'points', FILTER_SANITIZE_NUMBER_INT ) );
		$note    = filter_input( INPUT_POST, 'note', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$action = is_string( $action ) ? sanitize_key( wp_unslash( $action ) ) : '';
		$note   = is_string( $note ) ? sanitize_text_field( wp_unslash( $note ) ) : '';

		if ( $user_id <= 0 || $points <= 0 || ! in_array( $action, array( 'add', 'remove' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rewardly-loyalty-settings&tab=adjustments&rewardly_user=' . $user_id ) );
			exit;
		}

		if ( 'add' === $action ) {
			Rewardly_Loyalty_Helpers::add_points( $user_id, $points, 0, 'adjust_add', $note ? $note : __( 'Manual loyalty balance adjustment.', 'nodevora-customer-rewards-for-woocommerce' ) );
		} else {
			Rewardly_Loyalty_Helpers::subtract_points( $user_id, $points, 0, 'adjust_remove', $note ? $note : __( 'Manual loyalty balance removal.', 'nodevora-customer-rewards-for-woocommerce' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rewardly-loyalty-settings&tab=adjustments&rewardly_user=' . $user_id ) );
		exit;
	}
}
