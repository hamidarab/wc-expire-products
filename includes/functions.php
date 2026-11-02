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

/*
 * The release ships class-license-check.php ionCube-protected. Without the
 * loader, or without the license runtime it is bound to, requiring it ends
 * the request, so it is loaded only when both are present.
 */
if ( extension_loaded( 'ionCube Loader' ) && class_exists( 'RTL_License_ca50fd723e00b32e', false ) ) {
	require_once PRODUCT_EXPIRATION_EASY_PEASY_PATH . 'includes/class-license-check.php';
}

/**
 * Backward-compatible license helper used by encoded release builds.
 *
 * @return bool
 */
function product_expiration_easy_peasy_is_license_active() {
	return class_exists( __NAMESPACE__ . '\\License_Check', false ) && License_Check::is_active();
}

/**
 * Explain why the license is not active, for the admin notice.
 *
 * @return string Empty when the license check ran and found no load error.
 */
function product_expiration_easy_peasy_license_error() {
	if ( ! extension_loaded( 'ionCube Loader' ) ) {
		return __( 'ionCube Loader is required to validate the RTL-Theme license.', 'product-expiration-easy-peasy' );
	}

	if ( ! class_exists( __NAMESPACE__ . '\\License_Check', false ) ) {
		return __( 'The RTL-Theme license file is missing or unreadable.', 'product-expiration-easy-peasy' );
	}

	return License_Check::get_error();
}
