<?php
/**
 * Plugin Name: Nodevora Customer Rewards for WooCommerce
 * Description: WooCommerce loyalty points with discounts, expiration, customer history, emails, shortcodes, and Cart & Checkout Blocks support.
 * Version: 4.8.6
 * Author: Ahmed Ghanem
 * Author URI: https://profiles.wordpress.org/ahmedxy/
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * WC tested up to: 11.2.1
 * Text Domain: nodevora-customer-rewards-for-woocommerce
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Une seule identité peut être chargée ; le changement de dossier reste une opération explicite. */
$nodevora_legacy_active = in_array( 'rewardly-loyalty/rewardly-loyalty.php', (array) get_option( 'active_plugins', array() ), true );
if ( $nodevora_legacy_active || defined( 'REWARDLY_LOYALTY_FILE' ) || class_exists( 'Rewardly_Loyalty_Helpers', false ) ) {
	add_action( 'admin_notices', static function() {
		if ( current_user_can( 'activate_plugins' ) ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'Another Rewardly or Nodevora installation is active. Back up your data, deactivate the previous package, then activate the official Nodevora package. Financial data must be retained.', 'nodevora-customer-rewards-for-woocommerce' ) . '</p></div>'; }
	} );
	return;
}
/* Les déclarations conditionnelles empêchent les collisions avec une autre copie déjà chargée. */
if ( ! function_exists( 'rewardly_loyalty_activate' ) ) {
define( 'NODEVORA_CUSTOMER_REWARDS_VERSION', '4.8.6' );
define( 'REWARDLY_LOYALTY_DEV_PRO_MODE', false );
define( 'REWARDLY_LOYALTY_PATH', plugin_dir_path( __FILE__ ) );
define( 'REWARDLY_LOYALTY_URL', plugin_dir_url( __FILE__ ) );
define( 'REWARDLY_LOYALTY_FILE', __FILE__ );
define( 'REWARDLY_LOYALTY_BASENAME', plugin_basename( __FILE__ ) );
define( 'REWARDLY_LOYALTY_CRON_HOOK', 'rewardly_loyalty_daily_expiration' );

/**
 * Declare compatibility with modern WooCommerce order storage and Cart/Checkout Blocks.
 */
add_action(
	'before_woocommerce_init',
	static function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

// Installation vierge vérifiée ; les données historiques nécessitent une synchronisation explicite.
require_once REWARDLY_LOYALTY_PATH . 'includes/financial/bootstrap.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/core/class-rewardly-loyalty-helpers.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/core/class-rewardly-loyalty-emails.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/core/class-rewardly-loyalty-points.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/core/class-rewardly-loyalty-redeem.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/core/class-rewardly-loyalty-pro.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/front/class-rewardly-loyalty-account.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/front/class-rewardly-loyalty-product.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/front/class-nodevora-loyalty-blocks.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/design/class-rewardly-loyalty-design.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/shortcodes/class-rewardly-loyalty-shortcodes.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/admin/class-nodevora-access.php';
Nodevora_Access::init();
require_once REWARDLY_LOYALTY_PATH . 'includes/admin/class-nodevora-data.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/admin/class-nodevora-design-admin.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/admin/class-rewardly-loyalty-settings.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/admin/class-rewardly-loyalty-admin-adjustments.php';
require_once REWARDLY_LOYALTY_PATH . 'includes/admin/class-rewardly-loyalty-admin.php';


function rewardly_loyalty_activate() {
	if ( is_multisite() ) { wp_die( esc_html__( 'Nodevora financial operations currently support single-site WordPress only.', 'nodevora-customer-rewards-for-woocommerce' ), '', array( 'response' => 409 ) ); }
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
		add_option( 'rewardly_loyalty_settings', Rewardly_Loyalty_Helpers::get_default_settings() );
	}
	if ( class_exists( 'Rewardly_Loyalty_Account' ) ) {
		Rewardly_Loyalty_Account::add_endpoint();
	}
	if ( ! wp_next_scheduled( REWARDLY_LOYALTY_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', REWARDLY_LOYALTY_CRON_HOOK );
	}
	Nodevora_Installation::activate();
	set_transient( 'nodevora_activation_redirect', get_current_user_id(), 120 );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'rewardly_loyalty_activate' );

function rewardly_loyalty_deactivate() {
	wp_clear_scheduled_hook( REWARDLY_LOYALTY_CRON_HOOK );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'rewardly_loyalty_deactivate' );

function rewardly_loyalty_maybe_schedule_cron() {
	if ( ! wp_next_scheduled( REWARDLY_LOYALTY_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', REWARDLY_LOYALTY_CRON_HOOK );
	}
}

function rewardly_loyalty_maybe_upgrade() {
	$installed_version = get_option( 'rewardly_loyalty_installed_version', '' );
	if ( NODEVORA_CUSTOMER_REWARDS_VERSION === $installed_version ) {
		return;
	}

	if ( ! wp_next_scheduled( REWARDLY_LOYALTY_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', REWARDLY_LOYALTY_CRON_HOOK );
	}
	flush_rewrite_rules( false );
	update_option( 'rewardly_loyalty_installed_version', NODEVORA_CUSTOMER_REWARDS_VERSION );
}

function rewardly_loyalty_should_enqueue_front_assets() {
	if ( is_admin() || ! rewardly_loyalty_is_woocommerce_active() || ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) || ! function_exists( 'is_account_page' ) || ! function_exists( 'is_product' ) ) {
		return false;
	}
	if ( is_cart() || is_checkout() || is_account_page() || is_product() ) {
		return true;
	}
	if ( is_singular() ) {
		global $post;
		if ( $post instanceof WP_Post ) {
			$content = (string) $post->post_content;
			foreach ( array( 'rewardly_points_balance', 'rewardly_points_value', 'rewardly_points_history', 'rewardly_account_block', 'rewardly_loyalty_notice', 'rewardly_loyalty_card' ) as $shortcode ) {
				if ( has_shortcode( $content, $shortcode ) ) {
					return true;
				}
			}
		}
	}
	return false;
}

function rewardly_loyalty_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

function rewardly_loyalty_get_woocommerce_admin_url() {
	if ( ! current_user_can( 'install_plugins' ) ) {
		return '';
	}
	if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
		return wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ), 'activate-plugin_woocommerce/woocommerce.php' );
	}
	return wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' );
}

