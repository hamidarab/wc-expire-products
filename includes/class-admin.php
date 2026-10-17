<?php
/**
 * WordPress and WooCommerce administration integration.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers product fields and list-table controls.
 */
final class Admin {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Resolved date picker calendar, cached for the request.
	 *
	 * @var string
	 */
	private $input_calendar = '';

	/**
	 * Cutoff date cached for one product-list request.
	 *
	 * @var string
	 */
	private $list_cutoff = '';

	/**
	 * Register admin hooks.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_expiration_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_expiration_field' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_expiration_field_to_variations' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_expiration_field_for_variations' ), 10, 2 );

		add_filter( 'woocommerce_product_filters', array( $this, 'add_expiration_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_products_by_expiration' ) );
		add_filter( 'posts_clauses', array( $this, 'apply_expiration_query_clauses' ), 20, 2 );

		add_filter( 'manage_edit-product_columns', array( $this, 'add_expiration_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'display_expiration_column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( $this, 'make_expiration_column_sortable' ) );

		add_action( 'quick_edit_custom_box', array( $this, 'add_to_quick_edit' ), 10, 2 );
		add_action( 'save_post_product', array( $this, 'save_quick_edit_data' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Calendar used by the expiration inputs and the date picker.
	 *
	 * @return string "jalali" or "gregorian".
	 */
	private function get_input_calendar() {
		if ( '' === $this->input_calendar ) {
			$this->input_calendar = Date::input_calendar( $this->settings->get_setting( 'datepicker_calendar' ) );
		}

		return $this->input_calendar;
	}

	/**
	 * Example date in the input format of the picker calendar.
	 *
	 * @return string
	 */
	private function get_date_example() {
		return Date::input_example( $this->get_input_calendar() );
	}

	/**
	 * Attributes that attach the date picker and the inline validator.
	 *
	 * @return array<string, string>
	 */
	private function get_date_input_attributes() {
		return array(
			'data-peep-datepicker' => '',
			'data-calendar'        => $this->get_input_calendar(),
			'autocomplete'         => 'off',
			'spellcheck'           => 'false',
		);
	}

	/**
	 * Add the expiration field to a simple product.
	 *
	 * @return void
	 */
	public function add_expiration_field() {
		wp_nonce_field( 'peep_save_product_expiration', 'peep_product_expiration_nonce' );

		woocommerce_wp_text_input(
			array(
				'id'                => '_expiration_date',
				'value'             => Date::input_value( get_post_meta( get_the_ID(), '_expiration_date', true ), $this->get_input_calendar() ),
				'label'             => __( 'Expiration Date', 'product-expiration-easy-peasy' ),
				'placeholder'       => $this->get_date_example(),
				'type'              => 'text',
				'custom_attributes' => $this->get_date_input_attributes(),
				'wrapper_class'     => 'form-row-wide show_if_simple show_if_external',
				'desc_tip'          => true,
				/* translators: %s: example date in the expected input format. */
				'description'       => sprintf( __( 'Pick a date or type it, for example %s. Leave empty for no expiration date.', 'product-expiration-easy-peasy' ), $this->get_date_example() ),
			)
		);
	}

