<?php
/**
 * Call-to-action box. Override by copying to your-theme/pulsegrid/sidebar-cta.php.
 *
 * @var array $args See library.php.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

$pgcc_s = $args['settings'];
?>
<div class="pgcc-cta pgcc-cta-primary">
	<h3><?php echo esc_html( $pgcc_s['cta_title'] ); ?></h3>
	<?php if ( $pgcc_s['cta_text'] ) : ?>
		<p><?php echo esc_html( $pgcc_s['cta_text'] ); ?></p>
	<?php endif; ?>
	<?php if ( $pgcc_s['cta_url'] ) : ?>
		<a class="pgcc-button wp-element-button" href="<?php echo esc_url( $pgcc_s['cta_url'] ); ?>"><?php echo esc_html( $pgcc_s['cta_button'] ); ?></a>
	<?php endif; ?>
</div>
