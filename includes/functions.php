<?php
/**
 * Public plugin helper functions.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backward-compatible license helper used by encoded release builds.
 *
 * @return bool
 */
function product_expiration_easy_peasy_is_license_active() {
	return License_Check::is_active();
}
