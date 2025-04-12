<?php
/**
 * Frontend functionality handler
 */
namespace PEEP_Product_Expiration;

class Frontend {
    /**
     * Initialize frontend hooks
     */
    public function __construct() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Display expiration date on product page
        add_filter('woocommerce_get_price_html', [$this, 'display_expiration'], 10, 2);
        
        // Display expiration date in cart
        add_filter('woocommerce_cart_item_name', [$this, 'display_expiration_in_cart'], 10, 3);
        
        // Display expiration date in order
        add_action('woocommerce_order_item_meta_end', [$this, 'display_expiration_in_order'], 10, 3);
        
        // Display expiration date in order emails
        add_action('woocommerce_email_order_item_meta', [$this, 'display_expiration_in_email'], 10, 3);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Only load CSS on product and cart/checkout pages
        if (is_product() || is_cart() || is_checkout() || is_account_page()) {
            wp_enqueue_style(
                'wc-product-expiration', 
                PEEP_URL . 'assets/css/style.css', 
                [], 
                PEEP_VERSION
            );
        }
    }

    /**
     * Display expiration date on product page
     */
    public function display_expiration($price_html, $product) {
        if (is_admin()) {
            return $price_html;
        }
        
        $settings = new Settings();
        
        if ('yes' !== $settings->get_setting('show_expiration')) {
            return $price_html;
        }
        
        $expiration_date = get_post_meta($product->get_id(), '_expiration_date', true);
        
        if (empty($expiration_date)) {
            return $price_html;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
            return $price_html;
        }
        
        $display_position = $settings->get_setting('display_position');
        
        // Only add to main product page, not to related products
        global $wp_query;
        if (isset($wp_query->queried_object) && isset($wp_query->queried_object->ID) && 
            $wp_query->queried_object->ID !== $product->get_id() && 'after_title' === $display_position) {
            return $price_html;
        }
        
        $display_text = $this->get_formatted_expiration_text($expiration_date);
        $expiration_html = '<div class="expiration-date">' . $display_text . '</div>';
        
        if ('after_price' === $display_position) {
            return $price_html . $expiration_html;
        }
        
        return $price_html;
    }

    /**
     * Display expiration date in cart
     */
    public function display_expiration_in_cart($item_name, $cart_item, $cart_item_key) {
        $settings = new Settings();
        
        if ('yes' !== $settings->get_setting('show_expiration')) {
            return $item_name;
        }
        
        $product_id = $cart_item['product_id'];
        $expiration_date = get_post_meta($product_id, '_expiration_date', true);
        
        if (empty($expiration_date)) {
            return $item_name;
        }
        
        $display_text = $this->get_formatted_expiration_text($expiration_date);
        
        return $item_name . '<div class="expiration-date">' . $display_text . '</div>';
    }

    /**
     * Display expiration date in order
     */
    public function display_expiration_in_order($item_id, $item, $order) {
        $settings = new Settings();
        
        if ('yes' !== $settings->get_setting('show_expiration')) {
            return;
        }
        
        $product_id = $item->get_product_id();
        $expiration_date = get_post_meta($product_id, '_expiration_date', true);
        
        if (empty($expiration_date)) {
            return;
        }
        
        $display_text = $this->get_formatted_expiration_text($expiration_date);
        
        echo '<div class="expiration-date">' . esc_html($display_text) . '</div>';
    }

    /**
     * Display expiration date in order emails
     */
    public function display_expiration_in_email($item_id, $item, $order) {
        $settings = new Settings();
        
        if ('yes' !== $settings->get_setting('show_in_order_email')) {
            return;
        }
        
        $product_id = $item->get_product_id();
        $expiration_date = get_post_meta($product_id, '_expiration_date', true);
        
        if (empty($expiration_date)) {
            return;
        }
        
        $display_text = $this->get_formatted_expiration_text($expiration_date);
        
        echo '<div class="expiration-date">' . esc_html($display_text) . '</div>';
    }

    /**
     * Get formatted expiration text based on settings
     */
    private function get_formatted_expiration_text($expiration_timestamp) {
        $settings = new Settings();
        $display_template = $settings->get_setting('display_text');
        $date_format = $settings->get_setting('date_format');

        // Check if the date is valid
        $expiration_timestamp = strtotime($expiration_timestamp);
        if (!$expiration_timestamp) {
            return;
        }
        
        // Get date components
        $year = date_i18n('Y', $expiration_timestamp);
        $month = date_i18n('m', $expiration_timestamp);
        $day = date_i18n('d', $expiration_timestamp);
        
        // Format date based on selected format
        if ($date_format === 'default') {
            $full_date = date_i18n(get_option('date_format'), $expiration_timestamp);
        } else {
            // Check if we have Persian date functions available
            if (function_exists('jdate')) {
                // Use Persian date function
                $full_date = jdate($date_format, $expiration_timestamp);
            } else {
                // Fallback to standard date formatting
                $full_date = date_i18n($date_format, $expiration_timestamp);
            }
        }
        
        // Replace placeholders
        $text = $display_template;
        $text = str_replace('%date%', $full_date, $text);
        $text = str_replace('%year%', $year, $text);
        $text = str_replace('%month%', $month, $text);
        $text = str_replace('%day%', $day, $text);
        
        return $text;
    }
}
