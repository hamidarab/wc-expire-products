<?php
/**
 * Selection, columns and rows for the expiring-products export.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns an export request into rows; the file and the preview share it.
 */
final class Exporter {
	/**
	 * Column IDs in their default order.
	 *
	 * @var array<int, string>
	 */
	public const COLUMNS = array( 'id', 'name', 'sku', 'cat', 'date', 'left', 'stock' );

	/**
	 * Columns ticked by default.
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT_COLUMNS = array( 'id', 'name', 'sku', 'date', 'left', 'stock' );

	/**
	 * Ranges offered for "near expiry", in days.
	 *
	 * @var array<int, string>
	 */
	public const RANGES = array( '7', '30', '90', 'custom' );

	/**
	 * Translated column labels.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			'id'    => __( 'Product ID', 'product-expiration-easy-peasy' ),
			'name'  => __( 'Product name', 'product-expiration-easy-peasy' ),
			'sku'   => __( 'SKU', 'product-expiration-easy-peasy' ),
			'cat'   => __( 'Category', 'product-expiration-easy-peasy' ),
			'date'  => __( 'Expiration date', 'product-expiration-easy-peasy' ),
			'left'  => __( 'Days left', 'product-expiration-easy-peasy' ),
			'stock' => __( 'Stock status', 'product-expiration-easy-peasy' ),
		);
	}

	/**
	 * Validate a raw export request.
	 *
	 * @param array<string, mixed> $data  Unslashed request data.
	 * @param string               $today Site today, Gregorian Y-m-d.
	 * @return array{scope: string, range: string, from: string, to: string, format: string, calendar: string, columns: array<int, string>}
	 */
	public static function request( array $data, $today ) {
		$pick = static function ( $key, array $allowed, $fallback ) use ( $data ) {
			$value = isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? sanitize_key( (string) $data[ $key ] ) : '';

			return in_array( $value, $allowed, true ) ? $value : $fallback;
		};

		$from = Date::sanitize_input( $data['from'] ?? '' );
		$to   = Date::sanitize_input( $data['to'] ?? '' );
		$from = '' === $from ? $today : $from;
		$to   = '' === $to ? self::add_days( $today, 30 ) : $to;

		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}

		$columns = isset( $data['columns'] ) && is_array( $data['columns'] ) ? array_map( 'sanitize_key', array_filter( $data['columns'], 'is_scalar' ) ) : self::DEFAULT_COLUMNS;
		$columns = array_values( array_unique( array_intersect( $columns, self::COLUMNS ) ) );

		// The product name is always in the file.
		if ( ! in_array( 'name', $columns, true ) ) {
			$after_id = isset( $columns[0] ) && 'id' === $columns[0];
			array_splice( $columns, $after_id ? 1 : 0, 0, 'name' );
		}