function rewardly_loyalty_render_woocommerce_notice() {
	if ( ! is_admin() || rewardly_loyalty_is_woocommerce_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$link = rewardly_loyalty_get_woocommerce_admin_url();
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Nodevora Customer Rewards for WooCommerce requires WooCommerce to work correctly.', 'nodevora-customer-rewards-for-woocommerce' ) . ' ';
	if ( ! empty( $link ) ) {
		echo '<a class="button button-secondary" href="' . esc_url( $link ) . '">' . esc_html__( 'Install or activate WooCommerce', 'nodevora-customer-rewards-for-woocommerce' ) . '</a>';
	}
	echo '</p></div>';
}

function rewardly_loyalty_plugin_action_links( $links ) {
	if ( rewardly_loyalty_is_woocommerce_active() ) {
		return $links;
	}
	$link = rewardly_loyalty_get_woocommerce_admin_url();
	if ( ! empty( $link ) ) {
		array_unshift( $links, '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Install or activate WooCommerce', 'nodevora-customer-rewards-for-woocommerce' ) . '</a>' );
	}
	return $links;
}
add_filter( 'plugin_action_links_' . REWARDLY_LOYALTY_BASENAME, 'rewardly_loyalty_plugin_action_links' );
add_action( 'admin_notices', 'rewardly_loyalty_render_woocommerce_notice' );
add_action( 'network_admin_notices', 'rewardly_loyalty_render_woocommerce_notice' );

function rewardly_loyalty_init() {
	rewardly_loyalty_maybe_schedule_cron();
	rewardly_loyalty_maybe_upgrade();
	if ( ! rewardly_loyalty_is_woocommerce_active() ) {
		return;
	}
	Nodevora_Loyalty_Blocks::init();
	Rewardly_Loyalty_Settings::init();
	Rewardly_Loyalty_Emails::init();
	Rewardly_Loyalty_Points::init();
	Rewardly_Loyalty_Redeem::init();
	Nodevora_Financial_Checkout::init();
	Nodevora_Financial_Lifecycle::init();
	Nodevora_Financial_Recovery::init();
	Nodevora_Financial_Readiness::init();
	Nodevora_Financial_Setup::init();
	Nodevora_Data::init();
	add_action('admin_init',array('Nodevora_Installation','maybe_initialize'));
	Rewardly_Loyalty_Pro::init();
	Rewardly_Loyalty_Account::init();
	Rewardly_Loyalty_Product::init();
	Rewardly_Loyalty_Shortcodes::init();
	Rewardly_Loyalty_Design::init();
	Rewardly_Loyalty_Admin::init();
	Rewardly_Loyalty_Admin_Adjustments::init();
}
add_action( 'plugins_loaded', 'rewardly_loyalty_init' );
add_action( 'init', static function() { load_plugin_textdomain( 'nodevora-customer-rewards-for-woocommerce', false, dirname( REWARDLY_LOYALTY_BASENAME ) . '/languages' ); } );

function rewardly_loyalty_enqueue_assets() {
	if ( ! rewardly_loyalty_should_enqueue_front_assets() ) {
		return;
	}

	wp_enqueue_style( 'nodevora-customer-rewards-for-woocommerce', REWARDLY_LOYALTY_URL . 'assets/css/loyalty.css', array(), filemtime( REWARDLY_LOYALTY_PATH . 'assets/css/loyalty.css' ) );

	$design_css = Rewardly_Loyalty_Design::get_front_inline_css();
	if ( ! empty( $design_css ) ) {
		wp_add_inline_style( 'nodevora-customer-rewards-for-woocommerce', $design_css );
	}

	if ( class_exists( 'Rewardly_Loyalty_Redeem' ) && Rewardly_Loyalty_Redeem::should_enqueue_blocks_script() ) {
		wp_enqueue_script(
			'nodevora-customer-rewards-for-woocommerce-blocks',
			REWARDLY_LOYALTY_URL . 'assets/js/loyalty-blocks.js',
			array( 'wp-element', 'wp-plugins', 'wp-i18n', 'wp-data', 'wc-blocks-data-store', 'wc-blocks-checkout' ),
			filemtime( REWARDLY_LOYALTY_PATH . 'assets/js/loyalty-blocks.js' ),
			true
		);

		wp_set_script_translations( 'nodevora-customer-rewards-for-woocommerce-blocks', 'nodevora-customer-rewards-for-woocommerce', REWARDLY_LOYALTY_PATH . 'languages' );
	}
}
add_action( 'wp_enqueue_scripts', 'rewardly_loyalty_enqueue_assets' );

function rewardly_loyalty_run_daily_expiration() {
	if ( ! rewardly_loyalty_is_woocommerce_active() || ! class_exists( 'Rewardly_Loyalty_Helpers' ) ) {
		return;
	}
	Rewardly_Loyalty_Helpers::run_daily_points_expiration();
}
add_action( REWARDLY_LOYALTY_CRON_HOOK, 'rewardly_loyalty_run_daily_expiration' );

}
