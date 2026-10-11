<?php
/**
 * Standalone smoke tests for bootstrap, validation, and Jalali formatting.
 *
 * Run with: php tests/run.php
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
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
	return 'fa_IR';
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

function peep_test_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

class Peep_Test_Wpdb {
	public $options = 'wp_options';

	public function prepare( $query, ...$arguments ) {
		return array( $query, $arguments );
	}

	public function query( $prepared ) {
		global $peep_test_options;

		list( $query, $arguments ) = $prepared;

		if ( 0 === strpos( $query, 'UPDATE ' ) ) {
			list( , $new_value, $option_name, $old_value ) = $arguments;

			if ( isset( $peep_test_options[ $option_name ] ) && maybe_serialize( $peep_test_options[ $option_name ] ) === $old_value ) {
				$peep_test_options[ $option_name ] = $new_value;
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

$first_worker  = new ProductExpirationEasyPeasy\Cron( $settings );
$second_worker = new ProductExpirationEasyPeasy\Cron( $settings );
$acquire_lock  = new ReflectionMethod( ProductExpirationEasyPeasy\Cron::class, 'acquire_lock' );
$release_lock  = new ReflectionMethod( ProductExpirationEasyPeasy\Cron::class, 'release_lock' );
$acquire_lock->setAccessible( true );
$release_lock->setAccessible( true );

peep_test_expect( true === $acquire_lock->invoke( $first_worker ), 'The first cron worker could not acquire its lock.' );
peep_test_expect( false === $acquire_lock->invoke( $second_worker ), 'A second cron worker acquired an active lock.' );
$release_lock->invoke( $first_worker );
peep_test_expect( true === $acquire_lock->invoke( $second_worker ), 'The lock owner did not release its lock.' );
$release_lock->invoke( $second_worker );

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

echo "bootstrap-smoke=passed\n";
