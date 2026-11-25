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

if ( empty( $nav_data ) || ( empty( $nav_data['current_level'] ) && empty( $nav_data['tree'] ) ) ) {
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

$is_pro_active = \DrillNav\Plugin::is_pro();

// Layout – accordion requires Pro; fall back to list silently.
$layout      = (string) ( $settings['layout'] ?? 'list' );
$pro_layouts = array( 'accordion' );
if ( in_array( $layout, $pro_layouts, true ) && ! $is_pro_active ) {
	$layout = 'list';
}

// Style preset – Cards requires Pro; fall back to default silently.
$style_preset = (string) ( $settings['style_preset'] ?? 'default' );
$pro_presets  = array( 'cards' );
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
if ( 'list' !== $layout ) {
	$nav_classes[] = 'drillnav--layout-' . esc_attr( $layout );
}
$mobile_toggle_type = 'drawer';
if ( $mobile_toggle ) {
	if ( $is_pro_active && 'fullscreen' === ( $settings['mobile_toggle_type'] ?? 'drawer' ) ) {
		$mobile_toggle_type = 'fullscreen';
		$nav_classes[]      = 'drillnav--drawer-nav';
		$nav_classes[]      = 'drillnav--fullscreen-nav';
	} else {
		$nav_classes[] = 'drillnav--drawer-nav';
	}
	if ( $is_pro_active ) {
		$drawer_effect   = (string) ( $settings['drawer_effect'] ?? 'default' );
		$drawer_position = (string) ( $settings['drawer_position'] ?? 'left' );
		if ( 'glassmorphism' === $drawer_effect ) {
			$nav_classes[] = 'drillnav--drawer-effect-glass';
		}
		if ( 'right' === $drawer_position ) {
			$nav_classes[] = 'drillnav--drawer-position-right';
		}
	}
}
$mobile_breakpoint = $is_pro_active ? max( 320, (int) ( $settings['mobile_breakpoint'] ?? 768 ) ) : 768;
$expand_icon       = $is_pro_active ? esc_html( (string) ( $settings['expand_icon'] ?? '›' ) ) : '&#8250;';
$back_icon         = $is_pro_active ? esc_html( (string) ( $settings['back_icon'] ?? '←' ) ) : '&#8592;';


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
	// Strict validation at render time also covers values stored by older
	// versions and page-builder arguments.
	$val = \DrillNav\Settings::css_value( (string) ( $settings[ $setting_key ] ?? '' ) );
	if ( '' !== $val ) {
		$style_parts[] = $css_var . ': ' . $val;
	}
}

// Block placement: merge the block-support attributes (colours, spacing,
// typography, alignment, custom class, anchor) into the <nav> element.
if ( ! empty( $use_block_wrapper ) && function_exists( 'get_block_wrapper_attributes' ) ) {
	$nav_wrapper_attrs = get_block_wrapper_attributes(
		array(
			'class' => implode( ' ', $nav_classes ),
			'style' => implode( '; ', $style_parts ),
		)
	);
} else {
	$nav_wrapper_attrs = 'class="' . esc_attr( implode( ' ', $nav_classes ) ) . '"';
	if ( $style_parts ) {
		$nav_wrapper_attrs .= ' style="' . esc_attr( implode( '; ', $style_parts ) ) . '"';
	}
}

