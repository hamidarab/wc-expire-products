<?php
/**
 * Plugin Name: Persian WooCommerce Product Expiration
 * Plugin URI: https://hamidarab.com
 * Description: Manage product expiration dates in WooCommerce with Persian calendar support
 * Version: 1.0.0
 * Author: Hamid Aarab
 * Author URI: https://hamidarab.com
 * Text Domain: wc-expiration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * Woo: persianwcexp:342928dfsfhsf8429842374wdf4234sfd
 */

namespace WC_Product_Expiration;

if (!defined('ABSPATH')) {
    exit;
}

// Constants
define('WC_PRODUCT_EXPIRATION_VERSION', '1.0.0');
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
            'description' => __('Manage product expiration dates in WooCommerce with Persian calendar support', 'wc-expiration')
        );
        return array_merge($links, $row_meta);
    }
    return $links;
}, 10, 2);

// Plugin name and description translation
add_filter('all_plugins', function($plugins) {
    $plugin_file = plugin_basename(__FILE__);
    if (isset($plugins[$plugin_file])) {
        $plugins[$plugin_file]['Name'] = __('Persian WooCommerce Product Expiration', 'wc-expiration');
        $plugins[$plugin_file]['Description'] = __('Manage product expiration dates in WooCommerce with Persian calendar support', 'wc-expiration');
    }
    return $plugins;
});

// Always include files directly, avoid class caching issues
function include_files() {
    require_once WC_PRODUCT_EXPIRATION_PATH . 'includes/class-admin.php';
    require_once WC_PRODUCT_EXPIRATION_PATH . 'includes/class-frontend.php';
    require_once WC_PRODUCT_EXPIRATION_PATH . 'includes/class-cron.php';
    require_once WC_PRODUCT_EXPIRATION_PATH . 'includes/class-settings.php';
}

// Only load translations at init - not earlier
add_action('init', function() {
    $locale = determine_locale();
    $mofile = WC_PRODUCT_EXPIRATION_PATH . 'languages/wc-expiration-' . $locale . '.mo';
    
    if (file_exists($mofile)) {
        load_textdomain('wc-expiration', $mofile);
    } else {
        load_plugin_textdomain('wc-expiration', false, dirname(plugin_basename(WC_PRODUCT_EXPIRATION_FILE)) . '/languages');
    }
}, 5); // Priority 5 to load before other init actions

/**
 * Main plugin class
 */
class WC_Product_Expiration {
    /**
     * @var self
     */
    private static $instance = null;

    /**
     * @var Admin
     */
    public $admin;

    /**
     * @var Frontend
     */
    public $frontend;

    /**
     * @var Cron
     */
    public $cron;

    /**
     * @var Settings
     */
    public $settings;

    /**
     * Singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            include_files(); // Ensure files are loaded right before instance creation
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize immediately if plugins_loaded has already fired
        if (did_action('plugins_loaded')) {
            $this->init_plugin();
        } else {
            add_action('plugins_loaded', [$this, 'init_plugin']);
        }
    }

    /**
     * Initialize plugin
     */
    public function init_plugin() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . 
                     esc_html__('WooCommerce Product Expiration requires WooCommerce to be installed and activated.', 'wc-expiration') . 
                     '</p></div>';
            });
            return;
        }

        if (is_admin()) {
            $this->admin = new Admin();
        }
        
        $this->settings = new Settings();
        $this->frontend = new Frontend();
        $this->cron = new Cron();
    }
}

// Make sure file inclusion function is available
include_files();

// Initialize plugin
add_action('plugins_loaded', function() {
    return WC_Product_Expiration::instance();
}, 20); // Higher priority to ensure WooCommerce is loaded first 