<?php
/**
 * Plugin Name: Product Expiration Easy Peasy
 * Plugin URI: https://hamidarab.ir/product-expiration
 * Description: Manage product expiration dates in WooCommerce
 * Version: 3.0.1
 * Author: Hamid Arab
 * Author URI: https://hamidarab.ir
 * Text Domain: product-expiration-easy-peasy
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * Woo: persianwcexp:342928dfsfhsf8429842374wdf4234sfd
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace WC_Product_Expiration;

if (!defined('ABSPATH')) {
    exit;
}

// Constants
define('WC_PRODUCT_EXPIRATION_VERSION', '3.0.0');
define('WC_PRODUCT_EXPIRATION_FILE', __FILE__);
define('WC_PRODUCT_EXPIRATION_PATH', plugin_dir_path(__FILE__));
define('WC_PRODUCT_EXPIRATION_URL', plugin_dir_url(__FILE__));

// Declare compatibility with HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Plugin name and description translation
add_filter('plugin_row_meta', function($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $row_meta = array(
            'description' => __('Manage product expiration dates in WooCommerce with Persian calendar support', 'product-expiration-easy-peasy')
        );
        return array_merge($links, $row_meta);
    }
    return $links;
}, 10, 2);

// Plugin name and description translation
add_filter('all_plugins', function($plugins) {
    $plugin_file = plugin_basename(__FILE__);
    if (isset($plugins[$plugin_file])) {
        $plugins[$plugin_file]['Name'] = __('Persian WooCommerce Product Expiration', 'product-expiration-easy-peasy');
        $plugins[$plugin_file]['Description'] = __('Manage product expiration dates in WooCommerce with Persian calendar support', 'product-expiration-easy-peasy');
    }
    return $plugins;
});

// Always include files directly, avoid class caching issues
function include_files() {
    require_once WC_PRODUCT_EXPIRATION_PATH . 'includes/class-admin.php';
    require_once WC_PRODUCT_EXPIRATION_PATH . 'includes/class-frontend.php';
    require_once WC_PRODUCT_EXPIRATION_PATH . 'includes/class-cron.php';
    require_once WC_PRODUCT_EXPIRATION_PATH . 'includes/class-settings.php';
    require_once WC_PRODUCT_EXPIRATION_PATH . 'includes/class-main.php';
}

// Make sure file inclusion function is available
include_files();

// Only load translations at init - not earlier
add_action('init', function() {
    $locale = determine_locale();
    $mofile = WC_PRODUCT_EXPIRATION_PATH . 'languages/product-expiration-easy-peasy-' . $locale . '.mo';
    
    if (file_exists($mofile)) {
        load_textdomain('product-expiration-easy-peasy', $mofile);
    } else {
        load_plugin_textdomain('product-expiration-easy-peasy', false, dirname(plugin_basename(WC_PRODUCT_EXPIRATION_FILE)) . '/languages');
    }
}, 5);

// Initialize plugin
add_action('plugins_loaded', function() {
    return \WC_Product_Expiration\Main::instance();
}, 20);