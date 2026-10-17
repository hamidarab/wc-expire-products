<?php
/**
 * Dialogs opened by peep-settings.js.
 *
 * @package ProductExpirationEasyPeasy
 *
 * @var array<string, mixed> $view Precomputed display data.
 */

use ProductExpirationEasyPeasy\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$peep_bullets = array(
	sprintf(
		/* translators: %s: cutoff date. */
		esc_html__( 'Any product that expires by %s goes out of stock.', 'product-expiration-easy-peasy' ),
		'<strong class="peep-strong">' . esc_html( $view['cutoff_label'] ) . '</strong>'
	),
	esc_html__( 'Only in-stock and backorder products change; products that were already out of stock stay as they are.', 'product-expiration-easy-peasy' ),
	esc_html__( 'Each product\'s previous status is kept, so it comes back in stock if you extend its date.', 'product-expiration-easy-peasy' ),
	esc_html__( 'The list of products that go out of stock is emailed to the report recipients.', 'product-expiration-easy-peasy' ),
);
?>
<dialog class="peep-modal" id="peep-check-modal" aria-labelledby="peep-check-title">
	<div class="peep-modal-head">
		<span class="peep-modal-tile"><?php View::icon( 'calendar-check' ); ?></span>
		<div>
			<h2 class="peep-modal-title" id="peep-check-title"><?php esc_html_e( 'Check product dates', 'product-expiration-easy-peasy' ); ?></h2>
			<p class="peep-modal-sub" id="peep-check-sub"></p>
		</div>
	</div>

	<div class="peep-modal-body" data-peep-state="confirm">
		<p><?php esc_html_e( 'The plugin checks the expiration date of every product and variation right now; the same check runs automatically every day.', 'product-expiration-easy-peasy' ); ?></p>
		<ul class="peep-checklist">
			<?php foreach ( $peep_bullets as $peep_bullet ) : ?>
				<li><?php View::icon( 'tick' ); ?><span><?php echo wp_kses( $peep_bullet, array( 'strong' => array( 'class' => true ) ) ); ?></span></li>
			<?php endforeach; ?>
		</ul>
		<div class="peep-box peep-box--info">
			<?php View::icon( 'info', 'peep-box-ico' ); ?>
			<p class="peep-muted"><?php esc_html_e( 'You can close this window; the check continues in the background and you can cancel it at any time.', 'product-expiration-easy-peasy' ); ?></p>
		</div>
		<div class="peep-modal-actions">
			<button type="button" class="peep-btn-soft" data-peep-close><?php esc_html_e( 'Close', 'product-expiration-easy-peasy' ); ?></button>
			<button type="button" class="peep-btn" data-peep-start><?php esc_html_e( 'Confirm and start the check', 'product-expiration-easy-peasy' ); ?></button>
		</div>
	</div>

	<div class="peep-modal-body" data-peep-state="running" hidden>
		<div class="peep-progress-head">
			<span id="peep-check-count"></span>
			<span class="peep-progress-pct" id="peep-check-pct" aria-hidden="true"></span>
		</div>
		<div class="peep-progress" role="progressbar" aria-labelledby="peep-check-count" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
			<span class="peep-progress-fill" id="peep-check-fill"></span>
		</div>
		<p class="peep-note" id="peep-check-note"></p>
		<div class="peep-modal-actions">
			<button type="button" class="peep-btn-soft" data-peep-close><?php esc_html_e( 'Close', 'product-expiration-easy-peasy' ); ?></button>
			<button type="button" class="peep-btn-soft-danger" data-peep-cancel><?php esc_html_e( 'Cancel check', 'product-expiration-easy-peasy' ); ?></button>
		</div>
	</div>

	<div class="peep-modal-body" data-peep-state="done" hidden>
		<div class="peep-box peep-box--ok">
			<?php View::icon( 'check', 'peep-box-ico' ); ?>
			<div>
				<strong class="peep-box-title"><?php esc_html_e( 'Check finished', 'product-expiration-easy-peasy' ); ?></strong>
				<p id="peep-check-done"></p>
			</div>
		</div>
		<div class="peep-modal-actions">
			<button type="button" class="peep-btn" data-peep-close><?php esc_html_e( 'Close', 'product-expiration-easy-peasy' ); ?></button>
		</div>
	</div>

	<div class="peep-modal-body" data-peep-state="cancelled" hidden>
		<div class="peep-box peep-box--warn">
			<?php View::icon( 'x', 'peep-box-ico' ); ?>
			<div>
				<strong class="peep-box-title"><?php esc_html_e( 'Check cancelled', 'product-expiration-easy-peasy' ); ?></strong>
				<p id="peep-check-cancelled"></p>
			</div>
		</div>
		<div class="peep-modal-actions">
			<button type="button" class="peep-btn-soft" data-peep-close><?php esc_html_e( 'Close', 'product-expiration-easy-peasy' ); ?></button>
			<button type="button" class="peep-btn" data-peep-start><?php esc_html_e( 'Start again', 'product-expiration-easy-peasy' ); ?></button>
		</div>
	</div>

	<p class="peep-error" id="peep-check-error" role="alert" hidden><?php View::icon( 'x' ); ?><span></span></p>
</dialog>

<dialog class="peep-modal peep-modal--wide" id="peep-preview-modal" aria-labelledby="peep-preview-title">
	<div class="peep-modal-head">
		<div>
			<h2 class="peep-modal-title" id="peep-preview-title"><?php esc_html_e( 'File preview', 'product-expiration-easy-peasy' ); ?></h2>
			<p class="peep-modal-sub" id="peep-preview-count"></p>
		</div>
		<button type="button" class="peep-modal-x" data-peep-close aria-label="<?php esc_attr_e( 'Close', 'product-expiration-easy-peasy' ); ?>"><?php View::icon( 'close' ); ?></button>
	</div>
	<div class="peep-table-wrap" id="peep-preview-table">
		<table class="peep-table">
			<thead></thead>
			<tbody></tbody>
		</table>
	</div>
	<p class="peep-note" id="peep-preview-empty" hidden><?php esc_html_e( 'No products found in this range.', 'product-expiration-easy-peasy' ); ?></p>
	<div class="peep-modal-actions">
		<button type="button" class="peep-btn-soft" data-peep-close><?php esc_html_e( 'Close', 'product-expiration-easy-peasy' ); ?></button>
	</div>
</dialog>
