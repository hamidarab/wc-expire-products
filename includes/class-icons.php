<?php
/**
 * Inline SVG icons for the admin screens.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static icon markup and the matching wp_kses() allow-list.
 */
final class Icons {
	/**
	 * Inner markup of each 24×24 stroke icon.
	 *
	 * @var array<string, string>
	 */
	private const PATHS = array(
		'alert'          => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
		'arrow'          => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
		'box'            => '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
		'calendar'       => '<rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		'calendar-check' => '<rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>',
		'check'          => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
		'chevron'        => '<path d="m15 18-6-6 6-6"/>',
		'clock'          => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'close'          => '<path d="M18 6 6 18M6 6l12 12"/>',
		'download'       => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'eye'            => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
		'file'           => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
		'grip'           => '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>',
		'help'           => '<circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
		'image'          => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
		'info'           => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
		'mail'           => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
		'minus'          => '<path d="M5 12h14"/>',
		'plus'           => '<path d="M12 5v14M5 12h14"/>',
		'refresh'        => '<path d="M21 12a9 9 0 1 1-2.6-6.4L21 8"/><path d="M21 3v5h-5"/>',
		'send'           => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
		'spinner'        => '<path d="M21 12a9 9 0 1 1-6.2-8.6"/>',
		'tag'            => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		'tick'           => '<path d="m5 12 5 5 9-10"/>',
		'x'              => '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>',
		'zap'            => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"/>',
	);

	/**
	 * Return an icon's markup; print it through wp_kses() with allowed_html().
	 *
	 * @param string $name    Icon name.
	 * @param string $classes Extra class names.
	 * @return string
	 */
	public static function get( $name, $classes = '' ) {
		if ( 'logo' === $name ) {
			return '<svg class="peep-logo" viewBox="0 0 120 120" aria-hidden="true" focusable="false"><rect width="120" height="120" rx="30" fill="#059669"/><path d="M46 28H86a9 9 0 0 1 9 9V83a9 9 0 0 1-9 9H46L22 60Z" fill="#fff"/><circle cx="42" cy="60" r="6.5" fill="#059669"/><circle cx="88" cy="88" r="21" fill="#EA580C" stroke="#fff" stroke-width="5"/><path d="M88 77v11l7 5" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}

		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}

		$fill = 'grip' === $name ? 'currentColor' : 'none';

		return '<svg class="' . esc_attr( trim( 'peep-icon ' . $classes ) ) . '" viewBox="0 0 24 24" fill="' . $fill . '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . self::PATHS[ $name ] . '</svg>';
	}

	/**
	 * Allowed markup for the icons.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html() {
		$shape = array_fill_keys( array( 'd', 'x', 'y', 'cx', 'cy', 'r', 'rx', 'width', 'height', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin' ), true );

		return array(
			'svg'    => $shape + array_fill_keys( array( 'class', 'viewbox', 'aria-hidden', 'focusable' ), true ),
			'path'   => $shape,
			'rect'   => $shape,
			'circle' => $shape,
		);
	}
}
