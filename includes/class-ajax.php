<?php
/**
 * Admin-ajax endpoints for the settings page.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts, polls and cancels the background check.
 */
final class Ajax {
	/**
	 * Nonce action shared by the check endpoints.
	 *
	 * @var string
	 */
	public const CHECK_NONCE = 'peep_check';

	/**
	 * Background check.
	 *
	 * @var Check_Job
	 */
	private $job;

	/**
	 * Register the endpoints.
	 *
	 * @param Check_Job $job Background check.
	 */
	public function __construct( Check_Job $job ) {
		$this->job = $job;

		add_action( 'wp_ajax_peep_check_start', array( $this, 'check_start' ) );
		add_action( 'wp_ajax_peep_check_status', array( $this, 'check_status' ) );
		add_action( 'wp_ajax_peep_check_cancel', array( $this, 'check_cancel' ) );
	}

	/**
	 * Start a check, or join the one already running.
	 *
	 * @return void
	 */
	public function check_start() {
		check_ajax_referer( self::CHECK_NONCE, 'nonce' );
		self::require_capability();

		wp_send_json_success( Check_Job::public_state( $this->job->start( 'manual', true ) ) );
	}

	/**
	 * Report the current or last check.
	 *
	 * @return void
	 */
	public function check_status() {
		check_ajax_referer( self::CHECK_NONCE, 'nonce' );
		self::require_capability();

		wp_send_json_success( Check_Job::public_state( Check_Job::get() ) );
	}

	/**
	 * Stop the running check after its current item.
	 *
	 * @return void
	 */
	public function check_cancel() {
		check_ajax_referer( self::CHECK_NONCE, 'nonce' );
		self::require_capability();

		wp_send_json_success( Check_Job::public_state( $this->job->cancel() ) );
	}

	/**
	 * End the request unless the user can manage WooCommerce.
	 *
	 * @return void
	 */
	private static function require_capability() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}
	}
}
