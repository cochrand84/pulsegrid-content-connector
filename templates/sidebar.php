<?php
/**
 * Sidebar on search and article views. Override by copying to
 * your-theme/pulsegrid/sidebar.php.
 *
 * - sidebar="cta": the call to action (sidebar-cta.php).
 * - Other modes come from add-ons, which render them on the
 *   `pgcc_sidebar_{mode}` action. A mode with nothing hooked shows the
 *   call to action.
 *
 * @var array $args See library.php.
 *
 * @package PulsegridContentConnector
 */

defined( 'ABSPATH' ) || exit;

$pgcc_mode = sanitize_key( $args['sidebar'] );
if ( 'cta' !== $pgcc_mode && has_action( 'pgcc_sidebar_' . $pgcc_mode ) ) {
	/**
	 * Render an add-on sidebar mode: pgcc_sidebar_{mode}.
	 *
	 * @param array $args View arguments.
	 */
	do_action( 'pgcc_sidebar_' . $pgcc_mode, $args );
} else {
	pgcc_template( 'sidebar-cta', $args );
}
?>
<p class="pgcc-disclaimer"><?php esc_html_e( 'This health information is for educational purposes only and is not a substitute for professional medical advice.', 'pulsegrid-content-connector' ); ?></p>
