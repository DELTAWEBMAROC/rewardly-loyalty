<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** État de préparation lisible, sans activer ni migrer un compte. */
class Nodevora_Financial_Readiness {
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	public static function state() {
		if ( is_multisite() ) { return 'unsupported_network'; }
		if ( ! Nodevora_Financial_Compatibility::selected() ) { return 'setup_required'; }
		if ( Nodevora_Financial_Setup::paused() ) { return 'paused'; }
		try { Nodevora_Financial_Checkout::require_enabled(); } catch ( Nodevora_Financial_Error $error ) { return 'setup_required'; }
		return 'configured';
	}

	public static function notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || 'configured' === self::state() ) { return; }
		$message = is_multisite()
			? __( 'Nodevora financial operations support single-site stores only. Network data is retained and loyalty spending is unavailable.', 'nodevora-customer-rewards-for-woocommerce' )
			: __( 'Your points data is preserved. Open Nodevora to complete synchronization or review the current pause.', 'nodevora-customer-rewards-for-woocommerce' );
		echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . ' <a href="'.esc_url(admin_url('admin.php?page=rewardly-loyalty-settings&tab=data')).'">'.esc_html__('Review my data','nodevora-customer-rewards-for-woocommerce').'</a></p></div>';
	}
}
