<?php
/**
 * Remove plugin-owned data and restore stock managed by the plugin.
 *
 * @package ProductExpirationEasyPeasy
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean one site's Product Expiration Easy Peasy data.
 *
 * @return void
 */
function peep_uninstall_site_data() {
	global $wpdb;

	$variation_parent_ids = array();

	while ( true ) {
		$product_ids = get_posts(
			array(
				'post_type'              => array( 'product', 'product_variation' ),
				'post_status'            => array_keys( get_post_stati() ),
				'fields'                 => 'ids',
				'posts_per_page'         => 50,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_peep_stock_managed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required one-time uninstall lookup.
				'meta_value'             => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required one-time uninstall lookup.
			)
		);

		if ( empty( $product_ids ) ) {
			break;
		}

		foreach ( $product_ids as $product_id ) {
			$previous_status = get_post_meta( $product_id, '_peep_previous_stock_status', true );
			$should_restore  = true;

			if ( ! in_array( $previous_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
				$previous_status = 'instock';
			}

			if ( function_exists( 'wc_update_product_stock_status' ) ) {
				$product = wc_get_product( $product_id );

				if ( ! $product || 'outofstock' !== $product->get_stock_status() ) {
					$should_restore = false;
				} elseif (
					'instock' === $previous_status
					&& $product->managing_stock()
					&& null !== $product->get_stock_quantity()
					&& $product->get_stock_quantity() <= 0
				) {
					$should_restore = false;
				}

				if ( $should_restore ) {
					wc_update_product_stock_status( $product_id, $previous_status );
				}

				if ( $product && $product->is_type( 'variation' ) && class_exists( 'WC_Product_Variable' ) ) {
					WC_Product_Variable::sync_stock_status( $product->get_parent_id() );
				}
			} else {
				$current_status = get_post_meta( $product_id, '_stock_status', true );
				$manages_stock  = 'yes' === get_post_meta( $product_id, '_manage_stock', true );
				$stock_quantity = get_post_meta( $product_id, '_stock', true );
				$parent_id      = wp_get_post_parent_id( $product_id );

				if ( 'outofstock' !== $current_status ) {
					$should_restore = false;
				} elseif ( 'instock' === $previous_status && $manages_stock && is_numeric( $stock_quantity ) && (float) $stock_quantity <= 0 ) {
					$should_restore = false;
				}

				if ( $should_restore ) {
					peep_uninstall_sync_stock_storage( $product_id, $previous_status );

					if ( $parent_id && 'product_variation' === get_post_type( $product_id ) ) {
						$variation_parent_ids[] = $parent_id;
					}
				}
			}

			delete_post_meta( $product_id, '_peep_stock_managed' );
			delete_post_meta( $product_id, '_peep_previous_stock_status' );
		}
	}

	if ( ! function_exists( 'wc_update_product_stock_status' ) ) {
		foreach ( array_unique( array_map( 'absint', $variation_parent_ids ) ) as $parent_id ) {
			peep_uninstall_sync_variable_parent_stock( $parent_id );
		}
	}

	wp_clear_scheduled_hook( 'peep_check_expired_products' );
	delete_option( 'peep_settings' );
	delete_option( 'peep_expiration_cron_lock' );
	delete_option( 'peep_check_job' );

	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'peep_check_batch' );
	}

	delete_transient( 'peep_dashboard_counts' );
	peep_uninstall_delete_product_meta( '_expiration_date' );
	peep_uninstall_delete_product_meta( '_peep_stock_managed' );
	peep_uninstall_delete_product_meta( '_peep_previous_stock_status' );

	$order_item_meta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
	$table_exists          = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time uninstall integrity check.
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $order_item_meta_table ) )
	);

	if ( $order_item_meta_table === $table_exists ) {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must remove plugin-owned order-item metadata.
			$wpdb->prepare(
				'DELETE FROM %i WHERE meta_key = %s',
				$order_item_meta_table,
				'_peep_expiration_date'
			)
		);
	}
}

/**
 * Keep WooCommerce stock storage consistent when WooCommerce is inactive.
 *
 * WooCommerce normally updates the product meta, lookup table, and visibility
 * taxonomy together. During uninstall its functions may be unavailable, so the
 * same persisted values are synchronized directly before plugin metadata is
 * removed.
 *
 * @param int    $product_id  Product or variation ID.
 * @param string $stock_status Valid WooCommerce stock status.
 * @return void
 */
