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

$is_pro_active = function_exists( 'drillnav_fs' ) && drillnav_fs()->can_use_premium_code__premium_only();
?>

<div class="wrap drillnav-settings-wrap">

	<h1>
		<?php esc_html_e( 'DrillNav Settings', 'drillnav-drilldown-navigation' ); ?>
		<span class="drillnav-version">v<?php echo esc_html( DRILLNAV_VERSION ); ?></span>
	</h1>

	<?php settings_errors( 'drillnav_settings_group' ); ?>

	<div class="drillnav-settings-layout">

		<div class="drillnav-settings-main">

			<form method="post" action="options.php">
				<?php
				settings_fields( 'drillnav_settings_group' );
				do_settings_sections( 'drillnav-drilldown-navigation' );
				submit_button( __( 'Save settings', 'drillnav-drilldown-navigation' ) );
				?>
			</form>

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
					<tr><td><code>mobile_toggle</code></td><td><code>no</code></td><td><?php esc_html_e( 'Show a hamburger icon on mobile that opens the navigation as a side drawer (yes / no)', 'drillnav-drilldown-navigation' ); ?></td></tr>
					<tr><td><code>max_width</code></td><td></td><td><?php esc_html_e( 'Limit the width of the navigation container, e.g. 300px or 60%. Leave empty for full width.', 'drillnav-drilldown-navigation' ); ?></td></tr>
					<tr><td><code>multiple_back_buttons</code></td><td><code>no</code></td><td><?php esc_html_e( 'Show one back button per drilled level (yes / no)', 'drillnav-drilldown-navigation' ); ?></td></tr>
					<tr><td><code>layout</code></td><td><code>list</code></td><td><?php esc_html_e( 'Display layout: list, horizontal, accordion (Pro), mega (Pro)', 'drillnav-drilldown-navigation' ); ?></td></tr>
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
				</tbody>
			</table>

		</div>

		<div class="drillnav-settings-sidebar">

			<div class="drillnav-card">
				<h3><?php esc_html_e( 'Quick start', 'drillnav-drilldown-navigation' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Open any page in the block editor.', 'drillnav-drilldown-navigation' ); ?></li>
					<li>
						<?php esc_html_e( 'Search for the', 'drillnav-drilldown-navigation' ); ?>
						<strong><?php esc_html_e( 'DrillNav', 'drillnav-drilldown-navigation' ); ?></strong>
						<?php esc_html_e( 'block and insert it.', 'drillnav-drilldown-navigation' ); ?>
					</li>
					<li><?php esc_html_e( 'The block automatically adapts to the current page hierarchy – no manual configuration required.', 'drillnav-drilldown-navigation' ); ?></li>
				</ol>
				<p><?php esc_html_e( 'Alternatively, use the shortcode:', 'drillnav-drilldown-navigation' ); ?><br>
				<code>[drillnav]</code></p>
			</div>

			<?php if ( ! $is_pro_active ) : ?>
			<div class="drillnav-card drillnav-card--pro">
				<h3>
					<?php esc_html_e( 'DrillNav Pro', 'drillnav-drilldown-navigation' ); ?>
					<span class="drillnav-pro-badge">PRO</span>
				</h3>
				<p><?php esc_html_e( 'Extend DrillNav with WooCommerce product category navigation:', 'drillnav-drilldown-navigation' ); ?></p>
				<ul>
					<li><?php esc_html_e( '✓ Drill-down through product categories', 'drillnav-drilldown-navigation' ); ?></li>
					<li><?php esc_html_e( '✓ Live product count per category', 'drillnav-drilldown-navigation' ); ?></li>
					<li><?php esc_html_e( '✓ Stock status filtering', 'drillnav-drilldown-navigation' ); ?></li>
					<li><?php esc_html_e( '✓ Exclude specific categories', 'drillnav-drilldown-navigation' ); ?></li>
					<li><?php esc_html_e( '✓ Priority support', 'drillnav-drilldown-navigation' ); ?></li>
				</ul>
				<?php
				$upgrade_url = function_exists( 'drillnav_fs' ) ? drillnav_fs()->get_upgrade_url() : '';
				if ( ! is_string( $upgrade_url ) || '' === $upgrade_url ) {
					$upgrade_url = '#';
				}
				?>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Upgrade to Pro →', 'drillnav-drilldown-navigation' ); ?>
				</a>
			</div>
			<?php endif; ?>

		</div>
	</div>

</div>