	/**
	 * Save the expiration date for a product.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	public function save_expiration_field( $post_id ) {
		if ( ! isset( $_POST['peep_product_expiration_nonce'] ) || ! is_scalar( $_POST['peep_product_expiration_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['peep_product_expiration_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'peep_save_product_expiration' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product || ! $product->is_type( array( 'simple', 'external' ) ) ) {
			return;
		}

		if ( isset( $_POST['_expiration_date'] ) && is_scalar( $_POST['_expiration_date'] ) ) {
			$raw_date = sanitize_text_field( wp_unslash( $_POST['_expiration_date'] ) );
			$this->persist_date( $post_id, $raw_date );
		}
	}

	/**
	 * Add an expiration field to a product variation.
	 *
	 * @param int      $loop           Variation loop index.
	 * @param array    $variation_data Variation data.
	 * @param \WP_Post $variation      Variation post.
	 * @return void
	 */
	public function add_expiration_field_to_variations( $loop, $variation_data, $variation ) {
		unset( $variation_data );

		// WooCommerce saves only the changed variations' fields over AJAX, so
		// every variation carries the nonce (without an id, to avoid duplicates).
		printf(
			'<input type="hidden" name="peep_variation_expiration_nonce" value="%s">',
			esc_attr( wp_create_nonce( 'peep_save_variation_expiration' ) )
		);

		woocommerce_wp_text_input(
			array(
				'id'                => "_variation_expiration_date{$loop}",
				'name'              => "_variation_expiration_date[{$loop}]",
				'value'             => Date::input_value( get_post_meta( $variation->ID, '_expiration_date', true ), $this->get_input_calendar() ),
				'label'             => __( 'Expiration Date', 'product-expiration-easy-peasy' ),
				'placeholder'       => $this->get_date_example(),
				'type'              => 'text',
				'custom_attributes' => $this->get_date_input_attributes(),
				'wrapper_class'     => 'form-row form-row-full',
				'desc_tip'          => true,
				'description'       => __( 'Set an expiration date for this variation.', 'product-expiration-easy-peasy' ),
			)
		);
	}

