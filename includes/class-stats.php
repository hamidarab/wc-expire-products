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
 * Computes and caches the status card counts with one aggregate query over Dated_Items.
 */
final class Stats {
	/**
	 * Transient that caches the counts. Renamed in 3.5.0 so counts cached
	 * under the old definition of a dated item are not shown.
	 *
	 * @var string
	 */
	public const TRANSIENT = 'peep_status_counts';

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

		list( $base, $args ) = Dated_Items::query();

		// $base is the shared dated-items fragment; its placeholders are filled from $args.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate count, cached in a transient below.
			$wpdb->prepare(
				'SELECT COUNT( DISTINCT p.ID ) AS dated,
					COUNT( DISTINCT CASE WHEN ' . Dated_Items::DATE . ' < %s THEN p.ID END ) AS expired,
					(
						SELECT COUNT( DISTINCT m.post_id ) FROM %i AS m
						INNER JOIN %i AS mp ON mp.ID = m.post_id
						WHERE m.meta_key = %s AND m.meta_value = %s AND mp.post_status NOT IN ( %s, %s )
					) AS managed
				' . $base,
				array_merge( array( Date::today(), $wpdb->postmeta, $wpdb->posts, '_peep_stock_managed', 'yes', 'trash', 'auto-draft' ), $args )
			),
			ARRAY_A
		);
		// phpcs:enable

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
