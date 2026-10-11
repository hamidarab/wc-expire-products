<?php
/**
 * Read-only counts for the settings page status cards.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes and caches dashboard counts with a single aggregate query.
 */
final class Stats {
	/**
	 * Transient that caches the counts.
	 *
	 * @var string
	 */
	public const TRANSIENT = 'peep_dashboard_counts';

	/**
	 * Return product and variation counts for the status cards.
	 *
	 * @return array{dated: int, expired: int, managed: int}
	 */
	public static function get_counts() {
		$cached = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['dated'], $cached['expired'], $cached['managed'] ) ) {
			return $cached;
		}

		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate count, cached in a transient below.
			$wpdb->prepare(
				'SELECT
					COUNT( DISTINCT CASE WHEN pm.meta_key = %s THEN pm.post_id END ) AS dated,
					COUNT( DISTINCT CASE WHEN pm.meta_key = %s AND pm.meta_value < %s THEN pm.post_id END ) AS expired,
					COUNT( DISTINCT CASE WHEN pm.meta_key = %s AND pm.meta_value = %s THEN pm.post_id END ) AS managed
				FROM %i AS pm
				INNER JOIN %i AS p ON p.ID = pm.post_id
				WHERE pm.meta_key IN ( %s, %s )
					AND pm.meta_value <> %s
					AND p.post_type IN ( %s, %s )
					AND p.post_status NOT IN ( %s, %s )',
				'_expiration_date',
				'_expiration_date',
				Date::today(),
				'_peep_stock_managed',
				'yes',
				$wpdb->postmeta,
				$wpdb->posts,
				'_expiration_date',
				'_peep_stock_managed',
				'',
				'product',
				'product_variation',
				'trash',
				'auto-draft'
			),
			ARRAY_A
		);

		$counts = array(
			'dated'   => isset( $row['dated'] ) ? absint( $row['dated'] ) : 0,
			'expired' => isset( $row['expired'] ) ? absint( $row['expired'] ) : 0,
			'managed' => isset( $row['managed'] ) ? absint( $row['managed'] ) : 0,
		);

		set_transient( self::TRANSIENT, $counts, 10 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Drop the cached counts after dates, stock, or settings change.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT );
	}
}
