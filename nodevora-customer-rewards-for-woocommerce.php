<?php
/**
 * Plugin Name: Nodevora Customer Rewards for WooCommerce
 * Description: Reward repeat purchases with points customers can use for WooCommerce discounts.
 * Plugin URI: https://wordpress.org/plugins/nodevora-customer-rewards-for-woocommerce/
 * Version: 4.8.6
 * Author: Ahmed Ghanem
 * Author URI: https://profiles.wordpress.org/ahmedxy/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nodevora-customer-rewards-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * WC tested up to: 11.2
 *
 * @package Nodevora
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
define( 'NODEVORA_VERSION', '4.8.6' );
define( 'NODEVORA_FILE', __FILE__ );
foreach ( array( 'store', 'engine', 'settings', 'commerce', 'admin', 'backup' ) as $nodevora_module ) {
	require_once __DIR__ . '/includes/class-nodevora-' . $nodevora_module . '.php';
}
require_once __DIR__ . '/includes/class-nodevora-parity.php';
register_activation_hook( __FILE__, array( 'Nodevora_Store', 'install' ) );
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'nodevora_v2_maintenance' );
	}
);
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) || version_compare(WC_VERSION,'8.2','<') ) {
			return; }
		if (1!==(int)get_option('nodevora_v2_schema')) {
            add_action('admin_notices',static function(){echo '<div class="notice notice-error"><p>'.esc_html__('Nodevora financial storage is not initialized. This release requires a fresh installation and does not migrate earlier loyalty data. Restore the matching earlier plugin before updating an existing store.','nodevora-customer-rewards-for-woocommerce').'</p></div>';});
            return;
        }
		Nodevora_Parity::init();
		Nodevora_Commerce::init();


		Nodevora_Backup::init();
	}
);
