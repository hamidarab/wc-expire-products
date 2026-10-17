<?php
/**
 * Minimal single-sheet XLSX writer.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes one worksheet with a bold header row, using ZipArchive.
 */
final class Xlsx_Writer {
	/**
	 * Whether this server can build XLSX files.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( '\ZipArchive' );
	}

	/**
	 * Write a workbook to a file.
	 *
	 * @param string                             $path   Target file.
	 * @param array<int, string>                 $header Header cells.
	 * @param array<int, array<int, string|int>> $rows   Data rows; integers become numbers.
	 * @param bool                               $rtl    Show the sheet right to left.
	 * @return bool
	 */
	public static function write( $path, array $header, array $rows, $rtl ) {
		$zip = new \ZipArchive();

		if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return false;
		}

		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

		$zip->addFromString( '[Content_Types].xml', $xml . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', $xml . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', $xml . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>' );

		$sheet  = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"' . ( $rtl ? ' rightToLeft="1"' : '' ) . '/></sheetViews><sheetData>';
		$sheet .= self::row( 1, $header, true );

		foreach ( array_values( $rows ) as $index => $cells ) {
			$sheet .= self::row( $index + 2, $cells, false );
		}

		$zip->addFromString( 'xl/worksheets/sheet1.xml', $xml . $sheet . '</sheetData></worksheet>' );

		return $zip->close();
	}

	/**
	 * One row of cells.
	 *
	 * @param int                    $number Row number, from 1.
	 * @param array<int, string|int> $cells  Cell values.
	 * @param bool                   $bold   Use the bold header style.
	 * @return string
	 */
	private static function row( $number, array $cells, $bold ) {
		$xml = '<row r="' . $number . '">';

		foreach ( array_values( $cells ) as $index => $value ) {
			$ref = self::column( $index ) . $number;

			if ( is_int( $value ) ) {
				$xml .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
				continue;
			}

			// Drop characters XML 1.0 cannot carry.
			$text = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $value );
			$xml .= '<c r="' . $ref . '" t="inlineStr"' . ( $bold ? ' s="1"' : '' ) . '><is><t xml:space="preserve">' . htmlspecialchars( (string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</t></is></c>';
		}

		return $xml . '</row>';
	}

	/**
	 * Spreadsheet column letters for a zero-based index.
	 *
	 * @param int $index Column index.
	 * @return string
	 */
	private static function column( $index ) {
		$letters = '';

		for ( $index++; $index > 0; $index = intdiv( $index - 1, 26 ) ) {
			$letters = chr( 65 + ( $index - 1 ) % 26 ) . $letters;
		}

		return $letters;
	}
}
