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
     * Check for expired products with pagination for better performance
     */
    public function check_expired_products() {
        $batch_size = 50; // Process products in smaller batches
        $page = 1;
        $expiring_products = [];
        $two_months_from_now = gmdate('Y-m-d', strtotime('+2 months'));
        
        do {
            $args = [
                'post_type'      => ['product', 'product_variation'],
                'posts_per_page' => $batch_size,
                'paged'          => $page,
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'     => '_expiration_date',
                        'compare' => 'EXISTS'
                    ],
                    [
                        'key'     => '_expiration_date',
                        'value'   => $two_months_from_now,
                        'compare' => '<=',
                        'type'    => 'DATE'
                    ]
                ],
                'fields'         => 'ids', // Only retrieve IDs for better performance
            ];

            $query = new \WP_Query($args);
            $product_ids = $query->posts;
            
            // Process this batch of products
            foreach ($product_ids as $product_id) {
                $expiration_date = get_post_meta($product_id, '_expiration_date', true);
                if (empty($expiration_date)) {
                    continue;
                }

                // Ensure date format is valid
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
                    continue;
                }

                $product_obj = wc_get_product($product_id);
                if (!$product_obj || $product_obj->get_stock_status() !== 'instock') {
                    continue;
                }

                // Set product to out of stock
                update_post_meta($product_id, '_stock_status', 'outofstock');
                wc_delete_product_transients($product_id);
                
                // Store for email notification
                $expiring_products[] = $product_obj;
            }
            
            $page++;
            // Continue until we've processed all matching products
        } while (count($product_ids) >= $batch_size);
        
        // If we found expiring products, send email
        if (!empty($expiring_products)) {
            $this->send_notification_email($expiring_products);
        }
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
        
        // translators: Initial message in the expiration notification email
        $email_body = esc_html__("The following products will expire in 2 months and have been set to out of stock:", 'product-expiration-easy-peasy') . "\n\n";
        
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
