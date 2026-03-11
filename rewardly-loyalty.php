<?php
/**
 * Plugin Name: Rewardly – WooCommerce Loyalty Program
 * Description: Advanced WooCommerce loyalty points system with point expiration, admin adjustments and email notifications.
 * Version: 3.2.3
 * Author: Ahmed Ghanem
 * Text Domain: rewardly-loyalty
 * Update URI: https://github.com/DELTAWEBMAROC/rewardly-loyalty/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REWARDLY_LOYALTY_VERSION', '3.2.3' );
define( 'REWARDLY_LOYALTY_PATH', plugin_dir_path( __FILE__ ) );
define( 'REWARDLY_LOYALTY_URL', plugin_dir_url( __FILE__ ) );
define( 'REWARDLY_LOYALTY_FILE', __FILE__ );
define( 'REWARDLY_LOYALTY_BASENAME', plugin_basename( __FILE__ ) );
define( 'REWARDLY_LOYALTY_CRON_HOOK', 'rewardly_loyalty_daily_expiration' );
define( 'REWARDLY_LOYALTY_REPO_URL', 'https://github.com/DELTAWEBMAROC/rewardly-loyalty/' );

require_once REWARDLY_LOYALTY_PATH . 'includes/class-rewardly-loyalty-updater.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/class-rewardly-loyalty-helpers.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/class-rewardly-loyalty-settings.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/class-rewardly-loyalty-emails.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/class-rewardly-loyalty-points.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/class-rewardly-loyalty-redeem.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/class-rewardly-loyalty-account.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/class-rewardly-loyalty-product.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/class-rewardly-loyalty-admin-adjustments.php';

/**
 * Créer la table des logs et les options par défaut à l’activation.
 *
 * @return void
 */
function rewardly_loyalty_activate() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'rewardly_loyalty_log';
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$table_name} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		order_id BIGINT UNSIGNED DEFAULT 0,
		type VARCHAR(30) NOT NULL,
		points INT NOT NULL DEFAULT 0,
		amount_dh DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		note TEXT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY order_id (order_id),
		KEY type (type)
	) {$charset_collate};";

	dbDelta( $sql );

	if ( false === get_option( 'rewardly_loyalty_settings', false ) ) {
		add_option( 'rewardly_loyalty_settings', Rewardly_Loyalty_Helpers::get_settings() );
	}

	if ( class_exists( 'Rewardly_Loyalty_Account' ) ) {
		Rewardly_Loyalty_Account::add_endpoint();
	}

	if ( ! wp_next_scheduled( REWARDLY_LOYALTY_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', REWARDLY_LOYALTY_CRON_HOOK );
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'rewardly_loyalty_activate' );

/**
 * Nettoyer les règles à la désactivation.
 *
 * @return void
 */
function rewardly_loyalty_deactivate() {
	wp_clear_scheduled_hook( REWARDLY_LOYALTY_CRON_HOOK );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'rewardly_loyalty_deactivate' );

/**
 * Garantir la planification du cron après une mise à jour.
 *
 * @return void
 */
function rewardly_loyalty_maybe_schedule_cron() {
	if ( ! wp_next_scheduled( REWARDLY_LOYALTY_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', REWARDLY_LOYALTY_CRON_HOOK );
	}
}

/**
 * Exécuter les tâches de migration légère après une mise à jour.
 *
 * @return void
 */
function rewardly_loyalty_maybe_upgrade() {
	$installed_version = get_option( 'rewardly_loyalty_installed_version', '' );

	if ( REWARDLY_LOYALTY_VERSION === $installed_version ) {
		return;
	}

	if ( ! wp_next_scheduled( REWARDLY_LOYALTY_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', REWARDLY_LOYALTY_CRON_HOOK );
	}

	flush_rewrite_rules( false );
	update_option( 'rewardly_loyalty_installed_version', REWARDLY_LOYALTY_VERSION );
}

/**
 * Charger les modules du plugin.
 *
 * @return void
 */
function rewardly_loyalty_init() {
	Rewardly_Loyalty_Updater::init();
	rewardly_loyalty_maybe_schedule_cron();
	rewardly_loyalty_maybe_upgrade();

	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	Rewardly_Loyalty_Settings::init();
	Rewardly_Loyalty_Emails::init();
	Rewardly_Loyalty_Points::init();
	Rewardly_Loyalty_Redeem::init();
	Rewardly_Loyalty_Account::init();
	Rewardly_Loyalty_Product::init();
	Rewardly_Loyalty_Admin_Adjustments::init();
}
add_action( 'plugins_loaded', 'rewardly_loyalty_init' );

/**
 * Charger les assets côté front.
 *
 * @return void
 */
function rewardly_loyalty_enqueue_assets() {
	if ( is_cart() || is_checkout() || is_account_page() || is_product() ) {
		wp_enqueue_style(
			'rewardly-loyalty',
			REWARDLY_LOYALTY_URL . 'assets/css/loyalty.css',
			array(),
			filemtime( REWARDLY_LOYALTY_PATH . 'assets/css/loyalty.css' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'rewardly_loyalty_enqueue_assets' );

/**
 * Tâche quotidienne d’expiration.
 *
 * @return void
 */
function rewardly_loyalty_run_daily_expiration() {
	if ( ! class_exists( 'Rewardly_Loyalty_Helpers' ) ) {
		return;
	}

	Rewardly_Loyalty_Helpers::run_daily_points_expiration();
}
add_action( REWARDLY_LOYALTY_CRON_HOOK, 'rewardly_loyalty_run_daily_expiration' );
