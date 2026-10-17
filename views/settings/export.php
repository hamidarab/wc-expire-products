<?php
/**
 * Settings tab: export expired and near-expiry products.
 *
 * A form of its own; without JavaScript it downloads the file directly.
 *
 * @package ProductExpirationEasyPeasy
 *
 * @var array<string, mixed> $view Precomputed display data.
 */

use ProductExpirationEasyPeasy\Export_Controller;
use ProductExpirationEasyPeasy\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$peep_export = $view['export'];
$peep_counts = $peep_export['counts'];
$peep_scopes = array(
	'expired' => array( __( 'Expired', 'product-expiration-easy-peasy' ), __( 'Their date has passed', 'product-expiration-easy-peasy' ) ),
	/* translators: %s: number of days. */
	'soon'    => array( __( 'Near expiry', 'product-expiration-easy-peasy' ), sprintf( _n( 'Expire within the next %s day', 'Expire within the next %s days', 30, 'product-expiration-easy-peasy' ), View::number( 30 ) ) ),
	'both'    => array( __( 'Both', 'product-expiration-easy-peasy' ), __( 'Both groups in one file', 'product-expiration-easy-peasy' ) ),
);
$peep_ranges = array();

foreach ( array( 7, 30, 90 ) as $peep_days ) {
	/* translators: %s: number of days. */
	$peep_ranges[ (string) $peep_days ] = sprintf( _n( '%s day', '%s days', $peep_days, 'product-expiration-easy-peasy' ), View::number( $peep_days ) );
}

