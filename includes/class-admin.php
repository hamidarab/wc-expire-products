<?php
namespace WC_Product_Expiration;

/**
 * Admin functionality handler
 */
class Admin {
    /**
     * Initialize admin hooks
     */
    public function __construct() {
        // Direct menu hook with higher priority
        add_action('admin_menu', [$this, 'add_admin_menu'], 99);

        // Product expiration field
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_expiration_field']);
        add_action('woocommerce_process_product_meta', [$this, 'save_expiration_field']);

        // Product list filters
        add_filter('woocommerce_product_filters', [$this, 'add_expiration_filter']);
        add_filter('parse_query', [$this, 'filter_products_by_expiration']);

        // Product list columns
        add_filter('manage_edit-product_columns', [$this, 'add_expiration_column']);
        add_action('manage_product_posts_custom_column', [$this, 'display_expiration_column'], 10, 2);
        add_filter('manage_edit-product_sortable_columns', [$this, 'make_expiration_column_sortable']);

        // Quick edit support
        add_action('quick_edit_custom_box', [$this, 'add_to_quick_edit'], 10, 2);
        add_action('save_post', [$this, 'save_quick_edit_data']);
        add_action('admin_footer', [$this, 'quick_edit_javascript']);
    }

    /**
     * Add menu to WooCommerce admin
     */
    public function add_admin_menu() {
        global $submenu;
        
        // Direct submenu manipulation for reliability
        if (isset($submenu['woocommerce']) && current_user_can('manage_woocommerce')) {
            $submenu['woocommerce'][] = [
                esc_html__('Expiration', 'product-expiration-easy-peasy'),
                'manage_woocommerce',
                'admin.php?page=wc-product-expiration'
            ];
            
            add_submenu_page(
                null,                                         // no parent menu
                esc_html__('Product Expiration Settings', 'product-expiration-easy-peasy'),
                '',                                          // no menu title
                'manage_woocommerce',
                'wc-product-expiration',
                [$this, 'render_settings_page']
            );
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'product-expiration-easy-peasy'));
        }

        require_once WC_PRODUCT_EXPIRATION_PATH . 'views/settings-page.php';
    }

    /**
     * Add expiration date field to product page
     */
    public function add_expiration_field() {
        // Add nonce field for validation
        wp_nonce_field('save_expiration_date', 'expiration_date_nonce');

        woocommerce_wp_text_input([
            'id'          => '_expiration_date',
            'label'       => __('Expiration Date', 'product-expiration-easy-peasy'),
            'placeholder' => 'e.g., 2025-05-10',
            'type'        => 'date',
            'wrapper_class' => 'form-row-wide'
        ]);
    }

    /**
     * Save expiration date field
     */
    public function save_expiration_field($post_id) {
        // Check if the nonce field is set
        if (!isset($_POST['expiration_date_nonce'])) {
            return $post_id;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['expiration_date_nonce']));

        // Verify the nonce
        if (!wp_verify_nonce($nonce, 'save_expiration_date')) {
            return $post_id;
        }

        // Check if user has permission to edit the product
        if (!current_user_can('edit_product', $post_id)) {
            return $post_id;
        }

        // Check if the expiration date is set
        if (isset($_POST['_expiration_date'])) {
            $expiration_date = sanitize_text_field(wp_unslash($_POST['_expiration_date']));

            update_post_meta($post_id, '_expiration_date', $expiration_date);
        }
    }         

    /**
     * Add expiration filter dropdown to product list
     */
    public function add_expiration_filter($filters) {
        global $typenow;
        
        if ('product' !== $typenow) {
            return $filters;
        }

        // Add nonce to the filter form
        $nonce = wp_create_nonce('product_expiration_filter_nonce');
        
        // Get current filter value
        $current_filter = '';
        if (isset($_GET['expiration_filter'])) {
            $current_filter = sanitize_text_field(wp_unslash($_GET['expiration_filter']));
            
            // Properly unslash the nonce before sanitizing it
            if (!isset($_GET['expiration_filter_nonce']) || 
                !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_GET['expiration_filter_nonce'])), 
                    'product_expiration_filter_nonce'
                )
            ) {
                // Nonce verification failed
                wp_die(esc_html__('Security check failed.', 'product-expiration-easy-peasy'));
            }
            // Only accept valid values
            if (!in_array($current_filter, ['expired', 'not_expired', 'no_date'])) {
                $current_filter = '';
            }
        } else {
            $current_filter = '';
        }
        
        // Build filter dropdown
        $dropdown_html = '<select name="expiration_filter" id="dropdown_expiration_filter">';
        $dropdown_html .= '<option value="">' . esc_html__('Expiration Date: All', 'product-expiration-easy-peasy') . '</option>';
        $dropdown_html .= '<option value="expired" ' . selected($current_filter, 'expired', false) . '>' . esc_html__('Expired', 'product-expiration-easy-peasy') . '</option>';
        $dropdown_html .= '<option value="not_expired" ' . selected($current_filter, 'not_expired', false) . '>' . esc_html__('Not Expired', 'product-expiration-easy-peasy') . '</option>';
        $dropdown_html .= '<option value="no_date" ' . selected($current_filter, 'no_date', false) . '>' . esc_html__('No Expiration Date', 'product-expiration-easy-peasy') . '</option>';
        $dropdown_html .= '</select>';
        
        // Return modified filters
        return $filters . $dropdown_html;
    }

    /**
     * Filter products by expiration status
     */
    public function filter_products_by_expiration($query) {
        // Verify the nonce
        if (!isset($_GET['expiration_filter_nonce']) || 
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_GET['expiration_filter_nonce'])), 
            'product_expiration_filter_nonce'
        )
        ) {
        wp_die(esc_html__('Security check failed.', 'product-expiration-easy-peasy'));
        }

        global $pagenow, $typenow;
        
        if ('edit.php' !== $pagenow || 'product' !== $typenow || !isset($_GET['expiration_filter']) || empty($_GET['expiration_filter'])) {
            return;
        }
        
        $meta_query = $query->get('meta_query') ? $query->get('meta_query') : [];
        $today = current_time('timestamp');
        
        switch ($_GET['expiration_filter']) {
            case 'expired':
                $meta_query[] = [
                    'key' => '_expiration_date',
                    'value' => $today,
                    'compare' => '<',
                    'type' => 'NUMERIC'
                ];
                break;
                
            case 'not_expired':
                $meta_query[] = [
                    'key' => '_expiration_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                ];
                break;
                
            case 'no_date':
                $meta_query[] = [
                    'key' => '_expiration_date',
                    'compare' => 'NOT EXISTS'
                ];
                break;
        }
        
        $query->set('meta_query', $meta_query);
    }

    /**
     * Add expiration column to products list
     */
    public function add_expiration_column($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            if ('price' === $key) {
                $new_columns['expiration_date'] = esc_html__('Expiration Date', 'product-expiration-easy-peasy');
            }
        }
        
        return $new_columns;
    }

    /**
     * Display expiration date in column
     */
    public function display_expiration_column($column, $post_id) {
        if ('expiration_date' !== $column) {
            return;
        }
        
        $expiration_date = get_post_meta($post_id, '_expiration_date', true);
        
        if (!empty($expiration_date)) {
            $expiration_timestamp = strtotime($expiration_date);
            $date_format = get_option('date_format');

            echo esc_html(date_i18n($date_format, $expiration_timestamp));

            $today = current_time('timestamp');
            if ($expiration_timestamp < $today) {
                echo ' <span style="color:red;">(' . esc_html__('Expired', 'product-expiration-easy-peasy') . ')</span>';
            }
        } else {
            echo '—';
        }
    }

    /**
     * Make expiration column sortable
     */
    public function make_expiration_column_sortable($columns) {
        $columns['expiration_date'] = 'expiration_date';
        return $columns;
    }

    /**
     * Sort by expiration date
     */
    public function sort_by_expiration($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');
        if ($orderby === 'expiration_date') {
            $query->set('meta_key', '_expiration_date');
            $query->set('orderby', 'meta_value');
        }
    }

    /**
     * Add expiration date field to quick edit
     */
    public function add_to_quick_edit($column_name, $post_type) {
        if ('expiration_date' !== $column_name || 'product' !== $post_type) {
            return;
        }
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php esc_html_e('Expiration Date', 'product-expiration-easy-peasy'); ?></span>
                    <span class="input-text-wrap">
                        <input type="date" name="expiration_date" class="expiration-date-input" value="">
                        <?php wp_nonce_field('product_expiration_quick_edit', 'product_expiration_quick_edit_nonce'); ?>
                    </span>
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Save quick edit data
     */
    public function save_quick_edit_data($post_id) {
        // Check for autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verify user capabilities
        if (!current_user_can('edit_post', $post_id) || 'product' !== get_post_type($post_id)) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['product_expiration_quick_edit_nonce']) || 
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['product_expiration_quick_edit_nonce'])), 
                'product_expiration_quick_edit'
            )
        ) {
            return;
        }
        
        // Process and save the expiration date
        if (isset($_POST['expiration_date']) && !empty($_POST['expiration_date'])) {
            $expiration_date = strtotime(sanitize_text_field(wp_unslash($_POST['expiration_date'])));
            update_post_meta($post_id, '_expiration_date', $expiration_date);
        }
    }

    /**
     * Add JavaScript for quick edit
     */
    public function quick_edit_javascript() {
        global $typenow;
        
        if ('product' !== $typenow) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(function($) {
            var $inline_editor = $('#inlineedit');
            
            $('.editinline').on('click', function() {
                var post_id = $(this).closest('tr').attr('id');
                post_id = post_id.replace('post-', '');
                
                var $expiration_date = $('#' + post_id + ' .column-expiration_date').text().trim();
                
                if ($expiration_date && $expiration_date !== '—') {
                    // Remove '(Expired)' text if present
                    $expiration_date = $expiration_date.replace(/\s*\(.*\)$/, '');
                    
                    // Convert to yyyy-mm-dd format for input
                    var date_parts = $expiration_date.split('/');
                    if (date_parts.length === 3) {
                        $expiration_date = date_parts[2] + '-' + date_parts[1] + '-' + date_parts[0];
                    }
                    
                    $inline_editor.find('.expiration-date-input').val($expiration_date);
                } else {
                    $inline_editor.find('.expiration-date-input').val('');
                }
            });
        });
        </script>
        <?php
    }
}
