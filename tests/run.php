<?php
/**
 * Standalone smoke tests for bootstrap, validation, Jalali formatting, and
 * the expiration worker.
 *
 * Run with: php tests/run.php
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'WP_LANG_DIR', '/srv/wp-content/languages' );

$peep_test_actions    = array();
$peep_test_activation = null;
$peep_test_scheduled  = false;
$peep_test_options    = array();
$peep_test_uuid       = 0;
$peep_test_events     = array();
$peep_test_i18n_calls = 0;
$peep_test_i18n_map   = array();

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $peep_test_actions, $peep_test_events;
	$peep_test_actions[ $hook ][ $priority ][] = array( $callback, $accepted_args );
	$peep_test_events[]                        = 'hook:' . $hook;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	add_action( $hook, $callback, $priority, $accepted_args );
}

function register_activation_hook( $file, $callback ) {
	global $peep_test_activation;
	$peep_test_activation = $callback;
}

function register_deactivation_hook( $file, $callback ) {
	unset( $file, $callback );
}

function load_plugin_textdomain() {
	global $peep_test_events;
	$peep_test_events[] = 'textdomain';
	return true;
}

function __( $text ) {
	global $peep_test_i18n_calls, $peep_test_i18n_map;
	++$peep_test_i18n_calls;
	return $peep_test_i18n_map[ $text ] ?? $text;
}

function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', (string) $path );
}

function peep_test_run_hook( $hook ) {
	global $peep_test_actions;

	$callbacks = $peep_test_actions[ $hook ] ?? array();
	ksort( $callbacks );

	foreach ( $callbacks as $priority_callbacks ) {
		foreach ( $priority_callbacks as $callback ) {
			call_user_func( $callback[0] );
		}
	}
}

function peep_test_hook_priority( $hook, $callback ) {
	global $peep_test_actions;

	foreach ( $peep_test_actions[ $hook ] ?? array() as $priority => $callbacks ) {
		foreach ( $callbacks as $registered ) {
			if ( $callback === $registered[0] ) {
				return $priority;
			}
		}
	}

	return null;
}

function wp_next_scheduled() {
	return false;
}

function wp_schedule_event() {
	global $peep_test_scheduled;
	$peep_test_scheduled = true;
	return true;
}

function wp_clear_scheduled_hook() {
	return 0;
}

function delete_option( $key ) {
	global $peep_test_options;
	unset( $peep_test_options[ $key ] );
	return true;
}

function get_option( $key, $default = false ) {
	global $peep_test_options;
	return array_key_exists( $key, $peep_test_options ) ? $peep_test_options[ $key ] : $default;
}

function update_option( $key, $value ) {
	global $peep_test_options;
	$peep_test_options[ $key ] = $value;
	return true;
}

function add_option( $key, $value ) {
	global $peep_test_options;

	if ( array_key_exists( $key, $peep_test_options ) ) {
		return false;
	}

	$peep_test_options[ $key ] = $value;
	return true;
}

function maybe_serialize( $value ) {
	return is_scalar( $value ) || null === $value ? (string) $value : serialize( $value );
}

function wp_generate_uuid4() {
	global $peep_test_uuid;
	++$peep_test_uuid;
	return '00000000-0000-4000-8000-' . str_pad( (string) $peep_test_uuid, 12, '0', STR_PAD_LEFT );
}

function wp_cache_delete() {
	return true;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, $args );
}

function wp_unslash( $value ) {
	return $value;
}

function sanitize_text_field( $value ) {
	$value = trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) );

	// Like _sanitize_text_fields(), drop percent-encoded octets such as "%da".
	while ( preg_match( '/%[a-f0-9]{2}/i', $value, $match ) ) {
		$value = str_replace( $match[0], '', $value );
	}

	return trim( $value );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_email( $value ) {
	return filter_var( (string) $value, FILTER_SANITIZE_EMAIL );
}

function absint( $value ) {
	return abs( (int) $value );
}

function determine_locale() {
	return $GLOBALS['peep_test_locale'] ?? 'fa_IR';
}

function wp_timezone() {
	return new DateTimeZone( 'Asia/Tehran' );
}

function wp_date( $format, $timestamp = null, $timezone = null ) {
	$date = new DateTimeImmutable( '@' . ( null === $timestamp ? time() : $timestamp ) );
	return $date->setTimezone( $timezone ?: wp_timezone() )->format( $format );
}

function is_admin() {
	return true;
}

function is_multisite() {
	return false;
}

// Minimal WooCommerce and WordPress doubles for the expiration worker.
$peep_test_meta     = array();
$peep_test_products = array();
$peep_test_mail     = array();
$peep_test_single   = array();

function get_post_meta( $post_id, $key, $single = false ) {
	global $peep_test_meta;
	return isset( $peep_test_meta[ $post_id ][ $key ] ) ? $peep_test_meta[ $post_id ][ $key ] : '';
}

function update_post_meta( $post_id, $key, $value ) {
	global $peep_test_meta;
	$peep_test_meta[ $post_id ][ $key ] = $value;
	return true;
}

function delete_post_meta( $post_id, $key ) {
	global $peep_test_meta;
	unset( $peep_test_meta[ $post_id ][ $key ] );
	return true;
}

function wc_get_product( $product_id ) {
	global $peep_test_products;
	return isset( $peep_test_products[ $product_id ] ) ? $peep_test_products[ $product_id ] : false;
}

function wc_update_product_stock_status( $product_id, $status ) {
	global $peep_test_products;
	$peep_test_products[ $product_id ]->set_stock_status( $status );
}

function wp_mail( $to, $subject, $body ) {
	global $peep_test_mail;
	$peep_test_mail[] = compact( 'to', 'subject', 'body' );
	return true;
}

function wp_schedule_single_event( $timestamp, $hook ) {
	global $peep_test_single;
	$peep_test_single[] = $hook;
	return true;
}

function wp_reset_postdata() {}

$peep_test_queue = array();

function as_enqueue_async_action( $hook, $args, $group ) {
	global $peep_test_queue;
	$peep_test_queue[] = array( $hook, $args, $group );
	return count( $peep_test_queue );
}

function as_has_scheduled_action( $hook, $args = null, $group = '' ) {
	global $peep_test_queue;

	foreach ( $peep_test_queue as $queued ) {
		if ( $hook === $queued[0] && ( null === $args || $args === $queued[1] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Run queued batches the way Action Scheduler would, one after another.
 */
function peep_test_drain( $job ) {
	global $peep_test_queue;
	$runs = 0;

	while ( $peep_test_queue ) {
		list( , $args ) = array_shift( $peep_test_queue );
		$job->run_batch( ...$args );
		++$runs;
	}

	return $runs;
}