$peep_ranges['custom'] = __( 'Custom', 'product-expiration-easy-peasy' );
$peep_formats          = array(
	'xlsx' => array( __( 'Excel', 'product-expiration-easy-peasy' ), __( 'Opens directly in Excel', 'product-expiration-easy-peasy' ) ),
	'csv'  => array( 'CSV', __( 'Lightweight and works with most software', 'product-expiration-easy-peasy' ) ),
);
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="peep-export-form">
	<input type="hidden" name="action" value="peep_export">
	<?php wp_nonce_field( Export_Controller::NONCE, 'peep_export_nonce' ); ?>

	<section class="peep-panel" id="peep-panel-export" aria-labelledby="peep-panel-export-title">
		<h2 class="peep-panel-title" id="peep-panel-export-title"><?php esc_html_e( 'Export', 'product-expiration-easy-peasy' ); ?></h2>

		<fieldset class="peep-field">
			<legend class="peep-label"><?php esc_html_e( 'Which products?', 'product-expiration-easy-peasy' ); ?></legend>
			<div class="peep-cards peep-cards--3">
				<?php foreach ( $peep_scopes as $peep_scope => $peep_scope_text ) : ?>
					<label class="peep-choice">
						<span class="peep-choice-head">
							<input type="radio" class="peep-sr" name="scope" value="<?php echo esc_attr( $peep_scope ); ?>" <?php checked( 'both', $peep_scope ); ?>>
							<span class="peep-choice-t"><?php echo esc_html( $peep_scope_text[0] ); ?></span>
							<span class="peep-dot"><span class="peep-dot-in"></span></span>
						</span>
						<span class="peep-choice-sub" id="<?php echo esc_attr( 'peep-scope-sub-' . $peep_scope ); ?>"><?php echo esc_html( $peep_scope_text[1] ); ?></span>
						<span class="peep-meta peep-count" data-peep-count="<?php echo esc_attr( $peep_scope ); ?>" data-count="<?php echo esc_attr( $peep_counts[ $peep_scope ] ); ?>">
							<?php
							/* translators: %s: number of products. */
							echo esc_html( sprintf( _n( '%s product', '%s products', $peep_counts[ $peep_scope ], 'product-expiration-easy-peasy' ), View::number( $peep_counts[ $peep_scope ] ) ) );
							?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<div class="peep-field" id="peep-range" role="group" aria-labelledby="peep-range-label">
			<div class="peep-range-row">
				<span class="peep-label" id="peep-range-label"><?php esc_html_e( 'Near-expiry range', 'product-expiration-easy-peasy' ); ?></span>
				<div class="peep-seg" role="radiogroup" aria-labelledby="peep-range-label">
					<?php foreach ( $peep_ranges as $peep_range => $peep_range_label ) : ?>
						<label class="peep-seg-btn">
							<input type="radio" class="peep-sr" name="range" value="<?php echo esc_attr( $peep_range ); ?>" <?php checked( '30', $peep_range ); ?>>
							<span><?php echo esc_html( $peep_range_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="peep-range-dates" id="peep-range-dates">
				<?php foreach ( array( 'from', 'to' ) as $peep_end ) : ?>
					<?php if ( 'to' === $peep_end ) : ?>
						<?php View::icon( 'arrow', 'peep-range-arrow' ); ?>
					<?php endif; ?>
					<label class="peep-date">
						<?php View::icon( 'calendar' ); ?>
						<span><?php echo esc_html( 'from' === $peep_end ? __( 'From', 'product-expiration-easy-peasy' ) : __( 'To', 'product-expiration-easy-peasy' ) ); ?></span>
						<input type="text" name="<?php echo esc_attr( $peep_end ); ?>" id="<?php echo esc_attr( 'peep-export-' . $peep_end ); ?>" value="<?php echo esc_attr( $peep_export[ $peep_end ] ); ?>" data-peep-datepicker data-calendar="<?php echo esc_attr( $peep_export['picker'] ); ?>" autocomplete="off" spellcheck="false" dir="ltr">
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<h2 class="peep-panel-title"><?php esc_html_e( 'Output file settings', 'product-expiration-easy-peasy' ); ?></h2>

		<div class="peep-file-row">
			<fieldset class="peep-field">
				<legend class="peep-label"><?php esc_html_e( 'File format', 'product-expiration-easy-peasy' ); ?></legend>
				<div class="peep-cards peep-cards--2">
					<?php foreach ( $peep_formats as $peep_format => $peep_format_text ) : ?>
						<?php if ( 'xlsx' !== $peep_format || $peep_export['xlsx'] ) : ?>
							<label class="peep-choice">
								<span class="peep-choice-head">
									<input type="radio" class="peep-sr" name="format" value="<?php echo esc_attr( $peep_format ); ?>" <?php checked( $peep_export['format'], $peep_format ); ?>>
									<span class="peep-choice-t"><?php View::icon( 'file' ); ?><?php echo esc_html( $peep_format_text[0] ); ?></span>
									<span class="peep-dot"><span class="peep-dot-in"></span></span>
								</span>
								<span class="peep-choice-sub"><?php echo esc_html( $peep_format_text[1] ); ?></span>
							</label>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<?php if ( ! $peep_export['xlsx'] ) : ?>
					<p class="peep-note"><?php esc_html_e( 'Excel export needs the PHP Zip extension, so only CSV is available.', 'product-expiration-easy-peasy' ); ?></p>
				<?php endif; ?>
			</fieldset>

			<fieldset class="peep-field">
				<legend class="peep-label"><?php esc_html_e( 'Dates in the file', 'product-expiration-easy-peasy' ); ?></legend>
				<div class="peep-seg">
					<?php
					foreach ( array(
						'jalali'    => __( 'Jalali', 'product-expiration-easy-peasy' ),
						'gregorian' => __( 'Gregorian', 'product-expiration-easy-peasy' ),
					) as $peep_calendar => $peep_calendar_label ) :
						?>
						<label class="peep-seg-btn">
							<input type="radio" class="peep-sr" name="calendar" value="<?php echo esc_attr( $peep_calendar ); ?>" <?php checked( $peep_export['calendar'], $peep_calendar ); ?>>
							<span><?php echo esc_html( $peep_calendar_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		</div>

		<fieldset class="peep-field">
			<legend class="peep-label">
				<?php esc_html_e( 'File columns', 'product-expiration-easy-peasy' ); ?>
				<?php View::tip( 'peep-tip-columns', __( 'Drag the columns to change their order. The product name is always included in the file.', 'product-expiration-easy-peasy' ) ); ?>
			</legend>
			<div class="peep-cols" id="peep-cols" aria-describedby="peep-tip-columns">
				<?php foreach ( $peep_export['labels'] as $peep_column => $peep_column_label ) : ?>
					<label class="peep-pill" data-peep-column="<?php echo esc_attr( $peep_column ); ?>">
						<span class="peep-grip" title="<?php esc_attr_e( 'Drag to move', 'product-expiration-easy-peasy' ); ?>"><?php View::icon( 'grip' ); ?></span>
						<?php if ( 'name' === $peep_column ) : ?>
							<input type="hidden" name="columns[]" value="name">
							<input type="checkbox" checked disabled>
						<?php else : ?>
							<input type="checkbox" name="columns[]" value="<?php echo esc_attr( $peep_column ); ?>" <?php checked( in_array( $peep_column, $peep_export['columns'], true ) ); ?>>
						<?php endif; ?>
						<span><?php echo esc_html( $peep_column_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<div class="peep-export-actions">
			<button type="submit" class="peep-btn" id="peep-export-submit">
				<?php View::icon( 'download', 'peep-export-icon' ); ?>
				<?php View::icon( 'spinner', 'peep-spin peep-export-spinner' ); ?>
				<span><?php echo esc_html( 'xlsx' === $peep_export['format'] ? __( 'Download Excel file', 'product-expiration-easy-peasy' ) : __( 'Download CSV file', 'product-expiration-easy-peasy' ) ); ?></span>
			</button>
			<button type="button" class="peep-btn-text peep-js-only" id="peep-export-preview" aria-haspopup="dialog"><?php View::icon( 'eye' ); ?><?php esc_html_e( 'File preview', 'product-expiration-easy-peasy' ); ?></button>
			<p class="peep-export-status" id="peep-export-status" role="status"></p>
		</div>
	</section>
</form>
