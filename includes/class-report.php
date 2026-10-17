<?php
/**
 * Plain-text email reports for store staff.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the recipients and sends the expiration and test reports.
 */
final class Report {
	/**
	 * Send one report listing every product changed by a check.
	 *
	 * @param Settings        $settings    Settings service.
	 * @param array<int, int> $product_ids Products set out of stock.
	 * @param string          $cutoff      Cutoff date of the check.
	 * @return bool Whether the message was accepted for delivery.
	 */
	public static function send( Settings $settings, array $product_ids, $cutoff ) {
		$emails = self::recipients( $settings );

		if ( empty( $emails ) || empty( $product_ids ) ) {
			return false;
		}

		$body = sprintf(
			/* translators: %s: localized cutoff date. */
			__( 'The following products expire by %s and were set to out of stock:', 'product-expiration-easy-peasy' ),
			Date::format( $cutoff )
		) . "\n\n";

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( $product ) {
				$body .= self::describe_product( $product );
			}
		}

		return wp_mail(
			$emails,
			__( 'Products near expiration', 'product-expiration-easy-peasy' ),
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Send a sample report to the saved recipients.
	 *
	 * @param Settings $settings Settings service.
	 * @return string One of "sent", "failed", or "no_recipients".
	 */
	public static function send_test( Settings $settings ) {
		$emails = self::recipients( $settings );

		if ( empty( $emails ) ) {
			return 'no_recipients';
		}

		$body  = __( 'This is a test message from Product Expiration Easy Peasy.', 'product-expiration-easy-peasy' ) . "\n\n";
		$body .= sprintf(
			/* translators: %s: localized cutoff date. */
			__( 'With the current settings, products expiring on or before %s are set to out of stock and listed in the daily report sent to this address.', 'product-expiration-easy-peasy' ),
			Date::format( Cron::get_cutoff_date( $settings ) )
		) . "\n";

		$sent = wp_mail(
			$emails,
			__( '[Test] Products near expiration', 'product-expiration-easy-peasy' ),
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);

		return $sent ? 'sent' : 'failed';
	}

	/**
	 * Resolve and validate the configured recipients.
	 *
	 * @param Settings $settings Settings service.
	 * @return array<int, string>
	 */
	public static function recipients( Settings $settings ) {
		$selected = (array) $settings->get_setting( 'email_recipients' );
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
			$emails = array_merge( $emails, explode( ',', (string) $settings->get_setting( 'custom_email' ) ) );
		}

		$emails = array_map( 'sanitize_email', array_map( 'trim', $emails ) );
		$emails = array_filter( $emails, 'is_email' );

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Build the report lines for one product.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return string
	 */
	private static function describe_product( $product ) {
		$product_id = $product->get_id();
		$date       = Date::sanitize( get_post_meta( $product_id, '_expiration_date', true ) );
		$edit_id    = $product->is_type( 'variation' ) && $product->get_parent_id() ? $product->get_parent_id() : $product_id;
		$edit_link  = add_query_arg(
			array(
				'post'   => $edit_id,
				'action' => 'edit',
			),
			admin_url( 'post.php' )
		);

		/* translators: %s: product name. */
		$text = sprintf( __( 'Product: %s', 'product-expiration-easy-peasy' ), wp_strip_all_tags( $product->get_name() ) ) . "\n";
		/* translators: %d: product or variation ID. */
		$text .= sprintf( __( 'Product ID: %d', 'product-expiration-easy-peasy' ), $product_id ) . "\n";
		/* translators: %s: localized expiration date. */
		$text .= sprintf( __( 'Expiration date: %s', 'product-expiration-easy-peasy' ), Date::format( $date ) ) . "\n";

		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );

			if ( $parent ) {
				/* translators: %s: parent product name. */
				$text .= sprintf( __( 'Parent product: %s', 'product-expiration-easy-peasy' ), wp_strip_all_tags( $parent->get_name() ) ) . "\n";
			}
		}

		/* translators: %s: product edit URL. */
		$text .= sprintf( __( 'Edit product: %s', 'product-expiration-easy-peasy' ), esc_url_raw( $edit_link ) ) . "\n\n";

		return $text;
	}
}
