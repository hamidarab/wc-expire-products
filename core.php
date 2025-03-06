<?php
/**
 * Plugin Name: WooCommerce Expiration Products
 * Description: Add expiration date to products and automatically mark them as out of stock with admin email notification.
 * Version: 1.0.4
 * Author: Hamid Araab
 * Text Domain: woocommerce-expiration-products
 */

if (!defined('ABSPATH')) {
    exit;
}



// افزودن فیلد تاریخ انقضا به صفحه محصول
add_action('woocommerce_product_options_general_product_data', function () {
    woocommerce_wp_text_input([
        'id'          => '_expiration_date',
        'label'       => __('تاریخ انقضا (YYYY-MM-DD)', 'woocommerce'),
        'placeholder' => 'مثلاً 2025-05-10',
        'desc_tip'    => 'true',
        'description' => 'تاریخ انقضا محصول را به فرمت YYYY-MM-DD وارد کنید.',
        'type'        => 'date'
    ]);
});

// اضافه کردن فیلد تاریخ انقضا به متغیرهای محصول
add_action('woocommerce_product_after_variable_attributes', function($loop, $variation_data, $variation) {
    woocommerce_wp_text_input([
        'id'          => "_variation_expiration_date{$loop}",
        'name'        => "_variation_expiration_date[{$loop}]",
        'value'       => get_post_meta($variation->ID, '_expiration_date', true),
        'label'       => __('تاریخ انقضا (YYYY-MM-DD)', 'woocommerce'),
        'placeholder' => 'مثلاً 2025-05-10',
        'desc_tip'    => 'true',
        'description' => 'تاریخ انقضا متغیر را به فرمت YYYY-MM-DD وارد کنید.',
        'type'        => 'date',
        'wrapper_class' => 'form-row form-row-full'
    ]);
}, 10, 3);

// ذخیره مقدار فیلد تاریخ انقضا
add_action('woocommerce_process_product_meta', function ($post_id) {
    if (isset($_POST['_expiration_date'])) {
        update_post_meta($post_id, '_expiration_date', sanitize_text_field($_POST['_expiration_date']));
    }
});

// ذخیره مقدار فیلد تاریخ انقضا برای متغیرهای محصول
add_action('woocommerce_save_product_variation', function($variation_id, $loop) {
    if (isset($_POST['_variation_expiration_date'][$loop])) {
        $expiration_date = sanitize_text_field($_POST['_variation_expiration_date'][$loop]);
        update_post_meta($variation_id, '_expiration_date', $expiration_date);
    }
}, 10, 2);

// تابع بررسی تاریخ انقضا و ناموجود کردن محصول
add_action('check_expired_products_cron', 'check_expired_products');
function check_expired_products() {
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
    $email_body  = "محصولات زیر ۲ ماه دیگر منقضی می‌شوند و ناموجود شده‌اند:\n\n";

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
                "متغیر - %s\n%s",
                $parent_product->get_name(),
                '- ' . implode("\n- ", $variation_details)
            );
            
            $email_body .= "نام محصول: {$title}\n";
            $email_body .= "نوع محصول: {$product_type}\n";
            $email_body .= "شناسه متغیر: {$product_id}\n";
            $email_body .= "شناسه محصول اصلی: {$parent_product->get_id()}\n";

        } else {

            $email_body .= "نام محصول: {$title}\n";
            $email_body .= "نوع محصول: ساده\n";
            $email_body .= "شناسه محصول: {$product_id}\n";

        }
        
        $email_body .= "تاریخ انقضا: {$expiration_date}\n";
        $email_body .= "ویرایش محصول: {$edit_link}\n\n";
    }

    if (strlen($email_body) > strlen("محصولات زیر ۲ ماه دیگر منقضی می‌شوند و ناموجود شده‌اند:\n\n")) {
        wp_mail($admin_email, 'محصولات نزدیک به تاریخ انقضا', $email_body);
    }
}

// ثبت کرون جاب برای اجرای روزانه
register_activation_hook(__FILE__, 'schedule_expiration_check');
function schedule_expiration_check() {
    if (!wp_next_scheduled('check_expired_products_cron')) {
        wp_schedule_event(time(), 'daily', 'check_expired_products_cron');
    }
}