		return array(
			'scope'    => $pick( 'scope', array( 'expired', 'soon', 'both' ), 'both' ),
			'range'    => $pick( 'range', self::RANGES, '30' ),
			'from'     => $from,
			'to'       => $to,
			'format'   => 'xlsx' === $pick( 'format', array( 'xlsx', 'csv' ), 'xlsx' ) && Xlsx_Writer::is_available() ? 'xlsx' : 'csv',
			'calendar' => $pick( 'calendar', array( 'jalali', 'gregorian' ), Date::is_persian_locale() ? 'jalali' : 'gregorian' ),
			'columns'  => $columns,
		);
	}

	/**
	 * The near-expiry window of a request.
	 *
	 * @param array<string, mixed> $request Validated request.
	 * @param string               $today   Site today, Gregorian Y-m-d.
	 * @return array{0: string, 1: string} First and last day, inclusive.
	 */
	public static function window( array $request, $today ) {
		if ( 'custom' === $request['range'] ) {
			return array( $request['from'], $request['to'] );
		}

		return array( $today, self::add_days( $today, (int) $request['range'] ) );
	}

	/**
	 * Whether a stored date belongs in the export.
	 *
	 * Mirrors the SQL in select(); the tests exercise the rules through it.
	 *
	 * @param string               $date    Gregorian Y-m-d.
	 * @param array<string, mixed> $request Validated request.
	 * @param string               $today   Site today, Gregorian Y-m-d.
	 * @return bool
	 */
	public static function matches( $date, array $request, $today ) {
		list( $from, $to ) = self::window( $request, $today );

		$expired = 'soon' !== $request['scope'] && $date < $today;
		$soon    = 'expired' !== $request['scope'] && $date >= $from && $date <= $to;

		return $expired || $soon;
	}

	/**
	 * Product counts for each scope card under the request's range.
	 *
	 * @param array<string, mixed> $request Validated request.
	 * @param string               $today   Site today, Gregorian Y-m-d.
	 * @return array{expired: int, soon: int, both: int}
	 */
	public function counts( array $request, $today ) {
		global $wpdb;

		list( $from, $to )   = self::window( $request, $today );
		list( $base, $args ) = self::base_query();

		// $base is a fixed SQL fragment whose placeholders are filled from $args.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live counts for the export cards.
			$wpdb->prepare(
				'SELECT SUM( expires < %s ) AS expired, SUM( expires BETWEEN %s AND %s ) AS soon, SUM( expires < %s OR expires BETWEEN %s AND %s ) AS any_scope
				FROM ( SELECT MIN( CAST( pm.meta_value AS DATE ) ) AS expires ' . $base . ' GROUP BY p.ID ) AS dated',
				array_merge( array( $today, $from, $to, $today, $from, $to ), $args )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'expired' => absint( $row['expired'] ?? 0 ),
			'soon'    => absint( $row['soon'] ?? 0 ),
			'both'    => absint( $row['any_scope'] ?? 0 ),
		);
	}

	/**
	 * Build the header and rows of the file, plus the scope counts.
	 *
	 * @param array<string, mixed> $request Validated request.
	 * @param string               $today   Site today, Gregorian Y-m-d.
	 * @param int                  $limit   Maximum rows, or 0 for all.
	 * @return array{count: int, counts: array<string, int>, columns: array<int, array{id: string, label: string}>, rows: array<int, array<int, string|int>>}
	 */
	public function rows( array $request, $today, $limit = 0 ) {
		$counts  = $this->counts( $request, $today );
		$labels  = self::labels();
		$columns = array();
		$rows    = array();

		foreach ( $request['columns'] as $column ) {
			$columns[] = array(
				'id'    => $column,
				'label' => $labels[ $column ],
			);
		}

		foreach ( $this->select( $request, $today, $limit ) as $item ) {
			$product = wc_get_product( $item[0] );

			if ( $product ) {
				$rows[] = $this->row( $product, $item[1], $request, $today );
			}
		}

		return array(
			'count'   => $counts[ $request['scope'] ],
			'counts'  => $counts,
			'columns' => $columns,
			'rows'    => $rows,
		);
	}

	/**
	 * File name for the download.
	 *
	 * @param array<string, mixed> $request Validated request.
	 * @param string               $today   Site today, Gregorian Y-m-d.
	 * @return string
	 */
	public static function filename( array $request, $today ) {
		$date = $today;

		if ( 'jalali' === $request['calendar'] ) {
			$parts = array_map( 'intval', explode( '-', $today ) );
			$date  = vsprintf( '%04d-%02d-%02d', Jalali::convert( $parts[0], $parts[1], $parts[2] ) );
		}

		return 'expiring-products-' . $date . '.' . $request['format'];
	}

	/**
	 * One row of cells, in the requested column order.
	 *
	 * @param \WC_Product          $product Product or variation.
	 * @param string               $date    Stored expiration date.
	 * @param array<string, mixed> $request Validated request.
	 * @param string               $today   Site today, Gregorian Y-m-d.
	 * @return array<int, string|int>
	 */
	private function row( $product, $date, array $request, $today ) {
		$parent   = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : null;
		$statuses = wc_get_product_stock_status_options();
		$status   = $product->get_stock_status();
		$cells    = array();

		foreach ( $request['columns'] as $column ) {
			switch ( $column ) {
				case 'id':
					$cells[] = $product->get_id();
					break;
				case 'name':
					$name       = wp_strip_all_tags( $parent ? $parent->get_name() : $product->get_name() );
					$attributes = $parent ? wc_get_formatted_variation( $product, true, false, true ) : '';
					$cells[]    = '' === $attributes ? $name : $name . ' - ' . wp_strip_all_tags( $attributes );
					break;
				case 'sku':
					$cells[] = (string) $product->get_sku();
					break;
				case 'cat':
					$terms   = wp_get_post_terms( $parent ? $parent->get_id() : $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
					$cells[] = is_array( $terms ) ? implode( ', ', $terms ) : '';
					break;
				case 'date':
					$cells[] = 'jalali' === $request['calendar'] ? Jalali::format( $date, 'Y/m/d' ) : $date;
					break;
				case 'left':
					$cells[] = (int) ( new \DateTimeImmutable( $today ) )->diff( new \DateTimeImmutable( $date ) )->format( '%r%a' );
					break;
				case 'stock':
					$cells[] = $statuses[ $status ] ?? $status;
					break;
			}
		}

		return $cells;
	}

	/**
	 * Matching products and variations with their dates, soonest first.
	 *
	 * A product with duplicate date rows counts once, by its earliest date.
	 *
	 * @param array<string, mixed> $request Validated request.
	 * @param string               $today   Site today, Gregorian Y-m-d.
	 * @param int                  $limit   Maximum rows, or 0 for all.
	 * @return array<int, array{0: int, 1: string}>
	 */
	private function select( array $request, $today, $limit ) {
		global $wpdb;

		list( $from, $to ) = self::window( $request, $today );

		list( $base, $args ) = self::base_query();

		// $base is a fixed SQL fragment whose placeholders are filled from $args.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- On-demand export of live data.
			$wpdb->prepare(
				'SELECT p.ID, MIN( CAST( pm.meta_value AS DATE ) ) AS expires ' . $base . '
				GROUP BY p.ID
				HAVING ( %d = 1 AND expires < %s ) OR ( %d = 1 AND expires BETWEEN %s AND %s )
				ORDER BY expires ASC, p.ID ASC
				LIMIT %d',
				array_merge(
					$args,
					array(
						'soon' === $request['scope'] ? 0 : 1,
						$today,
						'expired' === $request['scope'] ? 0 : 1,
						$from,
						$to,
						$limit ? $limit : PHP_INT_MAX,
					)
				)
			),
			ARRAY_N
		);
		// phpcs:enable

		$items = array();

		foreach ( (array) $results as $result ) {
			$items[] = array( absint( $result[0] ), (string) $result[1] );
		}

		return $items;
	}

	/**
	 * FROM and WHERE shared by every export query, with their arguments.
	 *
	 * Dated products and variations that are not trashed; variable parents
	 * are left out because their variations carry the dates.
	 *
	 * @return array{0: string, 1: array<int, string>}
	 */
	private static function base_query() {
		global $wpdb;

		return array(
			'FROM %i AS p
			INNER JOIN %i AS pm ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_type IN ( %s, %s )
				AND p.post_status NOT IN ( %s, %s )
				AND NOT EXISTS (
					SELECT 1 FROM %i AS tr
					INNER JOIN %i AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					INNER JOIN %i AS t ON t.term_id = tt.term_id
					WHERE tr.object_id = p.ID AND tt.taxonomy = %s AND t.slug = %s
				)',
			array( $wpdb->posts, $wpdb->postmeta, '_expiration_date', 'product', 'product_variation', 'trash', 'auto-draft', $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->terms, 'product_type', 'variable' ),
		);
	}

	/**
	 * Add whole days to a Gregorian date.
	 *
	 * @param string $date Gregorian Y-m-d.
	 * @param int    $days Days to add.
	 * @return string
	 */
	private static function add_days( $date, $days ) {
		return ( new \DateTimeImmutable( $date ) )->modify( '+' . (int) $days . ' days' )->format( 'Y-m-d' );
	}
}
