<?php
if (!defined('ABSPATH')) {
    exit;
}

use ProductExpirationEasyPeasy\Settings;

$settings = new Settings();
?>

<div class="wrap">
    <h1><?php echo esc_html__('Product Expiration Settings', 'product-expiration-easy-peasy'); ?></h1>

    <?php
    settings_errors('peep_product_expiration_messages');

    if (isset($_POST['save_expiration_settings'])) {
        check_admin_referer('expiration_settings_nonce');
        $settings->save_settings($_POST);
    }
    
    $current_settings = $settings->get_settings();
    ?>

    <form method="post" action="">
        <?php wp_nonce_field('expiration_settings_nonce'); ?>
        
        <table class="form-table widefat striped">
            <tr>
                <th scope="row" style="padding: 20px;">
                    <label for="notification_period_type"><?php echo esc_html__('Notification Period Type', 'product-expiration-easy-peasy'); ?></label>
                </th>
                <td>
                    <select id="notification_period_type" name="notification_period_type">
                        <option value="days" <?php selected($current_settings['notification_period_type'], 'days'); ?>><?php echo esc_html__('Days', 'product-expiration-easy-peasy'); ?></option>
                        <option value="months" <?php selected($current_settings['notification_period_type'], 'months'); ?>><?php echo esc_html__('Months', 'product-expiration-easy-peasy'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row" style="padding: 20px;">
                    <label for="notification_period"><?php echo esc_html__('Notification Period', 'product-expiration-easy-peasy'); ?></label>
                </th>
                <td>
                    <input type="number" id="notification_period" name="notification_period" 
                           value="<?php echo esc_attr($current_settings['notification_period']); ?>" min="1" max="365" />
                    <p class="description">
                        <?php echo esc_html__('Time before expiration to send notification', 'product-expiration-easy-peasy'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row" style="padding: 20px;">
                    <label for="show_expiration"><?php echo esc_html__('Show Expiration Date', 'product-expiration-easy-peasy'); ?></label>
                </th>
                <td>
                    <select id="show_expiration" name="show_expiration">
                        <option value="yes" <?php selected($current_settings['show_expiration'], 'yes'); ?>><?php echo esc_html__('Yes', 'product-expiration-easy-peasy'); ?></option>
                        <option value="no" <?php selected($current_settings['show_expiration'], 'no'); ?>><?php echo esc_html__('No', 'product-expiration-easy-peasy'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row" style="padding: 20px;">
                    <label for="display_text"><?php echo esc_html__('Display Text', 'product-expiration-easy-peasy'); ?></label>
                </th>
                <td>
                    <input type="text" id="display_text" name="display_text" class="regular-text" 
                           value="<?php echo esc_attr($current_settings['display_text']); ?>" />
                    <p class="description">
                        <?php echo esc_html__('Use these variables to display the date:', 'product-expiration-easy-peasy'); ?>
                        <br>
                        <code>%date%</code> - <?php echo esc_html__('Full date display (example: 2025/03/30)', 'product-expiration-easy-peasy'); ?>
                        <br>
                        <code>%year%</code> - <?php echo esc_html__('Year only (example: 2025)', 'product-expiration-easy-peasy'); ?>
                        <br>
                        <code>%month%</code> - <?php echo esc_html__('Month only (example: 03)', 'product-expiration-easy-peasy'); ?>
                        <br>
                        <code>%day%</code> - <?php echo esc_html__('Day only (example: 30)', 'product-expiration-easy-peasy'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row" style="padding: 20px;">
                    <label for="date_format"><?php echo esc_html__('Date Format', 'product-expiration-easy-peasy'); ?></label>
                </th>
                <td>
                    <select id="date_format" name="date_format">
                        <option value="default" <?php selected($current_settings['date_format'], 'default'); ?>><?php echo esc_html__('Default (WordPress Format)', 'product-expiration-easy-peasy'); ?></option>
                        <option value="Y/m/d" <?php selected($current_settings['date_format'], 'Y/m/d'); ?>><?php echo esc_html__('Year/Month/Day (1402/01/15)', 'product-expiration-easy-peasy'); ?></option>
                        <option value="Y/m" <?php selected($current_settings['date_format'], 'Y/m'); ?>><?php echo esc_html__('Year/Month (1402/01)', 'product-expiration-easy-peasy'); ?></option>
                        <option value="Ym" <?php selected($current_settings['date_format'], 'Ym'); ?>><?php echo esc_html__('YearMonth (140201)', 'product-expiration-easy-peasy'); ?></option>
                        <option value="Y-m-d" <?php selected($current_settings['date_format'], 'Y-m-d'); ?>><?php echo esc_html__('Year-Month-Day (1402-01-15)', 'product-expiration-easy-peasy'); ?></option>
                        <option value="d M Y" <?php selected($current_settings['date_format'], 'd M Y'); ?>><?php echo esc_html__('Day Month Year (15 March 2025)', 'product-expiration-easy-peasy'); ?></option>
                    </select>
                    <p class="description">
                        <?php echo esc_html__('Choose how to display the date in the product', 'product-expiration-easy-peasy'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row" style="padding: 20px;">
                    <label for="display_position"><?php echo esc_html__('Display Position', 'product-expiration-easy-peasy'); ?></label>
                </th>
                <td>
                    <select id="display_position" name="display_position">
                        <option value="after_price" <?php selected($current_settings['display_position'], 'after_price'); ?>><?php echo esc_html__('After Price', 'product-expiration-easy-peasy'); ?></option>
                        <option value="after_title" <?php selected($current_settings['display_position'], 'after_title'); ?>><?php echo esc_html__('After Title', 'product-expiration-easy-peasy'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row" style="padding: 20px;">
                    <label for="email_recipients"><?php echo esc_html__('Email Recipients', 'product-expiration-easy-peasy'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="email_recipients[]" value="admin" 
                                   <?php checked(in_array('admin', $current_settings['email_recipients'])); ?> />
                            <?php echo esc_html__('Site Admin', 'product-expiration-easy-peasy'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="email_recipients[]" value="shop_manager" 
                                   <?php checked(in_array('shop_manager', $current_settings['email_recipients'])); ?> />
                            <?php echo esc_html__('Shop Manager', 'product-expiration-easy-peasy'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="email_recipients[]" value="custom" 
                                   <?php checked(in_array('custom', $current_settings['email_recipients'])); ?> />
                            <?php echo esc_html__('Custom Email', 'product-expiration-easy-peasy'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row" style="padding: 20px;">
                    <label for="custom_email"><?php echo esc_html__('Custom Email', 'product-expiration-easy-peasy'); ?></label>
                </th>
                <td>
                    <input type="email" id="custom_email" name="custom_email" class="regular-text" 
                           value="<?php echo esc_attr($current_settings['custom_email']); ?>" />
                </td>
            </tr>

            <tr>
                <th scope="row" style="padding: 20px;">
                    <label for="show_in_order_email"><?php echo esc_html__('Show in Order Email', 'product-expiration-easy-peasy'); ?></label>
                </th>
                <td>
                    <select id="show_in_order_email" name="show_in_order_email">
                        <option value="yes" <?php selected($current_settings['show_in_order_email'], 'yes'); ?>><?php echo esc_html__('Yes', 'product-expiration-easy-peasy'); ?></option>
                        <option value="no" <?php selected($current_settings['show_in_order_email'], 'no'); ?>><?php echo esc_html__('No', 'product-expiration-easy-peasy'); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="save_expiration_settings" class="button-primary" 
                   value="<?php echo esc_attr__('Save Settings', 'product-expiration-easy-peasy'); ?>" />
        </p>
    </form>
</div>
