<?php
/**
 * One License_Check scenario in its own PHP process, called by tests/run.php.
 *
 * Usage: php tests/license-case.php <case> <plugin-dir>
 * Prints a JSON object with the license state and error.
 *
 * Cases:
 *   bootstrap      Load <plugin-dir>/product-expiration-easy-peasy.php, as WordPress
 *                  does, then includes/functions.php, as include_files() does.
 *   runtime-throws Require the real license runtime without WordPress, so its
 *                  constructor fails inside RTL-Theme's block.
 */

declare(strict_types=1);

$peep_case       = $argv[1] ?? '';
$peep_plugin_dir = rtrim( $argv[2] ?? '', '/' ) . '/';

define( 'ABSPATH', sys_get_temp_dir() . '/peep-no-wordpress/' );

$peep_fake_constructed = false;

function __( $text ) {
	return $text;
}

if ( 'bootstrap' === $peep_case ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}

	function plugin_dir_url( $file ) {
		return 'https://example.test/' . basename( dirname( $file ) ) . '/';
	}

	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}

	function add_action() {
		return true;
	}

	function add_filter() {
		return true;
	}

	function register_activation_hook() {}

	function register_deactivation_hook() {}

	require $peep_plugin_dir . 'product-expiration-easy-peasy.php';
} elseif ( 'runtime-throws' === $peep_case ) {
	define( 'PRODUCT_EXPIRATION_EASY_PEASY_PATH', $peep_plugin_dir );
	require $peep_plugin_dir . 'RTL_License_ca50fd723e00b32e.php';
} else {
	fwrite( STDERR, "Unknown case: {$peep_case}\n" );
	exit( 2 );
}

require $peep_plugin_dir . 'includes/functions.php';

$peep_active = ProductExpirationEasyPeasy\product_expiration_easy_peasy_is_license_active();

echo json_encode(
	array(
		'ioncube'          => extension_loaded( 'ionCube Loader' ),
		'runtime_loaded'   => class_exists( 'RTL_License_ca50fd723e00b32e', false ),
		'check_loaded'     => class_exists( 'ProductExpirationEasyPeasy\\License_Check', false ),
		'active'           => $peep_active,
		'error'            => ProductExpirationEasyPeasy\product_expiration_easy_peasy_license_error(),
		'fake_constructed' => $peep_fake_constructed,
	)
), "\n";
