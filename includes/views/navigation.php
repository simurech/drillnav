<?php
/**
 * Frontend navigation template.
 *
 * Available variables (set by Shortcode / Widget / Block render_callback):
 *   $nav_data  array   Full nav data from Navigator::get_nav_data()
 *   $instance  string  Unique ID for this navigation instance (for ARIA controls)
 *
 * @package DrillNav
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- intentional template scope.
defined( 'ABSPATH' ) || exit;

if ( empty( $nav_data ) || empty( $nav_data['current_level'] ) ) {
	return;
}

$mobile_toggle   = ! empty( $mobile_toggle ?? false );
$settings        = $nav_data['settings'] ?? array();
$show_back       = ! empty( $settings['show_back'] );
$animation       = (string) ( $settings['animation'] ?? 'slide' );
$color_scheme    = (string) ( $settings['color_scheme'] ?? 'default' );
$ancestors       = $nav_data['ancestors'] ?? array();
$current_level   = $nav_data['current_level'] ?? array();
$current_post_id = (int) ( $nav_data['post_id'] ?? 0 );

// Back target: immediate parent ancestor, or Home.
$back_item = ! empty( $ancestors ) ? $ancestors[ count( $ancestors ) - 1 ] : null;

$nav_label = ! empty( $settings['nav_label'] )
	? (string) $settings['nav_label']
	/* translators: ARIA label for the contextual page navigation landmark. */
	: __( 'Page navigation', 'drillnav-drilldown-navigation' );

// Style preset – Cards requires Pro; fall back to default silently.
$style_preset  = (string) ( $settings['style_preset'] ?? 'default' );
$pro_presets   = array( 'cards' );
$is_pro_active = function_exists( 'drillnav_fs' ) && drillnav_fs()->can_use_premium_code__premium_only();
if ( in_array( $style_preset, $pro_presets, true ) && ! $is_pro_active ) {
	$style_preset = 'default';
}

// Build nav element CSS classes.
$nav_classes = array( 'drillnav' );
if ( 'default' !== $color_scheme ) {
	$nav_classes[] = 'drillnav--scheme-' . $color_scheme;
}
if ( 'default' !== $style_preset ) {
	$nav_classes[] = 'drillnav--preset-' . esc_attr( $style_preset );
}
if ( $mobile_toggle ) {
	$nav_classes[] = 'drillnav--drawer-nav';
}

// Build inline style from CSS custom properties.
$css_prop_map = array(
	'max_width'              => '--drillnav-max-width',
	'custom_font_size'       => '--drillnav-font-size',
	'custom_padding_y'       => '--drillnav-item-padding-y',
	'custom_padding_x'       => '--drillnav-item-padding-x',
	'custom_border_radius'   => '--drillnav-border-radius',
	'custom_transition_speed' => '--drillnav-transition-speed',
	'custom_color_link'      => '--drillnav-color-link',
	'custom_color_current_bg' => '--drillnav-color-current-bg',
	'custom_color_hover'     => '--drillnav-color-btn-hover',
	'custom_color_arrow'     => '--drillnav-color-arrow',
);
$style_parts = array();
foreach ( $css_prop_map as $setting_key => $css_var ) {
	$val = (string) ( $settings[ $setting_key ] ?? '' );
	if ( '' !== $val ) {
		$style_parts[] = $css_var . ': ' . esc_attr( $val );
	}
}
$nav_style_attr = $style_parts ? ' style="' . implode( '; ', $style_parts ) . '"' : '';

