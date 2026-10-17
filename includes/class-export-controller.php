<?php
/**
 * Download and preview endpoints for the export tab.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams the export file and answers the preview request.
 */
final class Export_Controller {
	/**
	 * Nonce action for both endpoints.
	 *
	 * @var string
	 */
	public const NONCE = 'peep_export';

	/**
	 * Rows shown in the preview.
	 *
	 * @var int
	 */
	private const PREVIEW_ROWS = 20;

	/**
	 * Export query service.
	 *
	 * @var Exporter
	 */
	private $exporter;

	/**
	 * Register the endpoints.
	 *
	 * @param Exporter $exporter Export query service.
	 */
	public function __construct( Exporter $exporter ) {
		$this->exporter = $exporter;

		add_action( 'admin_post_peep_export', array( $this, 'download' ) );
		add_action( 'wp_ajax_peep_export_preview', array( $this, 'preview' ) );
	}

	/**
	 * Stream the file as an attachment.
	 *
	 * @return void
	 */
	public function download() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'product-expiration-easy-peasy' ) );
		}

		check_admin_referer( self::NONCE, 'peep_export_nonce' );

		$today   = Date::today();
		$request = Exporter::request( wp_unslash( $_POST ), $today ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exporter::request() whitelists every field.
		$table   = $this->exporter->rows( $request, $today );
		$header  = wp_list_pluck( $table['columns'], 'label' );

		nocache_headers();
		header( 'Content-Disposition: attachment; filename="' . Exporter::filename( $request, $today ) . '"' );

		if ( 'csv' === $request['format'] ) {
			header( 'Content-Type: text/csv; charset=UTF-8' );
			$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming the download.
			Csv_Writer::write( $output, $header, $table['rows'] );
			exit;
		}

		$path = wp_tempnam( 'peep-export' );

		if ( ! Xlsx_Writer::write( $path, $header, $table['rows'], is_rtl() ) ) {
			wp_delete_file( $path );
			wp_die( esc_html__( 'Something went wrong. Please try again.', 'product-expiration-easy-peasy' ) );
		}

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming the download.
		wp_delete_file( $path );
		exit;
	}

	/**
	 * The first rows and the scope counts, as JSON.
	 *
	 * @return void
	 */
	public function preview() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( null, 403 );
		}

		$today   = Date::today();
		$request = Exporter::request( wp_unslash( $_POST ), $today ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exporter::request() whitelists every field.

		wp_send_json_success( $this->exporter->rows( $request, $today, self::PREVIEW_ROWS ) );
	}
}
