<?php
/**
 * RTL-Theme license check.
 *
 * This file is sent to RTL-Theme for ionCube protection. The release ZIP ships
 * the protected copy from protected/includes/class-license-check.php; this
 * readable copy is the source. The plugin's main file loads the license
 * runtime; this class only queries it.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries the RTL-Theme license runtime with RTL-Theme's own check block.
 */
final class License_Check {
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

		if ( ! extension_loaded( 'ionCube Loader' ) ) {
			self::$error = __( 'ionCube Loader is required to validate the RTL-Theme license.', 'product-expiration-easy-peasy' );
			return false;
		}

		if ( ! is_file( PRODUCT_EXPIRATION_EASY_PEASY_PATH . 'RTL_License_ca50fd723e00b32e.php' ) ) {
			self::$error = __( 'The RTL-Theme license file is missing or unreadable.', 'product-expiration-easy-peasy' );
			return false;
		}

		if ( ! class_exists( 'RTL_License_ca50fd723e00b32e', false ) ) {
			self::$error = __( 'The RTL-Theme license class could not be loaded.', 'product-expiration-easy-peasy' );
			return false;
		}

		try {
			// phpcs:disable
			// --------------------------------------------------------------------------------------------------- Start RTL License

			$rtlLicenseClassName = 'RTL_License_ca50fd723e00b32e';

			if (
			    class_exists($rtlLicenseClassName, false)
			    && method_exists($rtlLicenseClassName, 'isActive')
			) {
			    try {
			        $rtlLicenseReflection = new \ReflectionClass($rtlLicenseClassName);
			        $rtlLicenseFilePath   = $rtlLicenseReflection->getFileName();

			        if (is_string($rtlLicenseFilePath) && is_file($rtlLicenseFilePath)) {
			            $rtlLicenseFileHash = @sha1_file($rtlLicenseFilePath);

			            if (
			                is_string($rtlLicenseFileHash)
			                && hash_equals('7e5cf81e7b51082f1f5e3fbc119fa2e8f98c57c6', $rtlLicenseFileHash)
			            ) {
			                $rtlLicenseClass = new $rtlLicenseClassName();

			                if ($rtlLicenseClass->{'isActive'}() === true) {
			                    self::$active = true;
			                }
			            }
			        }
			    } catch (\ReflectionException $e) {
			        // بررسی لایسنس ناموفق بود.
			    }
			}

			// ----------------------------------------------------------------------------------------------------- End RTL License
			// phpcs:enable
		} catch ( \Throwable $throwable ) {
			self::$active = false;
			self::$error  = __( 'The RTL-Theme license runtime could not be initialized.', 'product-expiration-easy-peasy' );
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
}
