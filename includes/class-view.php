<?php
/**
 * Small output helpers shared by the settings views.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints escaped icons and tooltips, and localizes numbers.
 */
final class View {
	/**
	 * Print an icon.
	 *
	 * @param string $name    Icon name.
	 * @param string $classes Extra class names.
	 * @return void
	 */
	public static function icon( $name, $classes = '' ) {
		echo wp_kses( Icons::get( $name, $classes ), Icons::allowed_html() );
	}

	/**
	 * Print a "?" button that shows a tooltip on hover and focus.
	 *
	 * @param string $id   Tooltip element ID.
	 * @param string $text Tooltip text.
	 * @return void
	 */
	public static function tip( $id, $text ) {
		printf(
			'<span class="peep-tip"><button type="button" class="peep-tip-btn" aria-describedby="%1$s"><span aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></button><span class="peep-tip-box" role="tooltip" id="%1$s">%4$s</span></span>',
			esc_attr( $id ),
			esc_html_x( '?', 'tooltip button', 'product-expiration-easy-peasy' ),
			esc_html__( 'More information', 'product-expiration-easy-peasy' ),
			esc_html( $text )
		);
	}

	/**
	 * Format a whole number for the admin language.
	 *
	 * @param int $number Number.
	 * @return string
	 */
	public static function number( $number ) {
		$formatted = number_format_i18n( (int) $number );

		return Date::is_persian_locale() ? Jalali::digits( $formatted ) : $formatted;
	}
}
