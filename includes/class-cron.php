<?php
/**
 * Scheduled product expiration processing.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marks products approaching expiration as out of stock and notifies staff.
 */
final class Cron {
	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	private const HOOK = 'peep_check_expired_products';

	/**
	 * Atomic lock option name.
	 *
	 * @var string
	 */
	private const LOCK_OPTION = 'peep_expiration_cron_lock';

	/**
	 * Number of products loaded at once.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Unique value owned by the current cron worker.
	 *
	 * @var string
	 */
	private $lock_value = '';

	/**
	 * Register the scheduled callback.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		add_action( self::HOOK, array( $this, 'check_expired_products' ) );
	}

	/**
	 * Ensure the daily job exists.
	 *
	 * @return void
	 */
	public static function schedule_check() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Remove the job and any stale lock.
	 *
	 * @return void
	 */
	public static function unschedule_check() {
		wp_clear_scheduled_hook( self::HOOK );
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Calculate the inclusive cutoff date from validated settings.
	 *
	 * @param Settings $settings Settings service.
	 * @return string Gregorian Y-m-d date.
	 */
	public static function get_cutoff_date( Settings $settings ) {
		$period = absint( $settings->get_setting( 'notification_period' ) );
		$type   = (string) $settings->get_setting( 'notification_period_type' );
		$period = max( 1, min( 365, $period ) );

		if ( 'weeks' === $type ) {
			$interval = new \DateInterval( 'P' . ( $period * 7 ) . 'D' );
		} elseif ( 'months' === $type ) {
			$interval = new \DateInterval( 'P' . $period . 'M' );
		} else {
			$interval = new \DateInterval( 'P' . $period . 'D' );
		}

		return ( new \DateTimeImmutable( 'today', wp_timezone() ) )->add( $interval )->format( 'Y-m-d' );
	}

	/**
	 * Restore stock previously changed by this plugin.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool Whether a managed stock value was restored.
	 */
	public static function restore_managed_stock( $product_id ) {
		if ( 'yes' !== get_post_meta( $product_id, '_peep_stock_managed', true ) ) {
			return false;
		}

		$previous_status = get_post_meta( $product_id, '_peep_previous_stock_status', true );
		$product         = wc_get_product( $product_id );

		if ( ! in_array( $previous_status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
			$previous_status = 'instock';
		}

		if ( ! $product || 'outofstock' !== $product->get_stock_status() ) {
			delete_post_meta( $product_id, '_peep_stock_managed' );
			delete_post_meta( $product_id, '_peep_previous_stock_status' );
			self::sync_variation_parent( $product_id );
			return false;
		}

		$stock_quantity = $product->get_stock_quantity();

		if ( 'instock' === $previous_status && $product->managing_stock() && null !== $stock_quantity && $stock_quantity <= 0 ) {
			delete_post_meta( $product_id, '_peep_stock_managed' );
			delete_post_meta( $product_id, '_peep_previous_stock_status' );
			self::sync_variation_parent( $product_id );
			return false;
		}

		wc_update_product_stock_status( $product_id, $previous_status );
		delete_post_meta( $product_id, '_peep_stock_managed' );
		delete_post_meta( $product_id, '_peep_previous_stock_status' );
		self::sync_variation_parent( $product_id );

		return true;
	}

	/**
	 * Process products in bounded batches.
	 *
	 * @return int Number of products whose stock was changed.
	 */
	public function check_expired_products() {
		if ( ! function_exists( 'wc_get_product' ) || ! $this->acquire_lock() ) {
			return 0;
		}

		$processed = 0;
		$page      = 1;
		$cutoff    = self::get_cutoff_date( $this->settings );

		try {
			do {
				$query = new \WP_Query(
					array(
						'post_type'              => array( 'product', 'product_variation' ),
						'post_status'            => 'publish',
						'fields'                 => 'ids',
						'posts_per_page'         => self::BATCH_SIZE,
						'paged'                  => $page,
						'orderby'                => 'ID',
						'order'                  => 'ASC',
						'no_found_rows'          => true,
						'cache_results'          => false,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required indexed plugin metadata lookup.
							array(
								'key'     => '_expiration_date',
								'value'   => $cutoff,
								'compare' => '<=',
								'type'    => 'DATE',
							),
						),
					)
				);

				$product_ids  = array_map( 'absint', $query->posts );
				$notification = array();

				foreach ( $product_ids as $product_id ) {
					$date    = Date::sanitize( get_post_meta( $product_id, '_expiration_date', true ) );
					$product = wc_get_product( $product_id );

					if (
						'' === $date
						|| ! $product
						|| $product->is_type( 'variable' )
						|| ! in_array( $product->get_stock_status(), array( 'instock', 'onbackorder' ), true )
					) {
						continue;
					}

					$previous_status = $product->get_stock_status();

					update_post_meta( $product_id, '_peep_previous_stock_status', $previous_status );
					update_post_meta( $product_id, '_peep_stock_managed', 'yes' );

					try {
						$product->set_stock_status( 'outofstock' );
						$saved_product_id = $product->save();
					} catch ( \Throwable $throwable ) {
						$saved_product_id = 0;
					}

					if ( ! $saved_product_id ) {
						delete_post_meta( $product_id, '_peep_stock_managed' );
						delete_post_meta( $product_id, '_peep_previous_stock_status' );
						continue;
					}

					self::sync_variation_parent( $product_id );

					$notification[] = $product;
					++$processed;
				}

				if ( ! empty( $notification ) ) {
					$this->send_notification_email( $notification, $cutoff );
				}

				$batch_count = count( $product_ids );
				++$page;
			} while ( self::BATCH_SIZE === $batch_count );
		} finally {
			$this->release_lock();
			wp_reset_postdata();
		}

		return $processed;
	}

	/**
	 * Acquire an atomic, self-expiring cron lock.
	 *
	 * @return bool
	 */
	private function acquire_lock() {
		$now        = time();
		$lock_value = $now . ':' . wp_generate_uuid4();

		if ( add_option( self::LOCK_OPTION, $lock_value, '', false ) ) {
			$this->lock_value = $lock_value;
			return true;
		}

		$existing      = get_option( self::LOCK_OPTION, '' );
		$existing_text = is_scalar( $existing ) ? (string) $existing : '';
		$lock_time     = absint( $existing_text );

		if ( $lock_time && ( $now - $lock_time ) < 15 * MINUTE_IN_SECONDS ) {
			return false;
		}

		global $wpdb;

		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-swap is required for an overlap-safe lock.
			$wpdb->prepare(
				'UPDATE %i SET option_value = %s WHERE option_name = %s AND option_value = %s',
				$wpdb->options,
				$lock_value,
				self::LOCK_OPTION,
				maybe_serialize( $existing )
			)
		);
		wp_cache_delete( self::LOCK_OPTION, 'options' );

		if ( 1 === $updated ) {
			$this->lock_value = $lock_value;
			return true;
		}

		return false;
	}

