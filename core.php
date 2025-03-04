<?php
/**
 * Plugin Name: WooCommerce Expiration Products
 * Description: افزودن تاریخ انقضا به محصولات و ناموجود کردن آن‌ها به‌صورت خودکار همراه با ارسال ایمیل به مدیر.
 * Version: 1.0
 * Author: حمید اعراب
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

// ذخیره مقدار فیلد تاریخ انقضا
add_action('woocommerce_process_product_meta', function ($post_id) {
    if (isset($_POST['_expiration_date'])) {
        update_post_meta($post_id, '_expiration_date', sanitize_text_field($_POST['_expiration_date']));
    }
});

// تابع بررسی تاریخ انقضا و ناموجود کردن محصول
add_action('check_expired_products_cron', 'check_expired_products');
function check_expired_products() {
    $args = [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => '_expiration_date',
                'value'   => date('Y-m-d', strtotime('+2 months')), // روی ۲ ماه مانده به انقضا تنظیم شده
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
        update_post_meta($product->ID, '_stock_status', 'outofstock'); // ناموجود کردن محصول
        wc_delete_product_transients($product->ID); // به‌روزرسانی کش محصول

        // دریافت اطلاعات محصول
        $product_obj = wc_get_product($product->ID);
        $title = $product_obj->get_name();
        $link  = get_edit_post_link($product->ID);
        
        $email_body .= "نام محصول: {$title}\n";
        $email_body .= "آیدی محصول: {$product->ID}\n";
        $email_body .= "ویرایش محصول: {$link}\n\n";
    }

    // ارسال ایمیل به مدیر
    wp_mail($admin_email, 'محصولات نزدیک به تاریخ انقضا', $email_body);
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

    // بررسی اینکه آیا محصولی که در حال نمایش است، همان محصول اصلی صفحه است
    if (!is_singular('product') || (isset($wp_query->queried_object_id) && $wp_query->queried_object_id != $product->get_id())) {
        return $price;
    }

    $expiration_date = get_post_meta($product->get_id(), '_expiration_date', true);

    if ($expiration_date) {
        $timestamp = strtotime($expiration_date);
        $formatted_date = date('m/Y', $timestamp); // تبدیل تاریخ به فرمت mm/YYYY
        $price .= '<br><span class="expiration-date" style="font-size: 14px; margin: 30px 0 0; display: flex ; font-weight: bold;">انقضا: ' . esc_html($formatted_date) . '</span>';
    }

    return $price;
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