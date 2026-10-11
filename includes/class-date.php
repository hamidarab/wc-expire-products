<?php
/**
 * Expiration date validation and conversion helpers.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the canonical Gregorian Y-m-d value stored by the plugin.
 */
final class Date {
	/**
	 * Whether the current user-facing locale is Persian.
	 *
	 * @return bool
	 */
	public static function is_persian_locale() {
		return 0 === strpos( determine_locale(), 'fa' );
	}

	/**
	 * Validate a date entered in the administration interface.
	 *
	 * Four-digit years below 1700 are treated as Jalali. Persian and Arabic
	 * digits and either slash or hyphen separators are accepted. The canonical
	 * stored result is always Gregorian Y-m-d.
	 *
	 * @param mixed $value Raw date input.
	 * @return string Empty string when invalid, otherwise Gregorian Y-m-d.
	 */
	public static function sanitize_input( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( (string) $value );
		$value = strtr(
			$value,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);

		if ( 1 !== preg_match( '/^(\d{4})([-\/])(\d{1,2})\2(\d{1,2})$/', $value, $matches ) ) {
			return '';
		}

		$year  = (int) $matches[1];
		$month = (int) $matches[3];
		$day   = (int) $matches[4];

		if ( $year < 1700 ) {
			$gregorian = Jalali::to_gregorian( $year, $month, $day );

			if ( 3 !== count( $gregorian ) ) {
				return '';
			}

			return self::sanitize( sprintf( '%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2] ) );
		}

		return self::sanitize( sprintf( '%04d-%02d-%02d', $year, $month, $day ) );
	}

	/**
	 * Format a stored date for the administration input locale.
	 *
	 * @param mixed $date Stored Gregorian date.
	 * @return string
	 */
	public static function input_value( $date ) {
		$date = self::sanitize( $date );

		if ( '' === $date ) {
			return '';
		}

		return self::is_persian_locale() ? Jalali::format( $date, 'Y-m-d' ) : $date;
	}

	/**
	 * Validate and normalize a date value.
	 *
	 * @param mixed $value Raw date value.
	 * @return string Empty string when invalid, otherwise a Y-m-d date.
	 */
	public static function sanitize( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( (string) $value );

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			return '';
		}

		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return '';
		}

		return sprintf( '%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3] );
	}

	/**
	 * Get today's date in the WordPress site timezone.
	 *
	 * @return string
	 */
	public static function today() {
		return wp_date( 'Y-m-d', null, wp_timezone() );
	}

	/**
	 * Convert a stored date to a Unix timestamp in the site timezone.
	 *
	 * @param string $date Stored Y-m-d date.
	 * @return int|false
	 */
	public static function timestamp( $date ) {
		$date = self::sanitize( $date );

		if ( '' === $date ) {
			return false;
		}

		$date_time = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		if ( false === $date_time ) {
			return false;
		}

		return $date_time->getTimestamp();
	}

	/**
	 * Format a stored date for the current locale.
	 *
	 * Persian locales use the bundled Jalali converter. Other locales use the
	 * WordPress date API and site timezone.
	 *
	 * @param string $date   Stored Y-m-d date.
	 * @param string $format Configured format or "default".
	 * @return string
	 */
	public static function format( $date, $format = 'default' ) {
		$date = self::sanitize( $date );

		if ( '' === $date ) {
			return '';
		}

		if ( self::is_persian_locale() ) {
			$format = 'default' === $format ? 'Y/m/d' : $format;
			return Jalali::format( $date, $format );
		}

		$timestamp = self::timestamp( $date );

		if ( false === $timestamp ) {
			return '';
		}

		$format = 'default' === $format ? get_option( 'date_format' ) : $format;

		return wp_date( $format, $timestamp, wp_timezone() );
	}
}
