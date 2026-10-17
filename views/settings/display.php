<?php
/**
 * Settings tab: how the date appears to customers.
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

$peep_positions = array(
	'after_price' => __( 'After price', 'product-expiration-easy-peasy' ),
	'after_title' => __( 'After title', 'product-expiration-easy-peasy' ),
);
$peep_shown     = 'yes' === $current['show_expiration'];
?>
<section class="peep-panel" id="peep-panel-display" aria-labelledby="peep-panel-display-title">
	<h2 class="peep-panel-title" id="peep-panel-display-title"><?php esc_html_e( 'Display settings', 'product-expiration-easy-peasy' ); ?></h2>

	<?php
	$peep_key   = 'show_expiration';
	$peep_label = __( 'Show the expiration date on the product page', 'product-expiration-easy-peasy' );
	$peep_hint  = __( 'When off, the date is only visible in the dashboard.', 'product-expiration-easy-peasy' );
	require __DIR__ . '/switch-row.php';
	?>

	<div class="peep-display">
		<div class="peep-display-fields">
			<div class="peep-field">
				<div class="peep-label">
					<label for="display_text"><?php esc_html_e( 'Text beside the date', 'product-expiration-easy-peasy' ); ?></label>
					<?php
					/* translators: Keep %date% unchanged; it is the placeholder the setting uses. */
					View::tip( 'peep-tip-text', __( 'When displayed, %date% is replaced with the expiration date, and you can place it anywhere in the text.', 'product-expiration-easy-peasy' ) );
					?>
				</div>
				<input type="text" class="peep-input" id="display_text" name="display_text" value="<?php echo esc_attr( $current['display_text'] ); ?>" aria-describedby="peep-tip-text">
			</div>

			<fieldset class="peep-field">
				<legend class="peep-label"><?php esc_html_e( 'Date format', 'product-expiration-easy-peasy' ); ?></legend>
				<div class="peep-cards peep-cards--formats">
					<?php foreach ( $view['samples'] as $peep_format => $peep_sample ) : ?>
						<label class="peep-choice peep-fmt">
							<input type="radio" class="peep-sr" name="date_format" value="<?php echo esc_attr( $peep_format ); ?>" data-sample="<?php echo esc_attr( $peep_sample ); ?>" <?php checked( $current['date_format'], $peep_format ); ?>>
							<span class="peep-fmt-sample"><?php echo esc_html( $peep_sample ); ?></span>
							<?php if ( 'default' === $peep_format ) : ?>
								<span class="peep-fmt-code"><?php esc_html_e( 'WordPress default', 'product-expiration-easy-peasy' ); ?></span>
							<?php else : ?>
								<span class="peep-fmt-code" dir="ltr"><?php echo esc_html( $peep_format ); ?></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<fieldset class="peep-field">
				<legend class="peep-label"><?php esc_html_e( 'Date position on the product page', 'product-expiration-easy-peasy' ); ?></legend>
				<div class="peep-seg">
					<?php foreach ( $peep_positions as $peep_position => $peep_position_label ) : ?>
						<label class="peep-seg-btn">
							<input type="radio" class="peep-sr" name="display_position" value="<?php echo esc_attr( $peep_position ); ?>" <?php checked( $current['display_position'], $peep_position ); ?>>
							<span><?php echo esc_html( $peep_position_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<?php
			$peep_key   = 'show_in_order_email';
			$peep_label = __( 'Date in order emails', 'product-expiration-easy-peasy' );
			$peep_hint  = __( 'Show each product\'s expiration date in order emails.', 'product-expiration-easy-peasy' );
			require __DIR__ . '/switch-row.php';
			?>
		</div>

		<div class="peep-preview">
			<p class="peep-preview-label"><?php esc_html_e( 'Product page preview', 'product-expiration-easy-peasy' ); ?></p>
			<div class="peep-preview-card" id="peep-preview" data-parts="<?php echo esc_attr( wp_json_encode( $view['date_parts'] ) ); ?>" aria-live="polite">
				<span class="peep-preview-img"><?php View::icon( 'image' ); ?></span>
				<p class="peep-preview-title"><?php esc_html_e( 'Product title', 'product-expiration-easy-peasy' ); ?></p>
				<p class="peep-badge" data-peep-position="after_title"<?php echo $peep_shown && 'after_title' === $current['display_position'] ? '' : ' hidden'; ?>><?php echo esc_html( $view['preview_text'] ); ?></p>
				<p class="peep-preview-price"><?php echo wp_kses_post( $view['price'] ); ?></p>
				<p class="peep-badge" data-peep-position="after_price"<?php echo $peep_shown && 'after_price' === $current['display_position'] ? '' : ' hidden'; ?>><?php echo esc_html( $view['preview_text'] ); ?></p>
				<p class="peep-preview-off" id="peep-preview-off"<?php echo $peep_shown ? ' hidden' : ''; ?>><?php esc_html_e( 'The expiration date is not shown to customers.', 'product-expiration-easy-peasy' ); ?></p>
			</div>
		</div>
	</div>
</section>