/**
 * Dated products at or before the cutoff, as the job's SQL selects them.
 */
function peep_test_due_ids( $cutoff, $after = 0 ) {
	global $peep_test_meta;
	$ids = array();

	foreach ( $peep_test_meta as $post_id => $meta ) {
		$date = $meta['_expiration_date'] ?? '';

		if ( '' !== $date && $date <= $cutoff && $post_id > $after ) {
			$ids[] = $post_id;
		}
	}

	sort( $ids );
	return $ids;
}

function get_transient( $key ) {
	return get_option( '_transient_' . $key );
}

function set_transient( $key, $value ) {
	return update_option( '_transient_' . $key, $value );
}

function delete_transient( $key ) {
	return delete_option( '_transient_' . $key );
}

function get_users() {
	return array();
}

function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

function add_query_arg( $args, $url ) {
	return $url . '?' . http_build_query( $args );
}

function esc_url_raw( $url ) {
	return $url;
}

function esc_url( $url ) {
	return $url;
}

function esc_html__( $text ) {
	return __( $text );
}

function wc_get_product_stock_status_options() {
	return array(
		'instock'     => 'In stock',
		'outofstock'  => 'Out of stock',
		'onbackorder' => 'On backorder',
	);
}

function wc_get_formatted_variation() {
	return '';
}

