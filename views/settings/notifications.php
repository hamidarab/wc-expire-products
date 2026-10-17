<?php
/**
 * Settings tab: who receives the daily report.
 *
 * @package ProductExpirationEasyPeasy
 *
 * @var array<string, mixed> $current Validated settings.
 * @var array<string, mixed> $view    Precomputed display data.
 */

use ProductExpirationEasyPeasy\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$peep_recipients = array(
	'admin'        => array( __( 'Site admin', 'product-expiration-easy-peasy' ), __( 'The email address in WordPress general settings', 'product-expiration-easy-peasy' ), $view['admin_email'] ),
	/* translators: %s: number of users with the Shop manager role. */
	'shop_manager' => array( __( 'Shop managers', 'product-expiration-easy-peasy' ), __( 'All users with the Shop manager role', 'product-expiration-easy-peasy' ), sprintf( _n( '%s user', '%s users', $view['managers'], 'product-expiration-easy-peasy' ), View::number( $view['managers'] ) ) ),
	'custom'       => array( __( 'Custom email', 'product-expiration-easy-peasy' ), __( 'For example, the warehouse or purchasing manager', 'product-expiration-easy-peasy' ), __( 'Manual', 'product-expiration-easy-peasy' ) ),
);
$peep_selected   = (array) $current['email_recipients'];
$peep_test_texts = array(
	''              => __( 'A sample of the daily report is sent to the saved recipients.', 'product-expiration-easy-peasy' ),
	'sent'          => __( 'The test email was sent to the saved recipients.', 'product-expiration-easy-peasy' ),
	'failed'        => __( 'The test email could not be sent. Check the site email configuration.', 'product-expiration-easy-peasy' ),
	'no_recipients' => __( 'No valid recipient is saved, so the test email was not sent.', 'product-expiration-easy-peasy' ),
);
$peep_test       = isset( $peep_test_texts[ $view['test_status'] ] ) ? $view['test_status'] : '';
?>
<section class="peep-panel" id="peep-panel-notifications" aria-labelledby="peep-panel-notifications-title">
	<h2 class="peep-panel-title" id="peep-panel-notifications-title"><?php esc_html_e( 'Notifications', 'product-expiration-easy-peasy' ); ?></h2>
	<p class="peep-panel-intro"><?php esc_html_e( 'The daily report of products that go out of stock is emailed as a short, itemized message.', 'product-expiration-easy-peasy' ); ?></p>

	<fieldset class="peep-field">
		<legend class="peep-label"><?php esc_html_e( 'Report recipients', 'product-expiration-easy-peasy' ); ?></legend>
		<div class="peep-checks">
			<?php foreach ( $peep_recipients as $peep_value => $peep_recipient ) : ?>
				<label class="peep-check">
					<input type="checkbox" name="email_recipients[]" value="<?php echo esc_attr( $peep_value ); ?>" id="<?php echo esc_attr( 'peep-recipient-' . $peep_value ); ?>" <?php checked( in_array( $peep_value, $peep_selected, true ) ); ?>>
					<span class="peep-check-text">
						<span class="peep-check-t"><?php echo esc_html( $peep_recipient[0] ); ?></span>
						<span class="peep-check-sub"><?php echo esc_html( $peep_recipient[1] ); ?></span>
					</span>
					<span class="peep-meta"<?php echo 'admin' === $peep_value ? ' dir="ltr"' : ''; ?>><?php echo esc_html( $peep_recipient[2] ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="peep-error peep-error--warn" id="peep-recipients-warning" role="status"<?php echo empty( $peep_selected ) ? '' : ' hidden'; ?>><?php View::icon( 'alert' ); ?><span><?php esc_html_e( 'No recipient is selected, so no expiry report will be sent.', 'product-expiration-easy-peasy' ); ?></span></p>
	</fieldset>

	<div class="peep-field" id="peep-custom-email-wrap">
		<div class="peep-label">
			<label for="custom_email"><?php esc_html_e( 'Custom addresses', 'product-expiration-easy-peasy' ); ?></label>
			<?php View::tip( 'peep-tip-custom-email', __( 'Separate multiple addresses with commas.', 'product-expiration-easy-peasy' ) ); ?>
		</div>
		<input type="email" multiple class="peep-input" id="custom_email" name="custom_email" dir="ltr" value="<?php echo esc_attr( $current['custom_email'] ); ?>" autocomplete="email" aria-describedby="peep-tip-custom-email peep-custom-email-error">
		<p class="peep-error" id="peep-custom-email-error" role="alert" hidden><?php View::icon( 'x' ); ?><span></span></p>
	</div>

	<div class="peep-box peep-test">
		<button type="submit" class="peep-btn-soft" form="peep-test-email-form" id="peep-test-email"><?php View::icon( 'send' ); ?><span><?php esc_html_e( 'Send test email', 'product-expiration-easy-peasy' ); ?></span></button>
		<p class="peep-test-status<?php echo in_array( $peep_test, array( 'failed', 'no_recipients' ), true ) ? ' is-error' : ''; ?>" id="peep-test-status" role="status" data-state="<?php echo esc_attr( $peep_test ); ?>"><?php echo esc_html( $peep_test_texts[ $peep_test ] ); ?></p>
	</div>
</section>
