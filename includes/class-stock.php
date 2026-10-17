<?php
/**
 * Stock changes made by the expiration check.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets dated products out of stock and restores the status they had before.
 */
final class Stock {
	/**
	 * Set one dated product out of stock and remember its previous status.
	 *
	 * Only in-stock and backorder products change; variable parents follow
	 * their variations.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool Whether the stock status was changed.
	 */
	public static function mark_out_of_stock( $product_id ) {
		$date    = Date::sanitize( get_post_meta( $product_id, '_expiration_date', true ) );
		$product = wc_get_product( $product_id );

		if (
			'' === $date
			|| ! $product
			|| $product->is_type( 'variable' )
			|| ! in_array( $product->get_stock_status(), array( 'instock', 'onbackorder' ), true )
		) {
			return false;
		}

		update_post_meta( $product_id, '_peep_previous_stock_status', $product->get_stock_status() );
		update_post_meta( $product_id, '_peep_stock_managed', 'yes' );

		try {
			$product->set_stock_status( 'outofstock' );
			$saved_product_id = $product->save();
		} catch ( \Throwable $throwable ) {
			$saved_product_id = 0;
		}

		if ( ! $saved_product_id ) {
			self::forget( $product_id );
			return false;
		}

		self::sync_variation_parent( $product_id );

		return true;
	}

	/**
	 * Restore stock previously changed by this plugin.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool Whether a managed stock value was restored.
	 */
	public static function restore( $product_id ) {
		if ( 'yes' !== get_post_meta( $product_id, '_peep_stock_managed', true ) ) {
			return false;
		}

		$previous_status = get_post_meta( $product_id, '_peep_previous_stock_status', true );
		$product         = wc_get_product( $product_id );

		if ( ! in_array( $previous_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
			$previous_status = 'instock';
		}

		$restorable = $product && 'outofstock' === $product->get_stock_status();

		// An empty managed stock cannot honestly go back to "in stock".
		if ( $restorable && 'instock' === $previous_status && $product->managing_stock() ) {
			$stock_quantity = $product->get_stock_quantity();
			$restorable     = null === $stock_quantity || $stock_quantity > 0;
		}

		if ( $restorable ) {
			wc_update_product_stock_status( $product_id, $previous_status );
		}

		self::forget( $product_id );
		self::sync_variation_parent( $product_id );

		return $restorable;
	}

	/**
	 * Drop the plugin's stock bookkeeping for one product.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return void
	 */
	private static function forget( $product_id ) {
		delete_post_meta( $product_id, '_peep_stock_managed' );
		delete_post_meta( $product_id, '_peep_previous_stock_status' );
	}

	/**
	 * Synchronize a variable parent after a variation stock change.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return void
	 */
	private static function sync_variation_parent( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( $product && $product->is_type( 'variation' ) && $product->get_parent_id() ) {
			\WC_Product_Variable::sync_stock_status( $product->get_parent_id() );
		}
	}
}
