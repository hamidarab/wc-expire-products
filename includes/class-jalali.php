<?php
/**
 * Lightweight Gregorian-to-Jalali conversion for Persian storefronts.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats stored Gregorian dates as Jalali dates without a plugin dependency.
 */
final class Jalali {
	/**
	 * One-letter weekday names, indexed like PHP's "w" (0 = Sunday).
	 *
	 * @var array<int, string>
	 */
	public const WEEKDAY_INITIALS = array( 'ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش' );

	/**
	 * Convert a Jalali calendar date to Gregorian components.
	 *
	 * Invalid Jalali dates return an empty array. A round-trip check handles the
	 * varying final day of Esfand without maintaining a separate leap-year table.
	 *
	 * @param int $year  Jalali year.
	 * @param int $month Jalali month.
	 * @param int $day   Jalali day.
	 * @return array<int, int> Gregorian year, month, and day, or an empty array.
	 */
	public static function to_gregorian( $year, $month, $day ) {
		$year  = (int) $year;
		$month = (int) $month;
		$day   = (int) $day;

		$maximum_day = $month <= 6 ? 31 : 30;

		if ( $year < 979 || $month < 1 || $month > 12 || $day < 1 || $day > $maximum_day ) {
			return array();
		}

		$adjusted_year = $year + 1595;
		$days          = -355668
			+ ( 365 * $adjusted_year )
			+ ( intdiv( $adjusted_year, 33 ) * 8 )
			+ intdiv( ( $adjusted_year % 33 ) + 3, 4 )
			+ $day
			+ ( $month < 7 ? ( $month - 1 ) * 31 : ( ( $month - 7 ) * 30 ) + 186 );

		$gregorian_year = 400 * intdiv( $days, 146097 );
		$days          %= 146097;

		if ( $days > 36524 ) {
			--$days;
			$gregorian_year += 100 * intdiv( $days, 36524 );
			$days           %= 36524;

			if ( $days >= 365 ) {
				++$days;
			}
		}

		$gregorian_year += 4 * intdiv( $days, 1461 );
		$days           %= 1461;

		if ( $days > 365 ) {
			--$days;
			$gregorian_year += intdiv( $days, 365 );
			$days           %= 365;
		}

		$gregorian_day = $days + 1;
		$month_days    = array(
			0,
			31,
			( 0 === $gregorian_year % 4 && ( 0 !== $gregorian_year % 100 || 0 === $gregorian_year % 400 ) ) ? 29 : 28,
			31,
			30,
			31,
			30,
			31,
			31,
			30,
			31,
			30,
			31,
		);

		for ( $gregorian_month = 1; $gregorian_month <= 12 && $gregorian_day > $month_days[ $gregorian_month ]; ++$gregorian_month ) {
			$gregorian_day -= $month_days[ $gregorian_month ];
		}

		$round_trip = self::convert( $gregorian_year, $gregorian_month, $gregorian_day );

		if ( array( $year, $month, $day ) !== $round_trip ) {
			return array();
		}

		return array( $gregorian_year, $gregorian_month, $gregorian_day );
	}

	/**
	 * Convert a Gregorian calendar date to Jalali components.
	 *
	 * @param int $year  Gregorian year.
	 * @param int $month Gregorian month.
	 * @param int $day   Gregorian day.
	 * @return array<int, int> Jalali year, month, and day.
	 */
	public static function convert( $year, $month, $day ) {
		$gregorian_month_days = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$jalali_month_days    = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );

		$year  = (int) $year - 1600;
		$month = (int) $month - 1;
		$day   = (int) $day - 1;

		$gregorian_day_number = ( 365 * $year )
			+ intdiv( $year + 3, 4 )
			- intdiv( $year + 99, 100 )
			+ intdiv( $year + 399, 400 );

		for ( $index = 0; $index < $month; ++$index ) {
			$gregorian_day_number += $gregorian_month_days[ $index ];
		}

		if ( $month > 1 && ( 0 === $year % 4 && ( 0 !== $year % 100 || 0 === $year % 400 ) ) ) {
			++$gregorian_day_number;
		}

		$gregorian_day_number += $day;
		$jalali_day_number     = $gregorian_day_number - 79;
		$cycles                = intdiv( $jalali_day_number, 12053 );
		$jalali_day_number    %= 12053;
		$jalali_year           = 979 + ( 33 * $cycles ) + ( 4 * intdiv( $jalali_day_number, 1461 ) );
		$jalali_day_number    %= 1461;

		if ( $jalali_day_number >= 366 ) {
			$jalali_year += intdiv( $jalali_day_number - 1, 365 );
			--$jalali_day_number;
			$jalali_day_number %= 365;
		}

		for ( $index = 0; $index < 11 && $jalali_day_number >= $jalali_month_days[ $index ]; ++$index ) {
			$jalali_day_number -= $jalali_month_days[ $index ];
		}

		return array( $jalali_year, $index + 1, $jalali_day_number + 1 );
	}

	/**
	 * Format a validated Gregorian Y-m-d date as Jalali.
	 *
	 * @param string $date   Gregorian Y-m-d date.
	 * @param string $format Supported display format.
	 * @return string
	 */
	public static function format( $date, $format = 'Y/m/d' ) {
		$parts = array_map( 'intval', explode( '-', $date ) );

		if ( 3 !== count( $parts ) ) {
			return '';
		}

		list( $year, $month, $day ) = self::convert( $parts[0], $parts[1], $parts[2] );

		$month_names = array(
			1  => 'فروردین',
			2  => 'اردیبهشت',
			3  => 'خرداد',
			4  => 'تیر',
			5  => 'مرداد',
			6  => 'شهریور',
			7  => 'مهر',
			8  => 'آبان',
			9  => 'آذر',
			10 => 'دی',
			11 => 'بهمن',
			12 => 'اسفند',
		);

		$replacements = array(
			'Y' => (string) $year,
			'y' => substr( (string) $year, -2 ),
			'm' => sprintf( '%02d', $month ),
			'n' => (string) $month,
			'd' => sprintf( '%02d', $day ),
			'j' => (string) $day,
			'M' => $month_names[ $month ],
			'F' => $month_names[ $month ],
		);

		$output  = '';
		$escaped = false;

		for ( $index = 0, $length = strlen( $format ); $index < $length; ++$index ) {
			$character = $format[ $index ];

			if ( $escaped ) {
				$output .= $character;
				$escaped = false;
				continue;
			}

			if ( '\\' === $character ) {
				$escaped = true;
				continue;
			}

			$output .= isset( $replacements[ $character ] ) ? $replacements[ $character ] : $character;
		}

		return self::digits( $output );
	}

	/**
	 * Replace Latin digits with Persian digits.
	 *
	 * @param string $text Text containing Latin digits.
	 * @return string
	 */
	public static function digits( $text ) {
		return strtr(
			(string) $text,
			array(
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			)
		);
	}
}
