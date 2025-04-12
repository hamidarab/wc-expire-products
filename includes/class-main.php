<?php
namespace PEEP_Product_Expiration;

/**
 * Main plugin class
 */
class Main {
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
                     esc_html__('WooCommerce Product Expiration requires WooCommerce to be installed and activated.', 'product-expiration-easy-peasy') . 
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