<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Charger le composant avant l'hydratation des blocs WooCommerce. */
class Nodevora_Blocks_Integration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {
	public function get_name() { return 'nodevora-loyalty'; }
	public function initialize() {
		wp_register_script( 'nodevora-customer-rewards-for-woocommerce-blocks', REWARDLY_LOYALTY_URL . 'assets/js/loyalty-blocks.js', array( 'wp-element', 'wp-i18n', 'wp-data', 'wc-blocks-data-store', 'wc-blocks-checkout' ), filemtime( REWARDLY_LOYALTY_PATH . 'assets/js/loyalty-blocks.js' ), true );
		wp_set_script_translations( 'nodevora-customer-rewards-for-woocommerce-blocks', 'nodevora-customer-rewards-for-woocommerce', REWARDLY_LOYALTY_PATH . 'languages' );
	}
	public function get_script_handles() { return Rewardly_Loyalty_Helpers::is_enabled() && 'shortcode' !== Rewardly_Loyalty_Redeem::get_notice_display_mode() ? array( 'nodevora-customer-rewards-for-woocommerce-blocks' ) : array(); }
	public function get_editor_script_handles() { return array(); }
	public function get_script_data() { return array(); }
}