function wp_get_post_terms() {
	return array( 'Food', 'Drinks' );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

class Peep_Test_Product {
	private $id;
	private $status;
	private $type;

	public function __construct( $id, $status = 'instock', $type = 'simple' ) {
		$this->id     = $id;
		$this->status = $status;
		$this->type   = $type;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_name() {
		return 'Product ' . $this->id;
	}

	public function get_sku() {
		return 0 === $this->id % 2 ? '=SUM(A1)' : 'SKU-' . $this->id;
	}

	public function get_parent_id() {
		return 0;
	}

	public function is_type( $type ) {
		return in_array( $this->type, (array) $type, true );
	}

	public function get_stock_status() {
		return $this->status;
	}

	public function set_stock_status( $status ) {
		$this->status = $status;
	}

	public function managing_stock() {
		return false;
	}

	public function get_stock_quantity() {
		return null;
	}

	public function save() {
		return $this->id;
	}
}

/**
 * Evaluates the two meta-query shapes used by the Cron worker.
 */
class WP_Query {
	public $posts = array();

	public function __construct( $args ) {
		global $peep_test_meta;

		$matches = array();
		$exclude = isset( $args['post__not_in'] ) ? $args['post__not_in'] : array();

		foreach ( array_keys( $peep_test_meta ) as $post_id ) {
			if ( in_array( $post_id, $exclude, true ) ) {
				continue;
			}

			$ok = true;

			foreach ( $args['meta_query'] as $key => $clause ) {
				if ( 'relation' === $key ) {
					continue;
				}

				$value   = get_post_meta( $post_id, $clause['key'] );
				$compare = isset( $clause['compare'] ) ? $clause['compare'] : '=';

				if ( '' === $value
					|| ( '=' === $compare && $value !== $clause['value'] )
					|| ( '<=' === $compare && $value > $clause['value'] )
					|| ( '>' === $compare && $value <= $clause['value'] ) ) {
					$ok = false;
				}
			}

			if ( $ok ) {
				$matches[] = $post_id;
			}
		}

		sort( $matches );
		$page        = isset( $args['paged'] ) ? (int) $args['paged'] : 1;
		$this->posts = array_slice( $matches, ( $page - 1 ) * $args['posts_per_page'], $args['posts_per_page'] );
	}
}

function peep_test_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

class Peep_Test_Wpdb {
	public $options  = 'wp_options';
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public $terms    = 'wp_terms';
	public $term_taxonomy      = 'wp_term_taxonomy';
	public $term_relationships = 'wp_term_relationships';
	public $reads    = 0;

	public function get_var( $prepared ) {
		list( , $arguments ) = $prepared;
		return (string) count( peep_test_due_ids( $arguments[3] ) );
	}

	public function get_col( $prepared ) {
		list( , $arguments ) = $prepared;
		return array_map( 'strval', array_slice( peep_test_due_ids( $arguments[3], $arguments[4] ), 0, $arguments[8] ) );
	}

	/**
	 * Evaluates Exporter::select() from its prepared arguments.
	 */
	public function get_results( $prepared ) {
		global $peep_test_meta;
		list( , $a ) = $prepared;
		$rows        = array();

		foreach ( $peep_test_meta as $post_id => $meta ) {
			$date = $meta['_expiration_date'] ?? '';

			if ( '' !== $date && ( ( $a[12] && $date < $a[13] ) || ( $a[14] && $date >= $a[15] && $date <= $a[16] ) ) ) {
				$rows[] = array( (string) $post_id, $date );
			}
		}

		usort( $rows, static fn( $x, $y ) => array( $x[1], (int) $x[0] ) <=> array( $y[1], (int) $y[0] ) );

		return array_slice( $rows, 0, $a[17] );
	}

	public function get_row( $prepared = null ) {
		global $peep_test_meta;

		// Exporter::counts(): today, from, to, today, from, to come first.
		if ( is_array( $prepared ) && false !== strpos( $prepared[0], 'any_scope' ) ) {
			list( $today, $from, $to ) = $prepared[1];
			$counts                    = array( 'expired' => 0, 'soon' => 0, 'any_scope' => 0 );

			foreach ( $peep_test_meta as $meta ) {
				$date    = $meta['_expiration_date'] ?? '';
				$expired = '' !== $date && $date < $today;
				$soon    = '' !== $date && $date >= $from && $date <= $to;
				$counts['expired']   += (int) $expired;
				$counts['soon']      += (int) $soon;
				$counts['any_scope'] += (int) ( $expired || $soon );
			}

			return $counts;
		}

		++$this->reads;
		return array(
			'dated'   => '12',
			'expired' => '3',
			'managed' => '2',
		);
	}

	public function prepare( $query, ...$arguments ) {
		// Like wpdb::prepare(), a single array holds all the arguments.
		if ( 1 === count( $arguments ) && is_array( $arguments[0] ) ) {
			$arguments = $arguments[0];
		}

		return array( $query, $arguments );
	}

	public function query( $prepared ) {
		global $peep_test_options;

		list( $query, $arguments ) = $prepared;

		if ( 0 === strpos( $query, 'UPDATE ' ) ) {
			list( , $new_value, $option_name, $old_value ) = $arguments;

			if ( isset( $peep_test_options[ $option_name ] ) && maybe_serialize( $peep_test_options[ $option_name ] ) === $old_value ) {
				$peep_test_options[ $option_name ] = is_string( $new_value ) && 0 === strpos( $new_value, 'a:' ) ? unserialize( $new_value ) : $new_value;
				return 1;
			}

			return 0;
		}

		if ( 0 === strpos( $query, 'DELETE ' ) ) {
			list( , $option_name, $lock_value ) = $arguments;

			if ( isset( $peep_test_options[ $option_name ] ) && $peep_test_options[ $option_name ] === $lock_value ) {
				unset( $peep_test_options[ $option_name ] );
				return 1;
			}
		}

		return 0;
	}
}

$wpdb = new Peep_Test_Wpdb();

require dirname( __DIR__ ) . '/product-expiration-easy-peasy.php';

// Load order: nothing is translated before the text domain loads on init.
peep_test_run_hook( 'plugins_loaded' );
peep_test_expect( 0 === $peep_test_i18n_calls, 'A translation function ran during plugins_loaded, before the text domain is loaded.' );
peep_test_expect( ! in_array( 'textdomain', $peep_test_events, true ), 'The text domain was loaded before init.' );

$peep_test_textdomain_priority = peep_test_hook_priority( 'init', 'ProductExpirationEasyPeasy\\load_textdomain' );
$peep_test_start_priority      = peep_test_hook_priority( 'init', 'ProductExpirationEasyPeasy\\start' );
peep_test_expect( null !== $peep_test_textdomain_priority, 'The text domain loader is not hooked on init.' );
peep_test_expect( null !== $peep_test_start_priority && $peep_test_start_priority > $peep_test_textdomain_priority, 'The plugin starts before its text domain is loaded.' );

$peep_test_events = array();
peep_test_run_hook( 'init' );
$peep_test_textdomain_at = array_search( 'textdomain', $peep_test_events, true );
$peep_test_notice_at     = array_search( 'hook:admin_notices', $peep_test_events, true );
peep_test_expect( false !== $peep_test_textdomain_at && false !== $peep_test_notice_at && $peep_test_textdomain_at < $peep_test_notice_at, 'Main started before the text domain was loaded.' );
peep_test_expect( 1 === count( array_keys( $peep_test_events, 'textdomain', true ) ), 'The text domain was loaded more than once.' );

peep_test_expect(
	function_exists( 'ProductExpirationEasyPeasy\\product_expiration_easy_peasy_is_license_active' ),
	'License helper was not loaded before bootstrap.'
);
peep_test_expect(
	false === ProductExpirationEasyPeasy\product_expiration_easy_peasy_is_license_active(),
	'License helper must fail closed without ionCube, not terminate the request.'
);

$peep_plugin_links = ProductExpirationEasyPeasy\add_plugin_action_links( array( '<a href="#">Deactivate</a>' ) );
peep_test_expect( 2 === count( $peep_plugin_links ), 'The plugin-row Settings link was not added.' );
peep_test_expect(
	false !== strpos( reset( $peep_plugin_links ), 'admin.php?page=product-expiration-easy-peasy-settings' ),
	'The plugin-row Settings link does not target the expiration settings page.'
);

call_user_func( $peep_test_activation );
peep_test_expect( true === $peep_test_scheduled, 'Activation did not schedule the daily cron event.' );

peep_test_expect( '1404-1-1' === implode( '-', ProductExpirationEasyPeasy\Jalali::convert( 2025, 3, 21 ) ), 'Jalali Nowruz conversion failed.' );
peep_test_expect( array( 2025, 3, 21 ) === ProductExpirationEasyPeasy\Jalali::to_gregorian( 1404, 1, 1 ), 'Jalali input conversion failed.' );
peep_test_expect( array() === ProductExpirationEasyPeasy\Jalali::to_gregorian( 1402, 12, 30 ), 'Invalid non-leap Esfand date was accepted.' );
peep_test_expect( '' === ProductExpirationEasyPeasy\Date::sanitize( '2025-99-99' ), 'Invalid calendar date was accepted.' );
peep_test_expect( '2025-02-28' === ProductExpirationEasyPeasy\Date::sanitize( '2025-02-28' ), 'Valid Gregorian date was rejected.' );
peep_test_expect( '2025-03-21' === ProductExpirationEasyPeasy\Date::sanitize_input( '۱۴۰۴/۰۱/۰۱' ), 'Persian-digit Jalali input was not normalized.' );
peep_test_expect( '2025-03-20' === ProductExpirationEasyPeasy\Date::sanitize_input( '١٤٠٣-١٢-٣٠' ), 'Arabic-digit Jalali input was not normalized.' );
peep_test_expect( '' === ProductExpirationEasyPeasy\Date::sanitize_input( '1403/12-30' ), 'Mixed date separators were accepted.' );
peep_test_expect( '۱۴۰۴/۰۱/۰۱' === ProductExpirationEasyPeasy\Date::format( '2025-03-21', 'Y/m/d' ), 'Persian Jalali formatting failed.' );

$settings = new ProductExpirationEasyPeasy\Settings();
$settings->save_settings(
	array(
		'notification_period_type' => 'invalid',
		'notification_period'      => 9999,
		'show_expiration'          => 'invalid',
		'display_text'             => '<b>Expires:</b> %date%',
		'date_format'              => 'invalid',
		'display_position'         => 'invalid',
		'email_recipients'         => array( 'admin', 'attacker', array( 'nested' ) ),
		'custom_email'             => 'manager@example.test',
		'show_in_order_email'      => 'no',
	)
);
$saved = $settings->get_settings();

peep_test_expect( 'days' === $saved['notification_period_type'], 'Invalid period type was not rejected.' );
peep_test_expect( 365 === $saved['notification_period'], 'Notification period was not bounded.' );
peep_test_expect( array( 'admin' ) === $saved['email_recipients'], 'Recipient whitelist failed.' );
peep_test_expect( 'Expires: %date%' === $saved['display_text'], 'Display text was not sanitized.' );

$peep_test_options['peep_settings'] = array( 'notification_priod_type' => 'weeks' );
peep_test_expect( 'weeks' === $settings->get_setting( 'notification_period_type' ), 'Legacy misspelled period type was not migrated.' );

$settings->save_settings( array() );
peep_test_expect( array() === $settings->get_setting( 'email_recipients' ), 'Clearing every email recipient did not disable notifications.' );

// Settings keep every 3.2.0 key (plus datepicker_calendar) and ignore request-only fields.
$settings->save_settings(
	array(
		'action'                   => 'peep_save_settings',
		'peep_settings_nonce'      => 'abc',
		'peep_tab'                 => 'display',
		'notification_period_type' => 'months',
		'notification_period'      => '2',
		'show_expiration'          => 'no',
		'display_text'             => 'Best before %date%',
		'date_format'              => 'Y-m-d',
		'display_position'         => 'after_title',
		'email_recipients'         => array( 'shop_manager', 'custom' ),
		'custom_email'             => 'ops@example.test',
		'show_in_order_email'      => 'no',
	)
);
peep_test_expect(
	array( 'notification_period_type', 'notification_period', 'show_expiration', 'display_text', 'date_format', 'display_position', 'email_recipients', 'custom_email', 'show_in_order_email', 'datepicker_calendar' ) === array_keys( $peep_test_options['peep_settings'] ),
	'Saved option keys changed or request-only fields leaked into peep_settings.'
);
peep_test_expect( 'no' === $settings->get_setting( 'show_expiration' ) && 'no' === $settings->get_setting( 'show_in_order_email' ), 'Switch values are no longer stored as yes/no.' );

// Cutoff calculation follows the configured unit in the site timezone.
$peep_today = new DateTimeImmutable( 'today', wp_timezone() );
$settings->save_settings( array( 'notification_period' => 10, 'notification_period_type' => 'days' ) );
peep_test_expect( $peep_today->modify( '+10 days' )->format( 'Y-m-d' ) === ProductExpirationEasyPeasy\Cron::get_cutoff_date( $settings ), 'Day cutoff is wrong.' );
$settings->save_settings( array( 'notification_period' => 2, 'notification_period_type' => 'weeks' ) );
peep_test_expect( $peep_today->modify( '+14 days' )->format( 'Y-m-d' ) === ProductExpirationEasyPeasy\Cron::get_cutoff_date( $settings ), 'Week cutoff is wrong.' );
$settings->save_settings( array( 'notification_period' => 3, 'notification_period_type' => 'months' ) );
peep_test_expect( $peep_today->add( new DateInterval( 'P3M' ) )->format( 'Y-m-d' ) === ProductExpirationEasyPeasy\Cron::get_cutoff_date( $settings ), 'Month cutoff is wrong.' );

// One run across several 50-product batches sends exactly one report.
$settings->save_settings(
	array(
		'notification_period'      => 30,
		'notification_period_type' => 'days',
		'email_recipients'         => array( 'custom' ),
		'custom_email'             => 'ops@example.test',
	)
);
$peep_soon = $peep_today->modify( '+5 days' )->format( 'Y-m-d' );
$peep_late = $peep_today->modify( '+200 days' )->format( 'Y-m-d' );

for ( $peep_id = 1; $peep_id <= 120; $peep_id++ ) {
	$peep_test_products[ $peep_id ] = new Peep_Test_Product( $peep_id );
	update_post_meta( $peep_id, '_expiration_date', $peep_soon );
}

$peep_test_products[500] = new Peep_Test_Product( 500 );
update_post_meta( 500, '_expiration_date', $peep_late );

$peep_job    = new ProductExpirationEasyPeasy\Check_Job( $settings );
$peep_worker = new ProductExpirationEasyPeasy\Cron( $settings, $peep_job );

// A check runs in queued 50-item batches until done, and sends one report.
$peep_state = $peep_job->start( 'manual' );
peep_test_expect( 'running' === $peep_state['status'] && 120 === $peep_state['total'] && 0 === $peep_state['processed'], 'A started check has the wrong initial state.' );
peep_test_expect( 1 === count( $peep_test_queue ) && 'peep_check_batch' === $peep_test_queue[0][0] && 'peep' === $peep_test_queue[0][2], 'The first batch was not queued in the peep group.' );
peep_test_expect( $peep_state['id'] === $peep_job->start( 'cron' )['id'] && 1 === count( $peep_test_queue ), 'A second start did not return the running job.' );
peep_test_expect( array() === $peep_test_mail, 'A report was sent before the check finished.' );

peep_test_expect( 3 === peep_test_drain( $peep_job ), 'Expected three batches for 120 products.' );
$peep_state = ProductExpirationEasyPeasy\Check_Job::get();
peep_test_expect( 'done' === $peep_state['status'] && 120 === $peep_state['processed'] && 120 === $peep_state['changed'], 'The check did not finish every batch.' );
peep_test_expect( 1 === count( $peep_test_mail ), 'Expected one aggregated email per check, got ' . count( $peep_test_mail ) . '.' );
peep_test_expect( array( 'ops@example.test' ) === $peep_test_mail[0]['to'], 'Report went to the wrong recipients.' );
peep_test_expect( 120 === substr_count( $peep_test_mail[0]['body'], 'Product ID:' ), 'The aggregated report does not list every product.' );
peep_test_expect( 'instock' === $peep_test_products[500]->get_stock_status(), 'A product beyond the cutoff was changed.' );
peep_test_expect( ! isset( ProductExpirationEasyPeasy\Check_Job::public_state( $peep_state )['cutoff'] ) && false === get_option( 'peep_check_job_changed' ), 'Internal job data leaked or was not cleaned up.' );

// The daily event starts the same background check.
$peep_test_mail = array();
$peep_worker->check_expired_products();
peep_test_expect( 'cron' === ProductExpirationEasyPeasy\Check_Job::get()['source'] && 1 === count( $peep_test_queue ), 'The daily event did not start a background check.' );
peep_test_drain( $peep_job );
peep_test_expect( 0 === ProductExpirationEasyPeasy\Check_Job::get()['changed'] && array() === $peep_test_mail, 'A second check changed products again or sent an empty report.' );

// Cancelling stops further batches; changed products stay out of stock and are reported once.
for ( $peep_id = 1; $peep_id <= 120; $peep_id++ ) {
	ProductExpirationEasyPeasy\Stock::restore( $peep_id );
}

$peep_state = $peep_job->start( 'manual' );
list( , $peep_args ) = array_shift( $peep_test_queue );
$peep_job->run_batch( ...$peep_args );
peep_test_expect( 50 === ProductExpirationEasyPeasy\Check_Job::get()['processed'], 'The first batch did not process 50 items.' );
peep_test_expect( 'cancelled' === $peep_job->cancel()['status'], 'Cancel did not stop the running check.' );
peep_test_drain( $peep_job );
$peep_state = ProductExpirationEasyPeasy\Check_Job::get();
peep_test_expect( 'cancelled' === $peep_state['status'] && 50 === $peep_state['processed'] && 50 === $peep_state['changed'], 'A batch ran after the check was cancelled.' );
peep_test_expect( 'outofstock' === $peep_test_products[50]->get_stock_status() && 'instock' === $peep_test_products[51]->get_stock_status(), 'Cancelling changed the wrong products.' );
peep_test_expect( 1 === count( $peep_test_mail ) && 50 === substr_count( $peep_test_mail[0]['body'], 'Product ID:' ), 'A cancelled check did not report the products it changed.' );
peep_test_expect( 'cancelled' === $peep_job->cancel()['status'], 'Cancelling a stopped check changed it.' );
$peep_job->run_batch( $peep_args[0] );
peep_test_expect( 1 === count( $peep_test_mail ), 'A late batch reported the cancelled check twice.' );
$peep_test_mail = array();

// A queued batch keeps a slow job alive; without one, a job that stopped moving is replaced.
$peep_job->start( 'manual' );
$peep_live               = ProductExpirationEasyPeasy\Check_Job::get();
$peep_live['updated_at'] = time() - HOUR_IN_SECONDS;
$peep_test_options['peep_check_job'] = $peep_live;
peep_test_expect( ProductExpirationEasyPeasy\Check_Job::is_running() && $peep_live['id'] === $peep_job->start( 'cron' )['id'], 'A job with a queued batch was treated as dead.' );
$peep_test_queue                       = array();
$peep_stale                            = ProductExpirationEasyPeasy\Check_Job::get();
$peep_stale['updated_at']              = time() - HOUR_IN_SECONDS;
$peep_test_options['peep_check_job']   = $peep_stale;
peep_test_expect( $peep_stale['id'] !== $peep_job->start( 'manual' )['id'], 'A stale job blocked a new check.' );
peep_test_drain( $peep_job );
peep_test_expect( 70 === ProductExpirationEasyPeasy\Check_Job::get()['changed'] && 1 === count( $peep_test_mail ), 'The restarted check did not finish the remaining products.' );

// A manual start from the dialog does the first batch at once and queues the rest.
$peep_test_queue = array();
$peep_state      = $peep_job->start( 'manual', true );
peep_test_expect( 50 === $peep_state['processed'] && 1 === count( $peep_test_queue ), 'The first batch did not run in the start request.' );
peep_test_drain( $peep_job );
peep_test_expect( 'done' === ProductExpirationEasyPeasy\Check_Job::get()['status'], 'The rest of the check did not finish in the queue.' );

// The no-JavaScript button runs the whole check in the request.
$peep_test_mail = array();
$peep_job->start( 'manual' );
peep_test_expect( false === $peep_job->run_now( 'manual' ), 'run_now() ignored the running check.' );
$peep_test_queue                     = array();
$peep_test_options['peep_check_job'] = array( 'status' => 'done' ) + ProductExpirationEasyPeasy\Check_Job::get();
$peep_state                          = $peep_job->run_now( 'manual' );
peep_test_expect( 'done' === $peep_state['status'] && 120 === $peep_state['processed'] && array() === $peep_test_queue, 'run_now() did not finish synchronously.' );

// Shortening the period restores products now beyond the new cutoff.
update_post_meta( 7, '_expiration_date', $peep_today->modify( '+20 days' )->format( 'Y-m-d' ) );
$settings->save_settings( array( 'notification_period' => 10, 'notification_period_type' => 'days', 'email_recipients' => array() ) );
peep_test_expect( 1 === $peep_worker->restore_beyond_cutoff(), 'Only the product now beyond the cutoff should be restored.' );
peep_test_expect( 'instock' === $peep_test_products[7]->get_stock_status(), 'The previous stock status was not restored.' );
peep_test_expect( '' === get_post_meta( 7, '_peep_stock_managed' ), 'The managed flag was not cleared.' );
peep_test_expect( 'outofstock' === $peep_test_products[8]->get_stock_status(), 'A product inside the new cutoff was restored.' );

// The shared per-item step changes only dated, in-stock or backorder, non-variable items.
$peep_item_date = $peep_today->format( 'Y-m-d' );
$peep_items     = array(
	901 => array( 'onbackorder', 'simple', true ),
	902 => array( 'outofstock', 'simple', false ),
	903 => array( 'instock', 'variable', false ),
	904 => array( 'instock', 'simple', false ),
);

foreach ( $peep_items as $peep_id => list( $peep_status, $peep_type ) ) {
	$peep_test_products[ $peep_id ] = new Peep_Test_Product( $peep_id, $peep_status, $peep_type );

	if ( 904 !== $peep_id ) {
		update_post_meta( $peep_id, '_expiration_date', $peep_item_date );
	}
}

foreach ( $peep_items as $peep_id => list( , , $peep_expected ) ) {
	peep_test_expect( $peep_expected === ProductExpirationEasyPeasy\Stock::mark_out_of_stock( $peep_id ), "Stock::mark_out_of_stock({$peep_id}) returned the wrong result." );
}

peep_test_expect( 'onbackorder' === get_post_meta( 901, '_peep_previous_stock_status' ), 'The previous backorder status was not kept.' );
peep_test_expect( '' === get_post_meta( 902, '_peep_stock_managed' ), 'A product already out of stock was marked as managed.' );
peep_test_expect( true === ProductExpirationEasyPeasy\Stock::restore( 901 ) && 'onbackorder' === $peep_test_products[901]->get_stock_status(), 'Backorder status was not restored.' );
peep_test_expect( false === ProductExpirationEasyPeasy\Stock::restore( 901 ), 'An unmanaged product was restored twice.' );

foreach ( array_keys( $peep_items ) as $peep_id ) {
	unset( $peep_test_meta[ $peep_id ], $peep_test_products[ $peep_id ] );
}

// Dashboard counts are cached and every finished check drops the cache.
ProductExpirationEasyPeasy\Stats::flush();
$peep_reads = $wpdb->reads;
peep_test_expect( array( 'dated' => 12, 'expired' => 3, 'managed' => 2 ) === ProductExpirationEasyPeasy\Stats::get_counts(), 'Dashboard counts were not read.' );
ProductExpirationEasyPeasy\Stats::get_counts();
peep_test_expect( $peep_reads + 1 === $wpdb->reads, 'Dashboard counts were not cached.' );
$peep_job->run_now( 'manual' );
$peep_reads = $wpdb->reads;
ProductExpirationEasyPeasy\Stats::get_counts();
peep_test_expect( $peep_reads + 1 === $wpdb->reads, 'A finished check did not flush the dashboard counts.' );

// The public recipient resolver and test email share the same rules.
peep_test_expect( 'no_recipients' === ProductExpirationEasyPeasy\Report::send_test( $settings ), 'Test email ignored the empty recipient list.' );
$settings->save_settings( array( 'email_recipients' => array( 'admin', 'custom' ), 'custom_email' => 'not-an-email' ) );
$peep_test_options['admin_email'] = 'admin@example.test';
peep_test_expect( array( 'admin@example.test' ) === ProductExpirationEasyPeasy\Report::recipients( $settings ), 'Invalid custom email was not filtered.' );
$peep_test_mail = array();
peep_test_expect( 'sent' === ProductExpirationEasyPeasy\Report::send_test( $settings ) && 1 === count( $peep_test_mail ), 'Test email was not sent.' );

// The bundled translation wins over a stale WordPress.org language pack.
$peep_test_bundled = dirname( __DIR__ ) . '/languages/product-expiration-easy-peasy-fa_IR';
$peep_test_global  = WP_LANG_DIR . '/plugins/product-expiration-easy-peasy-fa_IR';

peep_test_expect( $peep_test_bundled . '.mo' === ProductExpirationEasyPeasy\use_bundled_translation( $peep_test_global . '.mo', 'product-expiration-easy-peasy' ), 'A stale global .mo was not redirected to the bundled file.' );
peep_test_expect(
	( is_file( $peep_test_bundled . '.l10n.php' ) ? $peep_test_bundled . '.l10n.php' : $peep_test_bundled . '.mo' ) === ProductExpirationEasyPeasy\use_bundled_translation( $peep_test_global . '.l10n.php', 'product-expiration-easy-peasy' ),
	'A stale global .l10n.php was not redirected to the bundled file.'
);
peep_test_expect( $peep_test_global . '.mo' === ProductExpirationEasyPeasy\use_bundled_translation( $peep_test_global . '.mo', 'woocommerce' ), 'Another text domain was redirected.' );
peep_test_expect( WP_LANG_DIR . '/plugins/product-expiration-easy-peasy-de_DE.mo' === ProductExpirationEasyPeasy\use_bundled_translation( WP_LANG_DIR . '/plugins/product-expiration-easy-peasy-de_DE.mo', 'product-expiration-easy-peasy' ), 'A locale without a bundled file was redirected.' );
peep_test_expect( '/elsewhere/product-expiration-easy-peasy-fa_IR.mo' === ProductExpirationEasyPeasy\use_bundled_translation( '/elsewhere/product-expiration-easy-peasy-fa_IR.mo', 'product-expiration-easy-peasy' ), 'A file outside WP_LANG_DIR/plugins was redirected.' );
peep_test_expect( 10 === peep_test_hook_priority( 'load_translation_file', 'ProductExpirationEasyPeasy\\use_bundled_translation' ), 'The load_translation_file filter is not registered.' );
peep_test_expect( 10 === peep_test_hook_priority( 'load_textdomain_mofile', 'ProductExpirationEasyPeasy\\use_bundled_translation' ), 'The load_textdomain_mofile filter is not registered.' );

// A default saved from an English admin screen follows the site language.
$peep_test_i18n_map = array( 'Expires: %date%' => 'تاریخ انقضا: %date%' );

$peep_test_options['peep_settings'] = array( 'display_text' => 'Expires: %date%' );
peep_test_expect( 'تاریخ انقضا: %date%' === $settings->get_setting( 'display_text' ), 'A stored English default was not translated.' );
$peep_test_options['peep_settings'] = array( 'display_text' => 'Expires on %date%' );
peep_test_expect( 'Expires on %date%' === $settings->get_setting( 'display_text' ), 'Custom display text was changed.' );
$peep_test_options['peep_settings'] = array();
peep_test_expect( 'تاریخ انقضا: %date%' === $settings->get_setting( 'display_text' ), 'The default display text was not translated.' );
$peep_test_options['peep_settings'] = array( 'display_text' => 'Expires: te%' );
peep_test_expect( 'تاریخ انقضا: %date%' === $settings->get_setting( 'display_text' ), 'The default stored by earlier releases (with %date% stripped) was not repaired.' );
$peep_test_options['peep_settings'] = array( 'display_text' => 'تاریخ انقضا: te%' );
peep_test_expect( 'تاریخ انقضا: %date%' === $settings->get_setting( 'display_text' ), 'The stripped translated default was not repaired.' );

$settings->save_settings( array( 'display_text' => 'Best before %day%/%month%/%year% (%date%)' ) );
peep_test_expect( 'Best before %day%/%month%/%year% (%date%)' === $settings->get_setting( 'display_text' ), 'Placeholders were stripped from the display text.' );
$peep_test_i18n_map = array();

// Bundled translations cover every source string and the compiled files are fresh.
require_once dirname( __DIR__ ) . '/tools/check-translations.php';
$peep_test_i18n_problems = peep_i18n_problems( dirname( __DIR__ ), 'fa_IR' );
peep_test_expect( array() === $peep_test_i18n_problems, "Translation check failed:\n  " . implode( "\n  ", $peep_test_i18n_problems ) );

// Date picker calendar setting.
$peep_test_options['peep_settings'] = array();
peep_test_expect( 'auto' === $settings->get_setting( 'datepicker_calendar' ), 'The date picker calendar does not default to auto.' );

foreach ( array( 'auto', 'jalali', 'gregorian' ) as $peep_calendar ) {
	$settings->save_settings( array( 'datepicker_calendar' => $peep_calendar ) );
	peep_test_expect( $peep_calendar === $settings->get_setting( 'datepicker_calendar' ), "Valid date picker calendar {$peep_calendar} was rejected." );
}

foreach ( array( 'persian', 'shamsi', array( 'jalali' ), '' ) as $peep_calendar ) {
	$settings->save_settings( array( 'datepicker_calendar' => $peep_calendar ) );
	peep_test_expect( 'auto' === $settings->get_setting( 'datepicker_calendar' ), 'An invalid date picker calendar did not fall back to auto.' );
}

// The resolver: auto follows the admin locale, explicit choices win.
$peep_resolver_cases = array(
	array( 'fa_IR', 'auto', 'jalali' ),
	array( 'fa_IR', 'jalali', 'jalali' ),
	array( 'fa_IR', 'gregorian', 'gregorian' ),
	array( 'en_US', 'auto', 'gregorian' ),
	array( 'en_US', 'jalali', 'jalali' ),
	array( 'en_US', 'gregorian', 'gregorian' ),
);

foreach ( $peep_resolver_cases as list( $peep_locale, $peep_preference, $peep_expected ) ) {
	$peep_test_locale = $peep_locale;
	peep_test_expect( $peep_expected === ProductExpirationEasyPeasy\Date::input_calendar( $peep_preference ), "input_calendar({$peep_preference}) under {$peep_locale} is not {$peep_expected}." );

	// Null reads the saved setting.
	$settings->save_settings( array( 'datepicker_calendar' => $peep_preference ) );
	peep_test_expect( $peep_expected === ProductExpirationEasyPeasy\Date::input_calendar(), "The saved {$peep_preference} setting did not resolve to {$peep_expected} under {$peep_locale}." );
}

// Input values follow the resolved calendar; digits follow the admin locale.
$peep_input_cases = array(
	array( 'fa_IR', 'jalali', '۱۴۰۵-۱۱-۱۳', '۱۴۰۵-۰۱-۰۱' ),
	array( 'en_US', 'jalali', '1405-11-13', '1405-01-01' ),
	array( 'fa_IR', 'gregorian', '2027-02-02', '2026-12-31' ),
	array( 'en_US', 'gregorian', '2027-02-02', '2026-12-31' ),
);

foreach ( $peep_input_cases as list( $peep_locale, $peep_calendar, $peep_value, $peep_example ) ) {
	$peep_test_locale = $peep_locale;
	peep_test_expect( $peep_value === ProductExpirationEasyPeasy\Date::input_value( '2027-02-02', $peep_calendar ), "input_value() for {$peep_calendar} under {$peep_locale} is wrong." );
	peep_test_expect( $peep_example === ProductExpirationEasyPeasy\Date::input_example( $peep_calendar ), "input_example() for {$peep_calendar} under {$peep_locale} is wrong." );

	// What the picker writes is saved as the same Gregorian date.
	peep_test_expect( '2027-02-02' === ProductExpirationEasyPeasy\Date::sanitize_input( $peep_value ), "sanitize_input() did not round-trip {$peep_value}." );
}

peep_test_expect( '' === ProductExpirationEasyPeasy\Date::input_value( '', 'jalali' ), 'An empty date produced an input value.' );
$peep_test_locale = 'fa_IR';

// The JS converter matches PHP (skipped when Node.js is not installed).
$peep_fixture = array(
	'days'   => array(),
	'esfand' => array(),
	'inputs' => array(),
);
$peep_day     = new DateTimeImmutable( '2015-01-01', new DateTimeZone( 'UTC' ) );

while ( $peep_day->format( 'Y' ) < 2036 ) {
	$peep_iso                 = $peep_day->format( 'Y-m-d' );
	$peep_fixture['days'][] = array( $peep_iso, ProductExpirationEasyPeasy\Jalali::convert( (int) $peep_day->format( 'Y' ), (int) $peep_day->format( 'n' ), (int) $peep_day->format( 'j' ) ), ProductExpirationEasyPeasy\Jalali::format( $peep_iso, 'j F Y' ) );
	$peep_day                 = $peep_day->modify( '+1 day' );
}

for ( $peep_year = 1380; $peep_year <= 1430; $peep_year++ ) {
	$peep_fixture['esfand'][] = array( $peep_year, 3 === count( ProductExpirationEasyPeasy\Jalali::to_gregorian( $peep_year, 12, 30 ) ) ? 30 : 29 );
}

foreach ( array( '۱۴۰۵-۱۱-۱۳', '1405-11-13', '2027-02-02', '۱۴۰۴/۰۱/۰۱', '١٤٠٣-١٢-٣٠', '1402-12-30', '1403-12-30', '1407-12-30', '1408-12-30', '2025-02-29', '2024-02-29', '1403/12-30', '', 'abc', '1405-13-01', '1405-07-31' ) as $peep_input ) {
	$peep_fixture['inputs'][] = array( $peep_input, ProductExpirationEasyPeasy\Date::sanitize_input( $peep_input ) );
}

peep_test_expect( 30 === array_column( $peep_fixture['esfand'], 1, 0 )[1403] && 29 === array_column( $peep_fixture['esfand'], 1, 0 )[1404], 'Esfand month lengths are wrong.' );

$peep_node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

if ( '' === $peep_node ) {
	echo "jalali-parity=skipped (node not found)\n";
} else {
	$peep_fixture_file = tempnam( sys_get_temp_dir(), 'peep-jalali-' );
	file_put_contents( $peep_fixture_file, json_encode( $peep_fixture, JSON_UNESCAPED_UNICODE ) );
	exec( escapeshellarg( $peep_node ) . ' ' . escapeshellarg( __DIR__ . '/jalali-parity.js' ) . ' ' . escapeshellarg( $peep_fixture_file ) . ' 2>&1', $peep_output, $peep_status );
	unlink( $peep_fixture_file );
	peep_test_expect( 0 === $peep_status, "The JS Jalali port does not match PHP:\n" . implode( "\n", $peep_output ) );
	echo implode( "\n", $peep_output ), "\n";
}

// Settings: custom_email keeps every valid address of a comma-separated list.
$settings->save_settings( array( 'custom_email' => ' ops@example.test, bad-address ,buyer@example.test, ops@example.test' ) );
peep_test_expect( 'ops@example.test, buyer@example.test' === $settings->get_setting( 'custom_email' ), 'The custom address list was not sanitized.' );
$settings->save_settings( array( 'email_recipients' => array( 'custom' ), 'custom_email' => 'ops@example.test,buyer@example.test' ) );
peep_test_expect( array( 'ops@example.test', 'buyer@example.test' ) === ProductExpirationEasyPeasy\Report::recipients( $settings ), 'Every custom address should receive the report.' );
$settings->save_settings( array( 'custom_email' => 'single@example.test' ) );
peep_test_expect( 'single@example.test' === $settings->get_setting( 'custom_email' ), 'A single custom address changed when saved.' );

// Export: request validation.
use ProductExpirationEasyPeasy\Exporter;

$peep_day0 = '2026-10-17';
$peep_req  = Exporter::request( array(), $peep_day0 );
peep_test_expect( 'both' === $peep_req['scope'] && '30' === $peep_req['range'] && Exporter::DEFAULT_COLUMNS === $peep_req['columns'], 'Export defaults are wrong.' );
peep_test_expect( '2026-11-16' === $peep_req['to'] && $peep_day0 === $peep_req['from'], 'The default custom range is not today to today + 30 days.' );
$peep_req = Exporter::request( array( 'scope' => 'evil', 'range' => '45', 'format' => 'pdf', 'calendar' => 'mars', 'from' => '2026-12-01', 'to' => '۱۴۰۵/۰۸/۰۱', 'columns' => array( 'stock', 'date', 'bogus', 'stock', 'id' ) ), $peep_day0 );
peep_test_expect( 'both' === $peep_req['scope'] && '30' === $peep_req['range'], 'Invalid scope or range was accepted.' );
peep_test_expect( '2026-10-23' === $peep_req['from'] && '2026-12-01' === $peep_req['to'], 'A backwards custom range was not swapped, or Jalali input was not converted.' );
peep_test_expect( array( 'name', 'stock', 'date', 'id' ) === $peep_req['columns'], 'Posted column order was not kept, or the name was not forced in.' );
peep_test_expect( array( 'id', 'name', 'sku' ) === Exporter::request( array( 'columns' => array( 'id', 'sku' ) ), $peep_day0 )['columns'], 'The forced name column is not placed after the ID.' );

// Export: which dates each scope and range selects.
$peep_cases = array(
	array( 'expired', '30', '2026-10-16', true ),
	array( 'expired', '30', '2026-10-17', false ),
	array( 'soon', '7', '2026-10-17', true ),
	array( 'soon', '7', '2026-10-24', true ),
	array( 'soon', '7', '2026-10-25', false ),
	array( 'soon', '7', '2026-10-16', false ),
	array( 'both', '7', '2026-10-16', true ),
	array( 'both', '90', '2027-01-15', true ),
	array( 'both', '90', '2027-01-16', false ),
);

foreach ( $peep_cases as list( $peep_scope, $peep_range, $peep_date, $peep_expected ) ) {
	$peep_req = Exporter::request( array( 'scope' => $peep_scope, 'range' => $peep_range ), $peep_day0 );
	peep_test_expect( $peep_expected === Exporter::matches( $peep_date, $peep_req, $peep_day0 ), "Export {$peep_scope}/{$peep_range} chose {$peep_date} wrongly." );
}

$peep_req = Exporter::request( array( 'scope' => 'soon', 'range' => 'custom', 'from' => '2027-03-01', 'to' => '2027-02-01' ), $peep_day0 );
peep_test_expect( Exporter::matches( '2027-02-15', $peep_req, $peep_day0 ) && ! Exporter::matches( '2027-03-02', $peep_req, $peep_day0 ), 'The custom range selects the wrong dates.' );

// Export: rows follow the column order and cells hold the right values.
$peep_saved_meta     = $peep_test_meta;
$peep_saved_products = $peep_test_products;
$peep_test_meta      = array();
$peep_test_products  = array();
$peep_today_iso      = ( new DateTimeImmutable( 'today', wp_timezone() ) )->format( 'Y-m-d' );

foreach ( array( 31 => -3, 32 => 5, 33 => 400 ) as $peep_id => $peep_offset ) {
	$peep_test_products[ $peep_id ] = new Peep_Test_Product( $peep_id, 32 === $peep_id ? 'outofstock' : 'instock' );
	update_post_meta( $peep_id, '_expiration_date', ( new DateTimeImmutable( $peep_today_iso ) )->modify( "{$peep_offset} days" )->format( 'Y-m-d' ) );
}

$peep_exporter = new Exporter();
$peep_req      = Exporter::request( array( 'columns' => array( 'left', 'name', 'date', 'stock', 'sku', 'cat' ), 'calendar' => 'jalali' ), $peep_today_iso );
$peep_table    = $peep_exporter->rows( $peep_req, $peep_today_iso );
peep_test_expect( array( 'left', 'name', 'date', 'stock', 'sku', 'cat' ) === array_column( $peep_table['columns'], 'id' ), 'Export header order is wrong.' );
peep_test_expect( 2 === $peep_table['count'] && array( -3, 'Product 31' ) === array_slice( $peep_table['rows'][0], 0, 2 ) && 5 === $peep_table['rows'][1][0], 'Export rows are wrong or not soonest first.' );
peep_test_expect( ProductExpirationEasyPeasy\Jalali::format( get_post_meta( 31, '_expiration_date' ), 'Y/m/d' ) === $peep_table['rows'][0][2], 'Jalali dates in the file do not match Jalali::format().' );
peep_test_expect( 'Out of stock' === $peep_table['rows'][1][3] && 'Food, Drinks' === $peep_table['rows'][0][5], 'Stock label or categories are wrong.' );
peep_test_expect( array( 'expired' => 1, 'soon' => 1, 'both' => 2 ) === $peep_exporter->counts( $peep_req, $peep_today_iso ), 'Scope counts are wrong.' );
peep_test_expect( 1 === count( $peep_exporter->rows( $peep_req, $peep_today_iso, 1 )['rows'] ), 'The preview limit was ignored.' );
peep_test_expect( 'expiring-products-' . vsprintf( '%04d-%02d-%02d', ProductExpirationEasyPeasy\Jalali::convert( 2026, 10, 17 ) ) . '.csv' === Exporter::filename( array( 'calendar' => 'jalali', 'format' => 'csv' ), '2026-10-17' ), 'The file name does not use the chosen calendar.' );

// CSV: UTF-8 byte order mark and no formula injection; numbers stay numbers.
$peep_csv = fopen( 'php://memory', 'w+' );
ProductExpirationEasyPeasy\Csv_Writer::write( $peep_csv, array( 'نام', 'SKU' ), array( array( '=HYPERLINK("x")', -3 ), array( '+1', '@a' ), array( '-x', 'plain' ) ) );
rewind( $peep_csv );
$peep_csv_text = stream_get_contents( $peep_csv );
peep_test_expect( 0 === strpos( $peep_csv_text, "\xEF\xBB\xBF" ), 'The CSV has no UTF-8 BOM.' );
peep_test_expect( false !== strpos( $peep_csv_text, "\"'=HYPERLINK(\"\"x\"\")\",-3" ) && false !== strpos( $peep_csv_text, "'+1,'@a" ) && false !== strpos( $peep_csv_text, "'-x,plain" ), "Formula cells were not neutralized:\n" . $peep_csv_text );

// XLSX: six well-formed parts, a bold header, and right-to-left when asked.
$peep_xlsx = tempnam( sys_get_temp_dir(), 'peep-xlsx-' );
peep_test_expect( ProductExpirationEasyPeasy\Xlsx_Writer::write( $peep_xlsx, array( 'نام', 'A & B <c>' ), array( array( 'محصول', 7 ), array( "bad\x01char", -2 ) ), true ), 'The XLSX could not be written.' );
$peep_zip = new ZipArchive();
$peep_zip->open( $peep_xlsx );
$peep_parts = array( '[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml' );

foreach ( $peep_parts as $peep_part ) {
	$peep_xml = $peep_zip->getFromName( $peep_part );
	peep_test_expect( false !== $peep_xml && false !== simplexml_load_string( $peep_xml ), "XLSX part {$peep_part} is missing or not valid XML." );
}

$peep_sheet = $peep_zip->getFromName( 'xl/worksheets/sheet1.xml' );
peep_test_expect( 6 === $peep_zip->numFiles && false !== strpos( $peep_sheet, 'rightToLeft="1"' ) && false !== strpos( $peep_sheet, '<c r="B3"><v>-2</v></c>' ) && false !== strpos( $peep_sheet, 's="1"' ), 'The XLSX sheet content is wrong.' );
$peep_zip->close();
unlink( $peep_xlsx );

$peep_test_meta     = $peep_saved_meta;
$peep_test_products = $peep_saved_products;

echo "bootstrap-smoke=passed\n";
