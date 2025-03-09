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
     * Check for expired products
     */
    public function check_expired_products() {
        $args = [
            'post_type'      => ['product', 'product_variation'],
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_expiration_date',
                    'compare' => 'EXISTS'
                ],
                [
                    'key'     => '_expiration_date',
                    'value'   => date('Y-m-d', strtotime('+2 months')),
                    'compare' => '<=',
                    'type'    => 'DATE'
                ]
            ]
        ];

        $products = get_posts($args);
        if (!$products) return;

        $admin_email = get_option('admin_email');
        $email_body  = __("The following products will expire in 2 months and have been set to out of stock:", 'wc-expiration') . "\n\n";

        foreach ($products as $product) {
            $product_id = $product->ID;
            
            $expiration_date = get_post_meta($product_id, '_expiration_date', true);
            if (empty($expiration_date)) {
                continue;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
                continue;
            }

            $product_obj = wc_get_product($product_id);
            if (!$product_obj || $product_obj->get_stock_status() !== 'instock') {
                continue;
            }

            update_post_meta($product_id, '_stock_status', 'outofstock');
            wc_delete_product_transients($product_id);

            $title = $product_obj->get_name();
            $edit_link = admin_url('post.php?post=' . $product_id . '&action=edit');
            
            if ($product->post_type === 'product_variation') {
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
                
                $product_type = sprintf(
                    __("Variable - %s\n%s", 'wc-expiration'),
                    $parent_product->get_name(),
                    '- ' . implode("\n- ", $variation_details)
                );
                
                $email_body .= sprintf(__("Product Name: %s\n", 'wc-expiration'), $title);
                $email_body .= sprintf(__("Product Type: %s\n", 'wc-expiration'), $product_type);
                $email_body .= sprintf(__("Variation ID: %s\n", 'wc-expiration'), $product_id);
                $email_body .= sprintf(__("Parent Product ID: %s\n", 'wc-expiration'), $parent_product->get_id());

            } else {
                $email_body .= sprintf(__("Product Name: %s\n", 'wc-expiration'), $title);
                $email_body .= sprintf(__("Product Type: %s\n", 'wc-expiration'), __("Simple", 'wc-expiration'));
                $email_body .= sprintf(__("Product ID: %s\n", 'wc-expiration'), $product_id);
            }
            
            $email_body .= sprintf(__("Expiration Date: %s\n", 'wc-expiration'), $expiration_date);
            $email_body .= sprintf(__("Edit Product: %s\n\n", 'wc-expiration'), $edit_link);
        }

        $initial_message = __("The following products will expire in 2 months and have been set to out of stock:", 'wc-expiration') . "\n\n";
        if (strlen($email_body) > strlen($initial_message)) {
            wp_mail(
                $admin_email, 
                __('Products Near Expiration Date', 'wc-expiration'), 
                $email_body
            );
        }
    }

    /**
     * Send notification email
     */
    private function send_notification_email($product, $expiration_date) {
        $recipients = WC_Expiration_Settings::get_option('email_recipients', 'admin');
        $custom_email = WC_Expiration_Settings::get_option('custom_email', '');
        
        if ($recipients === 'custom' && !empty($custom_email)) {
            $to = $custom_email;
        } else {
            $to = get_option('admin_email');
        }

        $subject = sprintf(__('Product Expired: %s', 'wc-expiration'), $product->post_title);
        $message = sprintf(
            __('Product "%s" expired on %s and has been automatically out of stock.', 'wc-expiration'),
            $product->post_title,
            $expiration_date
        );

        wp_mail($to, $subject, $message);
    }
}
