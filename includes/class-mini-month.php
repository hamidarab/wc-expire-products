<?php
/**
 * Month grids for the calendar previews on the settings page.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the current month in the Jalali or Gregorian calendar.
 */
final class Mini_Month {
	/**
	 * Build the month that contains a date.
	 *
	 * @param string $calendar "jalali" or "gregorian".
	 * @param string $today    Gregorian Y-m-d.
	 * @return array{title: string, rtl: bool, heads: array<int, array{label: string, fri: bool}>, cells: array<int, array{label: string, today: bool, fri: bool}|null>}
	 */
	public static function build( $calendar, $today ) {
		global $wp_locale;

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $today ) );
		$jalali                     = 'jalali' === $calendar;

		if ( $jalali ) {
			list( $year, $month, $day ) = Jalali::convert( $year, $month, $day );

			$first      = Jalali::to_gregorian( $year, $month, 1 );
			$length     = $month <= 6 ? 31 : ( $month < 12 || array() !== Jalali::to_gregorian( $year, 12, 30 ) ? 30 : 29 );
			$week_start = 6;
			$weekdays   = Jalali::WEEKDAY_INITIALS;
			$title      = Jalali::format( $today, 'F Y' );
			$number     = array( Jalali::class, 'digits' );
		} else {
			$first      = array( $year, $month, 1 );
			$length     = (int) gmdate( 't', gmmktime( 0, 0, 0, $month, 1, $year ) );
			$week_start = (int) get_option( 'start_of_week', 0 );
			$weekdays   = array();
			$title      = $wp_locale->get_month( $month ) . ' ' . $year;
			$number     = 'strval';

			for ( $index = 0; $index < 7; $index++ ) {
				$weekdays[] = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $index ) );
			}
		}

		$first_weekday = (int) gmdate( 'w', gmmktime( 0, 0, 0, $first[1], $first[2], $first[0] ) );
		$heads         = array();
		$cells         = array_fill( 0, ( $first_weekday - $week_start + 7 ) % 7, null );

		for ( $index = 0; $index < 7; $index++ ) {
			$weekday = ( $week_start + $index ) % 7;
			$heads[] = array(
				'label' => $weekdays[ $weekday ],
				'fri'   => $jalali && 5 === $weekday,
			);
		}

		for ( $date = 1; $date <= $length; $date++ ) {
			$cells[] = array(
				'label' => $number( $date ),
				'today' => $date === $day,
				'fri'   => $jalali && 5 === ( $first_weekday + $date - 1 ) % 7,
			);
		}

		return array(
			'title' => $title,
			'rtl'   => $jalali,
			'heads' => $heads,
			'cells' => $cells,
		);
	}
}