function peep_uninstall_sync_stock_storage( $product_id, $stock_status ) {
	global $wpdb;

	if ( ! in_array( $stock_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
		return;
	}

	update_post_meta( $product_id, '_stock_status', $stock_status );

	$lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
	$table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce is inactive, so its data-store synchronization is unavailable.
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $lookup_table ) )
	);

	if ( $lookup_table === $table_exists ) {
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Mirrors WooCommerce stock lookup persistence during uninstall.
			$lookup_table,
			array( 'stock_status' => $stock_status ),
			array( 'product_id' => $product_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	peep_uninstall_sync_outofstock_visibility( $product_id, $stock_status );
	clean_post_cache( $product_id );
}

/**
 * Synchronize WooCommerce's persisted out-of-stock visibility relationship.
 *
 * @param int    $product_id  Product or variation ID.
 * @param string $stock_status Valid WooCommerce stock status.
 * @return void
 */
function peep_uninstall_sync_outofstock_visibility( $product_id, $stock_status ) {
	global $wpdb;

	$term_taxonomy_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Taxonomy may not be registered while WooCommerce is inactive.
		$wpdb->prepare(
			'SELECT tt.term_taxonomy_id FROM %i AS tt INNER JOIN %i AS t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND t.slug = %s LIMIT 1',
			$wpdb->term_taxonomy,
			$wpdb->terms,
			'product_visibility',
			'outofstock'
		)
	);

	if ( ! $term_taxonomy_id ) {
		return;
	}

	if ( 'outofstock' === $stock_status ) {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Mirrors WooCommerce visibility persistence during uninstall.
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (object_id, term_taxonomy_id, term_order) VALUES (%d, %d, 0)',
				$wpdb->term_relationships,
				$product_id,
				$term_taxonomy_id
			)
		);
	} else {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Mirrors WooCommerce visibility persistence during uninstall.
			$wpdb->prepare(
				'DELETE FROM %i WHERE object_id = %d AND term_taxonomy_id = %d',
				$wpdb->term_relationships,
				$product_id,
				$term_taxonomy_id
			)
		);
	}

	$relationship_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Recounts the one directly synchronized WooCommerce term.
		$wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE term_taxonomy_id = %d',
			$wpdb->term_relationships,
			$term_taxonomy_id
		)
	);

	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keeps the directly synchronized WooCommerce term count accurate.
		$wpdb->term_taxonomy,
		array( 'count' => absint( $relationship_count ) ),
		array( 'term_taxonomy_id' => absint( $term_taxonomy_id ) ),
		array( '%d' ),
		array( '%d' )
	);

	clean_object_term_cache( $product_id, get_post_type( $product_id ) );
	wp_cache_delete( $product_id, 'product_visibility_relationships' );
	wp_cache_set_terms_last_changed();
}

/**
 * Recalculate a variable parent's stock status from its active children.
 *
 * @param int $parent_id Variable product ID.
 * @return void
 */
function peep_uninstall_sync_variable_parent_stock( $parent_id ) {
	global $wpdb;

	$child_statuses = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce is inactive, so its variable-product synchronizer is unavailable.
		$wpdb->prepare(
			"SELECT pm.meta_value
			FROM %i AS p
			INNER JOIN %i AS pm ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_parent = %d
			AND p.post_type = %s
			AND p.post_status IN ('publish', 'private')",
			$wpdb->posts,
			$wpdb->postmeta,
			'_stock_status',
			$parent_id,
			'product_variation'
		)
	);

	if ( in_array( 'instock', $child_statuses, true ) ) {
		$parent_status = 'instock';
	} elseif ( in_array( 'onbackorder', $child_statuses, true ) ) {
		$parent_status = 'onbackorder';
	} else {
		$parent_status = 'outofstock';
	}

	peep_uninstall_sync_stock_storage( $parent_id, $parent_status );
}

/**
 * Delete one plugin meta key only from WooCommerce products and variations.
 *
 * The legacy expiration key is intentionally generic, so a global
 * delete_post_meta_by_key() could erase unrelated post data.
 *
 * @param string $meta_key Product metadata key.
 * @return void
 */
function peep_uninstall_delete_product_meta( $meta_key ) {
	while ( true ) {
		$product_ids = get_posts(
			array(
				'post_type'              => array( 'product', 'product_variation' ),
				'post_status'            => array_keys( get_post_stati() ),
				'fields'                 => 'ids',
				'posts_per_page'         => 50,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required one-time uninstall lookup.
			)
		);

		if ( empty( $product_ids ) ) {
			break;
		}

		foreach ( $product_ids as $product_id ) {
			delete_post_meta( $product_id, $meta_key );
		}
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		peep_uninstall_site_data();
		restore_current_blog();
	}
} else {
	peep_uninstall_site_data();
}
