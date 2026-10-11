<?php
/**
 * Plugin settings service.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, validates, and persists plugin settings.
 */
final class Settings {
	/**
	 * WordPress option name.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'peep_settings';

	/**
	 * Untranslated default storefront text.
	 *
	 * @var string
	 */
	private const DEFAULT_DISPLAY_TEXT = 'Expires: %date%';

	/**
	 * Placeholders supported in the storefront text.
	 *
	 * @var string[]
	 */
	private const DISPLAY_PLACEHOLDERS = array( '%date%', '%year%', '%month%', '%day%' );

	/**
	 * Default option values.
	 *
	 * @var array<string, mixed>
	 */
	private $defaults = array(
		'notification_period_type' => 'days',
		'notification_period'      => 30,
		'show_expiration'          => 'yes',
		'display_text'             => self::DEFAULT_DISPLAY_TEXT,
		'date_format'              => 'default',
		'display_position'         => 'after_price',
		'email_recipients'         => array( 'admin' ),
		'custom_email'             => '',
		'show_in_order_email'      => 'yes',
	);

	/**
	 * Translate the default storefront text when it is needed.
	 *
	 * Translating lazily keeps __() out of the bootstrap, before the text
	 * domain is loaded on init.
	 *
	 * @return string
	 */
	private function get_default_display_text() {
		/* translators: %date% is replaced with the localized product expiration date. */
		return __( 'Expires: %date%', 'product-expiration-easy-peasy' );
	}

	/**
	 * Sanitize the storefront text without losing its placeholders.
	 *
	 * WordPress' sanitize_text_field() removes percent-encoded octets, so "%da" in
	 * %date% and %day% would otherwise be stripped.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function sanitize_display_text( $text ) {
		$tokens = array();

		foreach ( array_keys( self::DISPLAY_PLACEHOLDERS ) as $index ) {
			$tokens[] = '{{peep-placeholder-' . $index . '}}';
		}

		$text = str_replace( self::DISPLAY_PLACEHOLDERS, $tokens, $text );

		return str_replace( $tokens, self::DISPLAY_PLACEHOLDERS, sanitize_text_field( $text ) );
	}

	/**
	 * Whether a stored text is the untranslated default.
	 *
	 * Also matches the default as earlier releases stored it, after
	 * sanitize_text_field() had turned "%date%" into "te%".
	 *
	 * @param mixed $text Stored text.
	 * @return bool
	 */
	private function is_default_display_text( $text ) {
		return in_array(
			$text,
			array(
				self::DEFAULT_DISPLAY_TEXT,
				str_replace( '%date%', 'te%', self::DEFAULT_DISPLAY_TEXT ),
				str_replace( '%date%', 'te%', $this->get_default_display_text() ),
			),
			true
		);
	}

	/**
	 * Get validated settings with defaults applied.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( isset( $settings['notification_priod_type'] ) && ! array_key_exists( 'notification_period_type', $settings ) ) {
			$settings['notification_period_type'] = $settings['notification_priod_type'];
		}

		// A default saved from an English admin screen follows the site language.
		if ( isset( $settings['display_text'] ) && $this->is_default_display_text( $settings['display_text'] ) ) {
			unset( $settings['display_text'] );
		}

		return $this->sanitize_settings( wp_parse_args( $settings, $this->defaults ) );
	}

	/**
	 * Save a complete settings payload.
	 *
	 * @param array<string, mixed> $data Unslashed request data.
	 * @return bool Whether the option value changed.
	 */
	public function save_settings( array $data ) {
		return update_option( self::OPTION_NAME, $this->sanitize_settings( $data ), false );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed|null
	 */
	public function get_setting( $key ) {
		$settings = $this->get_settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
	}

	/**
	 * Sanitize and whitelist a settings array.
	 *
	 * @param array<string, mixed> $data Raw settings.
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( array $data ) {
		// Migrate the misspelled key used by early releases.
		if ( isset( $data['notification_priod_type'] ) && ! isset( $data['notification_period_type'] ) ) {
			$data['notification_period_type'] = $data['notification_priod_type'];
		}

		$period_types      = array( 'days', 'weeks', 'months' );
		$date_formats      = array( 'default', 'Y/m/d', 'Y/m', 'Ym', 'Y-m-d', 'd M Y' );
		$display_positions = array( 'after_price', 'after_title' );
		$recipient_types   = array( 'admin', 'shop_manager', 'custom' );

		$period_type = isset( $data['notification_period_type'] ) && is_scalar( $data['notification_period_type'] )
			? sanitize_key( (string) $data['notification_period_type'] )
			: $this->defaults['notification_period_type'];

		if ( ! in_array( $period_type, $period_types, true ) ) {
			$period_type = $this->defaults['notification_period_type'];
		}

		$period = isset( $data['notification_period'] ) && is_scalar( $data['notification_period'] ) ? absint( $data['notification_period'] ) : 30;
		$period = max( 1, min( 365, $period ) );

		$show_expiration = isset( $data['show_expiration'] ) && 'no' === $data['show_expiration'] ? 'no' : 'yes';
		$show_in_email   = isset( $data['show_in_order_email'] ) && 'no' === $data['show_in_order_email'] ? 'no' : 'yes';

		$display_text = isset( $data['display_text'] ) && is_scalar( $data['display_text'] )
			? $this->sanitize_display_text( (string) $data['display_text'] )
			: '';

		if ( '' === $display_text || $this->is_default_display_text( $display_text ) ) {
			$display_text = $this->get_default_display_text();
		}

		$date_format = isset( $data['date_format'] ) && is_scalar( $data['date_format'] )
			? sanitize_text_field( (string) $data['date_format'] )
			: $this->defaults['date_format'];

		if ( ! in_array( $date_format, $date_formats, true ) ) {
			$date_format = $this->defaults['date_format'];
		}

		$display_position = isset( $data['display_position'] ) && is_scalar( $data['display_position'] )
			? sanitize_key( (string) $data['display_position'] )
			: $this->defaults['display_position'];

		if ( ! in_array( $display_position, $display_positions, true ) ) {
			$display_position = $this->defaults['display_position'];
		}

		$recipients = isset( $data['email_recipients'] ) ? (array) $data['email_recipients'] : array();
		$recipients = array_filter( $recipients, 'is_scalar' );
		$recipients = array_map( 'sanitize_key', $recipients );
		$recipients = array_values( array_intersect( $recipient_types, array_unique( $recipients ) ) );

		$custom_email = isset( $data['custom_email'] ) && is_scalar( $data['custom_email'] )
			? sanitize_email( (string) $data['custom_email'] )
			: '';

		return array(
			'notification_period_type' => $period_type,
			'notification_period'      => $period,
			'show_expiration'          => $show_expiration,
			'display_text'             => $display_text,
			'date_format'              => $date_format,
			'display_position'         => $display_position,
			'email_recipients'         => $recipients,
			'custom_email'             => $custom_email,
			'show_in_order_email'      => $show_in_email,
		);
	}
}
