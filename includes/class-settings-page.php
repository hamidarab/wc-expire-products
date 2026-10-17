<?php
/**
 * The WooCommerce > Expiration settings screen.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the page, loads its assets, renders the views and handles its forms.
 */
final class Settings_Page {
	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	public const SLUG = 'product-expiration-easy-peasy-settings';

	/**
	 * Tabs, in display order.
	 *
	 * @var array<int, string>
	 */
	public const TABS = array( 'general', 'display', 'notifications', 'export', 'help' );

	/**
	 * Handle of the page script and style.
	 *
	 * @var string
	 */
	private const HANDLE = 'product-expiration-easy-peasy-settings';

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Expiration processing service.
	 *
	 * @var Cron
	 */
	private $cron;

	/**
	 * Background check.
	 *
	 * @var Check_Job
	 */
	private $job;

	/**
	 * Export query service.
	 *
	 * @var Exporter
	 */
	private $exporter;

	/**
	 * Register the page and its form handlers.
	 *
	 * @param Settings  $settings Settings service.
	 * @param Cron      $cron     Expiration processing service.
	 * @param Check_Job $job      Background check.
	 * @param Exporter  $exporter Export query service.
	 */
	public function __construct( Settings $settings, Cron $cron, Check_Job $job, Exporter $exporter ) {
		$this->settings = $settings;
		$this->cron     = $cron;
		$this->job      = $job;
		$this->exporter = $exporter;

		add_action( 'admin_menu', array( $this, 'add_menu' ), 60 );
		add_action( 'admin_post_peep_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_post_peep_run_check', array( $this, 'handle_run_check' ) );
		add_action( 'admin_post_peep_send_test_email', array( $this, 'handle_test_email' ) );
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
	}

