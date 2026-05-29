<?php
/**
 * DrillNav settings page template.
 *
 * @package DrillNav
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- intentional template scope.
defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$is_pro_active = function_exists( 'drillnav_fs' ) && drillnav_fs()->is__premium_only();

$upgrade_url = '';
if ( function_exists( 'drillnav_fs' ) ) {
	$upgrade_url = drillnav_fs()->get_upgrade_url();
}
if ( ! is_string( $upgrade_url ) || '' === $upgrade_url ) {
	$upgrade_url = '#';
}

// Build live-preview classes + inline style from saved settings.
$preview_opts = get_option( 'drillnav_settings', \DrillNav\Settings::defaults() );
if ( ! is_array( $preview_opts ) ) {
	$preview_opts = \DrillNav\Settings::defaults();
}
$pv_scheme    = sanitize_key( (string) ( $preview_opts['color_scheme'] ?? 'default' ) );
$pv_layout    = sanitize_key( (string) ( $preview_opts['layout'] ?? 'list' ) );
$pv_preset    = sanitize_key( (string) ( $preview_opts['style_preset'] ?? 'default' ) );
$pv_show_back = ! empty( $preview_opts['show_back_button'] );
$pv_classes   = array( 'drillnav' );
if ( 'default' !== $pv_scheme ) {
	$pv_classes[] = 'drillnav--scheme-' . $pv_scheme;
}
if ( 'default' !== $pv_preset ) {
	$pv_classes[] = 'drillnav--preset-' . $pv_preset;
}
if ( 'list' !== $pv_layout ) {
	$pv_classes[] = 'drillnav--layout-' . $pv_layout;
}
$pv_css_map = array(
	'max_width'               => '--drillnav-max-width',
	'custom_font_size'        => '--drillnav-font-size',
	'custom_padding_y'        => '--drillnav-item-padding-y',
	'custom_padding_x'        => '--drillnav-item-padding-x',
	'custom_border_radius'    => '--drillnav-border-radius',
	'custom_transition_speed' => '--drillnav-transition-speed',
	'custom_color_link'       => '--drillnav-color-link',
	'custom_color_current_bg' => '--drillnav-color-current-bg',
	'custom_color_hover'      => '--drillnav-color-btn-hover',
	'custom_color_arrow'      => '--drillnav-color-arrow',
);
$pv_style_parts = array();
foreach ( $pv_css_map as $setting_key => $css_var ) {
	$val = sanitize_text_field( (string) ( $preview_opts[ $setting_key ] ?? '' ) );
	if ( '' !== $val ) {
		$pv_style_parts[] = esc_attr( $css_var ) . ':' . esc_attr( $val );
	}
}
$pv_style_attr = $pv_style_parts ? ' style="' . implode( ';', $pv_style_parts ) . '"' : '';
?>

<div class="wrap drillnav-settings-wrap">

	<h1>
		<?php esc_html_e( 'DrillNav Settings', 'drillnav-drilldown-navigation' ); ?>
		<span class="drillnav-version">v<?php echo esc_html( DRILLNAV_VERSION ); ?></span>
	</h1>

	<?php settings_errors( 'drillnav_settings_group' ); ?>

	<div class="drillnav-settings-layout">

		<div class="drillnav-settings-main">

			<nav class="nav-tab-wrapper drillnav-tab-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'drillnav-drilldown-navigation' ); ?>">
				<a href="#tab-general"    class="nav-tab" data-tab="general"><?php esc_html_e( 'General', 'drillnav-drilldown-navigation' ); ?></a>
				<a href="#tab-appearance" class="nav-tab" data-tab="appearance"><?php esc_html_e( 'Appearance & Styling', 'drillnav-drilldown-navigation' ); ?></a>
				<a href="#tab-behavior"   class="nav-tab" data-tab="behavior"><?php esc_html_e( 'Behavior', 'drillnav-drilldown-navigation' ); ?></a>
				<a href="#tab-woocommerce" class="nav-tab" data-tab="woocommerce">
					<?php esc_html_e( 'WooCommerce', 'drillnav-drilldown-navigation' ); ?>
					<span class="drillnav-pro-badge-inline">PRO</span>
				</a>
				<a href="#tab-tracking" class="nav-tab" data-tab="tracking">
					<?php esc_html_e( 'Tracking', 'drillnav-drilldown-navigation' ); ?>
					<span class="drillnav-pro-badge-inline">PRO</span>
				</a>
				<a href="#tab-advanced" class="nav-tab" data-tab="advanced"><?php esc_html_e( 'Advanced', 'drillnav-drilldown-navigation' ); ?></a>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'drillnav_settings_group' ); ?>

				<?php /* ── Tab: General ── */ ?>
				<div id="tab-general" class="drillnav-tab-panel">

					<div class="drillnav-quickstart-card">
						<strong><?php esc_html_e( 'Quick start', 'drillnav-drilldown-navigation' ); ?></strong>
						<ol>
							<li><?php esc_html_e( 'Open any page in the block editor.', 'drillnav-drilldown-navigation' ); ?></li>
							<li>
								<?php esc_html_e( 'Search for the', 'drillnav-drilldown-navigation' ); ?>
								<strong><?php esc_html_e( 'DrillNav', 'drillnav-drilldown-navigation' ); ?></strong>
								<?php esc_html_e( 'block and insert it.', 'drillnav-drilldown-navigation' ); ?>
							</li>
							<li><?php esc_html_e( 'The block automatically adapts to the current page hierarchy – no manual configuration required.', 'drillnav-drilldown-navigation' ); ?></li>
						</ol>
						<p style="margin-bottom:0"><?php esc_html_e( 'Alternatively, use the shortcode:', 'drillnav-drilldown-navigation' ); ?> <code>[drillnav]</code></p>
					</div>

					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'drillnav-drilldown-navigation', 'drillnav_general' ); ?>
					</table>

					<h2 style="margin-top:2rem"><?php esc_html_e( 'Blog & Posts', 'drillnav-drilldown-navigation' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Controls how DrillNav behaves on the blog index, category archives, tag archives, and single post pages.', 'drillnav-drilldown-navigation' ); ?></p>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'drillnav-drilldown-navigation', 'drillnav_blog' ); ?>
					</table>

					<?php submit_button( __( 'Save settings', 'drillnav-drilldown-navigation' ) ); ?>
				</div>

				<?php /* ── Tab: Appearance & Styling ── */ ?>
				<div id="tab-appearance" class="drillnav-tab-panel" hidden>

					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'drillnav-drilldown-navigation', 'drillnav_appearance' ); ?>
					</table>

					<h3>
						<?php esc_html_e( 'Customize', 'drillnav-drilldown-navigation' ); ?>
						<span class="drillnav-pro-badge-inline">PRO</span>
					</h3>
					<?php if ( $is_pro_active ) : ?>
						<p class="description"><?php esc_html_e( 'Override individual CSS custom properties globally. Values set here apply to all navigation instances unless overridden per block.', 'drillnav-drilldown-navigation' ); ?></p>
					<?php else : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Pro upgrade page */
								esc_html__( 'Granular styling options are available in %s.', 'drillnav-drilldown-navigation' ),
								'<a href="' . esc_url( $upgrade_url ) . '">' . esc_html__( 'DrillNav Pro', 'drillnav-drilldown-navigation' ) . '</a>'
							);
							?>
						</p>
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'drillnav-drilldown-navigation', 'drillnav_customize' ); ?>
					</table>

					<?php submit_button( __( 'Save settings', 'drillnav-drilldown-navigation' ) ); ?>
				</div>

				<?php /* ── Tab: Behavior ── */ ?>
				<div id="tab-behavior" class="drillnav-tab-panel" hidden>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'drillnav-drilldown-navigation', 'drillnav_behavior' ); ?>
					</table>
					<?php submit_button( __( 'Save settings', 'drillnav-drilldown-navigation' ) ); ?>
				</div>

				<?php /* ── Tab: WooCommerce (Pro) ── */ ?>
				<div id="tab-woocommerce" class="drillnav-tab-panel" hidden>
					<h3>
						<?php esc_html_e( 'WooCommerce Product Filters', 'drillnav-drilldown-navigation' ); ?>
						<span class="drillnav-pro-badge-inline">PRO</span>
					</h3>
					<?php if ( $is_pro_active ) : ?>
						<p class="description"><?php esc_html_e( 'Define attribute-based filter rules. Categories are hidden when none of their products pass the active rules.', 'drillnav-drilldown-navigation' ); ?></p>
					<?php else : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Pro upgrade page */
								esc_html__( 'Available in %s. Filter product categories by attribute value – e.g. show only categories that carry products of a specific brand.', 'drillnav-drilldown-navigation' ),
								'<a href="' . esc_url( $upgrade_url ) . '">' . esc_html__( 'DrillNav Pro', 'drillnav-drilldown-navigation' ) . '</a>'
							);
							?>
						</p>
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'drillnav-drilldown-navigation', 'drillnav_woo_filters' ); ?>
					</table>
					<?php submit_button( __( 'Save settings', 'drillnav-drilldown-navigation' ) ); ?>
				</div>

				<?php /* ── Tab: Tracking (Pro) ── */ ?>
				<div id="tab-tracking" class="drillnav-tab-panel" hidden>

					<?php if ( ! $is_pro_active ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to Pro upgrade page */
							esc_html__( 'Analytics & Event Tracking is available in %s. Push navigation events to window.dataLayer for Google Tag Manager with per-event control.', 'drillnav-drilldown-navigation' ),
							'<a href="' . esc_url( $upgrade_url ) . '">' . esc_html__( 'DrillNav Pro', 'drillnav-drilldown-navigation' ) . '</a>'
						);
						?>
					</p>
					<?php endif; ?>

					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'drillnav-drilldown-navigation', 'drillnav_tracking' ); ?>
					</table>

					<?php if ( $is_pro_active ) : ?>
						<?php submit_button( __( 'Save settings', 'drillnav-drilldown-navigation' ) ); ?>
						<hr>
						<h3><?php esc_html_e( 'DataLayer event structure', 'drillnav-drilldown-navigation' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Example of the object pushed on each navigation event:', 'drillnav-drilldown-navigation' ); ?></p>
						<pre style="background:#f0f0f1;padding:1rem;border-radius:4px;font-size:12px;max-width:540px;overflow:auto;">window.dataLayer.push({
  event:                'drillnav_drilldown',
  drillnav_item_id:     42,
  drillnav_item_title:  'Services',
  drillnav_item_url:    '/services/',
  drillnav_depth:       1
});</pre>
					<?php else : ?>
						<p style="margin-top:1.5rem;">
							<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary">
								<?php esc_html_e( 'Upgrade to Pro →', 'drillnav-drilldown-navigation' ); ?>
							</a>
						</p>
					<?php endif; ?>

				</div>

				<?php /* ── Tab: Advanced ── */ ?>
				<div id="tab-advanced" class="drillnav-tab-panel" hidden>

					<h3><?php esc_html_e( 'Performance', 'drillnav-drilldown-navigation' ); ?></h3>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'drillnav-drilldown-navigation', 'drillnav_performance' ); ?>
					</table>

					<?php submit_button( __( 'Save settings', 'drillnav-drilldown-navigation' ) ); ?>

					<hr>

					<h2><?php esc_html_e( 'Cache', 'drillnav-drilldown-navigation' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'DrillNav caches navigation data for 7 days. The cache is automatically cleared when pages are added, edited, or deleted. Use the button below to clear it manually if needed.', 'drillnav-drilldown-navigation' ); ?>
					</p>
					<p>
						<button id="drillnav-clear-cache" class="button button-secondary">
							<?php esc_html_e( 'Clear cache', 'drillnav-drilldown-navigation' ); ?>
						</button>
					</p>
					<p id="drillnav-cache-notice" hidden></p>

					<hr>

					<h2><?php esc_html_e( 'Developer reference', 'drillnav-drilldown-navigation' ); ?></h2>

					<h3><?php esc_html_e( 'Shortcode', 'drillnav-drilldown-navigation' ); ?></h3>
					<p><?php esc_html_e( 'Place the navigation anywhere using the shortcode. All attributes are optional.', 'drillnav-drilldown-navigation' ); ?></p>
					<table class="widefat striped" style="max-width:700px">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Attribute', 'drillnav-drilldown-navigation' ); ?></th>
								<th><?php esc_html_e( 'Default', 'drillnav-drilldown-navigation' ); ?></th>
								<th><?php esc_html_e( 'Description', 'drillnav-drilldown-navigation' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>depth</code></td><td><code>0</code></td><td><?php esc_html_e( 'Max depth (0 = unlimited)', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>show_home</code></td><td><code>yes</code></td><td><?php esc_html_e( 'Show home link as first back-navigation step (yes / no)', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>home_label</code></td><td><?php esc_html_e( 'site name', 'drillnav-drilldown-navigation' ); ?></td><td><?php esc_html_e( 'Label for the home link', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>post_type</code></td><td><code>page</code></td><td><?php esc_html_e( 'Hierarchical post type to navigate', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>mobile_toggle</code></td><td><code>no</code></td><td><?php esc_html_e( 'Show a hamburger icon on mobile that opens a navigation drawer (yes / no)', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>max_width</code></td><td></td><td><?php esc_html_e( 'Limit the width of the navigation container, e.g. 300px or 60%.', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>multiple_back_buttons</code></td><td><code>no</code></td><td><?php esc_html_e( 'Show one back button per drilled level (yes / no)', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>layout</code></td><td><code>list</code></td><td><?php esc_html_e( 'Display layout: list, horizontal, accordion (Pro)', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>style_preset</code></td><td><code>default</code></td><td><?php esc_html_e( 'Style preset: default, compact, comfortable, or cards (Pro)', 'drillnav-drilldown-navigation' ); ?></td></tr>
						</tbody>
					</table>
					<p><code>[drillnav depth="2" show_home="yes" home_label="Home" post_type="page" mobile_toggle="yes"]</code></p>

					<h3><?php esc_html_e( 'CSS custom properties', 'drillnav-drilldown-navigation' ); ?></h3>
					<p><?php esc_html_e( 'Override these on the .drillnav selector in your theme to match your design.', 'drillnav-drilldown-navigation' ); ?></p>
					<table class="widefat striped" style="max-width:700px">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Property', 'drillnav-drilldown-navigation' ); ?></th>
								<th><?php esc_html_e( 'Default', 'drillnav-drilldown-navigation' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>--drillnav-color-bg</code></td><td><code>transparent</code></td></tr>
							<tr><td><code>--drillnav-color-text</code></td><td><code>inherit</code></td></tr>
							<tr><td><code>--drillnav-color-link</code></td><td><code>inherit</code></td></tr>
							<tr><td><code>--drillnav-color-current-bg</code></td><td><code>rgba(0,0,0,0.06)</code></td></tr>
							<tr><td><code>--drillnav-color-btn-hover</code></td><td><code>rgba(0,0,0,0.08)</code></td></tr>
							<tr><td><code>--drillnav-color-border</code></td><td><code>rgba(0,0,0,0.1)</code></td></tr>
							<tr><td><code>--drillnav-border-radius</code></td><td><code>4px</code></td></tr>
							<tr><td><code>--drillnav-transition-speed</code></td><td><code>200ms</code></td></tr>
							<tr><td><code>--drillnav-item-padding-y</code></td><td><code>0.5rem</code></td></tr>
							<tr><td><code>--drillnav-item-padding-x</code></td><td><code>0.75rem</code></td></tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Filter hooks', 'drillnav-drilldown-navigation' ); ?></h3>
					<table class="widefat striped" style="max-width:700px">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Hook', 'drillnav-drilldown-navigation' ); ?></th>
								<th><?php esc_html_e( 'Description', 'drillnav-drilldown-navigation' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>drillnav_nav_items</code></td><td><?php esc_html_e( 'Filters the complete navigation data array before caching and output.', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>drillnav_children_items</code></td><td><?php esc_html_e( 'Filters the list of child items for a given parent post.', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>drillnav_current_context</code></td><td><?php esc_html_e( 'Filters the resolved page context (post ID, ancestors, post type).', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>drillnav_cache_duration</code></td><td><?php esc_html_e( 'Filters the transient cache TTL in seconds (default: 604800 = 7 days).', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>drillnav_item_classes</code></td><td><?php esc_html_e( 'Filters the CSS class array on each navigation item\'s &lt;li&gt; element. Arguments: $classes (string[]), $item (array), $layout (string).', 'drillnav-drilldown-navigation' ); ?></td></tr>
							<tr><td><code>drillnav_item_attrs</code></td><td><?php esc_html_e( 'Adds custom HTML attributes to the item\'s &lt;a&gt; element. Return an associative array of attribute name → value. Arguments: $attrs (array), $item (array), $layout (string).', 'drillnav-drilldown-navigation' ); ?></td></tr>
						</tbody>
					</table>

				</div>

			</form>

		</div>

		<?php /* ── Sticky live preview panel ── */ ?>
		<div class="drillnav-preview-panel">
			<div class="drillnav-preview-panel__inner">
				<p class="drillnav-preview-panel__label"><?php esc_html_e( 'Live preview', 'drillnav-drilldown-navigation' ); ?></p>
				<p class="description" style="margin-bottom:.75rem;font-size:.8em"><?php esc_html_e( 'Updates instantly as you change colour scheme, layout, style preset, and custom CSS values.', 'drillnav-drilldown-navigation' ); ?></p>
				<div class="drillnav-preview-stage">
					<nav
						class="<?php echo esc_attr( implode( ' ', $pv_classes ) ); ?>"
						id="drillnav-settings-preview"
						aria-hidden="true"
						aria-label="preview"
						<?php echo $pv_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
					>
						<div class="drillnav__back-wrap"<?php echo $pv_show_back ? '' : ' hidden'; ?>>
							<a href="#" class="drillnav__back-btn" tabindex="-1">
								<span class="drillnav__back-arrow" aria-hidden="true">←</span>
								<span class="drillnav__back-label"><?php esc_html_e( 'Parent page', 'drillnav-drilldown-navigation' ); ?></span>
							</a>
						</div>
						<div class="drillnav__panel">
							<ul class="drillnav__list" role="list">
								<li class="drillnav__item drillnav__item--has-children" role="listitem">
									<div class="drillnav__row">
										<a href="#" class="drillnav__link" tabindex="-1"><?php esc_html_e( 'Services', 'drillnav-drilldown-navigation' ); ?></a>
										<button type="button" class="drillnav__expand-btn" tabindex="-1" aria-label="<?php esc_attr_e( 'Show sub-pages of Services', 'drillnav-drilldown-navigation' ); ?>"><span class="drillnav__arrow" aria-hidden="true">›</span></button>
									</div>
								</li>
								<li class="drillnav__item drillnav__item--current drillnav__item--has-children" role="listitem">
									<div class="drillnav__row">
										<a href="#" class="drillnav__link" aria-current="page" tabindex="-1"><?php esc_html_e( 'About Us', 'drillnav-drilldown-navigation' ); ?></a>
										<button type="button" class="drillnav__expand-btn" tabindex="-1"><span class="drillnav__arrow" aria-hidden="true">›</span></button>
									</div>
								</li>
								<li class="drillnav__item" role="listitem">
									<div class="drillnav__row">
										<a href="#" class="drillnav__link" tabindex="-1"><?php esc_html_e( 'Portfolio', 'drillnav-drilldown-navigation' ); ?></a>
									</div>
								</li>
								<li class="drillnav__item" role="listitem">
									<div class="drillnav__row">
										<a href="#" class="drillnav__link" tabindex="-1"><?php esc_html_e( 'Contact', 'drillnav-drilldown-navigation' ); ?></a>
									</div>
								</li>
							</ul>
						</div>
					</nav>
				</div>
			</div>
		</div>

	</div>

</div>
