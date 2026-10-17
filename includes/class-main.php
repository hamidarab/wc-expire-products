<?php
/**
 * Main plugin coordinator.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the plugin services once dependencies and the license are ready.
 */
final class Main {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Settings service.
	 *
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin once.
	 */
	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'render_woocommerce_notice' ) );
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '9.0', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'render_woocommerce_version_notice' ) );
			return;
		}

		if ( ! product_expiration_easy_peasy_is_license_active() ) {
			add_action( 'admin_notices', array( $this, 'render_license_notice' ) );
			return;
		}

		$this->settings = new Settings();
		$job            = new Check_Job( $this->settings );
		$cron           = new Cron( $this->settings, $job );

		if ( is_admin() ) {
			new Admin( $this->settings, $cron, $job );
			new Ajax( $job );
		}

		new Frontend( $this->settings );

		Cron::schedule_check();
	}

	/**
	 * Explain the missing WooCommerce dependency.
	 *
	 * @return void
	 */
	public function render_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Product Expiration Easy Peasy requires WooCommerce to be installed and active.', 'product-expiration-easy-peasy' )
		);
	}

	/**
	 * Explain an unsupported WooCommerce version.
	 *
	 * @return void
	 */
	public function render_woocommerce_version_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Product Expiration Easy Peasy requires WooCommerce 9.0 or newer.', 'product-expiration-easy-peasy' )
		);
	}

	/**
	 * Explain an inactive or unavailable RTL-Theme license runtime.
	 *
	 * @return void
	 */
	public function render_license_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$message = License_Check::get_error();

		if ( '' === $message ) {
			$message = __( 'Please activate the RTL-Theme license to use Product Expiration Easy Peasy.', 'product-expiration-easy-peasy' );
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}