// حذف کرون جاب هنگام غیرفعال شدن پلاگین
register_deactivation_hook(__FILE__, 'unschedule_expiration_check');
function unschedule_expiration_check() {
    wp_clear_scheduled_hook('check_expired_products_cron');
}

// نمایش تاریخ انقضا فقط در صفحه محصول، بعد از قیمت
add_filter('woocommerce_get_price_html', function ($price, $product) {
    global $wp_query;

    if (!is_singular('product') || (isset($wp_query->queried_object_id) && $wp_query->queried_object_id != $product->get_id())) {
        return $price;
    }

    $expiration_html = '';
    
    if ($product->is_type('variable')) {
        // برای محصولات متغیر، تاریخ انقضا را در JavaScript ذخیره می‌کنیم
        $variations = $product->get_available_variations();
        $variation_expiry_dates = [];
        
        foreach ($variations as $variation) {
            $variation_id = $variation['variation_id'];
            $expiration_date = get_post_meta($variation_id, '_expiration_date', true);
            if ($expiration_date) {
                $variation_expiry_dates[$variation_id] = date('m/Y', strtotime($expiration_date));
            }
        }
        
        if (!empty($variation_expiry_dates)) {
            $expiration_html = '<br><span class="expiration-date variable-expiration" style="font-size: 14px; margin: 30px 0 0; display: flex; font-weight: bold;">انقضا: <span class="expiry-value"></span></span>';
            // اضافه کردن JavaScript برای به‌روزرسانی تاریخ انقضا
            wc_enqueue_js("
                var variationExpiryDates = " . json_encode($variation_expiry_dates) . ";
                jQuery(document).on('found_variation', '.variations_form', function(event, variation) {
                    var expiryDate = variationExpiryDates[variation.variation_id];
                    if (expiryDate) {
                        jQuery('.variable-expiration .expiry-value').text(expiryDate);
                        jQuery('.variable-expiration').show();
                    } else {
                        jQuery('.variable-expiration').hide();
                    }
                });
                jQuery(document).on('reset_data', '.variations_form', function() {
                    jQuery('.variable-expiration').hide();
                });
            ");
        }
    } else {
        // برای محصولات ساده
        $expiration_date = get_post_meta($product->get_id(), '_expiration_date', true);
        if ($expiration_date) {
            $formatted_date = date('m/Y', strtotime($expiration_date));
            $expiration_html = '<br><span class="expiration-date" style="font-size: 14px; margin: 30px 0 0; display: flex; font-weight: bold;">انقضا: ' . esc_html($formatted_date) . '</span>';
        }
    }

    return $price . $expiration_html;
}, 10, 2);

// اضافه کردن فیلد تاریخ انقضا به ویرایش سریع
add_action('quick_edit_custom_box', function ($column_name, $post_type) {
    if ($post_type !== 'product' || $column_name !== 'price') return;

    ?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label>
                <span class="title">تاریخ انقضا</span>
                <span class="input-text-wrap">
                    <input type="text" name="_expiration_date" class="expiration_date_field" placeholder="مثلاً 10/2025">
                </span>
            </label>
        </div>
    </fieldset>
    <?php
}, 10, 2);

// ذخیره مقدار جدید تاریخ انقضا هنگام ویرایش سریع
add_action('save_post', function ($post_id) {
    if (!isset($_POST['_expiration_date'])) return;
    
    $expiration_date = sanitize_text_field($_POST['_expiration_date']);
    
    if (!empty($expiration_date)) {
        // تبدیل فرمت "MM/YYYY" به "YYYY-MM-DD"
        $date_parts = explode('/', $expiration_date);
        if (count($date_parts) === 2) {
            $formatted_date = $date_parts[1] . '-' . $date_parts[0] . '-01'; // تبدیل به YYYY-MM-DD
            update_post_meta($post_id, '_expiration_date', $formatted_date);
        }
    }
});

// اضافه کردن جاوا اسکریپت برای مقداردهی خودکار در ویرایش سریع
add_action('admin_footer', function () {
    ?>
    <script>
    jQuery(function($) {
        $('.editinline').on('click', function() {
            var post_id = $(this).closest('tr').attr('id').replace("post-", "");
            var expiration_date = $('#post-' + post_id).find('.column-expiration_date').text().trim();

            if (expiration_date) {
                $('input.expiration_date_field').val(expiration_date);
            }
        });
    });
    </script>
    <?php
});