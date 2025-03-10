<?php
namespace WC_Product_Expiration;

/**
 * Cron jobs handler
 */
class Cron {
    /**
     * Initialize cron functionality
     */
    public function __construct() {
        add_action('check_expired_products_cron', array($this, 'check_expired_products'));
    }

    /**
     * Schedule cron job
     */
    public static function schedule_check() {
        if (!wp_next_scheduled('check_expired_products_cron')) {
            wp_schedule_event(time(), 'daily', 'check_expired_products_cron');
        }
    }

    /**
     * Unschedule cron job
     */
    public static function unschedule_check() {
        wp_clear_scheduled_hook('check_expired_products_cron');
    }

    /**
     * Check for expired products with optimized queries for better performance
     *
     * @return int Number of products processed
     */
    public function check_expired_products() {
        global $wpdb;
    
        $batch_size = 50;
        $page = 0; // Start pagination at 0 for SQL LIMIT calculation
        $expiring_products = [];
        $processed_products_count = 0;
    
        // Fetch settings
        $settings = (new \WC_Product_Expiration\Settings())->get_settings();
        $notification_period_type = isset($settings['notification_period_type']) ? $settings['notification_period_type'] : 'months';
        $notification_period = isset($settings['notification_period']) ? (int)$settings['notification_period'] : 2;
    
        // Calculate expiration date
        $date_from_now = match ($notification_period_type) {
            'days' => gmdate('Y-m-d', strtotime("+{$notification_period} days")),
            'weeks' => gmdate('Y-m-d', strtotime("+{$notification_period} weeks")),
            default => gmdate('Y-m-d', strtotime("+{$notification_period} months"))
        };
    
        do {
            // Use $wpdb to query efficiently
            $offset = $page * $batch_size;
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT p.ID as product_id, pm.meta_value as expiration_date
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm
                    ON p.ID = pm.post_id
                    WHERE p.post_status = 'publish'
                      AND p.post_type IN ('product', 'product_variation')
                      AND pm.meta_key = '_expiration_date'
                      AND CAST(pm.meta_value AS DATE) <= %s
                    LIMIT %d, %d
                    ",
                    $date_from_now, 
                    $offset, 
                    $batch_size
                )
            );
    
            if (empty($results)) {
                break;
            }
    
            foreach ($results as $row) {
                $product_id = (int) $row->product_id;
                $expiration_date = $row->expiration_date;
    
                // Validate date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
                    continue;
                }
    
                // Load the WooCommerce product object
                $product_obj = wc_get_product($product_id);
                if (!$product_obj || $product_obj->get_stock_status() !== 'instock') {
                    continue;
                }
    
                // Mark product as out of stock
                update_post_meta($product_id, '_stock_status', 'outofstock');
                wc_delete_product_transients($product_id);
    
                $expiring_products[] = $product_obj;
                $processed_products_count++;
            }
    
            $page++;
        } while (count($results) === $batch_size);
    
        // Send the notification email
        if (!empty($expiring_products)) {
            $this->send_notification_email($expiring_products);
        }
    
        return $processed_products_count;
    }
    
    /**
     * Send notification email for expiring products
     * 
     * @param array $products Array of WC_Product objects
     */
    private function send_notification_email($products) {
        $recipients = WC_Expiration_Settings::get_option('email_recipients', 'admin');
        $custom_email = WC_Expiration_Settings::get_option('custom_email', '');
        
        if ($recipients === 'custom' && !empty($custom_email)) {
            $to = sanitize_email($custom_email);
        } else {
            $to = get_option('admin_email');
        }

        // Get the notification period settings from the settings table
        $settings = (new \WC_Product_Expiration\Settings())->get_settings();
        
        // Get expiration period in days, weeks, or months
        $notification_period_type = $settings['notification_period_type']; // 'days', 'weeks', etc.
        $notification_period = $settings['notification_period']; // 30, 60, etc.

        // Calculate the date based on the notification period type
        if ($notification_period_type === 'days') {
            $date_from_now = gmdate('Y-m-d', strtotime("+$notification_period days"));
        } elseif ($notification_period_type === 'weeks') {
            $date_from_now = gmdate('Y-m-d', strtotime("+$notification_period weeks"));
        } elseif ($notification_period_type === 'months') {
            $date_from_now = gmdate('Y-m-d', strtotime("+$notification_period months"));
        } else {
            // Default to 2 months if the type is not valid
            $date_from_now = gmdate('Y-m-d', strtotime('+2 months'));
        }
        
        // translators: Initial message in the expiration notification email
        $email_body = sprintf(esc_html__("The following products will expire in %s and have been set to out of stock:", 'product-expiration-easy-peasy'), $date_from_now) . "\n\n";
        
        foreach ($products as $product_obj) {
            $product_id = $product_obj->get_id();
            $expiration_date = get_post_meta($product_id, '_expiration_date', true);
            $title = $product_obj->get_name();
            $edit_link = admin_url('post.php?post=' . $product_id . '&action=edit');
            
            if ($product_obj->is_type('variation')) {
                $parent_product = wc_get_product($product_obj->get_parent_id());
                $variation_attributes = $product_obj->get_variation_attributes();
                
                $variation_details = [];
                foreach ($variation_attributes as $attribute => $value) {
                    $taxonomy = str_replace('attribute_', '', $attribute);

                    if (taxonomy_exists($taxonomy)) {
                        $term = get_term_by('slug', $value, $taxonomy);
                        $value = $term ? $term->name : $value;
                    }

                    $attribute_label = wc_attribute_label($taxonomy);
                    $variation_details[] = $attribute_label . ': ' . $value;
                }
                
                // translators: %1$s is the parent product name, %2$s is the variation details list
                $product_type = sprintf(
                    esc_html__("Variable - %1\$s\n%2\$s", 'product-expiration-easy-peasy'),
                    $parent_product->get_name(),
                    '- ' . implode("\n- ", $variation_details)
                );
                
                // translators: %s is the product name
                $email_body .= sprintf(esc_html__("Product Name: %s\n", 'product-expiration-easy-peasy'), $title);
                // translators: %s is the product type details
                $email_body .= sprintf(esc_html__("Product Type: %s\n", 'product-expiration-easy-peasy'), $product_type);
                // translators: %s is the variation ID
                $email_body .= sprintf(esc_html__("Variation ID: %s\n", 'product-expiration-easy-peasy'), $product_id);
                // translators: %s is the parent product ID
                $email_body .= sprintf(esc_html__("Parent Product ID: %s\n", 'product-expiration-easy-peasy'), $parent_product->get_id());
            } else {
                // translators: %s is the product name
                $email_body .= sprintf(esc_html__("Product Name: %s\n", 'product-expiration-easy-peasy'), $title);
                // translators: %s is the product type (Simple)
                $email_body .= sprintf(esc_html__("Product Type: %s\n", 'product-expiration-easy-peasy'), esc_html__("Simple", 'product-expiration-easy-peasy'));
                // translators: %s is the product ID
                $email_body .= sprintf(esc_html__("Product ID: %s\n", 'product-expiration-easy-peasy'), $product_id);
            }
            
            // translators: %s is the expiration date
            $email_body .= sprintf(esc_html__("Expiration Date: %s\n", 'product-expiration-easy-peasy'), $expiration_date);
            // translators: %s is the edit product link
            $email_body .= sprintf(esc_html__("Edit Product: %s\n\n", 'product-expiration-easy-peasy'), $edit_link);
        }
        
        // translators: Email subject for products nearing expiration
        wp_mail(
            $to, 
            esc_html__('Products Near Expiration Date', 'product-expiration-easy-peasy'), 
            $email_body
        );
    }
}
