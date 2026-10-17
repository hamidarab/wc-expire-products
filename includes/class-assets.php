<?php
/**
 * Scripts shared by the product screens and the settings page.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the Jalali converter and the date picker.
 */
final class Assets {
	/**
	 * Handle of the Jalali converter script.
	 *
	 * @var string
	 */
	public const JALALI = 'product-expiration-easy-peasy-jalali';

	/**
	 * Handle of the date picker script and style.
	 *
	 * @var string
	 */
	public const DATEPICKER = 'product-expiration-easy-peasy-datepicker';

	/**
	 * Enqueue the Jalali converter.
	 *
	 * @return void
	 */
	public static function enqueue_jalali() {
		wp_enqueue_script(
			self::JALALI,
			PRODUCT_EXPIRATION_EASY_PEASY_URL . 'admin/js/peep-jalali.js',
			array(),
			PRODUCT_EXPIRATION_EASY_PEASY_VERSION,
			true
		);
	}

	/**
	 * Enqueue the date picker with its calendar data and strings.
	 *
	 * @return void
	 */
	public static function enqueue_datepicker() {
		global $wp_locale;

		self::enqueue_jalali();

		wp_enqueue_style(
			self::DATEPICKER,
			PRODUCT_EXPIRATION_EASY_PEASY_URL . 'admin/css/peep-datepicker.css',
			array(),
			PRODUCT_EXPIRATION_EASY_PEASY_VERSION
		);

		wp_enqueue_script(
			self::DATEPICKER,
			PRODUCT_EXPIRATION_EASY_PEASY_URL . 'admin/js/peep-datepicker.js',
			array( self::JALALI ),
			PRODUCT_EXPIRATION_EASY_PEASY_VERSION,
			true
		);

		$weekdays       = array();
		$weekday_abbrev = array();

		for ( $index = 0; $index < 7; $index++ ) {
			$weekday          = $wp_locale->get_weekday( $index );
			$weekdays[]       = $weekday;
			$weekday_abbrev[] = $wp_locale->get_weekday_abbrev( $weekday );
		}

		wp_localize_script(
			self::DATEPICKER,
			'peepDatepicker',
			array(
				'today'         => Date::today(),
				'persianDigits' => Date::is_persian_locale(),
				'isRtl'         => is_rtl(),
				'gregorian'     => array(
					'months'      => array_values( $wp_locale->month ),
					'weekdays'    => $weekdays,
					'weekdaysMin' => $weekday_abbrev,
					'startOfWeek' => (int) get_option( 'start_of_week', 0 ),
				),
				'strings'       => array(
					'chooseDate'    => __( 'Choose date', 'product-expiration-easy-peasy' ),
					'previousMonth' => __( 'Previous month', 'product-expiration-easy-peasy' ),
					'nextMonth'     => __( 'Next month', 'product-expiration-easy-peasy' ),
					'month'         => __( 'Month', 'product-expiration-easy-peasy' ),
					'year'          => __( 'Year', 'product-expiration-easy-peasy' ),
					'today'         => __( 'Today', 'product-expiration-easy-peasy' ),
					'clear'         => __( 'Clear', 'product-expiration-easy-peasy' ),
				),
			)
		);
	}
}