	/**
	 * Release only the lock token owned by this worker.
	 *
	 * @return void
	 */
	private function release_lock() {
		if ( '' === $this->lock_value ) {
			return;
		}

		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Token comparison prevents one worker releasing another worker's lock.
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name = %s AND option_value = %s',
				$wpdb->options,
				self::LOCK_OPTION,
				$this->lock_value
			)
		);
		wp_cache_delete( self::LOCK_OPTION, 'options' );
		$this->lock_value = '';
	}

	/**
	 * Send a plain-text notification to selected store staff.
	 *
	 * @param array<int, \WC_Product> $products Products changed in this batch.
	 * @param string                  $cutoff   Cutoff date.
	 * @return void
	 */
	private function send_notification_email( array $products, $cutoff ) {
		$emails = $this->get_recipient_emails();

		if ( empty( $emails ) ) {
			return;
		}

		$body = sprintf(
			/* translators: %s: localized cutoff date. */
			__( 'The following products expire by %s and were set to out of stock:', 'product-expiration-easy-peasy' ),
			Date::format( $cutoff )
		) . "\n\n";

		foreach ( $products as $product ) {
			$product_id = $product->get_id();
			$date       = Date::sanitize( get_post_meta( $product_id, '_expiration_date', true ) );
			$edit_link  = add_query_arg(
				array(
					'post'   => $product_id,
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			);

			/* translators: %s: product name. */
			$body .= sprintf( __( 'Product: %s', 'product-expiration-easy-peasy' ), wp_strip_all_tags( $product->get_name() ) ) . "\n";
			/* translators: %d: product or variation ID. */
			$body .= sprintf( __( 'Product ID: %d', 'product-expiration-easy-peasy' ), $product_id ) . "\n";
			/* translators: %s: localized expiration date. */
			$body .= sprintf( __( 'Expiration date: %s', 'product-expiration-easy-peasy' ), Date::format( $date ) ) . "\n";

			if ( $product->is_type( 'variation' ) ) {
				$parent = wc_get_product( $product->get_parent_id() );

				if ( $parent ) {
					/* translators: %s: parent product name. */
					$body .= sprintf( __( 'Parent product: %s', 'product-expiration-easy-peasy' ), wp_strip_all_tags( $parent->get_name() ) ) . "\n";
				}
			}

			if ( $edit_link ) {
				/* translators: %s: product edit URL. */
				$body .= sprintf( __( 'Edit product: %s', 'product-expiration-easy-peasy' ), esc_url_raw( $edit_link ) ) . "\n";
			}

			$body .= "\n";
		}

		wp_mail(
			$emails,
			__( 'Products near expiration', 'product-expiration-easy-peasy' ),
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Resolve and validate configured notification recipients.
	 *
	 * @return array<int, string>
	 */
	private function get_recipient_emails() {
		$selected = (array) $this->settings->get_setting( 'email_recipients' );
		$emails   = array();

		if ( in_array( 'admin', $selected, true ) ) {
			$emails[] = get_option( 'admin_email' );
		}

		if ( in_array( 'shop_manager', $selected, true ) ) {
			$emails = array_merge(
				$emails,
				get_users(
					array(
						'role'   => 'shop_manager',
						'fields' => 'user_email',
					)
				)
			);
		}

		if ( in_array( 'custom', $selected, true ) ) {
			$emails[] = $this->settings->get_setting( 'custom_email' );
		}

		$emails = array_map( 'sanitize_email', $emails );
		$emails = array_filter( $emails, 'is_email' );

		return array_values( array_unique( $emails ) );
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
