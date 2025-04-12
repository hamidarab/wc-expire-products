<?php
namespace PEEP_Product_Expiration;

/**
 * Cron jobs handler
 */
class Cron {
    /**
     * Batch size for product processing
     * @var int
     */
    private $batch_size = 50;

    /**
     * Initialize cron functionality
     */
    public function __construct() {
        add_action('peep_check_expired_products', array($this, 'check_expired_products'));
    }

    /**
     * Schedule cron job
     */
    public static function schedule_check() {
        if (!wp_next_scheduled('peep_check_expired_products')) {
            wp_schedule_event(time(), 'daily', 'peep_check_expired_products');
        }
    }

    /**
     * Unschedule cron job
     */
    public static function unschedule_check() {
        wp_clear_scheduled_hook('peep_check_expired_products');
    }

    /**
     * Check for expired products with optimized queries for better performance
     *
     * @return int Number of products processed
     */
    public function check_expired_products() {
        global $wpdb;
        $offset = 0; // Start with offset 0
        $expiring_products = [];
        $processed_products_count = 0;
    
        // Fetch settings
        $settings = (new \PEEP_Product_Expiration\Settings())->get_settings();
        $notification_period_type = isset($settings['notification_period_type']) ? sanitize_text_field($settings['notification_period_type']) : 'months';
        $notification_period = isset($settings['notification_period']) ? (int)$settings['notification_period'] : 2;
    
        // Calculate expiration date - proper namespacing for DateTime classes
        $date_from_now = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->modify("+{$notification_period} {$notification_period_type}")
            ->format('Y-m-d');
    
        do {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $product_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT p.ID FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type IN ('product', 'product_variation')
                    AND p.post_status = 'publish'
                    AND pm.meta_key = '_expiration_date'
                    AND pm.meta_value <= %s
                    GROUP BY p.ID
                    LIMIT %d OFFSET %d",
                    $date_from_now,
                    $this->batch_size,
                    $offset
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    
            // Exit loop if no products found
            if (empty($product_ids)) {
                break;
            }
    
            foreach ($product_ids as $product_id) {
                $expiration_date = get_post_meta($product_id, '_expiration_date', true);
    
                // Validate expiration date format
                if (!$expiration_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
                    continue; // Skip invalid entries
                }
    
                // Load WooCommerce product object
                $product_obj = wc_get_product($product_id);
                if (!$product_obj || $product_obj->get_stock_status() !== 'instock') {
                    continue; // Skip products already marked 'outofstock'
                }
    
                // Mark product as out of stock
                update_post_meta($product_id, '_stock_status', 'outofstock');
                wc_delete_product_transients($product_id);
    
                // Collect expired products for notifications
                $expiring_products[] = $product_obj;
                $processed_products_count++;
            }
    
            $offset += $this->batch_size; // Increase offset for next batch
        } while (!empty($product_ids));
    
        // Send notification email if there are expired products
        if (!empty($expiring_products)) {
            $this->send_notification_email($expiring_products);
        }
    
        // Return count of processed products
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
        $settings = (new \PEEP_Product_Expiration\Settings())->get_settings();
        
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