// Embed the full nav data as JSON for the JS layer.
$json_data = wp_json_encode(
	array(
		'postId'       => $current_post_id,
		'ancestors'    => $ancestors,
		'currentLevel' => $current_level,
		'settings'     => $settings,
	)
);
?>
<?php if ( $mobile_toggle ) : ?>
<div class="drillnav-drawer-wrap" data-drillnav-drawer-wrap>
	<button
		type="button"
		class="drillnav-toggle-btn"
		data-drillnav-toggle
		aria-expanded="false"
		aria-controls="<?php echo esc_attr( $instance ); ?>"
		aria-label="<?php esc_attr_e( 'Open navigation menu', 'drillnav-drilldown-navigation' ); ?>"
	>
		<span class="drillnav-hamburger" aria-hidden="true">
			<span></span>
			<span></span>
			<span></span>
		</span>
	</button>
	<div class="drillnav-backdrop" data-drillnav-backdrop aria-hidden="true"></div>
<?php endif; ?>
<nav
	class="<?php echo esc_attr( implode( ' ', $nav_classes ) ); ?>"
	role="navigation"
	aria-label="<?php echo esc_attr( $nav_label ); ?>"
	data-drillnav-instance="<?php echo esc_attr( $instance ); ?>"
	data-drillnav-animation="<?php echo esc_attr( $animation ); ?>"
	<?php echo $nav_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above via esc_attr. ?>
>
	<?php // Hidden JSON data payload – read by frontend.js. ?>
	<script type="application/json" id="<?php echo esc_attr( $instance ); ?>-data">
		<?php echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output is safe. ?>
	</script>

	<?php if ( $show_back && $back_item ) : ?>
	<div class="drillnav__back-wrap">
		<a
			href="<?php echo esc_url( $back_item['url'] ); ?>"
			class="drillnav__back-btn"
			data-drillnav-back
			aria-label="<?php
				/* translators: %s: parent page title */
				echo esc_attr( sprintf( __( 'Back to %s', 'drillnav-drilldown-navigation' ), $back_item['title'] ) );
			?>"
		>
			<span class="drillnav__back-arrow" aria-hidden="true">&#8592;</span>
			<span class="drillnav__back-label"><?php echo esc_html( $back_item['title'] ); ?></span>
		</a>
	</div>
	<?php endif; ?>

	<div
		class="drillnav__panel"
		id="<?php echo esc_attr( $instance ); ?>-panel"
		role="region"
		aria-live="polite"
		aria-relevant="additions removals"
	>
		<ul class="drillnav__list" role="list">
			<?php foreach ( $current_level as $item ) : ?>
			<?php
			$item_id      = (int) $item['id'];
			$is_current   = (bool) $item['is_current'];
			$has_children = (bool) $item['has_children'];
			$item_title   = (string) $item['title'];
			$item_url     = (string) $item['url'];

			$li_classes = array( 'drillnav__item' );
			if ( $is_current ) {
				$li_classes[] = 'drillnav__item--current';
			}
			if ( $has_children ) {
				$li_classes[] = 'drillnav__item--has-children';
			}
			?>
			<li class="<?php echo esc_attr( implode( ' ', $li_classes ) ); ?>" role="listitem">
				<div class="drillnav__row">
					<a
						href="<?php echo esc_url( $item_url ); ?>"
						class="drillnav__link"
						<?php if ( $is_current ) : ?>
						aria-current="page"
						<?php endif; ?>
					>
						<?php echo esc_html( $item_title ); ?>
					</a>
					<?php if ( $has_children ) : ?>
					<button
						type="button"
						class="drillnav__expand-btn"
						aria-label="<?php
							/* translators: %s: page title */
							echo esc_attr( sprintf( __( 'Show sub-pages of %s', 'drillnav-drilldown-navigation' ), $item_title ) );
						?>"
						data-drillnav-item-id="<?php echo esc_attr( (string) $item_id ); ?>"
						data-drillnav-item-type="<?php echo esc_attr( (string) ( $item['post_type'] ?? 'page' ) ); ?>"
					>
						<span class="drillnav__arrow" aria-hidden="true">&#8250;</span>
					</button>
					<?php endif; ?>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
</nav>
<?php if ( $mobile_toggle ) : ?>
</div>
<?php endif; ?>
