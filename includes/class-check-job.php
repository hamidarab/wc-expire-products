<?php
/**
 * Background expiration check with progress and cancel.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the expiration check in Action Scheduler batches.
 *
 * The job record lives in one small, non-autoloaded option. Every write is a
 * compare-and-swap on that option, so two starters, a running batch and a
 * cancel request never overwrite each other. The IDs a job changed are kept
 * in a second option that only the batch worker writes.
 */
final class Check_Job {
	/**
	 * Option that holds the current or last job.
	 *
	 * @var string
	 */
	public const OPTION = 'peep_check_job';

	/**
	 * Option that holds the IDs the current job set out of stock.
	 *
	 * @var string
	 */
	public const CHANGED_OPTION = 'peep_check_job_changed';

	/**
	 * Action Scheduler hook for one batch.
	 *
	 * @var string
	 */
	public const HOOK = 'peep_check_batch';

	/**
	 * Action Scheduler group.
	 *
	 * @var string
	 */
	private const GROUP = 'peep';

	/**
	 * Items processed per batch.
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 50;

	/**
	 * A running job without a queued batch that has not moved for this long is dead.
	 *
	 * @var int
	 */
	private const STALE_AFTER = 10 * MINUTE_IN_SECONDS;

	/**
	 * Seconds the no-JavaScript request works before handing over to the queue.
	 *
	 * @var int
	 */
	private const REQUEST_BUDGET = 20;

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Register the batch callback.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		add_action( self::HOOK, array( $this, 'run_batch' ) );
	}

	/**
	 * Read the current or last job, bypassing the object cache.
	 *
	 * @return array<string, mixed> Empty when no job has run yet.
	 */
	public static function get() {
		wp_cache_delete( self::OPTION, 'options' );
		$job = get_option( self::OPTION, array() );

		return is_array( $job ) ? $job : array();
	}

	/**
	 * Whether a live job is running.
	 *
	 * @return bool
	 */
	public static function is_running() {
		return self::is_live( self::get() );
	}

	/**
	 * Job fields safe to send to the browser.
	 *
	 * @param array<string, mixed> $job Job state.
	 * @return array<string, mixed>
	 */
	public static function public_state( array $job ) {
		return array_intersect_key( $job, array_flip( array( 'id', 'status', 'total', 'processed', 'changed', 'managed', 'finished_at' ) ) );
	}

	/**
	 * Start a background check, or return the one already running.
	 *
	 * @param string $source "manual" or "cron".
	 * @return array<string, mixed> The job state.
	 */
	public function start( $source ) {
		$job = $this->begin( $source );

		if ( null === $job ) {
			return self::get();
		}

		$this->queue( $job['id'] );

		return $job;
	}

	/**
	 * Run a check in this request; the no-JavaScript fallback.
	 *
	 * Stops after REQUEST_BUDGET seconds and leaves the rest to the queue, so a
	 * large store cannot hit the PHP time limit halfway through.
	 *
	 * @param string $source "manual" or "cron".
	 * @return array<string, mixed>|false The job, or false when another job is running.
	 */
	public function run_now( $source ) {
		$job = $this->begin( $source );

		if ( null === $job ) {
			return false;
		}

		$started = microtime( true );

		do {
			$more = $this->process_batch( $job['id'] );
		} while ( $more && microtime( true ) - $started < self::REQUEST_BUDGET );

		if ( $more ) {
			$this->queue( $job['id'] );
		}

		return self::get();
	}

	/**
	 * Action Scheduler callback: process one batch and queue the next.
	 *
	 * @param string $id Job ID.
	 * @return void
	 */
	public function run_batch( $id ) {
		$id = (string) $id;

		if ( $this->process_batch( $id ) ) {
			$this->queue( $id );
		}
	}

	/**
	 * Ask the running job to stop after its current item.
	 *
	 * The worker that sees the cancellation sends the report.
	 *
	 * @return array<string, mixed> The job state.
	 */
	public function cancel() {
		return $this->mutate(
			static function ( $job ) {
				if ( ! isset( $job['status'] ) || 'running' !== $job['status'] ) {
					return null;
				}

				$job['status']      = 'cancelled';
				$job['finished_at'] = time();

				return $job;
			}
		);
	}

	/**
	 * Create a new running job unless a live one exists.
	 *
	 * @param string $source "manual" or "cron".
	 * @return array<string, mixed>|null The new job, or null when another job is live.
	 */
	private function begin( $source ) {
		$current = self::get();

		if ( self::is_live( $current ) ) {
			return null;
		}

		$cutoff = Cron::get_cutoff_date( $this->settings );
		Stats::flush();
		$now = time();
		$job = array(
			'id'          => wp_generate_uuid4(),
			'source'      => 'cron' === $source ? 'cron' : 'manual',
			'status'      => 'running',
			'total'       => $this->count( $cutoff ),
			'processed'   => 0,
			'changed'     => 0,
			'managed'     => Stats::get_counts()['managed'],
			'last_id'     => 0,
			'cutoff'      => $cutoff,
			'started_at'  => $now,
			'updated_at'  => $now,
			'finished_at' => 0,
			'reported'    => false,
		);

		if ( ! $this->replace( $current, $job ) ) {
			return null;
		}

		// A job that died or was cancelled before a worker saw it still owes its report.
		if ( isset( $current['id'], $current['cutoff'] ) && empty( $current['reported'] ) ) {
			Report::send( $this->settings, $this->changed_ids( $current['id'] ), $current['cutoff'] );
		}

		delete_option( self::CHANGED_OPTION );

		return $job;
	}

	/**
	 * Process up to one batch of the given job.
	 *
	 * @param string $id Job ID.
	 * @return bool Whether another batch is needed.
	 */
	private function process_batch( $id ) {
		$job = self::get();

		if ( ! isset( $job['id'], $job['status'] ) || $id !== $job['id'] || 'running' !== $job['status'] ) {
			$this->finalize( $id );
			return false;
		}

		$product_ids = $this->query_ids( $job['cutoff'], (int) $job['last_id'] );
		$changed_ids = array();
		$stopped     = false;

		foreach ( $product_ids as $product_id ) {
			$changed = Stock::mark_out_of_stock( $product_id );

			if ( $changed ) {
				$changed_ids[] = $product_id;
			}

			$job = $this->mutate(
				static function ( $state ) use ( $id, $product_id, $changed ) {
					if ( ! isset( $state['id'] ) || $id !== $state['id'] ) {
						return null;
					}

					++$state['processed'];
					$state['changed']   += $changed ? 1 : 0;
					$state['last_id']    = $product_id;
					$state['updated_at'] = time();

					return $state;
				}
			);

			if ( ! isset( $job['id'], $job['status'] ) || $id !== $job['id'] || 'running' !== $job['status'] ) {
				$stopped = true;
				break;
			}
		}

		$this->record_changed( $id, $changed_ids );

		if ( ! $stopped && self::BATCH_SIZE === count( $product_ids ) ) {
			return true;
		}

		$this->finalize( $id );

		return false;
	}

	/**
	 * Close the job once and send its one report.
	 *
	 * A running job becomes "done"; a cancelled one keeps its status.
	 *
	 * @param string $id Job ID.
	 * @return void
	 */
	private function finalize( $id ) {
		$won = false;
		$job = $this->mutate(
			static function ( $state ) use ( $id, &$won ) {
				$won = false;

				if ( ! isset( $state['id'], $state['status'] ) || $id !== $state['id'] || ! empty( $state['reported'] ) ) {
					return null;
				}

				if ( 'running' === $state['status'] ) {
					$state['status']      = 'done';
					$state['finished_at'] = time();
				}

				$state['reported']   = true;
				$state['updated_at'] = time();
				$won                 = true;

				return $state;
			}
		);

		if ( ! $won ) {
			return;
		}

		Stats::flush();
		Report::send( $this->settings, $this->changed_ids( $id ), $job['cutoff'] );
		delete_option( self::CHANGED_OPTION );
	}

	/**
	 * Append the IDs one batch set out of stock.
	 *
	 * @param string          $id          Job ID.
	 * @param array<int, int> $product_ids Changed IDs.
	 * @return void
	 */
	private function record_changed( $id, array $product_ids ) {
		if ( array() === $product_ids ) {
			return;
		}

		$job = self::get();

		// A newer job owns the option now.
		if ( ! isset( $job['id'] ) || $id !== $job['id'] || ! empty( $job['reported'] ) ) {
			return;
		}

		update_option(
			self::CHANGED_OPTION,
			array(
				'id'  => $id,
				'ids' => array_merge( $this->changed_ids( $id ), $product_ids ),
			),
			false
		);
	}

	/**
	 * IDs the given job set out of stock.
	 *
	 * @param string $id Job ID.
	 * @return array<int, int>
	 */
	private function changed_ids( $id ) {
		wp_cache_delete( self::CHANGED_OPTION, 'options' );
		$changed = get_option( self::CHANGED_OPTION, array() );

		return isset( $changed['id'], $changed['ids'] ) && $id === $changed['id'] ? array_map( 'absint', (array) $changed['ids'] ) : array();
	}

	/**
	 * Queue the next batch of a job.
	 *
	 * @param string $id Job ID.
	 * @return void
	 */
	private function queue( $id ) {
		as_enqueue_async_action( self::HOOK, array( $id ), self::GROUP );
	}

	/**
	 * Whether a job is running and something will still move it forward.
	 *
	 * A queued batch keeps a job alive however slow the queue is; without
	 * one, a job that has not moved for STALE_AFTER seconds is dead.
	 *
	 * @param array<string, mixed> $job Job state.
	 * @return bool
	 */
	private static function is_live( array $job ) {
		if ( ! isset( $job['id'], $job['status'], $job['updated_at'] ) || 'running' !== $job['status'] ) {
			return false;
		}

		return time() - (int) $job['updated_at'] < self::STALE_AFTER
			|| as_has_scheduled_action( self::HOOK, array( $job['id'] ), self::GROUP );
	}

	/**
	 * Apply a change to the stored job, retrying when another writer won.
	 *
	 * @param callable $change Receives the job; returns the new job, or null to leave it.
	 * @return array<string, mixed> The stored job after the change.
	 */
	private function mutate( callable $change ) {
		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$current = self::get();
			$next    = $change( $current );

			if ( null === $next ) {
				return $current;
			}

			if ( $this->replace( $current, $next ) ) {
				return $next;
			}
		}

		return self::get();
	}

	/**
	 * Atomically replace the stored job if it still equals the expected value.
	 *
	 * @param array<string, mixed> $expected Job as last read; empty when none is stored.
	 * @param array<string, mixed> $next     New job.
	 * @return bool
	 */
	private function replace( array $expected, array $next ) {
		if ( array() === $expected ) {
			return add_option( self::OPTION, $next, '', false );
		}

		global $wpdb;

		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-swap keeps concurrent writers from losing updates.
			$wpdb->prepare(
				'UPDATE %i SET option_value = %s WHERE option_name = %s AND option_value = %s',
				$wpdb->options,
				maybe_serialize( $next ),
				self::OPTION,
				maybe_serialize( $expected )
			)
		);
		wp_cache_delete( self::OPTION, 'options' );

		return 1 === $updated;
	}

	/**
	 * Count the products and variations the check will look at.
	 *
	 * @param string $cutoff Cutoff date.
	 * @return int
	 */
	private function count( $cutoff ) {
		global $wpdb;

		return absint(
			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One count per job start.
				$wpdb->prepare(
					'SELECT COUNT( DISTINCT p.ID ) FROM %i AS p
					INNER JOIN %i AS pm ON pm.post_id = p.ID
					WHERE pm.meta_key = %s AND CAST( pm.meta_value AS DATE ) <= %s
						AND p.post_type IN ( %s, %s ) AND p.post_status = %s',
					$wpdb->posts,
					$wpdb->postmeta,
					'_expiration_date',
					$cutoff,
					'product',
					'product_variation',
					'publish'
				)
			)
		);
	}

	/**
	 * The next batch of IDs after the last processed one.
	 *
	 * @param string $cutoff  Cutoff date.
	 * @param int    $last_id Last processed ID.
	 * @return array<int, int>
	 */
	private function query_ids( $cutoff, $last_id ) {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyset pagination over live data.
			$wpdb->prepare(
				'SELECT DISTINCT p.ID FROM %i AS p
				INNER JOIN %i AS pm ON pm.post_id = p.ID
				WHERE pm.meta_key = %s AND CAST( pm.meta_value AS DATE ) <= %s
					AND p.ID > %d AND p.post_type IN ( %s, %s ) AND p.post_status = %s
				ORDER BY p.ID ASC LIMIT %d',
				$wpdb->posts,
				$wpdb->postmeta,
				'_expiration_date',
				$cutoff,
				$last_id,
				'product',
				'product_variation',
				'publish',
				self::BATCH_SIZE
			)
		);

		return array_map( 'absint', (array) $ids );
	}
}
