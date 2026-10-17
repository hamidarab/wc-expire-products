<?php
/**
 * Settings page.
 *
 * Without JavaScript every panel shows, stacked, and every form still
 * submits; peep-settings.js turns the navigation into tabs.
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

$peep_tabs = array(
	'general'       => array( 'zap', __( 'General settings', 'product-expiration-easy-peasy' ), __( 'Out-of-stock timing and calendar', 'product-expiration-easy-peasy' ) ),
	'display'       => array( 'eye', __( 'Display settings', 'product-expiration-easy-peasy' ), __( 'Text, format and position of the date', 'product-expiration-easy-peasy' ) ),
	'notifications' => array( 'mail', __( 'Notifications', 'product-expiration-easy-peasy' ), __( 'Daily report recipients', 'product-expiration-easy-peasy' ) ),
	'export'        => array( 'download', __( 'Export', 'product-expiration-easy-peasy' ), __( 'Excel and CSV output', 'product-expiration-easy-peasy' ) ),
	'help'          => array( 'help', __( 'Help & status', 'product-expiration-easy-peasy' ), __( 'Entering dates and requirements', 'product-expiration-easy-peasy' ) ),
);
?>
<div class="wrap peep-settings" id="peep-settings">
	<header class="peep-head">
		<div class="peep-brand">
			<?php View::icon( 'logo' ); ?>
			<div>
				<h1 class="peep-title"><?php esc_html_e( 'Enqezayar settings', 'product-expiration-easy-peasy' ); ?></h1>
				<span class="peep-chip">
					<?php
					/* translators: %s: plugin version number. */
					printf( esc_html__( 'Version %s', 'product-expiration-easy-peasy' ), esc_html( $view['version'] ) );
					?>
				</span>
			</div>
		</div>

		<div class="peep-head-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="peep-check-form">
				<input type="hidden" name="action" value="peep_run_check">
				<input type="hidden" name="peep_tab" value="general" class="peep-tab-input">
				<?php wp_nonce_field( 'peep_run_check', 'peep_run_check_nonce' ); ?>
				<button type="submit" class="peep-btn" id="peep-check-open" aria-haspopup="dialog">
					<?php View::icon( 'refresh', 'peep-check-icon' ); ?>
					<?php View::icon( 'spinner', 'peep-spin peep-check-spinner' ); ?>
					<span class="peep-check-label"><?php esc_html_e( 'Check product dates', 'product-expiration-easy-peasy' ); ?></span>
				</button>
			</form>
			<button type="button" class="peep-icon-danger peep-js-only" id="peep-check-x" aria-label="<?php esc_attr_e( 'Cancel check', 'product-expiration-easy-peasy' ); ?>" hidden><?php View::icon( 'close' ); ?></button>
		</div>
	</header>
	<hr class="wp-header-end">

	<?php if ( $view['notice'] ) : ?>
		<div class="notice notice-<?php echo esc_attr( $view['notice'][0] ); ?> is-dismissible"><p><?php echo esc_html( $view['notice'][1] ); ?></p></div>
	<?php endif; ?>

	<section class="peep-stats" aria-label="<?php esc_attr_e( 'Status overview', 'product-expiration-easy-peasy' ); ?>">
		<div class="peep-stat">
			<div class="peep-stat-row">
				<span class="peep-tile"><?php View::icon( 'tag' ); ?></span>
				<span class="peep-stat-value"><?php echo esc_html( View::number( $view['counts']['dated'] ) ); ?></span>
			</div>
			<p class="peep-stat-label"><?php esc_html_e( 'Products and variations with a date', 'product-expiration-easy-peasy' ); ?></p>
		</div>
		<div class="peep-stat">
			<div class="peep-stat-row">
				<span class="peep-tile peep-tile--danger"><?php View::icon( 'alert' ); ?></span>
				<span class="peep-stat-value peep-stat-value--danger"><?php echo esc_html( View::number( $view['counts']['expired'] ) ); ?></span>
				<a class="peep-btn-danger" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&expiration_filter=expired' ) ); ?>"><?php esc_html_e( 'Expired list', 'product-expiration-easy-peasy' ); ?></a>
			</div>
			<p class="peep-stat-label"><?php esc_html_e( 'Expired', 'product-expiration-easy-peasy' ); ?></p>
		</div>
		<div class="peep-stat">
			<div class="peep-stat-row">
				<span class="peep-tile peep-tile--accent"><?php View::icon( 'box' ); ?></span>
				<span class="peep-stat-value peep-stat-value--accent" id="peep-stat-managed"><?php echo esc_html( View::number( $view['counts']['managed'] ) ); ?></span>
			</div>
			<p class="peep-stat-label"><?php esc_html_e( 'Set out of stock by the plugin', 'product-expiration-easy-peasy' ); ?></p>
		</div>
		<div class="peep-stat">
			<div class="peep-stat-row">
				<span class="peep-tile peep-tile--info"><?php View::icon( 'clock' ); ?></span>
				<span class="peep-stat-value peep-stat-value--text"><?php echo esc_html( $view['next_run'] ? $view['next_run']['date'] : __( 'Not scheduled', 'product-expiration-easy-peasy' ) ); ?></span>
			</div>
			<p class="peep-stat-label" id="peep-stat-next" aria-live="polite"><?php echo esc_html( $view['next_run'] ? $view['next_run']['diff'] : '' ); ?></p>
		</div>
	</section>

	<div class="peep-layout">
		<nav class="peep-nav-card" aria-label="<?php esc_attr_e( 'Settings sections', 'product-expiration-easy-peasy' ); ?>">
			<div class="peep-nav-list">
				<?php foreach ( $peep_tabs as $peep_key => $peep_tab ) : ?>
					<a class="peep-nav" id="<?php echo esc_attr( 'peep-tab-' . $peep_key ); ?>" href="<?php echo esc_attr( '#peep-panel-' . $peep_key ); ?>" data-peep-tab="<?php echo esc_attr( $peep_key ); ?>">
						<span class="peep-nav-ico"><?php View::icon( $peep_tab[0] ); ?></span>
						<span>
							<span class="peep-nav-t"><?php echo esc_html( $peep_tab[1] ); ?></span>
							<span class="peep-nav-h"><?php echo esc_html( $peep_tab[2] ); ?></span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</nav>

		<div class="peep-main">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="peep-settings-form">
				<input type="hidden" name="action" value="peep_save_settings">
				<input type="hidden" name="peep_tab" value="general" class="peep-tab-input">
				<?php wp_nonce_field( 'peep_save_settings', 'peep_settings_nonce' ); ?>

				<?php
				require __DIR__ . '/general.php';
				require __DIR__ . '/display.php';
				require __DIR__ . '/notifications.php';
				?>

				<div class="peep-savebar" id="peep-savebar">
					<p class="peep-save-status" id="peep-save-status" role="status"><?php esc_html_e( 'Settings saved', 'product-expiration-easy-peasy' ); ?></p>
					<button type="submit" class="peep-btn" id="peep-save"><?php esc_html_e( 'Save changes', 'product-expiration-easy-peasy' ); ?></button>
				</div>
			</form>

			<?php
			require __DIR__ . '/export.php';
			require __DIR__ . '/help.php';
			?>
		</div>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="peep-test-email-form" hidden>
		<input type="hidden" name="action" value="peep_send_test_email">
		<input type="hidden" name="peep_tab" value="notifications">
		<?php wp_nonce_field( 'peep_send_test_email', 'peep_test_email_nonce' ); ?>
	</form>

	<?php require __DIR__ . '/modals.php'; ?>
</div>
