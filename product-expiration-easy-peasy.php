<?php
/**
 * Plugin Name: Product Expiration Easy Peasy
 * Plugin URI:  https://www.rtl-theme.com/?p=293806
 * Description: Manage WooCommerce product expiration dates, stock status, and notifications.
 * Version:     3.4.1
 * Author:      Hamid Arab
 * Author URI:  https://hamidarab.ir
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

define( 'PRODUCT_EXPIRATION_EASY_PEASY_VERSION', '3.4.1' );
define( 'PRODUCT_EXPIRATION_EASY_PEASY_FILE', __FILE__ );
define( 'PRODUCT_EXPIRATION_EASY_PEASY_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRODUCT_EXPIRATION_EASY_PEASY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Add a direct settings shortcut to the plugin row.
 *
 * @param array<int|string, string> $links Existing plugin action links.
 * @return array<int|string, string>
 */
function add_plugin_action_links( $links ) {
	$settings_url  = admin_url( 'admin.php?page=product-expiration-easy-peasy-settings' );
	$settings_link = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( $settings_url ),
		esc_html__( 'Settings', 'product-expiration-easy-peasy' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( PRODUCT_EXPIRATION_EASY_PEASY_FILE ), __NAMESPACE__ . '\\add_plugin_action_links' );

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
		'includes/class-stats.php',
		'includes/class-icons.php',
		'includes/class-view.php',
		'includes/class-assets.php',
		'includes/class-mini-month.php',
		'includes/class-stock.php',
		'includes/class-report.php',
		'includes/class-check-job.php',
		'includes/class-admin.php',
		'includes/class-settings-page.php',
		'includes/class-frontend.php',
		'includes/class-cron.php',
		'includes/class-ajax.php',
		'includes/class-main.php',
	);

	foreach ( $files as $file ) {
		require_once PRODUCT_EXPIRATION_EASY_PEASY_PATH . $file;
	}
}

/**
 * Prefer the bundled translation over a stale WordPress.org language pack.
 *
 * WordPress loads WP_LANG_DIR/plugins/ first, so an old pack for this domain
 * would hide every string added since. Only this text domain is redirected.
 *
 * @param string|false $file   Translation file WordPress is about to load.
 * @param string       $domain Text domain.
 * @return string|false
 */
function use_bundled_translation( $file, $domain ) {
	if ( 'product-expiration-easy-peasy' !== $domain || ! is_string( $file ) ) {
		return $file;
	}

	if ( wp_normalize_path( dirname( $file ) ) !== wp_normalize_path( WP_LANG_DIR . '/plugins' ) ) {
		return $file;
	}

	$bundled = PRODUCT_EXPIRATION_EASY_PEASY_PATH . 'languages/' . basename( $file );

	if ( file_exists( $bundled ) ) {
		return $bundled;
	}

	// No bundled .l10n.php: fall back to the bundled .mo, not the stale pack.
	if ( str_ends_with( $bundled, '.l10n.php' ) ) {
		$bundled_mo = substr( $bundled, 0, -strlen( '.l10n.php' ) ) . '.mo';

		if ( file_exists( $bundled_mo ) ) {
			return $bundled_mo;
		}
	}

	return $file;
}
add_filter( 'load_translation_file', __NAMESPACE__ . '\\use_bundled_translation', 10, 2 );
add_filter( 'load_textdomain_mofile', __NAMESPACE__ . '\\use_bundled_translation', 10, 2 );

/**
 * Load translations on init, as WordPress 6.7+ expects.
 *
 * @return void
 */
function load_textdomain() {
	load_plugin_textdomain(
		'product-expiration-easy-peasy',
		false,
		dirname( plugin_basename( PRODUCT_EXPIRATION_EASY_PEASY_FILE ) ) . '/languages'
	);
}
add_action( 'init', __NAMESPACE__ . '\\load_textdomain', 0 );

/**
 * Include the plugin classes after all regular plugins are available.
 *
 * @return void
 */
function bootstrap() {
	include_files();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap', 20 );

/**
 * Create the plugin services once translations are loaded.
 *
 * @return void
 */
function start() {
	if ( class_exists( __NAMESPACE__ . '\\Main' ) ) {
		Main::instance();
	}
}
add_action( 'init', __NAMESPACE__ . '\\start', 1 );

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
