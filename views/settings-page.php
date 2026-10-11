<?php
/**
 * Product expiration settings page.
 *
 * Without JavaScript every section renders stacked and the form still saves;
 * admin/js/settings.js upgrades the section links into an ARIA tab set.
 *
 * @package ProductExpirationEasyPeasy
 *
 * @var array<string, mixed> $current_settings Validated settings.
 * @var array<string, mixed> $view             Precomputed display data.
 */

use ProductExpirationEasyPeasy\Admin;
use ProductExpirationEasyPeasy\Date;
use ProductExpirationEasyPeasy\Jalali;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_updated = isset( $_GET['settings-updated'] ) && is_scalar( $_GET['settings-updated'] ) && 'true' === sanitize_key( wp_unslash( $_GET['settings-updated'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only confirmation flag.
$notice_key       = isset( $_GET['peep_notice'] ) && is_scalar( $_GET['peep_notice'] ) ? sanitize_key( wp_unslash( $_GET['peep_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice selector.
$recipients       = (array) $current_settings['email_recipients'];
$svg_html         = Admin::get_svg_allowed_html();
$is_custom_email  = in_array( 'custom', $recipients, true );
$is_display_off   = 'yes' !== $current_settings['show_expiration'];
$is_email_off     = 'yes' !== $current_settings['show_in_order_email'];
$notice_count     = isset( $_GET['peep_count'] ) && is_scalar( $_GET['peep_count'] ) ? absint( $_GET['peep_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice detail.
$format_number    = static function ( $number ) use ( $view ) {
	$formatted = number_format_i18n( (int) $number );

	return $view['is_persian'] ? Jalali::digits( $formatted ) : $formatted;
};

$notices = array(
	'check_done'           => array(
		'success',
		sprintf(
			/* translators: %s: number of products and variations. */
			_n( 'Check complete. %s product was set out of stock.', 'Check complete. %s products were set out of stock.', $notice_count, 'product-expiration-easy-peasy' ),
			$format_number( $notice_count )
		),
	),
	'check_locked'         => array( 'warning', __( 'Another expiration check is already running. Try again in a few minutes.', 'product-expiration-easy-peasy' ) ),
	'test_sent'            => array( 'success', __( 'Test email sent to the saved recipients.', 'product-expiration-easy-peasy' ) ),
	'test_failed'          => array( 'error', __( 'The test email could not be sent. Check the site email configuration.', 'product-expiration-easy-peasy' ) ),
	'test_no_recipients'   => array( 'warning', __( 'No valid recipient is saved, so the test email was not sent.', 'product-expiration-easy-peasy' ) ),
	'custom_email_missing' => array( 'warning', __( 'Custom email is selected, but the address was empty or invalid, so no custom email will receive reports.', 'product-expiration-easy-peasy' ) ),
);

$section_tabs = array(
	'rules'         => array( 'zap', __( 'Automation rules', 'product-expiration-easy-peasy' ) ),
	'display'       => array( 'eye', __( 'Storefront display', 'product-expiration-easy-peasy' ) ),
	'notifications' => array( 'mail', __( 'Notifications', 'product-expiration-easy-peasy' ) ),
	'help'          => array( 'help', __( 'Help & status', 'product-expiration-easy-peasy' ) ),
);

$preview_text = strtr(
	(string) $current_settings['display_text'],
	array(
		'%date%'  => $view['format_samples'][ $current_settings['date_format'] ],
		'%year%'  => $view['today_parts']['year'],
		'%month%' => $view['today_parts']['month'],
		'%day%'   => $view['today_parts']['day'],
	)
);
?>
<div class="wrap peep-settings-wrap" data-peep-calendar="<?php echo esc_attr( $view['is_persian'] ? 'jalali' : 'gregorian' ); ?>">
	<header class="peep-header">
		<?php echo wp_kses( Admin::get_icon( 'logo' ), $svg_html ); ?>
		<div class="peep-header__text">
			<h1 class="peep-header__title"><?php esc_html_e( 'Product Expiration Settings', 'product-expiration-easy-peasy' ); ?></h1>
			<p class="peep-header__version">
				<?php
				/* translators: %s: plugin version number. */
				printf( esc_html__( 'Version %s', 'product-expiration-easy-peasy' ), esc_html( PRODUCT_EXPIRATION_EASY_PEASY_VERSION ) );
				?>
			</p>
		</div>
		<p class="peep-pill peep-pill--calendar">
			<?php echo wp_kses( Admin::get_icon( 'calendar' ), $svg_html ); ?>
			<span>
				<?php
				if ( $view['is_persian'] ) {
					esc_html_e( 'Dates are shown in the Jalali calendar (built-in converter)', 'product-expiration-easy-peasy' );
				} else {
					esc_html_e( 'Dates are shown in the Gregorian calendar', 'product-expiration-easy-peasy' );
				}
				?>
			</span>
		</p>
	</header>
	<hr class="wp-header-end">

	<?php if ( $settings_updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved successfully.', 'product-expiration-easy-peasy' ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $notices[ $notice_key ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice_key ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice_key ][1] ); ?></p></div>
	<?php endif; ?>

	<section class="peep-stats" aria-label="<?php esc_attr_e( 'Status overview', 'product-expiration-easy-peasy' ); ?>">
		<div class="peep-stat">
			<span class="peep-stat__icon"><?php echo wp_kses( Admin::get_icon( 'tag' ), $svg_html ); ?></span>
			<p class="peep-stat__value"><?php echo esc_html( $format_number( $view['counts']['dated'] ) ); ?></p>
			<p class="peep-stat__label"><?php esc_html_e( 'Products and variations with a date', 'product-expiration-easy-peasy' ); ?></p>
		</div>
		<div class="peep-stat peep-stat--danger">
			<span class="peep-stat__icon"><?php echo wp_kses( Admin::get_icon( 'alert' ), $svg_html ); ?></span>
			<p class="peep-stat__value"><?php echo esc_html( $format_number( $view['counts']['expired'] ) ); ?></p>
			<p class="peep-stat__label"><?php esc_html_e( 'Expired', 'product-expiration-easy-peasy' ); ?></p>
			<a class="peep-stat__link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&expiration_filter=expired' ) ); ?>"><?php esc_html_e( 'View expired products', 'product-expiration-easy-peasy' ); ?></a>
		</div>
		<div class="peep-stat">
			<span class="peep-stat__icon"><?php echo wp_kses( Admin::get_icon( 'box' ), $svg_html ); ?></span>
			<p class="peep-stat__value"><?php echo esc_html( $format_number( $view['counts']['managed'] ) ); ?></p>
			<p class="peep-stat__label"><?php esc_html_e( 'Set out of stock by the plugin', 'product-expiration-easy-peasy' ); ?></p>
		</div>
		<div class="peep-stat">
			<span class="peep-stat__icon"><?php echo wp_kses( Admin::get_icon( 'clock' ), $svg_html ); ?></span>
			<p class="peep-stat__value peep-stat__value--text">
				<?php
				if ( $view['next_run'] ) {
					echo esc_html( Date::format_timestamp( $view['next_run'] ) );
				} else {
					esc_html_e( 'Not scheduled', 'product-expiration-easy-peasy' );
				}
				?>
			</p>
			<p class="peep-stat__label"><?php esc_html_e( 'Next check', 'product-expiration-easy-peasy' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="peep-stat__action">
				<input type="hidden" name="action" value="peep_run_check">
				<input type="hidden" name="peep_tab" value="rules" class="peep-tab-input">
				<?php wp_nonce_field( 'peep_run_check', 'peep_run_check_nonce' ); ?>
				<button type="submit" class="button peep-button"><?php echo wp_kses( Admin::get_icon( 'refresh' ), $svg_html ); ?><?php esc_html_e( 'Run check now', 'product-expiration-easy-peasy' ); ?></button>
			</form>
		</div>
	</section>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="peep-layout" id="peep-settings-form">
		<input type="hidden" name="action" value="peep_save_settings">
		<input type="hidden" name="peep_tab" value="rules" class="peep-tab-input">
		<?php wp_nonce_field( 'peep_save_settings', 'peep_settings_nonce' ); ?>

		<nav class="peep-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'product-expiration-easy-peasy' ); ?>">
			<div class="peep-tabs__list">
				<?php foreach ( $section_tabs as $tab_key => $section_tab ) : ?>
					<a class="peep-tab" id="<?php echo esc_attr( 'peep-tab-' . $tab_key ); ?>" href="<?php echo esc_attr( '#peep-panel-' . $tab_key ); ?>" data-peep-tab="<?php echo esc_attr( $tab_key ); ?>">
						<?php echo wp_kses( Admin::get_icon( $section_tab[0] ), $svg_html ); ?>
						<span><?php echo esc_html( $section_tab[1] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</nav>

		<div class="peep-content">
			<section class="peep-panel" id="peep-panel-rules" aria-labelledby="peep-panel-rules-title">
				<h2 class="peep-panel__title" id="peep-panel-rules-title"><?php esc_html_e( 'Automation rules', 'product-expiration-easy-peasy' ); ?></h2>
				<p class="peep-panel__intro"><?php esc_html_e( 'Choose when products near their expiration date are taken off sale.', 'product-expiration-easy-peasy' ); ?></p>

				<fieldset class="peep-field" data-peep-today="<?php echo esc_attr( $view['today'] ); ?>">
					<legend class="peep-field__label"><?php esc_html_e( 'How long before expiry should a product go out of stock?', 'product-expiration-easy-peasy' ); ?></legend>
					<div class="peep-inline">
						<label class="screen-reader-text" for="notification_period"><?php esc_html_e( 'Amount', 'product-expiration-easy-peasy' ); ?></label>
						<input type="number" id="notification_period" name="notification_period" class="peep-input peep-input--number" value="<?php echo esc_attr( $current_settings['notification_period'] ); ?>" min="1" max="365" step="1" required inputmode="numeric" aria-describedby="peep-period-hint peep-period-error">
						<label class="screen-reader-text" for="notification_period_type"><?php esc_html_e( 'Unit', 'product-expiration-easy-peasy' ); ?></label>
						<select id="notification_period_type" name="notification_period_type" class="peep-input">
							<option value="days" <?php selected( $current_settings['notification_period_type'], 'days' ); ?>><?php esc_html_e( 'Days', 'product-expiration-easy-peasy' ); ?></option>
							<option value="weeks" <?php selected( $current_settings['notification_period_type'], 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'product-expiration-easy-peasy' ); ?></option>
							<option value="months" <?php selected( $current_settings['notification_period_type'], 'months' ); ?>><?php esc_html_e( 'Months', 'product-expiration-easy-peasy' ); ?></option>
						</select>
					</div>
					<p class="peep-field__hint" id="peep-period-hint"><?php esc_html_e( 'A whole number from 1 to 365.', 'product-expiration-easy-peasy' ); ?></p>
					<p class="peep-inline-message peep-inline-message--error" id="peep-period-error" role="alert" hidden>
						<?php echo wp_kses( Admin::get_icon( 'x' ), $svg_html ); ?>
						<span class="peep-inline-message__text"></span>
					</p>
					<p class="peep-callout peep-callout--preview" id="peep-cutoff-preview" aria-live="polite">
						<?php echo wp_kses( Admin::get_icon( 'calendar' ), $svg_html ); ?>
						<span>
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: cutoff date, wrapped in bold markup. */
									__( 'With the current setting, products expiring on or before %s are set out of stock and included in the email report.', 'product-expiration-easy-peasy' ),
									'<strong class="peep-cutoff-date">' . esc_html( $view['cutoff_label'] ) . '</strong>'
								),
								array( 'strong' => array( 'class' => true ) )
							);
							?>
						</span>
					</p>
				</fieldset>

				<div class="peep-callout peep-callout--info">
					<?php echo wp_kses( Admin::get_icon( 'info' ), $svg_html ); ?>
					<p><?php esc_html_e( 'If you clear or extend a date, the previous stock status is restored automatically. This applies only to products this plugin set out of stock.', 'product-expiration-easy-peasy' ); ?></p>
				</div>

				<fieldset class="peep-field" aria-describedby="peep-datepicker-calendar-hint">
					<legend class="peep-field__label"><?php esc_html_e( 'Date picker calendar', 'product-expiration-easy-peasy' ); ?></legend>
					<p class="peep-field__hint" id="peep-datepicker-calendar-hint"><?php esc_html_e( 'Calendar used by the date picker on product and Quick Edit fields. Dates are always stored in a standard format, so you can switch at any time.', 'product-expiration-easy-peasy' ); ?></p>
					<div class="peep-choice-grid">
						<?php
						$picker_labels = array(
							'auto'      => __( 'Auto (follows the admin language)', 'product-expiration-easy-peasy' ),
							'jalali'    => __( 'Jalali (Shamsi)', 'product-expiration-easy-peasy' ),
							'gregorian' => __( 'Gregorian (Miladi)', 'product-expiration-easy-peasy' ),
						);

						foreach ( $picker_labels as $picker_calendar => $picker_label ) :
							?>
							<label class="peep-choice">
								<input type="radio" class="peep-choice__input" name="datepicker_calendar" value="<?php echo esc_attr( $picker_calendar ); ?>" <?php checked( $current_settings['datepicker_calendar'], $picker_calendar ); ?>>
								<span class="peep-choice__body">
									<span class="peep-choice__title"><?php echo esc_html( $picker_label ); ?></span>
									<span class="peep-choice__meta" dir="ltr"><?php echo esc_html( $view['picker_samples'][ $picker_calendar ] ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			</section>

			<section class="peep-panel" id="peep-panel-display" aria-labelledby="peep-panel-display-title">
				<h2 class="peep-panel__title" id="peep-panel-display-title"><?php esc_html_e( 'Storefront display', 'product-expiration-easy-peasy' ); ?></h2>
				<p class="peep-panel__intro"><?php esc_html_e( 'Control how the expiration date appears to customers.', 'product-expiration-easy-peasy' ); ?></p>

				<div class="peep-field peep-switch-field">
					<input type="hidden" name="show_expiration" value="no">
					<input type="checkbox" class="peep-switch__input" id="show_expiration" name="show_expiration" value="yes" role="switch" aria-describedby="peep-show-expiration-hint" <?php checked( $current_settings['show_expiration'], 'yes' ); ?>>
					<label class="peep-switch" for="show_expiration">
						<span class="peep-switch__track" aria-hidden="true"><span class="peep-switch__thumb"></span></span>
						<span class="peep-switch__label"><?php esc_html_e( 'Show the expiration date to customers', 'product-expiration-easy-peasy' ); ?></span>
					</label>
					<p class="peep-field__hint" id="peep-show-expiration-hint"><?php esc_html_e( 'On product pages, in the cart and in the customer account.', 'product-expiration-easy-peasy' ); ?></p>
				</div>

				<div class="peep-field peep-switch-field">
					<input type="hidden" name="show_in_order_email" value="no">
					<input type="checkbox" class="peep-switch__input" id="show_in_order_email" name="show_in_order_email" value="yes" role="switch" aria-describedby="peep-order-email-hint" <?php checked( $current_settings['show_in_order_email'], 'yes' ); ?>>
					<label class="peep-switch" for="show_in_order_email">
						<span class="peep-switch__track" aria-hidden="true"><span class="peep-switch__thumb"></span></span>
						<span class="peep-switch__label"><?php esc_html_e( 'Show the date in order emails', 'product-expiration-easy-peasy' ); ?></span>
					</label>
					<p class="peep-field__hint" id="peep-order-email-hint"><?php esc_html_e( 'Adds the purchase-time expiration date to each item in WooCommerce order emails.', 'product-expiration-easy-peasy' ); ?></p>
				</div>

				<div class="peep-dependent<?php echo ( $is_display_off && $is_email_off ) ? ' is-dimmed' : ''; ?>" data-peep-depends="any">
					<div class="peep-field">
						<label class="peep-field__label" for="display_text"><?php esc_html_e( 'Display text', 'product-expiration-easy-peasy' ); ?></label>
						<input type="text" id="display_text" name="display_text" class="peep-input peep-input--wide" value="<?php echo esc_attr( $current_settings['display_text'] ); ?>" aria-describedby="peep-display-text-hint">
						<div class="peep-chips" role="group" aria-label="<?php esc_attr_e( 'Insert a placeholder', 'product-expiration-easy-peasy' ); ?>" hidden>
							<?php foreach ( array( '%date%', '%year%', '%month%', '%day%' ) as $placeholder ) : ?>
								<button type="button" class="peep-chip" data-peep-insert="<?php echo esc_attr( $placeholder ); ?>" dir="ltr"><?php echo esc_html( $placeholder ); ?></button>
							<?php endforeach; ?>
						</div>
						<p class="peep-field__hint" id="peep-display-text-hint">
							<span class="peep-hint-js" hidden><?php esc_html_e( 'Select a placeholder to insert it at the cursor.', 'product-expiration-easy-peasy' ); ?></span>
							<span class="peep-hint-nojs">
								<?php esc_html_e( 'Available placeholders:', 'product-expiration-easy-peasy' ); ?>
								<code>%date%</code> <code>%year%</code> <code>%month%</code> <code>%day%</code>
							</span>
						</p>
					</div>

					<fieldset class="peep-field">
						<legend class="peep-field__label"><?php esc_html_e( 'Date format', 'product-expiration-easy-peasy' ); ?></legend>
						<div class="peep-choice-grid">
							<?php foreach ( $view['format_samples'] as $format => $sample ) : ?>
								<label class="peep-choice">
									<input type="radio" class="peep-choice__input" name="date_format" value="<?php echo esc_attr( $format ); ?>" data-sample="<?php echo esc_attr( $sample ); ?>" <?php checked( $current_settings['date_format'], $format ); ?>>
									<span class="peep-choice__body">
										<span class="peep-choice__title"><?php echo esc_html( $sample ); ?></span>
										<?php if ( 'default' === $format ) : ?>
											<span class="peep-choice__meta"><?php esc_html_e( 'Site default', 'product-expiration-easy-peasy' ); ?></span>
										<?php else : ?>
											<code class="peep-choice__meta" dir="ltr"><?php echo esc_html( $format ); ?></code>
										<?php endif; ?>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<div class="peep-preview" aria-live="polite">
						<p class="peep-preview__label"><?php esc_html_e( 'Preview (using today’s date)', 'product-expiration-easy-peasy' ); ?></p>
						<div class="peep-preview__stage">
							<div class="expiration-date"><strong class="peep-preview__text" data-year="<?php echo esc_attr( $view['today_parts']['year'] ); ?>" data-month="<?php echo esc_attr( $view['today_parts']['month'] ); ?>" data-day="<?php echo esc_attr( $view['today_parts']['day'] ); ?>"><?php echo esc_html( $preview_text ); ?></strong></div>
						</div>
					</div>
				</div>

				<fieldset class="peep-field peep-dependent<?php echo $is_display_off ? ' is-dimmed' : ''; ?>" data-peep-depends="show_expiration">
					<legend class="peep-field__label"><?php esc_html_e( 'Position on the product page', 'product-expiration-easy-peasy' ); ?></legend>
					<div class="peep-choice-grid peep-choice-grid--two">
						<?php
						$positions = array(
							'after_title' => __( 'After title', 'product-expiration-easy-peasy' ),
							'after_price' => __( 'After price', 'product-expiration-easy-peasy' ),
						);
						foreach ( $positions as $position => $position_label ) :
							$badge_y = 'after_title' === $position ? 22 : 40;
							$price_y = 'after_title' === $position ? 40 : 28;
							?>
							<label class="peep-choice peep-choice--diagram">
								<input type="radio" class="peep-choice__input" name="display_position" value="<?php echo esc_attr( $position ); ?>" <?php checked( $current_settings['display_position'], $position ); ?>>
								<span class="peep-choice__body">
									<svg class="peep-diagram" viewBox="0 0 120 64" aria-hidden="true" focusable="false">
										<rect x="4" y="6" width="40" height="52" rx="4" class="peep-diagram__image"/>
										<rect x="52" y="10" width="56" height="7" rx="2" class="peep-diagram__line"/>
										<rect x="52" y="<?php echo esc_attr( $badge_y ); ?>" width="40" height="10" rx="3" class="peep-diagram__badge"/>
										<rect x="52" y="<?php echo esc_attr( $price_y ); ?>" width="26" height="6" rx="2" class="peep-diagram__price"/>
									</svg>
									<span class="peep-choice__title"><?php echo esc_html( $position_label ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			</section>

			<section class="peep-panel" id="peep-panel-notifications" aria-labelledby="peep-panel-notifications-title">
				<h2 class="peep-panel__title" id="peep-panel-notifications-title"><?php esc_html_e( 'Notifications', 'product-expiration-easy-peasy' ); ?></h2>
				<p class="peep-panel__intro"><?php esc_html_e( 'Choose who receives the report of products set out of stock by each daily check.', 'product-expiration-easy-peasy' ); ?></p>

				<fieldset class="peep-field" id="peep-recipients">
					<legend class="peep-field__label"><?php esc_html_e( 'Who should receive the expiry report?', 'product-expiration-easy-peasy' ); ?></legend>
					<div class="peep-checks">
						<label class="peep-check">
							<input type="checkbox" name="email_recipients[]" value="admin" <?php checked( in_array( 'admin', $recipients, true ) ); ?>>
							<span class="peep-check__text"><?php esc_html_e( 'Site admin', 'product-expiration-easy-peasy' ); ?></span>
							<span class="peep-check__meta" dir="ltr"><?php echo esc_html( $view['admin_email'] ); ?></span>
						</label>
						<label class="peep-check">
							<input type="checkbox" name="email_recipients[]" value="shop_manager" <?php checked( in_array( 'shop_manager', $recipients, true ) ); ?>>
							<span class="peep-check__text"><?php esc_html_e( 'Shop managers', 'product-expiration-easy-peasy' ); ?></span>
							<span class="peep-check__meta">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: number of users with the Shop Manager role. */
										_n( '%s user', '%s users', $view['shop_manager_count'], 'product-expiration-easy-peasy' ),
										$format_number( $view['shop_manager_count'] )
									)
								);
								?>
							</span>
						</label>
						<label class="peep-check">
							<input type="checkbox" name="email_recipients[]" value="custom" id="peep-recipient-custom" aria-controls="peep-custom-email-wrap" <?php checked( $is_custom_email ); ?>>
							<span class="peep-check__text"><?php esc_html_e( 'Custom email', 'product-expiration-easy-peasy' ); ?></span>
						</label>
					</div>

					<p class="peep-inline-message peep-inline-message--warning" id="peep-recipients-warning" role="status"<?php echo empty( $recipients ) ? '' : ' hidden'; ?>>
						<?php echo wp_kses( Admin::get_icon( 'alert' ), $svg_html ); ?>
						<span><?php esc_html_e( 'No recipient is selected, so no expiry report will be sent.', 'product-expiration-easy-peasy' ); ?></span>
					</p>

					<div class="peep-disclosure" id="peep-custom-email-wrap">
						<label class="peep-field__label" for="custom_email"><?php esc_html_e( 'Custom email address', 'product-expiration-easy-peasy' ); ?></label>
						<input type="email" id="custom_email" name="custom_email" class="peep-input peep-input--wide" dir="ltr" value="<?php echo esc_attr( $current_settings['custom_email'] ); ?>" autocomplete="email" aria-describedby="peep-custom-email-error">
						<p class="peep-inline-message peep-inline-message--error" id="peep-custom-email-error" role="alert" hidden>
							<?php echo wp_kses( Admin::get_icon( 'x' ), $svg_html ); ?>
							<span class="peep-inline-message__text"></span>
						</p>
					</div>
				</fieldset>

				<div class="peep-field">
					<button type="submit" class="button peep-button" form="peep-test-email-form" aria-describedby="peep-test-email-hint"><?php echo wp_kses( Admin::get_icon( 'mail' ), $svg_html ); ?><?php esc_html_e( 'Send test email', 'product-expiration-easy-peasy' ); ?></button>
					<p class="peep-field__hint" id="peep-test-email-hint"><?php esc_html_e( 'Sends a sample message to the saved recipients. Save your changes first.', 'product-expiration-easy-peasy' ); ?></p>
				</div>
			</section>

			<section class="peep-panel" id="peep-panel-help" aria-labelledby="peep-panel-help-title">
				<h2 class="peep-panel__title" id="peep-panel-help-title"><?php esc_html_e( 'Help & status', 'product-expiration-easy-peasy' ); ?></h2>
				<p class="peep-panel__intro"><?php esc_html_e( 'How to set expiration dates, and the status of the plugin and its requirements.', 'product-expiration-easy-peasy' ); ?></p>

				<h3 class="peep-subtitle"><?php esc_html_e( 'Setting expiration dates', 'product-expiration-easy-peasy' ); ?></h3>
				<ol class="peep-steps">
					<li><?php esc_html_e( 'Simple product: open the product, go to Product data > General and fill in Expiration Date.', 'product-expiration-easy-peasy' ); ?></li>
					<li><?php esc_html_e( 'Variable product: open Product data > Variations, expand a variation and fill in its Expiration Date.', 'product-expiration-easy-peasy' ); ?></li>
					<li><?php esc_html_e( 'Quick Edit: in the product list, hover over a simple product, choose Quick Edit and fill in Expiration Date.', 'product-expiration-easy-peasy' ); ?></li>
					<li>
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: example Jalali date, wrapped in code markup. */
								__( 'On Persian admin screens, enter a Jalali date such as %s. Persian, Arabic and Latin digits are accepted, with a hyphen or slash as the separator.', 'product-expiration-easy-peasy' ),
								'<code dir="ltr">' . esc_html( Jalali::digits( '1405-01-01' ) ) . '</code>'
							),
							array( 'code' => array( 'dir' => true ) )
						);
						?>
					</li>
				</ol>

				<h3 class="peep-subtitle"><?php esc_html_e( 'System status', 'product-expiration-easy-peasy' ); ?></h3>
				<?php
				$status_rows = array(
					array( __( 'Plugin version', 'product-expiration-easy-peasy' ), PRODUCT_EXPIRATION_EASY_PEASY_VERSION, null ),
					array(
						__( 'RTL-Theme license', 'product-expiration-easy-peasy' ),
						$view['license_active'] ? __( 'Active', 'product-expiration-easy-peasy' ) : __( 'Not active', 'product-expiration-easy-peasy' ),
						$view['license_active'],
					),
					array(
						__( 'ionCube Loader', 'product-expiration-easy-peasy' ),
						'' !== $view['ioncube_version']
							/* translators: %s: ionCube Loader version. */
							? sprintf( __( 'Loaded (%s)', 'product-expiration-easy-peasy' ), $view['ioncube_version'] )
							: __( 'Not loaded', 'product-expiration-easy-peasy' ),
						'' !== $view['ioncube_version'],
					),
					array( 'WooCommerce', defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not detected', 'product-expiration-easy-peasy' ), defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '9.0', '>=' ) ),
					array( 'PHP', PHP_VERSION, version_compare( PHP_VERSION, '8.1', '>=' ) ),
					array( 'WordPress', get_bloginfo( 'version' ), null ),
				);
				?>
				<table class="peep-status-table">
					<tbody>
						<?php foreach ( $status_rows as $status_row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $status_row[0] ); ?></th>
								<td>
									<?php if ( null === $status_row[2] ) : ?>
										<span dir="ltr"><?php echo esc_html( $status_row[1] ); ?></span>
									<?php else : ?>
										<span class="peep-status-text <?php echo esc_attr( $status_row[2] ? 'is-ok' : 'is-problem' ); ?>">
											<?php echo wp_kses( Admin::get_icon( $status_row[2] ? 'check' : 'x' ), $svg_html ); ?>
											<span><?php echo esc_html( $status_row[1] ); ?></span>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>

			<div class="peep-savebar">
				<p class="peep-savebar__status" id="peep-save-status" role="status"></p>
				<button type="submit" class="button button-primary peep-button-primary" id="peep-save"><?php esc_html_e( 'Save changes', 'product-expiration-easy-peasy' ); ?></button>
			</div>
		</div>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="peep-test-email-form" hidden>
		<input type="hidden" name="action" value="peep_send_test_email">
		<input type="hidden" name="peep_tab" value="notifications">
		<?php wp_nonce_field( 'peep_send_test_email', 'peep_test_email_nonce' ); ?>
	</form>
</div>
