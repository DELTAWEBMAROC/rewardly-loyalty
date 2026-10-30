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
		?><div class="rewardly-admin-card"><h2><?php esc_html_e( 'Manual points adjustments', 'nodevora-customer-rewards-for-woocommerce' ); ?></h2><form method="get" style="margin:16px 0;"><input type="hidden" name="page" value="rewardly-loyalty-settings"><input type="hidden" name="tab" value="adjustments"><label for="nodevora-adjustment-user"><?php esc_html_e( 'User ID, email or username', 'nodevora-customer-rewards-for-woocommerce' ); ?></label><input id="nodevora-adjustment-user" type="text" name="rewardly_user" value="<?php echo esc_attr( $query ); ?>" placeholder="<?php echo esc_attr__( 'User ID, email or username', 'nodevora-customer-rewards-for-woocommerce' ); ?>" style="display:block;width:100%;max-width:320px;min-width:0;"><?php submit_button( __( 'Search', 'nodevora-customer-rewards-for-woocommerce' ), 'secondary', '', false ); ?></form><?php if ( $current_user ) : $balance = Rewardly_Loyalty_Helpers::get_user_points( $current_user->ID ); ?><div style="background:#fff;padding:16px;border:1px solid #ddd;border-radius:8px;max-width:760px;min-width:0;box-sizing:border-box;overflow-wrap:anywhere;"><p><strong><?php esc_html_e( 'User:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( $current_user->display_name ); ?> (<?php echo esc_html( $current_user->user_email ); ?>)</p><p><strong><?php esc_html_e( 'Current balance:', 'nodevora-customer-rewards-for-woocommerce' ); ?></strong> <?php echo esc_html( $balance ); ?> <?php esc_html_e( 'points', 'nodevora-customer-rewards-for-woocommerce' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'rewardly_loyalty_adjust_points' ); ?><input type="hidden" name="nodevora_operation" value="<?php echo esc_attr(wp_generate_uuid4()); ?>"><?php ?><input type="hidden" name="action" value="rewardly_loyalty_adjust_points"><input type="hidden" name="user_id" value="<?php echo esc_attr( $current_user->ID ); ?>"><table class="form-table"><tr><th scope="row"><label for="nodevora-adjustment-adjustment_action"><?php esc_html_e( 'Action', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><select id="nodevora-adjustment-adjustment_action" name="adjustment_action"><option value="add"><?php esc_html_e( 'Add points', 'nodevora-customer-rewards-for-woocommerce' ); ?></option><option value="remove"><?php esc_html_e( 'Remove points', 'nodevora-customer-rewards-for-woocommerce' ); ?></option></select></td></tr><tr><th scope="row"><label for="nodevora-adjustment-points"><?php esc_html_e( 'Points', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><input type="number" min="1" id="nodevora-adjustment-points" name="points" required></td></tr><tr><th scope="row"><label for="nodevora-adjustment-note"><?php esc_html_e( 'Note', 'nodevora-customer-rewards-for-woocommerce' ); ?></label></th><td><textarea id="nodevora-adjustment-note" name="note" rows="3" cols="50"></textarea></td></tr></table><?php submit_button( __( 'Save adjustment', 'nodevora-customer-rewards-for-woocommerce' ) ); ?></form></div><?php elseif ( '' !== $query ) : ?><div class="notice notice-warning"><p><?php esc_html_e( 'No user found.', 'nodevora-customer-rewards-for-woocommerce' ); ?></p></div><?php endif; ?></div><?php
	}

	public static function handle_form() {
		if ( ! Nodevora_Access::can_edit() || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage loyalty points.', 'nodevora-customer-rewards-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) { wp_die( '', '', array( 'response' => 405 ) ); }
		check_admin_referer( 'rewardly_loyalty_adjust_points' );

		$raw_user = $_POST['user_id'] ?? ''; $raw_points = $_POST['points'] ?? '';
		$action = isset( $_POST['adjustment_action'] ) && is_string( $_POST['adjustment_action'] ) ? sanitize_key( wp_unslash( $_POST['adjustment_action'] ) ) : '';
		$note = isset( $_POST['note'] ) && is_string( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( ! is_scalar( $raw_user ) || ! is_scalar( $raw_points ) || ! preg_match( '/^[1-9][0-9]{0,8}$/D', (string) $raw_user ) || ! preg_match( '/^[1-9][0-9]{0,8}$/D', (string) $raw_points ) || ! get_user_by( 'id', (int) $raw_user ) || ! in_array( $action, array( 'add', 'remove' ), true ) ) {
			wp_die( esc_html__( 'Enter a valid customer and a positive whole number of points.', 'nodevora-customer-rewards-for-woocommerce' ), '', array( 'response' => 400 ) );
		}
		$user_id = (int) $raw_user; $points = (int) $raw_points;

		if(Nodevora_Financial_Compatibility::selected()){
			$id=isset($_POST['nodevora_operation'])&&is_string($_POST['nodevora_operation'])?sanitize_text_field(wp_unslash($_POST['nodevora_operation'])):'';
			if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$id)){wp_die(esc_html__('Missing adjustment identity. Reload the form.','nodevora-customer-rewards-for-woocommerce'), '', array('response'=>400));}
			$key='adjust:'.get_current_user_id().':'.$id;
			try{if('add'===$action){Rewardly_Loyalty_Helpers::add_points($user_id,$points,0,'adjust_add',$note,$key);}else{Rewardly_Loyalty_Helpers::subtract_points_exact($user_id,$points,0,'adjust_remove',$note,$key);}}
			catch(Nodevora_Financial_Error $e){wp_die(esc_html__('Adjustment was not authorized. Check account readiness and retry with the same form.','nodevora-customer-rewards-for-woocommerce'), '', array('response'=>409));}
			wp_safe_redirect(admin_url('admin.php?page=rewardly-loyalty-settings&tab=adjustments&rewardly_user='.$user_id));exit;
		}
		wp_die( esc_html__( 'Points are preserved. Complete financial setup before adjusting balances.', 'nodevora-customer-rewards-for-woocommerce' ), '', array( 'response' => 409 ) );
	}
}
