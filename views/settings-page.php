<?php
/**
 * Product expiration settings page.
 *
 * @package ProductExpirationEasyPeasy
 *
 * @var array<string, mixed> $current_settings Validated settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_updated = isset( $_GET['settings-updated'] ) && is_scalar( $_GET['settings-updated'] ) && 'true' === sanitize_key( wp_unslash( $_GET['settings-updated'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only confirmation flag.
$recipients       = (array) $current_settings['email_recipients'];
?>
<div class="wrap peep-settings-wrap">
	<h1><?php esc_html_e( 'Product Expiration Settings', 'product-expiration-easy-peasy' ); ?></h1>

	<?php if ( $settings_updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved successfully.', 'product-expiration-easy-peasy' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="peep_save_settings">
		<?php wp_nonce_field( 'peep_save_settings', 'peep_settings_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="notification_period_type"><?php esc_html_e( 'Notification Period Type', 'product-expiration-easy-peasy' ); ?></label>
				</th>
				<td>
					<select id="notification_period_type" name="notification_period_type">
						<option value="days" <?php selected( $current_settings['notification_period_type'], 'days' ); ?>><?php esc_html_e( 'Days', 'product-expiration-easy-peasy' ); ?></option>
						<option value="weeks" <?php selected( $current_settings['notification_period_type'], 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'product-expiration-easy-peasy' ); ?></option>
						<option value="months" <?php selected( $current_settings['notification_period_type'], 'months' ); ?>><?php esc_html_e( 'Months', 'product-expiration-easy-peasy' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="notification_period"><?php esc_html_e( 'Notification Period', 'product-expiration-easy-peasy' ); ?></label>
				</th>
				<td>
					<input type="number" id="notification_period" name="notification_period" value="<?php echo esc_attr( $current_settings['notification_period'] ); ?>" min="1" max="365">
					<p class="description"><?php esc_html_e( 'Products expiring within this period are set to out of stock and included in the notification email.', 'product-expiration-easy-peasy' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="show_expiration"><?php esc_html_e( 'Show Expiration Date', 'product-expiration-easy-peasy' ); ?></label>
				</th>
				<td>
					<select id="show_expiration" name="show_expiration">
						<option value="yes" <?php selected( $current_settings['show_expiration'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'product-expiration-easy-peasy' ); ?></option>
						<option value="no" <?php selected( $current_settings['show_expiration'], 'no' ); ?>><?php esc_html_e( 'No', 'product-expiration-easy-peasy' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="display_text"><?php esc_html_e( 'Display Text', 'product-expiration-easy-peasy' ); ?></label>
				</th>
				<td>
					<input type="text" id="display_text" name="display_text" class="regular-text" value="<?php echo esc_attr( $current_settings['display_text'] ); ?>">
					<p class="description">
						<?php esc_html_e( 'Available placeholders:', 'product-expiration-easy-peasy' ); ?>
						<code>%date%</code>, <code>%year%</code>, <code>%month%</code>, <code>%day%</code>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="date_format"><?php esc_html_e( 'Date Format', 'product-expiration-easy-peasy' ); ?></label>
				</th>
				<td>
					<select id="date_format" name="date_format">
						<option value="default" <?php selected( $current_settings['date_format'], 'default' ); ?>><?php esc_html_e( 'Default WordPress format', 'product-expiration-easy-peasy' ); ?></option>
						<option value="Y/m/d" <?php selected( $current_settings['date_format'], 'Y/m/d' ); ?>>Y/m/d</option>
						<option value="Y/m" <?php selected( $current_settings['date_format'], 'Y/m' ); ?>>Y/m</option>
						<option value="Ym" <?php selected( $current_settings['date_format'], 'Ym' ); ?>>Ym</option>
						<option value="Y-m-d" <?php selected( $current_settings['date_format'], 'Y-m-d' ); ?>>Y-m-d</option>
						<option value="d M Y" <?php selected( $current_settings['date_format'], 'd M Y' ); ?>>d M Y</option>
					</select>
					<p class="description"><?php esc_html_e( 'Persian locales are displayed with the bundled Jalali converter; stored values remain standard Gregorian dates.', 'product-expiration-easy-peasy' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="display_position"><?php esc_html_e( 'Display Position', 'product-expiration-easy-peasy' ); ?></label>
				</th>
				<td>
					<select id="display_position" name="display_position">
						<option value="after_price" <?php selected( $current_settings['display_position'], 'after_price' ); ?>><?php esc_html_e( 'After Price', 'product-expiration-easy-peasy' ); ?></option>
						<option value="after_title" <?php selected( $current_settings['display_position'], 'after_title' ); ?>><?php esc_html_e( 'After Title', 'product-expiration-easy-peasy' ); ?></option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Email Recipients', 'product-expiration-easy-peasy' ); ?></th>
				<td>
					<fieldset>
						<label><input type="checkbox" name="email_recipients[]" value="admin" <?php checked( in_array( 'admin', $recipients, true ) ); ?>> <?php esc_html_e( 'Site Admin', 'product-expiration-easy-peasy' ); ?></label><br>
						<label><input type="checkbox" name="email_recipients[]" value="shop_manager" <?php checked( in_array( 'shop_manager', $recipients, true ) ); ?>> <?php esc_html_e( 'Shop Managers', 'product-expiration-easy-peasy' ); ?></label><br>
						<label><input type="checkbox" name="email_recipients[]" value="custom" <?php checked( in_array( 'custom', $recipients, true ) ); ?>> <?php esc_html_e( 'Custom Email', 'product-expiration-easy-peasy' ); ?></label>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="custom_email"><?php esc_html_e( 'Custom Email', 'product-expiration-easy-peasy' ); ?></label>
				</th>
				<td>
					<input type="email" id="custom_email" name="custom_email" class="regular-text ltr" value="<?php echo esc_attr( $current_settings['custom_email'] ); ?>" autocomplete="email">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="show_in_order_email"><?php esc_html_e( 'Show in Order Email', 'product-expiration-easy-peasy' ); ?></label>
				</th>
				<td>
					<select id="show_in_order_email" name="show_in_order_email">
						<option value="yes" <?php selected( $current_settings['show_in_order_email'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'product-expiration-easy-peasy' ); ?></option>
						<option value="no" <?php selected( $current_settings['show_in_order_email'], 'no' ); ?>><?php esc_html_e( 'No', 'product-expiration-easy-peasy' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'product-expiration-easy-peasy' ) ); ?>
	</form>
</div>
