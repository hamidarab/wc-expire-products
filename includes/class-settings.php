<?php
namespace ProductExpirationEasyPeasy;

/**
 * Settings handler
 */
class Settings {
    private $option_name = 'peep_settings';
    private $default_settings;

    public function __construct() {
        $this->default_settings = [
            'notification_period_type' => 'days',
            'notification_period' => 30,
            'show_expiration' => 'yes',
            'display_text' => 'Expires: %date%',
            'date_format' => 'default',
            'display_position' => 'after_price',
            'email_recipients' => ['admin'],
            'custom_email' => '',
            'show_in_order_email' => 'yes'
        ];
    }

    /**
     * Get all settings
     */
    public function get_settings() {
        $settings = get_option($this->option_name, []);
        return wp_parse_args($settings, $this->default_settings);
    }

    /**
     * Save settings
     */
    public function save_settings($data) {
        if (isset($data['notification_priod_type'])) {
            $period_type = sanitize_text_field($data['notification_priod_type']);
        } else {
            $period_type = sanitize_text_field($data['notification_period_type'] ?? 'days');
        }

        $display_text = isset($data['display_text']) ? stripslashes($data['display_text']) : $this->default_settings['display_text'];

        $settings = [
            'notification_period_type' => $period_type,
            'notification_period' => absint($data['notification_period'] ?? 30),
            'show_expiration' => sanitize_text_field($data['show_expiration'] ?? 'yes'),
            'display_text' => $display_text,
            'date_format' => sanitize_text_field($data['date_format'] ?? 'default'),
            'display_position' => sanitize_text_field($data['display_position'] ?? 'after_price'),
            'email_recipients' => isset($data['email_recipients']) ? (array) $data['email_recipients'] : ['admin'],
            'custom_email' => sanitize_email($data['custom_email'] ?? ''),
            'show_in_order_email' => sanitize_text_field($data['show_in_order_email'] ?? 'yes')
        ];

        update_option($this->option_name, $settings);

        add_settings_error(
            'peep_product_expiration_messages',
            'settings_updated',
            __('Settings saved successfully.', 'product-expiration-easy-peasy'),
            'updated'
        );
    }

    /**
     * Get single setting
     */
    public function get_setting($key) {
        $settings = $this->get_settings();
        return $settings[$key] ?? $this->default_settings[$key] ?? null;
    }
}
