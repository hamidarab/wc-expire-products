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
	 * Background check.
	 *
	 * @var Check_Job
	 */
	private $job;

	/**
	 * Register the scheduled callback.
	 *
	 * @param Settings  $settings Settings service.
	 * @param Check_Job $job      Background check.
	 */
	public function __construct( Settings $settings, Check_Job $job ) {
		$this->settings = $settings;
		$this->job      = $job;
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
	 * Remove the scheduled events and any queued check batch.
	 *
	 * @return void
	 */
	public static function unschedule_check() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::RESTORE_HOOK );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Check_Job::HOOK );
		}

		delete_option( Check_Job::OPTION );
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
	 * Daily callback: start the same background check as the manual button.
	 *
	 * @return void
	 */
	public function check_expired_products() {
		$this->job->start( 'cron' );
	}

	/**
	 * Restore managed stock for products whose date is now beyond the cutoff.
	 *
	 * Runs after the notification period is shortened so products that no
	 * longer fall inside the window become available again immediately.
	 *
	 * @return int|false Number of restored products, or false when a check is
	 *                   running and a retry was scheduled.
	 */
	public function restore_beyond_cutoff() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}

		// The running check works from the old cutoff; restoring now could undo it.
		if ( Check_Job::is_running() ) {
			if ( ! wp_next_scheduled( self::RESTORE_HOOK ) ) {
				wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::RESTORE_HOOK );
			}

			return false;
		}

		$restored = 0;
		$seen     = array();
		$cutoff   = self::get_cutoff_date( $this->settings );

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

		wp_reset_postdata();
		Stats::flush();

		return $restored;
	}
}
