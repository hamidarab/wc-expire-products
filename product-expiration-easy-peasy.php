<?php
/**
 * Plugin Name: Product Expiration Easy Peasy
 * Plugin URI:  https://www.rtl-theme.com/?p=293806
 * Description: Manage WooCommerce product expiration dates, stock status, and notifications.
 * Version:     3.2.0
 * Author:      Hamid Arab
 * Update URI:  https://www.rtl-theme.com/?p=293806
 * Text Domain: product-expiration-easy-peasy
 * Domain Path: /languages
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 9.0
 * WC tested up to: 9.7
 * License:     GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PRODUCT_EXPIRATION_EASY_PEASY_VERSION', '3.2.0' );
define( 'PRODUCT_EXPIRATION_EASY_PEASY_FILE', __FILE__ );
define( 'PRODUCT_EXPIRATION_EASY_PEASY_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRODUCT_EXPIRATION_EASY_PEASY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the plugin classes in a deterministic order.
 *
 * The license adapter must be loaded before any license helper is called. This
 * ordering prevents the undefined-function fatal that affected the encoded
 * release build.
 *
 * @return void
 */
function include_files() {
	$files = array(
		'includes/class-license-check.php',
		'includes/functions.php',
		'includes/class-jalali.php',
		'includes/class-date.php',
		'includes/class-settings.php',
		'includes/class-admin.php',
		'includes/class-frontend.php',
		'includes/class-cron.php',
		'includes/class-main.php',
	);

	foreach ( $files as $file ) {
		require_once PRODUCT_EXPIRATION_EASY_PEASY_PATH . $file;
	}
}

/**
 * Bootstrap the plugin after all regular plugins are available.
 *
 * @return void
 */
function bootstrap() {
	include_files();
	Main::instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 20 );

/**
 * Declare compatibility with WooCommerce's custom order tables.
 *
 * @return void
 */
function declare_woocommerce_compatibility() {
	if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			PRODUCT_EXPIRATION_EASY_PEASY_FILE,
			true
		);
	}
}
add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\declare_woocommerce_compatibility' );

/**
 * Schedule the daily expiration check when the plugin is activated.
 *
 * @param bool $network_wide Whether WordPress is activating the plugin network-wide.
 * @return void
 */
function activate( $network_wide = false ) {
	require_once PRODUCT_EXPIRATION_EASY_PEASY_PATH . 'includes/class-cron.php';

	if ( is_multisite() && $network_wide ) {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			Cron::schedule_check();
			restore_current_blog();
		}

		return;
	}

	Cron::schedule_check();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Remove scheduled jobs when the plugin is deactivated.
 *
 * @param bool $network_wide Whether WordPress is deactivating the plugin network-wide.
 * @return void
 */
function deactivate( $network_wide = false ) {
	require_once PRODUCT_EXPIRATION_EASY_PEASY_PATH . 'includes/class-cron.php';

	if ( is_multisite() && $network_wide ) {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			Cron::unschedule_check();
			restore_current_blog();
		}

		return;
	}

	Cron::unschedule_check();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
