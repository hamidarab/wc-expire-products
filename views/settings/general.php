<?php
/**
 * Settings tab: out-of-stock timing and the date picker calendar.
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

$peep_units     = array(
	'days'   => __( 'Days', 'product-expiration-easy-peasy' ),
	'weeks'  => __( 'Weeks', 'product-expiration-easy-peasy' ),
	'months' => __( 'Months', 'product-expiration-easy-peasy' ),
);
$peep_calendars = array(
	'auto'      => __( 'Automatic', 'product-expiration-easy-peasy' ),
	'jalali'    => __( 'Jalali', 'product-expiration-easy-peasy' ),
	'gregorian' => __( 'Gregorian', 'product-expiration-easy-peasy' ),
);
?>
<section class="peep-panel" id="peep-panel-general" aria-labelledby="peep-panel-general-title">
	<h2 class="peep-panel-title" id="peep-panel-general-title"><?php esc_html_e( 'General settings', 'product-expiration-easy-peasy' ); ?></h2>

	<fieldset class="peep-field">
		<legend class="peep-label">
			<?php esc_html_e( 'How long before expiry should a product go out of stock?', 'product-expiration-easy-peasy' ); ?>
			<?php View::tip( 'peep-tip-period', __( 'A whole number from 1 to 365.', 'product-expiration-easy-peasy' ) ); ?>
		</legend>
		<div class="peep-period">
			<div class="peep-stepper">
				<button type="button" class="peep-step peep-js-only" data-peep-step="1" aria-controls="notification_period" aria-label="<?php esc_attr_e( 'Increase', 'product-expiration-easy-peasy' ); ?>"><?php View::icon( 'plus' ); ?></button>
				<label class="screen-reader-text" for="notification_period"><?php esc_html_e( 'Amount', 'product-expiration-easy-peasy' ); ?></label>
				<input type="number" class="peep-input peep-number" id="notification_period" name="notification_period" value="<?php echo esc_attr( $current['notification_period'] ); ?>" min="1" max="365" step="1" required inputmode="numeric" aria-describedby="peep-tip-period peep-period-error">
				<button type="button" class="peep-step peep-js-only" data-peep-step="-1" aria-controls="notification_period" aria-label="<?php esc_attr_e( 'Decrease', 'product-expiration-easy-peasy' ); ?>"><?php View::icon( 'minus' ); ?></button>
			</div>
			<div class="peep-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Time unit', 'product-expiration-easy-peasy' ); ?>">
				<?php foreach ( $peep_units as $peep_unit => $peep_unit_label ) : ?>
					<label class="peep-seg-btn">
						<input type="radio" class="peep-sr" name="notification_period_type" value="<?php echo esc_attr( $peep_unit ); ?>" <?php checked( $current['notification_period_type'], $peep_unit ); ?>>
						<span><?php echo esc_html( $peep_unit_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<p class="peep-error" id="peep-period-error" role="alert" hidden><?php View::icon( 'x' ); ?><span></span></p>
	</fieldset>

	<div class="peep-box peep-box--mint">
		<span class="peep-tile peep-tile--solid"><?php View::icon( 'calendar' ); ?></span>
		<p aria-live="polite">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: cutoff date. */
					__( 'With this setting, any product that expires by %s goes out of stock today, and its details are included in the email reports.', 'product-expiration-easy-peasy' ),
					'<strong class="peep-strong" id="peep-cutoff">' . esc_html( $view['cutoff_label'] ) . '</strong>'
				),
				array(
					'strong' => array(
						'class' => true,
						'id'    => true,
					),
				)
			);
			?>
		</p>
	</div>

	<div class="peep-box peep-box--info">
		<span class="peep-tile peep-tile--info"><?php View::icon( 'refresh' ); ?></span>
		<div>
			<strong class="peep-box-title"><?php esc_html_e( 'Automatic stock restore', 'product-expiration-easy-peasy' ); ?></strong>
			<p class="peep-muted"><?php esc_html_e( 'If you clear or extend a product\'s expiration date, its stock returns to the status it had before it went out of stock. This only applies to products the plugin itself set out of stock.', 'product-expiration-easy-peasy' ); ?></p>
		</div>
	</div>

	<fieldset class="peep-field">
		<legend class="peep-label">
			<?php esc_html_e( 'Calendar type', 'product-expiration-easy-peasy' ); ?>
			<?php View::tip( 'peep-tip-calendar', __( 'The calendar that opens in the product date field and in Quick Edit. Dates are always stored in a standard format, so you can switch the calendar at any time.', 'product-expiration-easy-peasy' ) ); ?>
		</legend>
		<div class="peep-cards peep-cards--3">
			<?php foreach ( $peep_calendars as $peep_calendar => $peep_calendar_label ) : ?>
				<?php $peep_month = $view['calendars'][ $peep_calendar ]['month']; ?>
				<label class="peep-choice peep-cal">
					<span class="peep-choice-head">
						<input type="radio" class="peep-sr" name="datepicker_calendar" value="<?php echo esc_attr( $peep_calendar ); ?>" <?php checked( $current['datepicker_calendar'], $peep_calendar ); ?>>
						<span class="peep-choice-t"><?php echo esc_html( $peep_calendar_label ); ?></span>
						<span class="peep-dot"><span class="peep-dot-in"></span></span>
					</span>
					<?php if ( null === $peep_month ) : ?>
						<span class="peep-choice-sub"><?php esc_html_e( 'Follows the dashboard language', 'product-expiration-easy-peasy' ); ?></span>
					<?php else : ?>
						<span class="peep-mini" dir="<?php echo esc_attr( $peep_month['rtl'] ? 'rtl' : 'ltr' ); ?>" aria-hidden="true">
							<span class="peep-mini-head">
								<span class="peep-mini-arrow"><?php View::icon( 'chevron', 'peep-mini-prev' ); ?></span>
								<span><?php echo esc_html( $peep_month['title'] ); ?></span>
								<span class="peep-mini-arrow"><?php View::icon( 'chevron', 'peep-mini-next' ); ?></span>
							</span>
							<span class="peep-mini-grid">
								<?php foreach ( $peep_month['heads'] as $peep_head ) : ?>
									<span class="peep-mini-wd<?php echo $peep_head['fri'] ? ' is-fri' : ''; ?>"><?php echo esc_html( $peep_head['label'] ); ?></span>
								<?php endforeach; ?>
								<?php foreach ( $peep_month['cells'] as $peep_cell ) : ?>
									<?php if ( null === $peep_cell ) : ?>
										<span></span>
									<?php else : ?>
										<span class="peep-mini-day<?php echo $peep_cell['today'] ? ' is-today' : ''; ?><?php echo $peep_cell['fri'] ? ' is-fri' : ''; ?>"><?php echo esc_html( $peep_cell['label'] ); ?></span>
									<?php endif; ?>
								<?php endforeach; ?>
							</span>
						</span>
					<?php endif; ?>
					<span class="peep-chip peep-sample" dir="ltr"><?php echo esc_html( $view['calendars'][ $peep_calendar ]['sample'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
	</fieldset>
</section>
