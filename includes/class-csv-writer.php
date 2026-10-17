<?php
/**
 * CSV output for the export.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes UTF-8 CSV with a byte order mark, so Excel reads Persian text.
 */
final class Csv_Writer {
	/**
	 * Write the header and rows to an open stream.
	 *
	 * @param resource                           $handle Writable stream.
	 * @param array<int, string>                 $header Header cells.
	 * @param array<int, array<int, string|int>> $rows   Data rows.
	 * @return void
	 */
	public static function write( $handle, array $header, array $rows ) {
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming to php://output.

		foreach ( array_merge( array( $header ), $rows ) as $cells ) {
			fputcsv( $handle, array_map( array( self::class, 'cell' ), $cells ), ',', '"', '\\' );
		}
	}

	/**
	 * Neutralize text a spreadsheet would run as a formula.
	 *
	 * Numbers stay numbers, so a negative "days left" is not touched.
	 *
	 * @param string|int $value Cell value.
	 * @return string|int
	 */
	public static function cell( $value ) {
		if ( is_int( $value ) ) {
			return $value;
		}

		$value = (string) $value;

		return '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ? "'" . $value : $value;
	}
}
