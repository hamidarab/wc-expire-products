<?php
/**
 * WooCommerce storefront integration.
 *
 * @package ProductExpirationEasyPeasy
 */

namespace ProductExpirationEasyPeasy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays expiration dates and snapshots them into new order items.
 */
final class Frontend {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Register storefront hooks.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'woocommerce_get_price_html', array( $this, 'display_expiration_after_price' ), 10, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_expiration_after_title' ), 6 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'display_expiration_in_cart' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'snapshot_expiration_in_order_item' ), 10, 4 );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'display_expiration_in_order' ), 10, 4 );
	}

	/**
	 * Load the small frontend assets only where the feature can appear.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! is_product() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			return;
		}

		wp_enqueue_style(
			'product-expiration-easy-peasy',
			PRODUCT_EXPIRATION_EASY_PEASY_URL . 'assets/css/style.css',
			array(),
			PRODUCT_EXPIRATION_EASY_PEASY_VERSION
		);

		if ( is_product() ) {
			wp_enqueue_script(
				'product-expiration-easy-peasy',
				PRODUCT_EXPIRATION_EASY_PEASY_URL . 'assets/js/script.js',
				array( 'jquery', 'wc-add-to-cart-variation' ),
				PRODUCT_EXPIRATION_EASY_PEASY_VERSION,
				true
			);
		}
	}

	/**
	 * Append expiration markup to the main product price.
	 *
	 * @param string      $price_html Existing price HTML.
	 * @param \WC_Product $product    Product object.
	 * @return string
	 */
	public function display_expiration_after_price( $price_html, $product ) {
		if ( is_admin() || 'after_price' !== $this->settings->get_setting( 'display_position' ) ) {
			return $price_html;
		}

		if ( ! is_product() || (int) get_queried_object_id() !== (int) $product->get_id() ) {
			return $price_html;
		}

		return $price_html . $this->get_product_expiration_html( $product );
	}

	/**
	 * Render expiration markup immediately after the product title.
	 *
	 * @return void
	 */
	public function display_expiration_after_title() {
		if ( 'after_title' !== $this->settings->get_setting( 'display_position' ) ) {
			return;
		}

		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		echo wp_kses(
			$this->get_product_expiration_html( $product ),
			array(
				'div'    => array(
					'class'             => true,
					'data-variation-id' => true,
					'hidden'            => true,
				),
				'strong' => array(),
			)
		);
	}

	/**
	 * Append expiration information to a cart item.
	 *
	 * @param string $item_name     Existing cart item name.
	 * @param array  $cart_item     Cart item data.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function display_expiration_in_cart( $item_name, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );

		if ( 'yes' !== $this->settings->get_setting( 'show_expiration' ) ) {
			return $item_name;
		}

		$product_id = ! empty( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : absint( $cart_item['product_id'] );
		$date       = Date::sanitize( get_post_meta( $product_id, '_expiration_date', true ) );

		if ( '' === $date ) {
			return $item_name;
		}

		return $item_name . '<div class="expiration-date">' . esc_html( $this->format_expiration_text( $date ) ) . '</div>';
	}

	/**
	 * Store the purchase-time expiration value on a new order item.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart values.
	 * @param \WC_Order              $order         Order object.
	 * @return void
	 */
	public function snapshot_expiration_in_order_item( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );

		$product_id = ! empty( $values['variation_id'] ) ? absint( $values['variation_id'] ) : absint( $values['product_id'] );
		$date       = Date::sanitize( get_post_meta( $product_id, '_expiration_date', true ) );

		if ( '' !== $date ) {
			$item->add_meta_data( '_peep_expiration_date', $date, true );
		}
	}

	/**
	 * Display the purchase-time expiration date in order views and emails.
	 *
	 * @param int                    $item_id    Order item ID.
	 * @param \WC_Order_Item_Product $item       Order item.
	 * @param \WC_Order              $order      Order object.
	 * @param bool                   $plain_text Whether the current email is plain text.
	 * @return void
	 */
	public function display_expiration_in_order( $item_id, $item, $order, $plain_text ) {
		unset( $item_id, $order );

		$is_email = $plain_text || did_action( 'woocommerce_email_before_order_table' ) > did_action( 'woocommerce_email_after_order_table' );

		if ( $is_email && 'yes' !== $this->settings->get_setting( 'show_in_order_email' ) ) {
			return;
		}

		if ( ! $is_email && 'yes' !== $this->settings->get_setting( 'show_expiration' ) ) {
			return;
		}

		$date = Date::sanitize( $item->get_meta( '_peep_expiration_date', true ) );

		if ( '' === $date ) {
			$product = $item->get_product();
			$date    = $product ? Date::sanitize( get_post_meta( $product->get_id(), '_expiration_date', true ) ) : '';
		}

		if ( '' === $date ) {
			return;
		}

		$text = $this->format_expiration_text( $date );

		if ( $plain_text ) {
			echo wp_strip_all_tags( $text ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email output; stored template and date are sanitized.
			return;
		}

		echo '<div class="expiration-date">' . esc_html( $text ) . '</div>';
	}

	/**
	 * Build product-page markup for simple and variable products.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string
	 */
	private function get_product_expiration_html( $product ) {
		if ( 'yes' !== $this->settings->get_setting( 'show_expiration' ) ) {
			return '';
		}

		if ( $product->is_type( 'variable' ) ) {
			$html             = '';
			$visible_children = $product->get_visible_children();

			foreach ( $visible_children as $variation_id ) {
				$date = Date::sanitize( get_post_meta( $variation_id, '_expiration_date', true ) );

				if ( '' === $date ) {
					continue;
				}

				$html .= sprintf(
					'<div class="expiration-date variable-expiration" data-variation-id="%1$d" hidden><strong>%2$s</strong></div>',
					absint( $variation_id ),
					esc_html( $this->format_expiration_text( $date ) )
				);
			}

			if ( '' === $html && empty( $product->get_children() ) ) {
				$date = Date::sanitize( get_post_meta( $product->get_id(), '_expiration_date', true ) );

				if ( '' !== $date ) {
					$html = '<div class="expiration-date"><strong>' . esc_html( $this->format_expiration_text( $date ) ) . '</strong></div>';
				}
			}

			return $html;
		}

		$date = Date::sanitize( get_post_meta( $product->get_id(), '_expiration_date', true ) );

		if ( '' === $date ) {
			return '';
		}

		return '<div class="expiration-date"><strong>' . esc_html( $this->format_expiration_text( $date ) ) . '</strong></div>';
	}

	/**
	 * Apply configured placeholders to a localized date.
	 *
	 * @param string $date Stored Y-m-d date.
	 * @return string
	 */
	private function format_expiration_text( $date ) {
		$date_format = $this->settings->get_setting( 'date_format' );
		$template    = (string) $this->settings->get_setting( 'display_text' );

		return strtr(
			$template,
			array(
				'%date%'  => Date::format( $date, $date_format ),
				'%year%'  => Date::format( $date, 'Y' ),
				'%month%' => Date::format( $date, 'm' ),
				'%day%'   => Date::format( $date, 'd' ),
			)
		);
	}
}
