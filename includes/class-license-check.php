<?php
/**
 * Safe adapter for the RTL-Theme license runtime.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and queries the generated RTL-Theme license class.
 */
final class License_Check {
	/**
	 * Generated license class name.
	 *
	 * @var string
	 */
	private const CLASS_NAME = 'RTL_License_700d86aad12a6fe9';

	/**
	 * Expected SHA-1 supplied with the generated license file.
	 *
	 * @var string
	 */
	private const FILE_HASH = 'f1e5fcf95fe320f67ad54e9280173cc604afb5ce';

	/**
	 * Cached activation result.
	 *
	 * @var bool|null
	 */
	private static $active = null;

	/**
	 * Human-readable load error for administrators.
	 *
	 * @var string
	 */
	private static $error = '';

	/**
	 * Determine whether the RTL-Theme license is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( null !== self::$active ) {
			return self::$active;
		}

		self::$active = false;

		if ( ! self::load_runtime() ) {
			return false;
		}

		$class_name = self::CLASS_NAME;

		try {
			$license = new $class_name();

			if ( ! method_exists( $license, 'isActive' ) ) {
				self::$error = __( 'The RTL-Theme license runtime is missing its activation method.', 'product-expiration-easy-peasy' );
				return false;
			}

			self::$active = true === $license->isActive();
		} catch ( \Throwable $throwable ) {
			self::$error = __( 'The RTL-Theme license runtime could not be initialized.', 'product-expiration-easy-peasy' );
		}

		return self::$active;
	}

	/**
	 * Return the last runtime error, if any.
	 *
	 * @return string
	 */
	public static function get_error() {
		return self::$error;
	}

	/**
	 * Validate and load the generated ionCube file.
	 *
	 * @return bool
	 */
	private static function load_runtime() {
		$class_name = self::CLASS_NAME;

		if ( class_exists( $class_name, false ) ) {
			return true;
		}

		$license_file = PRODUCT_EXPIRATION_EASY_PEASY_PATH . self::CLASS_NAME . '.php';

		if ( ! is_readable( $license_file ) ) {
			self::$error = __( 'The RTL-Theme license file is missing or unreadable.', 'product-expiration-easy-peasy' );
			return false;
		}

		$actual_hash = sha1_file( $license_file );

		if ( ! is_string( $actual_hash ) || ! hash_equals( self::FILE_HASH, $actual_hash ) ) {
			self::$error = __( 'The RTL-Theme license file failed its integrity check.', 'product-expiration-easy-peasy' );
			return false;
		}

		if ( ! extension_loaded( 'ionCube Loader' ) ) {
			self::$error = __( 'ionCube Loader is required to validate the RTL-Theme license.', 'product-expiration-easy-peasy' );
			return false;
		}

		try {
			require_once $license_file;
		} catch ( \Throwable $throwable ) {
			self::$error = __( 'The RTL-Theme license runtime could not be initialized.', 'product-expiration-easy-peasy' );
			return false;
		}

		if ( ! class_exists( $class_name, false ) ) {
			self::$error = __( 'The RTL-Theme license class could not be loaded.', 'product-expiration-easy-peasy' );
			return false;
		}

		return true;
	}
}