// Embed the full nav data as JSON for the JS layer.
$json_payload = array(
	'postId'       => $current_post_id,
	'ancestors'    => $ancestors,
	'currentLevel' => $current_level,
	'settings'     => $settings,
);
if ( ! empty( $nav_data['menu_children'] ) ) {
	// Menu source (Pro): sub-items are resolved client-side, menu item IDs
	// are not post IDs and cannot be loaded via the REST endpoint.
	$json_payload['menuChildren'] = $nav_data['menu_children'];
}
$json_data = wp_json_encode( $json_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
?>
<?php if ( $mobile_toggle && 768 !== $mobile_breakpoint ) : ?>
<style>
.drillnav-drawer-wrap-<?php echo esc_attr( $instance ); ?> .drillnav-toggle-btn { display: inline-flex; }
@media (min-width: <?php echo (int) ( $mobile_breakpoint + 1 ); ?>px) {
	.drillnav-drawer-wrap-<?php echo esc_attr( $instance ); ?> .drillnav-toggle-btn { display: none; }
}
@media (max-width: <?php echo (int) $mobile_breakpoint; ?>px) {
	.drillnav-drawer-wrap-<?php echo esc_attr( $instance ); ?> .drillnav-backdrop { display: block; }
	.drillnav-drawer-wrap-<?php echo esc_attr( $instance ); ?> .drillnav--drawer-nav { display: block; }
}
</style>
<?php endif; ?>
<?php if ( $mobile_toggle ) : ?>
<div class="drillnav-drawer-wrap drillnav-drawer-wrap-<?php echo esc_attr( $instance ); ?>" data-drillnav-drawer-wrap>
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
<?php
$ajax_content     = ( $is_pro_active && ! empty( $settings['ajax_content'] ) );
$content_selector = $ajax_content ? (string) ( $settings['content_selector'] ?? 'main' ) : '';
?>
<nav
	<?php echo $nav_wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above / by get_block_wrapper_attributes(). ?>
	aria-label="<?php echo esc_attr( $nav_label ); ?>"
	data-drillnav-instance="<?php echo esc_attr( $instance ); ?>"
	data-drillnav-animation="<?php echo esc_attr( $animation ); ?>"
	data-drillnav-layout="<?php echo esc_attr( $layout ); ?>"
	<?php if ( $ajax_content ) : ?>
	data-drillnav-ajax-content="1"
	data-drillnav-content-selector="<?php echo esc_attr( $content_selector ); ?>"
	<?php endif; ?>
>
	<?php // Hidden JSON data payload – read by frontend.js. ?>
	<script type="application/json" id="<?php echo esc_attr( $instance ); ?>-data">
		<?php echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output is safe. ?>
	</script>

	<?php
	$render_extra_attrs = static function( array $attrs ): void {
		foreach ( $attrs as $attr_name => $attr_value ) {
			if ( is_string( $attr_name ) && preg_match( '/^[a-z][a-z0-9\-_:]*$/i', $attr_name ) ) {
				echo ' ' . esc_attr( $attr_name ) . '="' . esc_attr( (string) $attr_value ) . '"';
			}
		}
	};

	// Helper: renders the optional icon prefix inside a link.
	$render_icon = static function( array $item ): void {
		if ( empty( $item['icon'] ) ) {
			return;
		}
		$icon = (string) $item['icon'];
		if ( str_starts_with( $icon, 'dashicons-' ) ) {
			printf( '<span class="dashicons %s drillnav__icon" aria-hidden="true"></span>', esc_attr( $icon ) );
		} else {
			printf( '<span class="drillnav__icon" aria-hidden="true">%s</span>', esc_html( $icon ) );
		}
	};

	// Helper: renders the optional product count after the link text.
	// Only items that explicitly opt in via show_count render a count.
	$render_count = static function( array $item ): void {
		if ( empty( $item['show_count'] ) || ! isset( $item['count'] ) ) {
			return;
		}
		printf( '<span class="drillnav__count">(%d)</span>', (int) $item['count'] );
	};

	// Helper: renders the optional badge after a link (inside the row div).
	$render_badge = static function( array $item ): void {
		if ( empty( $item['badge'] ) ) {
			return;
		}
		$valid_colors = array( 'red', 'green', 'blue', 'orange', 'gray' );
		$color        = in_array( $item['badge_color'] ?? 'red', $valid_colors, true ) ? $item['badge_color'] : 'red';
		printf(
			'<span class="drillnav__badge drillnav__badge--%s" aria-hidden="true">%s</span>',
			esc_attr( $color ),
			esc_html( (string) $item['badge'] )
		);
	};
	?>

	<?php
	$nav_title = (string) ( $settings['nav_title'] ?? '' );
	if ( '' !== $nav_title ) :
		/**
		 * Filters the HTML tag of the navigation heading.
		 *
		 * @param string $tag Default 'h2'.
		 */
		$title_tag = (string) apply_filters( 'drillnav_title_tag', 'h2' );
		if ( ! in_array( $title_tag, array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ), true ) ) {
			$title_tag = 'h2';
		}
		printf( '<%1$s class="drillnav__title">%2$s</%1$s>', tag_escape( $title_tag ), esc_html( $nav_title ) );
	endif;
	?>

	<?php if ( 'accordion' === $layout ) : ?>
	<?php
	$tree         = $nav_data['tree'] ?? array();
	$render_items = null;
	$accordion_lazy = ! empty( $settings['accordion_lazy'] );
	$render_items = function( array $items ) use ( &$render_items, $current_post_id, $layout, $render_extra_attrs, $accordion_lazy, $render_icon, $render_badge, $render_count, $expand_icon ): void {
		foreach ( $items as $item ) {
			$item_id      = (int) $item['id'];
			$item_title   = (string) $item['title'];
			$item_url     = (string) $item['url'];
			$is_current   = (bool) $item['is_current'];
			$has_children = ! empty( $item['has_children'] );

			$li_classes = array( 'drillnav__item' );
			if ( $is_current ) {
				$li_classes[] = 'drillnav__item--current';
			}
			if ( $has_children ) {
				$li_classes[] = 'drillnav__item--has-children';
			}
			$li_classes  = (array) apply_filters( 'drillnav_item_classes', $li_classes, $item, $layout );
			$extra_attrs = (array) apply_filters( 'drillnav_item_attrs', array(), $item, $layout );
			?>
			<li class="<?php echo esc_attr( implode( ' ', $li_classes ) ); ?>" role="listitem">
				<div class="drillnav__row">
					<a
						href="<?php echo esc_url( $item_url ); ?>"
						class="drillnav__link"
						<?php $render_extra_attrs( $extra_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
						<?php if ( $is_current ) : ?>
						aria-current="page"
						<?php endif; ?>
					><?php $render_icon( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
						<?php echo esc_html( $item_title ); ?>
						<?php $render_count( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
					</a>
					<?php $render_badge( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
					<?php if ( $has_children ) : ?>
					<button
						type="button"
						class="drillnav__expand-btn"
						aria-label="<?php
							/* translators: %s: page title */
							echo esc_attr( sprintf( __( 'Show sub-pages of %s', 'drillnav-drilldown-navigation' ), $item_title ) );
						?>"
						aria-expanded="false"
						data-drillnav-item-id="<?php echo esc_attr( (string) $item_id ); ?>"
						data-drillnav-item-type="<?php echo esc_attr( (string) ( $item['post_type'] ?? 'page' ) ); ?>"
						<?php if ( $accordion_lazy ) : ?>data-drillnav-lazy="1"<?php endif; ?>
					>
						<span class="drillnav__arrow" aria-hidden="true"><?php echo $expand_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above. ?></span>
					</button>
					<?php endif; ?>
				</div>
				<?php if ( $has_children ) : ?>
				<ul class="drillnav__sublist" role="list" data-drillnav-sub aria-hidden="true">
					<?php $render_items( $item['children'] ); ?>
				</ul>
				<?php endif; ?>
			</li>
			<?php
		}
	};
	?>
	<div class="drillnav__panel" id="<?php echo esc_attr( $instance ); ?>-panel">
		<ul class="drillnav__list" role="list">
			<?php $render_items( $tree ); ?>
		</ul>
	</div>
	<?php else : ?>

	<?php
	$search_min = (int) ( $settings['search_filter_min_items'] ?? 5 );
	$show_search = ! empty( $settings['search_filter'] )
		&& in_array( $layout, array( 'list', 'horizontal' ), true )
		&& ( 0 === $search_min || count( $current_level ) >= $search_min );
	?>
	<?php if ( $show_search ) : ?>
	<div class="drillnav__search">
		<input
			type="search"
			class="drillnav__search-input"
			placeholder="<?php esc_attr_e( 'Filter…', 'drillnav-drilldown-navigation' ); ?>"
			aria-label="<?php esc_attr_e( 'Filter navigation items', 'drillnav-drilldown-navigation' ); ?>"
			autocomplete="off"
		>
		<button type="button" class="drillnav__search-clear" hidden aria-label="<?php esc_attr_e( 'Clear filter', 'drillnav-drilldown-navigation' ); ?>">&#215;</button>
	</div>
	<?php endif; ?>

	<?php if ( $show_back && $back_item && empty( $back_item['is_current'] ) ) : ?>
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
			<span class="drillnav__back-arrow" aria-hidden="true"><?php echo $back_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above. ?></span>
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
			$li_classes  = (array) apply_filters( 'drillnav_item_classes', $li_classes, $item, $layout );
			$extra_attrs = (array) apply_filters( 'drillnav_item_attrs', array(), $item, $layout );
			?>
			<li class="<?php echo esc_attr( implode( ' ', $li_classes ) ); ?>" role="listitem">
				<div class="drillnav__row">
					<a
						href="<?php echo esc_url( $item_url ); ?>"
						class="drillnav__link"
						<?php $render_extra_attrs( $extra_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
						<?php if ( $is_current ) : ?>
						aria-current="page"
						<?php endif; ?>
					><?php $render_icon( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
						<?php echo esc_html( $item_title ); ?>
						<?php $render_count( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
					</a>
					<?php $render_badge( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside helper. ?>
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
						<span class="drillnav__arrow" aria-hidden="true"><?php echo $expand_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above. ?></span>
					</button>
					<?php endif; ?>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>
</nav>
<?php if ( $mobile_toggle ) : ?>
</div>
<?php endif; ?>
