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
	 * Single-event hook that restores stock after the period is shortened.
	 *
	 * @var string
	 */
	private const RESTORE_HOOK = 'peep_restore_beyond_cutoff';

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
		add_action( self::RESTORE_HOOK, array( $this, 'restore_beyond_cutoff' ) );
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
		wp_clear_scheduled_hook( self::RESTORE_HOOK );
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
	 * Scheduled callback for the daily expiration check.
	 *
	 * @return int Number of products whose stock was changed.
	 */
	public function check_expired_products() {
		$processed = $this->run_check();

		return false === $processed ? 0 : $processed;
	}

	/**
	 * Process products in bounded batches and send one report per run.
	 *
	 * @return int|false Number of products whose stock was changed, or false
	 *                   when another worker currently holds the lock.
	 */
	public function run_check() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}

		if ( ! $this->acquire_lock() ) {
			return false;
		}

		$changed_ids = array();
		$page        = 1;
		$cutoff      = self::get_cutoff_date( $this->settings );

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

				$product_ids = array_map( 'absint', $query->posts );

				foreach ( $product_ids as $product_id ) {
					if ( Stock::mark_out_of_stock( $product_id ) ) {
						$changed_ids[] = $product_id;
					}
				}

				$batch_count = count( $product_ids );
				++$page;
			} while ( self::BATCH_SIZE === $batch_count );
		} finally {
			$this->release_lock();
			wp_reset_postdata();
			Stats::flush();
		}

		// One aggregated report per run instead of one email per batch.
		Report::send( $this->settings, $changed_ids, $cutoff );

		return count( $changed_ids );
	}

	/**
	 * Restore managed stock for products whose date is now beyond the cutoff.
	 *
	 * Runs after the notification period is shortened so products that no
	 * longer fall inside the window become available again immediately.
	 *
	 * @return int|false Number of restored products, or false when the lock is
	 *                   busy and a retry was scheduled.
	 */
	public function restore_beyond_cutoff() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}

		if ( ! $this->acquire_lock() ) {
			if ( ! wp_next_scheduled( self::RESTORE_HOOK ) ) {
				wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::RESTORE_HOOK );
			}

			return false;
		}

		$restored = 0;
		$seen     = array();
		$cutoff   = self::get_cutoff_date( $this->settings );

		try {
			do {
				$query = new \WP_Query(
					array(
						'post_type'              => array( 'product', 'product_variation' ),
						'post_status'            => 'any',
						'fields'                 => 'ids',
						'posts_per_page'         => self::BATCH_SIZE,
						'post__not_in'           => $seen, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Bounded by the managed products already handled in this run.
						'orderby'                => 'ID',
						'order'                  => 'ASC',
						'no_found_rows'          => true,
						'cache_results'          => false,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required indexed plugin metadata lookup.
							'relation' => 'AND',
							array(
								'key'   => '_peep_stock_managed',
								'value' => 'yes',
							),
							array(
								'key'     => '_expiration_date',
								'value'   => $cutoff,
								'compare' => '>',
								'type'    => 'DATE',
							),
						),
					)
				);

				$product_ids = array_map( 'absint', $query->posts );

				foreach ( $product_ids as $product_id ) {
					$seen[] = $product_id;

					if ( Stock::restore( $product_id ) ) {
						++$restored;
					}
				}

				$batch_count = count( $product_ids );
			} while ( self::BATCH_SIZE === $batch_count );
		} finally {
			$this->release_lock();
			wp_reset_postdata();
			Stats::flush();
		}

		return $restored;
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
}