	/**
	 * Save the expiration date for one product variation.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop index.
	 * @return void
	 */
	public function save_expiration_field_for_variations( $variation_id, $loop ) {
		if ( ! isset( $_POST['peep_variation_expiration_nonce'] ) || ! is_scalar( $_POST['peep_variation_expiration_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['peep_variation_expiration_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'peep_save_variation_expiration' ) || ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		if ( ! isset( $_POST['_variation_expiration_date'] ) || ! is_array( $_POST['_variation_expiration_date'] ) ) {
			return;
		}

		$raw_dates = wp_unslash( $_POST['_variation_expiration_date'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each scalar entry is sanitized in the loop below.
		$dates     = array();

		foreach ( $raw_dates as $index => $raw_date ) {
			if ( is_scalar( $raw_date ) ) {
				$dates[ $index ] = sanitize_text_field( (string) $raw_date );
			}
		}

		if ( array_key_exists( $loop, $dates ) ) {
			$this->persist_date( $variation_id, $dates[ $loop ] );
		}
	}

	/**
	 * Append the expiration filter to the product list.
	 *
	 * @param string $filters Existing product filters HTML.
	 * @return string
	 */
	public function add_expiration_filter( $filters ) {
		$current_filter = $this->get_requested_expiration_filter();

		$filters .= '<select name="expiration_filter" id="dropdown_expiration_filter">';
		$filters .= '<option value="">' . esc_html__( 'Expiration Date: All', 'product-expiration-easy-peasy' ) . '</option>';
		$filters .= '<option value="expired" ' . selected( $current_filter, 'expired', false ) . '>' . esc_html__( 'Expired', 'product-expiration-easy-peasy' ) . '</option>';
		$filters .= '<option value="not_expired" ' . selected( $current_filter, 'not_expired', false ) . '>' . esc_html__( 'Not Expired', 'product-expiration-easy-peasy' ) . '</option>';
		$filters .= '<option value="no_date" ' . selected( $current_filter, 'no_date', false ) . '>' . esc_html__( 'No Expiration Date', 'product-expiration-easy-peasy' ) . '</option>';
		$filters .= '</select>';

		return $filters;
	}

	/**
	 * Apply the selected expiration filter to the product list query.
	 *
	 * @param \WP_Query $query WordPress query.
	 * @return void
	 */
	public function filter_products_by_expiration( $query ) {
		if ( ! $this->is_product_list_query( $query ) ) {
			return;
		}

		$filter = $this->get_requested_expiration_filter();

		if ( '' === $filter ) {
			return;
		}

		$query->set( 'peep_expiration_filter', $filter );
	}

	/**
	 * Add the expiration column after the price column.
	 *
	 * @param array<string, string> $columns Product columns.
	 * @return array<string, string>
	 */
	public function add_expiration_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;

			if ( 'price' === $key ) {
				$new_columns['expiration_date'] = __( 'Expiration Date', 'product-expiration-easy-peasy' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render the expiration value in the product list.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 * @return void
	 */
	public function display_expiration_column( $column, $post_id ) {
		if ( 'expiration_date' !== $column ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product ) {
			echo '&mdash;';
			return;
		}

		$expiration_date = '';
		$managed_count   = 0;

		if ( $product->is_type( 'variable' ) ) {
			$dates     = array();
			$child_ids = $product->get_children();

			foreach ( $child_ids as $child_id ) {
				$date = Date::sanitize( get_post_meta( $child_id, '_expiration_date', true ) );

				if ( '' !== $date ) {
					$dates[] = $date;
				}

				if ( 'yes' === get_post_meta( $child_id, '_peep_stock_managed', true ) ) {
					++$managed_count;
				}
			}

			if ( ! empty( $dates ) ) {
				sort( $dates, SORT_STRING );
				$expiration_date = reset( $dates );
			} elseif ( empty( $child_ids ) ) {
				$expiration_date = Date::sanitize( get_post_meta( $post_id, '_expiration_date', true ) );
			}
		} else {
			$expiration_date = Date::sanitize( get_post_meta( $post_id, '_expiration_date', true ) );
			$managed_count   = 'yes' === get_post_meta( $post_id, '_peep_stock_managed', true ) ? 1 : 0;
		}

		if ( '' === $expiration_date ) {
			echo '&mdash;';
			return;
		}

		if ( '' === $this->list_cutoff ) {
			$this->list_cutoff = Cron::get_cutoff_date( $this->settings );
		}

		if ( $expiration_date < Date::today() ) {
			$state = array( 'expired', 'alert', __( 'Expired', 'product-expiration-easy-peasy' ) );
		} elseif ( $expiration_date <= $this->list_cutoff ) {
			$state = array( 'near', 'clock', __( 'Near expiry', 'product-expiration-easy-peasy' ) );
		} else {
			$state = array( 'ok', 'check', __( 'Not expired', 'product-expiration-easy-peasy' ) );
		}

		$svg_html = Icons::allowed_html();

		echo '<span class="expiration-formatted">' . esc_html( Date::format( $expiration_date, 'default' ) ) . '</span>';
		echo '<span class="expiration-hidden" data-date="' . esc_attr( Date::input_value( $expiration_date, $this->get_input_calendar() ) ) . '"></span>';
		echo '<span class="peep-status-list">';
		echo '<span class="peep-status peep-status--' . esc_attr( $state[0] ) . '">' . wp_kses( Icons::get( $state[1] ), $svg_html ) . '<span>' . esc_html( $state[2] ) . '</span></span>';

		if ( $managed_count > 0 ) {
			$managed_label = $product->is_type( 'variable' )
				/* translators: %s: number of variations. */
				? sprintf( _n( '%s variation auto out of stock', '%s variations auto out of stock', $managed_count, 'product-expiration-easy-peasy' ), number_format_i18n( $managed_count ) )
				: __( 'Auto out of stock', 'product-expiration-easy-peasy' );

			echo '<span class="peep-status peep-status--managed">' . wp_kses( Icons::get( 'box' ), $svg_html ) . '<span>' . esc_html( $managed_label ) . '</span></span>';
		}

		echo '</span>';
	}

	/**
	 * Make the expiration column sortable.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function make_expiration_column_sortable( $columns ) {
		$columns['expiration_date'] = 'expiration_date';

		return $columns;
	}

	/**
	 * Filter and sort products by their effective expiration date.
	 *
	 * Variable products use the earliest date among their variations; other
	 * supported product types use their own expiration metadata.
	 *
	 * @param array<string, string> $clauses SQL clauses.
	 * @param \WP_Query             $query   WordPress query.
	 * @return array<string, string>
	 */
	public function apply_expiration_query_clauses( $clauses, $query ) {
		if ( ! $this->is_product_list_query( $query ) ) {
			return $clauses;
		}

		$filter  = (string) $query->get( 'peep_expiration_filter' );
		$is_sort = 'expiration_date' === $query->get( 'orderby' );

		if ( '' === $filter && ! $is_sort ) {
			return $clauses;
		}

		global $wpdb;

		$effective_date = $this->get_effective_expiration_sql();

		if ( 'expired' === $filter ) {
			$clauses['where'] .= $wpdb->prepare( " AND ({$effective_date}) < %s", Date::today() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The expression is internally prepared and contains no request data.
		} elseif ( 'not_expired' === $filter ) {
			$clauses['where'] .= $wpdb->prepare( " AND ({$effective_date}) >= %s", Date::today() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The expression is internally prepared and contains no request data.
		} elseif ( 'no_date' === $filter ) {
			$clauses['where'] .= " AND ({$effective_date}) IS NULL"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The expression is internally prepared and contains no request data.
		}

		if ( $is_sort ) {
			$direction          = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';
			$clauses['orderby'] = "{$effective_date} {$direction}, {$wpdb->posts}.ID {$direction}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direction is whitelisted and identifiers come from wpdb.
		}

		return $clauses;
	}

	/**
	 * Build a prepared SQL expression for the effective list-table date.
	 *
	 * @return string
	 */
	private function get_effective_expiration_sql() {
		global $wpdb;

		$has_variations = $wpdb->prepare(
			'EXISTS (SELECT 1 FROM %i AS peep_variation_type WHERE peep_variation_type.post_parent = %i.ID AND peep_variation_type.post_type = %s AND peep_variation_type.post_status IN (%s, %s))',
			$wpdb->posts,
			$wpdb->posts,
			'product_variation',
			'publish',
			'private'
		);
		$variation_date = $wpdb->prepare(
			'(SELECT MIN(NULLIF(peep_variation_meta.meta_value, %s)) FROM %i AS peep_variation INNER JOIN %i AS peep_variation_meta ON peep_variation_meta.post_id = peep_variation.ID WHERE peep_variation.post_parent = %i.ID AND peep_variation.post_type = %s AND peep_variation.post_status IN (%s, %s) AND peep_variation_meta.meta_key = %s)',
			'',
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->posts,
			'product_variation',
			'publish',
			'private',
			'_expiration_date'
		);
		$product_date   = $wpdb->prepare(
			'(SELECT MIN(NULLIF(peep_product_meta.meta_value, %s)) FROM %i AS peep_product_meta WHERE peep_product_meta.post_id = %i.ID AND peep_product_meta.meta_key = %s)',
			'',
			$wpdb->postmeta,
			$wpdb->posts,
			'_expiration_date'
		);

		return "CASE WHEN {$has_variations} THEN {$variation_date} ELSE {$product_date} END";
	}

	/**
	 * Add the expiration input to Quick Edit.
	 *
	 * @param string $column_name Column key.
	 * @param string $post_type   Post type.
	 * @return void
	 */
	public function add_to_quick_edit( $column_name, $post_type ) {
		if ( 'expiration_date' !== $column_name || 'product' !== $post_type ) {
			return;
		}
		$attributes = '';

		foreach ( $this->get_date_input_attributes() as $name => $value ) {
			$attributes .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		?>
		<fieldset class="inline-edit-col-right peep-quick-edit-fieldset">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Expiration Date', 'product-expiration-easy-peasy' ); ?></span>
					<span class="input-text-wrap">
						<input type="text" name="expiration_date" class="expiration-date-input" value="" placeholder="<?php echo esc_attr( $this->get_date_example() ); ?>"<?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute names, values escaped above. ?>>
						<?php wp_nonce_field( 'peep_quick_edit_expiration', 'peep_quick_edit_nonce' ); ?>
					</span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save a Quick Edit expiration value.
	 *
	 * @param int $post_id Product ID.
	 * @return void
	 */
	public function save_quick_edit_data( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['peep_quick_edit_nonce'] ) || ! is_scalar( $_POST['peep_quick_edit_nonce'] ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['peep_quick_edit_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'peep_quick_edit_expiration' ) ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product || ! $product->is_type( array( 'simple', 'external' ) ) || ! isset( $_POST['expiration_date'] ) || ! is_scalar( $_POST['expiration_date'] ) ) {
			return;
		}

		$raw_date = sanitize_text_field( wp_unslash( $_POST['expiration_date'] ) );
		$this->persist_date( $post_id, $raw_date );
	}

	/**
	 * Load the date picker and field scripts on product screens.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$screen = get_current_screen();

		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'product-expiration-easy-peasy-admin',
			PRODUCT_EXPIRATION_EASY_PEASY_URL . 'admin/css/admin-style.css',
			array(),
			PRODUCT_EXPIRATION_EASY_PEASY_VERSION
		);

		Assets::enqueue_datepicker();

		wp_enqueue_script(
			'product-expiration-easy-peasy-admin',
			PRODUCT_EXPIRATION_EASY_PEASY_URL . 'admin/js/admin-script.js',
			array( 'jquery', Assets::JALALI, Assets::DATEPICKER ),
			PRODUCT_EXPIRATION_EASY_PEASY_VERSION,
			true
		);

		wp_localize_script(
			'product-expiration-easy-peasy-admin',
			'peepAdmin',
			array(
				/* translators: %s: example date in the expected input format. */
				'invalid' => sprintf( __( 'Enter a valid date such as %s, or leave the field empty.', 'product-expiration-easy-peasy' ), $this->get_date_example() ),
			)
		);
	}

	/**
	 * Return a whitelisted product-list filter from the request.
	 *
	 * @return string
	 */
	private function get_requested_expiration_filter() {
		if ( ! isset( $_GET['expiration_filter'] ) || ! is_scalar( $_GET['expiration_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
			return '';
		}

		$filter = sanitize_key( wp_unslash( $_GET['expiration_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.

		return in_array( $filter, array( 'expired', 'not_expired', 'no_date' ), true ) ? $filter : '';
	}

	/**
	 * Determine whether a query is the editable product list.
	 *
	 * @param \WP_Query $query WordPress query.
	 * @return bool
	 */
	private function is_product_list_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || ! current_user_can( 'edit_products' ) ) {
			return false;
		}

		$post_type = $query->get( 'post_type' );

		return 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) );
	}

	/**
	 * Store a valid date or delete the metadata when explicitly cleared.
	 *
	 * @param int   $post_id Product or variation ID.
	 * @param mixed $raw     Raw date value.
	 * @return void
	 */
	private function persist_date( $post_id, $raw ) {
		if ( ! is_scalar( $raw ) ) {
			return;
		}

		$raw = sanitize_text_field( (string) $raw );

		if ( '' === $raw ) {
			Stock::restore( $post_id );
			delete_post_meta( $post_id, '_expiration_date' );
			Stats::flush();
			return;
		}

		$date = Date::sanitize_input( $raw );

		if ( '' === $date ) {
			if ( class_exists( '\WC_Admin_Meta_Boxes' ) ) {
				\WC_Admin_Meta_Boxes::add_error( __( 'Enter a valid expiration date. Persian sites accept Jalali Y-m-d; other locales use Gregorian Y-m-d.', 'product-expiration-easy-peasy' ) );
			}

			return;
		}

		update_post_meta( $post_id, '_expiration_date', $date );
		Stats::flush();

		if ( $date > Cron::get_cutoff_date( $this->settings ) ) {
			Stock::restore( $post_id );
		}
	}
}