	/**
	 * Add the page below WooCommerce.
	 *
	 * @return void
	 */
	public function add_menu() {
		$hook = add_submenu_page(
			'woocommerce',
			__( 'Enqezayar settings', 'product-expiration-easy-peasy' ),
			__( 'Expiration', 'product-expiration-easy-peasy' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'load' ) );
		}
	}

	/**
	 * Hook the assets and body class for this screen only.
	 *
	 * @return void
	 */
	public function load() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Mark the body so the page background can follow the design.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		return $classes . ' peep-settings-screen';
	}

	/**
	 * Enqueue the page style and script.
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_enqueue_style( self::HANDLE, PRODUCT_EXPIRATION_EASY_PEASY_URL . 'admin/css/peep-settings.css', array(), PRODUCT_EXPIRATION_EASY_PEASY_VERSION );

		Assets::enqueue_datepicker();

		wp_enqueue_script( self::HANDLE, PRODUCT_EXPIRATION_EASY_PEASY_URL . 'admin/js/peep-settings.js', array( Assets::JALALI ), PRODUCT_EXPIRATION_EASY_PEASY_VERSION, true );
		wp_localize_script( self::HANDLE, 'peepSettings', $this->script_data() );
		wp_enqueue_script( self::HANDLE . '-export', PRODUCT_EXPIRATION_EASY_PEASY_URL . 'admin/js/peep-export.js', array( self::HANDLE, Assets::DATEPICKER ), PRODUCT_EXPIRATION_EASY_PEASY_VERSION, true );
		wp_localize_script( self::HANDLE . '-export', 'peepExport', $this->export_script_data() );
	}

	/**
	 * Strip one-time notice arguments from the address bar after display.
	 *
	 * @param array<int, string> $args Removable query arguments.
	 * @return array<int, string>
	 */
	public function removable_query_args( $args ) {
		$args[] = 'peep_notice';
		$args[] = 'peep_count';

		return $args;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'product-expiration-easy-peasy' ) );
		}

		$current = $this->settings->get_settings();
		$view    = $this->view_data( $current );

		require PRODUCT_EXPIRATION_EASY_PEASY_PATH . 'views/settings/page.php';
	}

	/**
	 * Validate and save the settings form, then redirect back.
	 *
	 * @return void
	 */
	public function handle_save() {
		self::require_capability();
		check_admin_referer( 'peep_save_settings', 'peep_settings_nonce' );

		$previous_cutoff = Cron::get_cutoff_date( $this->settings );

		// The settings service sanitizes and whitelists every submitted value.
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->settings->save_settings( is_array( $data ) ? $data : array() );

		// A shorter period must release products that are no longer inside it.
		if ( Cron::get_cutoff_date( $this->settings ) < $previous_cutoff ) {
			$this->cron->restore_beyond_cutoff();
		}

		Stats::flush();

		$args    = array( 'settings-updated' => 'true' );
		$typed   = isset( $data['custom_email'] ) && is_scalar( $data['custom_email'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $data['custom_email'] ) ) ) : array();
		$kept    = (string) $this->settings->get_setting( 'custom_email' );
		$is_used = in_array( 'custom', (array) $this->settings->get_setting( 'email_recipients' ), true );

		if ( $is_used && '' === $kept ) {
			$args['peep_notice'] = 'custom_email_missing';
		} elseif ( count( $typed ) > count( array_filter( explode( ',', $kept ) ) ) ) {
			$args['peep_notice'] = 'custom_email_dropped';
		}

		$this->redirect( $args );
	}

	/**
	 * Run the check in this request; used when JavaScript is off.
	 *
	 * @return void
	 */
	public function handle_run_check() {
		self::require_capability();
		check_admin_referer( 'peep_run_check', 'peep_run_check_nonce' );

		$job = $this->job->run_now( 'manual' );

		if ( false === $job ) {
			$this->redirect( array( 'peep_notice' => 'check_locked' ) );
		}

		if ( 'running' === $job['status'] ) {
			$this->redirect( array( 'peep_notice' => 'check_running' ) );
		}

		$this->redirect(
			array(
				'peep_notice' => 'check_done',
				'peep_count'  => (int) $job['changed'],
			)
		);
	}

	/**
	 * Send a sample report to the saved recipients.
	 *
	 * @return void
	 */
	public function handle_test_email() {
		self::require_capability();
		check_admin_referer( 'peep_send_test_email', 'peep_test_email_nonce' );

		$this->redirect( array( 'peep_notice' => 'test_' . Report::send_test( $this->settings ) ) );
	}

	/**
	 * Stop the request unless the user can manage WooCommerce.
	 *
	 * @return void
	 */
	private static function require_capability() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'product-expiration-easy-peasy' ) );
		}
	}

	/**
	 * Redirect back to the page, reopening the submitted tab.
	 *
	 * The caller must have verified a nonce first.
	 *
	 * @param array<string, string|int> $args Extra query arguments.
	 * @return void
	 */
	private function redirect( array $args = array() ) {
		$tab = isset( $_POST['peep_tab'] ) && is_scalar( $_POST['peep_tab'] ) ? sanitize_key( wp_unslash( $_POST['peep_tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Every caller verifies its own nonce first.
		$tab = 'rules' === $tab ? 'general' : $tab;
		$url = add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );

		if ( in_array( $tab, self::TABS, true ) ) {
			$url .= '#' . $tab;
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Everything the views print, computed up front.
	 *
	 * @param array<string, mixed> $current Validated settings.
	 * @return array<string, mixed>
	 */
	private function view_data( array $current ) {
		$today   = Date::today();
		$cutoff  = Cron::get_cutoff_date( $this->settings );
		$samples = array();

		foreach ( Settings::get_date_formats() as $format ) {
			$samples[ $format ] = Date::format( $today, $format );
		}

		$calendars = array();

		foreach ( Settings::get_datepicker_calendars() as $calendar ) {
			$calendars[ $calendar ] = array(
				'sample' => Date::input_value( $today, Date::input_calendar( $calendar ) ),
				'month'  => 'auto' === $calendar ? null : Mini_Month::build( $calendar, $today ),
			);
		}

		$parts = array(
			'%date%'  => $samples[ $current['date_format'] ],
			'%year%'  => Date::format( $today, 'Y' ),
			'%month%' => Date::format( $today, 'm' ),
			'%day%'   => Date::format( $today, 'd' ),
		);

		$notice_key = isset( $_GET['peep_notice'] ) && is_scalar( $_GET['peep_notice'] ) ? sanitize_key( wp_unslash( $_GET['peep_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice selector.
		$is_iranian = in_array( get_woocommerce_currency(), array( 'IRR', 'IRT', 'IRHR', 'IRHT' ), true );

		return array(
			'version'      => Date::is_persian_locale() ? Jalali::digits( PRODUCT_EXPIRATION_EASY_PEASY_VERSION ) : PRODUCT_EXPIRATION_EASY_PEASY_VERSION,
			'counts'       => Stats::get_counts(),
			'next_run'     => $this->next_run(),
			'cutoff_label' => self::long_date( $cutoff ),
			'date_parts'   => $parts,
			'preview_text' => strtr( (string) $current['display_text'], $parts ),
			'samples'      => $samples,
			'calendars'    => $calendars,
			'price'        => wc_price( $is_iranian ? 480000 : 49 ),
			'admin_email'  => (string) get_option( 'admin_email' ),
			'managers'     => count(
				get_users(
					array(
						'role'   => 'shop_manager',
						'fields' => 'ID',
					)
				)
			),
			'notice'       => $this->notice( $notice_key ),
			'test_status'  => 0 === strpos( $notice_key, 'test_' ) ? substr( $notice_key, 5 ) : '',
			'status_rows'  => $this->status_rows(),
			'export'       => $this->export_data( $today ),
		);
	}

	/**
	 * Data and strings for peep-settings.js.
	 *
	 * @return array<string, mixed>
	 */
	private function script_data() {
		$plural = static function ( $nooped ) {
			return array( translate_nooped_plural( $nooped, 1, 'product-expiration-easy-peasy' ), translate_nooped_plural( $nooped, 2, 'product-expiration-easy-peasy' ) );
		};

		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( Ajax::CHECK_NONCE ),
			'job'     => Check_Job::public_state( Check_Job::get() ),
			'today'   => Date::today(),
			'jalali'  => Date::is_persian_locale(),
			'i18n'    => array(
				'unsaved'       => __( 'You have unsaved changes', 'product-expiration-easy-peasy' ),
				'saving'        => __( 'Saving…', 'product-expiration-easy-peasy' ),
				'periodInvalid' => __( 'Enter a whole number from 1 to 365.', 'product-expiration-easy-peasy' ),
				'emailMissing'  => __( 'Enter an email address, or untick Custom email.', 'product-expiration-easy-peasy' ),
				'emailInvalid'  => __( 'Enter a valid email address, for example name@example.com.', 'product-expiration-easy-peasy' ),
				'testUnsaved'   => __( 'Changes are not saved yet; the test email goes to the previously saved recipients.', 'product-expiration-easy-peasy' ),
				'sending'       => __( 'Sending…', 'product-expiration-easy-peasy' ),
				/* translators: %s: percentage of the check that is done, without the percent sign. */
				'checkingPct'   => __( 'Checking… %s%%', 'product-expiration-easy-peasy' ),
				'checking'      => __( 'Checking…', 'product-expiration-easy-peasy' ),
				'done'          => __( 'Done', 'product-expiration-easy-peasy' ),
				'stopped'       => __( 'Stopped', 'product-expiration-easy-peasy' ),
				'lastCheck'     => __( 'Last check: just now', 'product-expiration-easy-peasy' ),
				'failed'        => __( 'Something went wrong. Please try again.', 'product-expiration-easy-peasy' ),
				'doneNone'      => __( 'No products needed to go out of stock.', 'product-expiration-easy-peasy' ),
				'cancelNone'    => __( 'No product had changed by then.', 'product-expiration-easy-peasy' ),
				/* translators: 1: products checked so far, 2: products to check. */
				'progress'      => $plural( _n_noop( '%1$s of %2$s product checked', '%1$s of %2$s products checked', 'product-expiration-easy-peasy' ) ),
				/* translators: %s: number of products. */
				'runningNote'   => $plural( _n_noop( '%s product set out of stock so far. Closing this window does not stop the check.', '%s products set out of stock so far. Closing this window does not stop the check.', 'product-expiration-easy-peasy' ) ),
				/* translators: %s: number of products. */
				'doneChecked'   => $plural( _n_noop( '%s product checked.', '%s products checked.', 'product-expiration-easy-peasy' ) ),
				/* translators: %s: number of products. */
				'doneChanged'   => $plural( _n_noop( '%s product went out of stock and the report was sent to the recipients.', '%s products went out of stock and the report was sent to the recipients.', 'product-expiration-easy-peasy' ) ),
				/* translators: 1: products checked, 2: products to check. */
				'cancelChecked' => $plural( _n_noop( '%1$s of %2$s product had been checked.', '%1$s of %2$s products had been checked.', 'product-expiration-easy-peasy' ) ),
				/* translators: %s: number of products. */
				'cancelChanged' => $plural( _n_noop( 'The %s product set out of stock before cancelling stays out of stock.', 'The %s products set out of stock before cancelling stay out of stock.', 'product-expiration-easy-peasy' ) ),
				'checkLabel'    => __( 'Check product dates', 'product-expiration-easy-peasy' ),
				'cancelLabel'   => __( 'Cancel check', 'product-expiration-easy-peasy' ),
			),
		);
	}

	/**
	 * Defaults and counts for the export tab.
	 *
	 * @param string $today Site today, Gregorian Y-m-d.
	 * @return array<string, mixed>
	 */
	private function export_data( $today ) {
		$request = Exporter::request( array(), $today );
		$picker  = Date::input_calendar();

		return array(
			'counts'   => $this->exporter->counts( $request, $today ),
			'labels'   => Exporter::labels(),
			'columns'  => $request['columns'],
			'format'   => $request['format'],
			'calendar' => $request['calendar'],
			'xlsx'     => Xlsx_Writer::is_available(),
			'picker'   => $picker,
			'from'     => Date::input_value( $request['from'], $picker ),
			'to'       => Date::input_value( $request['to'], $picker ),
		);
	}

	/**
	 * Data and strings for peep-export.js.
	 *
	 * @return array<string, mixed>
	 */
	private function export_script_data() {
		$plural   = static function ( $nooped ) {
			return array( translate_nooped_plural( $nooped, 1, 'product-expiration-easy-peasy' ), translate_nooped_plural( $nooped, 2, 'product-expiration-easy-peasy' ) );
		};
		$statuses = wc_get_product_stock_status_options();

		return array(
			'nonce' => wp_create_nonce( Export_Controller::NONCE ),
			'stock' => array(
				'in'  => $statuses['instock'] ?? '',
				'out' => $statuses['outofstock'] ?? '',
			),
			'i18n'  => array(
				/* translators: %s: number of products. */
				'products'     => $plural( _n_noop( '%s product', '%s products', 'product-expiration-easy-peasy' ) ),
				/* translators: %s: number of rows. */
				'rows'         => $plural( _n_noop( '%s row', '%s rows', 'product-expiration-easy-peasy' ) ),
				/* translators: %s: number of days. */
				'days'         => $plural( _n_noop( '%s day', '%s days', 'product-expiration-easy-peasy' ) ),
				/* translators: %s: number of days. */
				'daysAgo'      => $plural( _n_noop( '%s day ago', '%s days ago', 'product-expiration-easy-peasy' ) ),
				/* translators: %s: number of days. */
				'within'       => $plural( _n_noop( 'Expire within the next %s day', 'Expire within the next %s days', 'product-expiration-easy-peasy' ) ),
				/* translators: 1: first date, 2: last date. */
				'between'      => __( 'Expire between %1$s and %2$s', 'product-expiration-easy-peasy' ),
				'downloadXlsx' => __( 'Download Excel file', 'product-expiration-easy-peasy' ),
				'downloadCsv'  => __( 'Download CSV file', 'product-expiration-easy-peasy' ),
				'building'     => __( 'Building file…', 'product-expiration-easy-peasy' ),
				/* translators: %s: file name. */
				'downloaded'   => __( 'File %s was downloaded.', 'product-expiration-easy-peasy' ),
				'failed'       => __( 'Something went wrong. Please try again.', 'product-expiration-easy-peasy' ),
			),
		);
	}

	/**
	 * Next scheduled check, as "date, at time" and "in about …".
	 *
	 * @return array{date: string, diff: string}|null
	 */
	private function next_run() {
		$timestamp = wp_next_scheduled( 'peep_check_expired_products' );

		if ( ! $timestamp ) {
			return null;
		}

		$persian = Date::is_persian_locale();
		$date    = $persian ? Date::format( wp_date( 'Y-m-d', $timestamp ), 'j F' ) : wp_date( 'F j', $timestamp );
		$time    = wp_date( 'H:i', $timestamp );
		$diff    = human_time_diff( time(), $timestamp );

		return array(
			/* translators: 1: date, 2: time of day. */
			'date' => sprintf( __( '%1$s, at %2$s', 'product-expiration-easy-peasy' ), $date, $persian ? Jalali::digits( $time ) : $time ),
			/* translators: %s: human-readable time span, such as "5 hours". */
			'diff' => sprintf( __( 'Next check in about %s', 'product-expiration-easy-peasy' ), $persian ? Jalali::digits( $diff ) : $diff ),
		);
	}

	/**
	 * A date written out in full for the admin language.
	 *
	 * @param string $date Gregorian Y-m-d.
	 * @return string
	 */
	public static function long_date( $date ) {
		return Date::format( $date, Date::is_persian_locale() ? 'j F Y' : 'default' );
	}

	/**
	 * The top-of-page notice for a one-time notice key.
	 *
	 * @param string $key Notice key from the redirect.
	 * @return array{0: string, 1: string}|null Notice type and text.
	 */
	private function notice( $key ) {
		$count = isset( $_GET['peep_count'] ) && is_scalar( $_GET['peep_count'] ) ? absint( $_GET['peep_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice detail.

		switch ( $key ) {
			case 'check_done':
				/* translators: %s: number of products and variations. */
				return array( 'success', sprintf( _n( 'Check complete. %s product was set out of stock.', 'Check complete. %s products were set out of stock.', $count, 'product-expiration-easy-peasy' ), View::number( $count ) ) );
			case 'check_locked':
				return array( 'warning', __( 'Another expiration check is already running. Try again in a few minutes.', 'product-expiration-easy-peasy' ) );
			case 'check_running':
				return array( 'info', __( 'The check continues in the background. Reload this page later to see the result.', 'product-expiration-easy-peasy' ) );
			case 'custom_email_dropped':
				return array( 'warning', __( 'Some custom addresses were not valid and were removed. Check the list on the Notifications tab.', 'product-expiration-easy-peasy' ) );
			case 'custom_email_missing':
				return array( 'warning', __( 'Custom email is selected, but the address was empty or invalid, so no custom email will receive reports.', 'product-expiration-easy-peasy' ) );
		}

		return null;
	}

	/**
	 * Rows of the system status list: label, value, and true/false/null for OK.
	 *
	 * @return array<int, array{0: string, 1: string, 2: bool|null}>
	 */
	private function status_rows() {
		$license = License_Check::is_active();
		$ioncube = function_exists( 'ioncube_loader_version' ) ? (string) ioncube_loader_version() : '';
		$digits  = Date::is_persian_locale() ? array( Jalali::class, 'digits' ) : 'strval';

		return array(
			array( __( 'Plugin version', 'product-expiration-easy-peasy' ), $digits( PRODUCT_EXPIRATION_EASY_PEASY_VERSION ), null ),
			array( __( 'RTL-Theme license', 'product-expiration-easy-peasy' ), $license ? __( 'Active', 'product-expiration-easy-peasy' ) : __( 'Not active', 'product-expiration-easy-peasy' ), $license ),
			array(
				__( 'ionCube Loader', 'product-expiration-easy-peasy' ),
				/* translators: %s: ionCube Loader version. */
				'' !== $ioncube ? sprintf( __( 'Loaded (%s)', 'product-expiration-easy-peasy' ), $digits( $ioncube ) ) : __( 'Not loaded', 'product-expiration-easy-peasy' ),
				'' !== $ioncube,
			),
			array( 'WooCommerce', defined( 'WC_VERSION' ) ? $digits( WC_VERSION ) : __( 'Not detected', 'product-expiration-easy-peasy' ), defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '9.0', '>=' ) ),
			array( 'PHP', $digits( PHP_VERSION ), version_compare( PHP_VERSION, '8.1', '>=' ) ),
			array( 'WordPress', $digits( get_bloginfo( 'version' ) ), null ),
		);
	}
}
