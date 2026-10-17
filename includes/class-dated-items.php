<?php
/**
 * The one definition of a "dated item".
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SQL shared by the status cards, the background check and the export.
 *
 * A dated item is a product or variation that is not trashed, has a
 * non-empty _expiration_date, and is not of the variable product type.
 * Variable parents are left out because their variations carry the dates
 * and the check never changes the parent itself.
 */
final class Dated_Items {
	/**
	 * SQL expression for an item's expiration date.
	 *
	 * @var string
	 */
	public const DATE = 'CAST( pm.meta_value AS DATE )';

	/**
	 * FROM and WHERE that select dated items, aliased p (post) and pm (date meta).
	 *
	 * Callers append their own "AND …" conditions and pass these arguments
	 * to $wpdb->prepare() in this position.
	 *
	 * @return array{0: string, 1: array<int, string>}
	 */
	public static function query() {
		global $wpdb;

		return array(
			'FROM %i AS p
			INNER JOIN %i AS pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value <> %s
			WHERE p.post_type IN ( %s, %s )
				AND p.post_status NOT IN ( %s, %s )
				AND NOT EXISTS (
					SELECT 1 FROM %i AS tr
					INNER JOIN %i AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					INNER JOIN %i AS t ON t.term_id = tt.term_id
					WHERE tr.object_id = p.ID AND tt.taxonomy = %s AND t.slug = %s
				)',
			array( $wpdb->posts, $wpdb->postmeta, '_expiration_date', '', 'product', 'product_variation', 'trash', 'auto-draft', $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->terms, 'product_type', 'variable' ),
		);
	}
}
