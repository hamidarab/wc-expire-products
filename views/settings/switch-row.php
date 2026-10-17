<?php
/**
 * A switch for a yes/no setting, with its label and tooltip.
 *
 * @package ProductExpirationEasyPeasy
 *
 * @var array<string, mixed> $current     Validated settings.
 * @var string               $peep_key    Setting key.
 * @var string               $peep_label  Label.
 * @var string               $peep_hint   Tooltip text.
 */

use ProductExpirationEasyPeasy\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="peep-switch-row">
	<input type="hidden" name="<?php echo esc_attr( $peep_key ); ?>" value="no">
	<input type="checkbox" class="peep-sr peep-switch-input" id="<?php echo esc_attr( $peep_key ); ?>" name="<?php echo esc_attr( $peep_key ); ?>" value="yes" role="switch" aria-describedby="<?php echo esc_attr( 'peep-tip-' . $peep_key ); ?>" <?php checked( $current[ $peep_key ], 'yes' ); ?>>
	<label class="peep-switch-label" for="<?php echo esc_attr( $peep_key ); ?>"><?php echo esc_html( $peep_label ); ?></label>
	<?php View::tip( 'peep-tip-' . $peep_key, $peep_hint ); ?>
	<label class="peep-switch" for="<?php echo esc_attr( $peep_key ); ?>" aria-hidden="true"><span class="peep-knob"></span></label>
</div>
