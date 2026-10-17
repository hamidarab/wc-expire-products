<?php
/**
 * Settings tab: how to enter dates, and the system status.
 *
 * @package ProductExpirationEasyPeasy
 *
 * @var array<string, mixed> $view Precomputed display data.
 */

use ProductExpirationEasyPeasy\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$peep_steps = array(
	array( __( 'Simple product', 'product-expiration-easy-peasy' ), __( 'Open the product and fill in the "Expiration date" field under "Product data › General".', 'product-expiration-easy-peasy' ) ),
	array( __( 'Variable product', 'product-expiration-easy-peasy' ), __( 'Under "Product data › Variations", open each variation and enter its own expiration date.', 'product-expiration-easy-peasy' ) ),
	array( __( 'Quick Edit', 'product-expiration-easy-peasy' ), __( 'In the product list, click "Quick Edit" and change the date right there.', 'product-expiration-easy-peasy' ) ),
	array( __( 'Manual entry', 'product-expiration-easy-peasy' ), __( 'Enter the date like 1405-01-01. Persian, Arabic and Latin digits are accepted, and the separator can be a hyphen or a slash.', 'product-expiration-easy-peasy' ) ),
);
?>
<section class="peep-panel" id="peep-panel-help" aria-labelledby="peep-panel-help-title">
	<h2 class="peep-panel-title" id="peep-panel-help-title"><?php esc_html_e( 'Help & status', 'product-expiration-easy-peasy' ); ?></h2>
	<p class="peep-panel-intro"><?php esc_html_e( 'How to enter expiration dates, and whether the plugin\'s requirements are working.', 'product-expiration-easy-peasy' ); ?></p>

	<ol class="peep-steps">
		<?php foreach ( $peep_steps as $peep_index => $peep_step ) : ?>
			<li class="peep-step-card">
				<span class="peep-num" aria-hidden="true"><?php echo esc_html( View::number( $peep_index + 1 ) ); ?></span>
				<div>
					<h3 class="peep-step-t"><?php echo esc_html( $peep_step[0] ); ?></h3>
					<p class="peep-muted"><?php echo esc_html( $peep_step[1] ); ?></p>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>

	<div>
		<h3 class="peep-subtitle"><?php esc_html_e( 'System status', 'product-expiration-easy-peasy' ); ?></h3>
		<ul class="peep-sys">
			<?php foreach ( $view['status_rows'] as $peep_row ) : ?>
				<li>
					<span><?php echo esc_html( $peep_row[0] ); ?></span>
					<span class="peep-meta<?php echo true === $peep_row[2] ? ' is-ok' : ''; ?><?php echo false === $peep_row[2] ? ' is-problem' : ''; ?>">
						<?php
						if ( null !== $peep_row[2] ) {
							View::icon( $peep_row[2] ? 'tick' : 'alert' );
						}
						?>
						<span dir="auto"><?php echo esc_html( $peep_row[1] ); ?></span>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
