<?php
/**
 * Beaver Builder module frontend template for DrillNav.
 *
 * $module  – the FLBuilderModule instance; $settings – the saved field values.
 *
 * @package DrillNav
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'drillnav' ) ) {
	return;
}

$drillnav_args = array(
	'depth'        => absint( $settings->depth ?? 0 ),
	'show_home'    => ! empty( $settings->show_home ),
	'layout'       => sanitize_key( $settings->layout ?? 'list' ),
	'animation'    => sanitize_key( $settings->animation ?? 'slide' ),
	'color_scheme' => sanitize_key( $settings->color_scheme ?? 'default' ),
	'style_preset' => sanitize_key( $settings->style_preset ?? 'default' ),
);

echo drillnav()->render_navigation( $drillnav_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
