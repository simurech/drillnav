<?php
/**
 * Admin menu and settings page.
 *
 * @package DrillNav
 */

namespace DrillNav\Admin;

defined( 'ABSPATH' ) || exit;

use DrillNav\Cache;
use DrillNav\Loader;
use DrillNav\Settings;

/**
 * Registers the admin menu, settings page, and admin-only AJAX handlers.
 */
class Admin {

	public function __construct(
		private readonly Settings $settings,
		private readonly Cache    $cache
	) {}

	/**
	 * Registers all admin hooks.
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'admin_menu',                        array( $this, 'add_menu_page' ) );
		$loader->add_action( 'admin_init',                        array( $this, 'register_settings' ) );
		$loader->add_action( 'admin_enqueue_scripts',             array( $this, 'enqueue_assets' ) );
		$loader->add_action( 'wp_ajax_drillnav_clear_cache',      array( $this, 'ajax_clear_cache' ) );
		$loader->add_action( 'admin_notices',                     array( $this, 'onboarding_notice' ) );
		$loader->add_action( 'wp_ajax_drillnav_dismiss_onboard',  array( $this, 'ajax_dismiss_onboarding' ) );

		// Invalidate cache when posts/pages change.
		$loader->add_action( 'save_post',    array( $this->cache, 'invalidate_on_post_change' ) );
		$loader->add_action( 'delete_post',  array( $this->cache, 'invalidate_on_post_change' ) );
		$loader->add_action( 'trashed_post', array( $this->cache, 'invalidate_on_post_change' ) );
	}

	/** Adds the DrillNav settings page under the Settings menu. */
	public function add_menu_page(): void {
		$hook = add_options_page(
			__( 'DrillNav Settings', 'drillnav-drilldown-navigation' ),
			__( 'DrillNav', 'drillnav-drilldown-navigation' ),
			'manage_options',
			'drillnav-drilldown-navigation',
			array( $this, 'render_settings_page' )
		);

		// Hook help tabs to load when this specific page loads.
		if ( $hook ) {
			add_action( "load-{$hook}", array( $this, 'add_help_tabs' ) );
		}
	}

	/**
	 * Adds WordPress contextual help tabs to the settings page.
	 * This is the primary documentation surface – no external docs needed.
	 */
	public function add_help_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// ---- Tab 1: Getting Started ----
		$screen->add_help_tab(
			array(
				'id'      => 'drillnav-getting-started',
				'title'   => __( 'Getting started', 'drillnav-drilldown-navigation' ),
				'content' =>
					'<h2>' . esc_html__( 'Getting started with DrillNav', 'drillnav-drilldown-navigation' ) . '</h2>' .
					'<p>' . esc_html__( 'DrillNav shows a contextual, drill-down navigation based on the current page\'s position in the site hierarchy. No manual configuration of individual pages is needed – the plugin detects the hierarchy automatically.', 'drillnav-drilldown-navigation' ) . '</p>' .
					'<h3>' . esc_html__( 'Three ways to place the navigation', 'drillnav-drilldown-navigation' ) . '</h3>' .
					'<ul>' .
					'<li><strong>' . esc_html__( 'Block (recommended):', 'drillnav-drilldown-navigation' ) . '</strong> ' . esc_html__( 'Open a page in the block editor and insert the "DrillNav" block. The Inspector panel (right sidebar) lets you configure depth, home link, animation and more.', 'drillnav-drilldown-navigation' ) . '</li>' .
					'<li><strong>' . esc_html__( 'Shortcode:', 'drillnav-drilldown-navigation' ) . '</strong> ' . esc_html__( 'Add [drillnav] to any page, post, or text widget. Supports attributes: depth, show_home, home_label.', 'drillnav-drilldown-navigation' ) . '</li>' .
					'<li><strong>' . esc_html__( 'Widget:', 'drillnav-drilldown-navigation' ) . '</strong> ' . esc_html__( 'Go to Appearance > Widgets and add "DrillNav – Contextual Navigation" to any sidebar.', 'drillnav-drilldown-navigation' ) . '</li>' .
					'</ul>' .
					'<h3>' . esc_html__( 'Blog / Posts', 'drillnav-drilldown-navigation' ) . '</h3>' .
					'<p>' . esc_html__( 'DrillNav automatically detects blog pages (posts index, category archives, single posts) and shows post categories as the navigation hierarchy. Individual posts appear as leaf items within their category.', 'drillnav-drilldown-navigation' ) . '</p>' .
					'<p>' . esc_html__( 'Tip: set a "Posts page" in Settings > Reading so DrillNav can use its title as the blog root label.', 'drillnav-drilldown-navigation' ) . '</p>',
			)
		);

		// ---- Tab 2: Shortcode Reference ----
		$screen->add_help_tab(
			array(
				'id'      => 'drillnav-shortcode',
				'title'   => __( 'Shortcode reference', 'drillnav-drilldown-navigation' ),
				'content' =>
					'<h2>' . esc_html__( 'Shortcode reference', 'drillnav-drilldown-navigation' ) . '</h2>' .
					'<p><code>[drillnav]</code> — ' . esc_html__( 'renders the contextual navigation using global plugin settings.', 'drillnav-drilldown-navigation' ) . '</p>' .
					'<h3>' . esc_html__( 'Attributes', 'drillnav-drilldown-navigation' ) . '</h3>' .
					'<table class="widefat striped">' .
					'<thead><tr><th>' . esc_html__( 'Attribute', 'drillnav-drilldown-navigation' ) . '</th><th>' . esc_html__( 'Default', 'drillnav-drilldown-navigation' ) . '</th><th>' . esc_html__( 'Description', 'drillnav-drilldown-navigation' ) . '</th></tr></thead>' .
					'<tbody>' .
					'<tr><td><code>depth</code></td><td><code>0</code></td><td>' . esc_html__( 'Maximum number of levels to display. 0 = unlimited.', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>show_home</code></td><td><code>yes</code></td><td>' . esc_html__( 'Show/hide the home link as the first back-navigation step. Values: yes / no.', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>home_label</code></td><td>' . esc_html__( 'Site name', 'drillnav-drilldown-navigation' ) . '</td><td>' . esc_html__( 'Custom label for the home link.', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>post_type</code></td><td><code>page</code></td><td>' . esc_html__( 'Hierarchical post type to navigate (e.g. page, product). Overrides the global setting for this instance only.', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'</tbody></table>' .
					'<h3>' . esc_html__( 'Examples', 'drillnav-drilldown-navigation' ) . '</h3>' .
					'<p><code>[drillnav depth="3"]</code><br>' .
					'<code>[drillnav show_home="no" home_label="Start"]</code><br>' .
					'<code>[drillnav post_type="services"]</code></p>',
			)
		);

		// ---- Tab 3: Developer Reference ----
		$screen->add_help_tab(
			array(
				'id'      => 'drillnav-developer',
				'title'   => __( 'Developer reference', 'drillnav-drilldown-navigation' ),
				'content' =>
					'<h2>' . esc_html__( 'Developer reference', 'drillnav-drilldown-navigation' ) . '</h2>' .

					'<h3>' . esc_html__( 'PHP Filter hooks', 'drillnav-drilldown-navigation' ) . '</h3>' .
					'<table class="widefat striped">' .
					'<thead><tr><th>' . esc_html__( 'Hook', 'drillnav-drilldown-navigation' ) . '</th><th>' . esc_html__( 'Arguments', 'drillnav-drilldown-navigation' ) . '</th><th>' . esc_html__( 'Description', 'drillnav-drilldown-navigation' ) . '</th></tr></thead>' .
					'<tbody>' .
					'<tr><td><code>drillnav_nav_items</code></td><td><code>$data, $args</code></td><td>' . esc_html__( 'Filter the complete navigation data array before rendering.', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>drillnav_current_context</code></td><td><code>$context</code></td><td>' . esc_html__( 'Filter the resolved page context (post_id, ancestors, blog_type, etc.).', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>drillnav_children_items</code></td><td><code>$items, $parent_id, $args</code></td><td>' . esc_html__( 'Filter the child items before they are cached and returned.', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>drillnav_cache_duration</code></td><td><code>$seconds</code></td><td>' . esc_html__( 'Adjust the transient TTL (default: WEEK_IN_SECONDS = 604 800).', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>drillnav_language</code></td><td><code>$lang</code></td><td>' . esc_html__( 'Return the active language code for cache key scoping (used by the WPML/Polylang integration).', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>drillnav_translate_post_id</code></td><td><code>$post_id, $post_type</code></td><td>' . esc_html__( 'Translate a post ID to its equivalent in the current language (used by the WPML/Polylang integration).', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>drillnav_translate_string</code></td><td><code>$value, $key</code></td><td>' . esc_html__( 'Translate a user-defined label string (home_label, nav_label, blog_label) to the current language (used by the WPML/Polylang integration).', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>drillnav_item_classes</code></td><td><code>$classes, $item, $layout</code></td><td>' . esc_html__( 'Filter the CSS class array on each navigation item\'s &lt;li&gt; element. $item contains id, title, url, is_current, has_children.', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'<tr><td><code>drillnav_item_attrs</code></td><td><code>$attrs, $item, $layout</code></td><td>' . esc_html__( 'Add or modify HTML attributes on the item\'s &lt;a&gt; element. Return an associative array of attribute name → value pairs.', 'drillnav-drilldown-navigation' ) . '</td></tr>' .
					'</tbody></table>' .

					'<h3>' . esc_html__( 'REST API', 'drillnav-drilldown-navigation' ) . '</h3>' .
					'<p><code>GET /wp-json/drillnav/v1/children?post_id=123&amp;post_type=page</code><br>' .
					esc_html__( 'Returns the direct children of a hierarchical post as a JSON array. Used by the frontend JavaScript for lazy-loading drill-down levels.', 'drillnav-drilldown-navigation' ) . '</p>' .
					'<p><code>GET /wp-json/drillnav/v1/woo-children?cat_id=5</code> <em>(' . esc_html__( 'Pro', 'drillnav-drilldown-navigation' ) . ')</em><br>' .
					esc_html__( 'Returns direct child product categories as JSON.', 'drillnav-drilldown-navigation' ) . '</p>' .

					'<h3>' . esc_html__( 'CSS Custom Properties', 'drillnav-drilldown-navigation' ) . '</h3>' .
					'<p>' . esc_html__( 'Override any of the following in your theme\'s stylesheet:', 'drillnav-drilldown-navigation' ) . '</p>' .
					'<pre style="background:#f0f0f1;padding:10px;overflow:auto;font-size:12px;">' .
					".drillnav {\n" .
					"  --drillnav-font-size:         1rem;\n" .
					"  --drillnav-color-bg:          transparent;\n" .
					"  --drillnav-color-text:         inherit;\n" .
					"  --drillnav-color-link:         inherit;\n" .
					"  --drillnav-color-current:      currentColor;\n" .
					"  --drillnav-color-current-bg:   rgba(0,0,0,.06);\n" .
					"  --drillnav-color-border:       rgba(0,0,0,.1);\n" .
					"  --drillnav-item-padding-y:     .5rem;\n" .
					"  --drillnav-item-padding-x:     .75rem;\n" .
					"  --drillnav-transition-speed:   200ms;\n" .
					"  --drillnav-border-radius:      4px;\n" .
					"}" .
					'</pre>',
			)
		);

		// Help sidebar with links.
		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'More information', 'drillnav-drilldown-navigation' ) . '</strong></p>' .
			'<p><a href="https://wordpress.org/support/plugin/drillnav-drilldown-navigation/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support forum', 'drillnav-drilldown-navigation' ) . '</a></p>' .
			'<p><a href="' . esc_url( $this->upgrade_url() ) . '">' . esc_html__( 'Upgrade to Pro', 'drillnav-drilldown-navigation' ) . '</a></p>'
		);
	}

	/** Registers settings sections and fields via the WordPress Settings API. */
	public function register_settings(): void {
		register_setting(
			'drillnav_settings_group',
			'drillnav_settings',
			array(
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		// --- General section ---
		add_settings_section(
			'drillnav_general',
			__( 'General', 'drillnav-drilldown-navigation' ),
			'__return_false',
			'drillnav-drilldown-navigation'
		);

		add_settings_field(
			'show_home',
			__( 'Show home link', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_checkbox' ),
			'drillnav-drilldown-navigation',
			'drillnav_general',
			array( 'key' => 'show_home', 'label' => __( 'Include a link to the site home as the first back-navigation step.', 'drillnav-drilldown-navigation' ) )
		);

		add_settings_field(
			'home_label',
			__( 'Home label', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_text' ),
			'drillnav-drilldown-navigation',
			'drillnav_general',
			array( 'key' => 'home_label', 'placeholder' => get_bloginfo( 'name' ), 'help' => __( 'Leave empty to use the site name.', 'drillnav-drilldown-navigation' ) )
		);

		add_settings_field(
			'depth',
			__( 'Max depth', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_number' ),
			'drillnav-drilldown-navigation',
			'drillnav_general',
			array( 'key' => 'depth', 'min' => 0, 'max' => 10, 'help' => __( '0 = unlimited levels.', 'drillnav-drilldown-navigation' ) )
		);

		add_settings_field(
			'post_types',
			__( 'Post types', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_post_types' ),
			'drillnav-drilldown-navigation',
			'drillnav_general',
			array( 'key' => 'post_types', 'help' => __( 'Which hierarchical post types the navigation should respond to. Only post types with parent–child relationships appear here. Regular Posts use a separate blog integration (categories &amp; tags) and are handled automatically.', 'drillnav-drilldown-navigation' ) )
		);

		add_settings_field(
			'nav_label',
			__( 'Navigation ARIA label', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_text' ),
			'drillnav-drilldown-navigation',
			'drillnav_general',
			array(
				'key'         => 'nav_label',
				'placeholder' => __( 'Page navigation', 'drillnav-drilldown-navigation' ),
				'help'        => __( 'Screen readers announce this label when entering the navigation landmark. Leave empty to use the translated default "Page navigation".', 'drillnav-drilldown-navigation' ),
			)
		);

		// --- Appearance section ---
		add_settings_section(
			'drillnav_appearance',
			__( 'Appearance', 'drillnav-drilldown-navigation' ),
			'__return_false',
			'drillnav-drilldown-navigation'
		);

		add_settings_field(
			'max_width',
			__( 'Max width', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_text' ),
			'drillnav-drilldown-navigation',
			'drillnav_appearance',
			array(
				'key'         => 'max_width',
				'placeholder' => __( 'e.g. 300px or 60%', 'drillnav-drilldown-navigation' ),
				'help'        => __( 'Limit the width of the navigation container. Leave empty for full width. Can be overridden per block or shortcode.', 'drillnav-drilldown-navigation' ),
			)
		);

		add_settings_field(
			'color_scheme',
			__( 'Colour scheme', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_select' ),
			'drillnav-drilldown-navigation',
			'drillnav_appearance',
			array(
				'key'     => 'color_scheme',
				'options' => array(
					'default' => __( 'Default (inherits theme)', 'drillnav-drilldown-navigation' ),
					'auto'    => __( 'Auto (follows OS light/dark preference)', 'drillnav-drilldown-navigation' ),
					'light'   => __( 'Light (white background)', 'drillnav-drilldown-navigation' ),
					'dark'    => __( 'Dark (dark background)', 'drillnav-drilldown-navigation' ),
				),
				'help'    => __( 'Applies a colour preset to the navigation. "Default" lets the active theme control all colours via CSS custom properties.', 'drillnav-drilldown-navigation' ),
			)
		);

		add_settings_field(
			'layout',
			__( 'Layout', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_select' ),
			'drillnav-drilldown-navigation',
			'drillnav_appearance',
			array(
				'key'     => 'layout',
				'options' => array(
					'list'        => __( 'List (default)', 'drillnav-drilldown-navigation' ),
					'horizontal'  => __( 'Horizontal', 'drillnav-drilldown-navigation' ),
					'accordion'   => __( 'Accordion (Pro)', 'drillnav-drilldown-navigation' ),
					'mega'        => __( 'Mega (Pro)', 'drillnav-drilldown-navigation' ),
				),
			)
		);

		add_settings_field(
			'accordion_lazy',
			__( 'Lazy-load Accordion', 'drillnav-drilldown-navigation' ) . ' <span class="drillnav-pro-badge-inline">PRO</span>',
			array( $this, 'field_checkbox_pro' ),
			'drillnav-drilldown-navigation',
			'drillnav_appearance',
			array(
				'key'         => 'accordion_lazy',
				'label'       => __( 'Load accordion child levels on demand via REST API.', 'drillnav-drilldown-navigation' ),
				'description' => __( 'Only applies when Layout is Accordion. Reduces initial page weight on large trees.', 'drillnav-drilldown-navigation' ),
			)
		);

		add_settings_field(
			'style_preset',
			__( 'Style preset', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_select' ),
			'drillnav-drilldown-navigation',
			'drillnav_appearance',
			array(
				'key'     => 'style_preset',
				'options' => array(
					'default'     => __( 'Default', 'drillnav-drilldown-navigation' ),
					'compact'     => __( 'Compact', 'drillnav-drilldown-navigation' ),
					'comfortable' => __( 'Comfortable', 'drillnav-drilldown-navigation' ),
					'cards'       => __( 'Cards (Pro)', 'drillnav-drilldown-navigation' ),
				),
				'help'    => __( 'Controls spacing and visual density. "Cards" requires DrillNav Pro.', 'drillnav-drilldown-navigation' ),
			)
		);

		add_settings_field(
			'search_filter',
			__( 'Search / Filter', 'drillnav-drilldown-navigation' ) . ' <span class="drillnav-pro-badge-inline">PRO</span>',
			array( $this, 'field_checkbox_pro' ),
			'drillnav-drilldown-navigation',
			'drillnav_appearance',
			array(
				'key'         => 'search_filter',
				'label'       => __( 'Show a live text filter above navigation items.', 'drillnav-drilldown-navigation' ),
				'description' => __( 'Available for List and Horizontal layouts only.', 'drillnav-drilldown-navigation' ),
			)
		);

		// --- Behavior section ---
		add_settings_section(
			'drillnav_behavior',
			__( 'Behavior', 'drillnav-drilldown-navigation' ),
			'__return_false',
			'drillnav-drilldown-navigation'
		);

		add_settings_field(
			'show_back_button',
			__( 'Show back button', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_checkbox' ),
			'drillnav-drilldown-navigation',
			'drillnav_behavior',
			array( 'key' => 'show_back_button', 'label' => __( 'Display a back-navigation link above the item list.', 'drillnav-drilldown-navigation' ) )
		);

		add_settings_field(
			'multiple_back_buttons',
			__( 'Multiple back buttons', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_checkbox' ),
			'drillnav-drilldown-navigation',
			'drillnav_behavior',
			array( 'key' => 'multiple_back_buttons', 'label' => __( 'Show one back button per drilled level (oldest first). Click any to jump directly to that level.', 'drillnav-drilldown-navigation' ) )
		);

		add_settings_field(
			'mobile_toggle',
			__( 'Mobile hamburger toggle', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_checkbox' ),
			'drillnav-drilldown-navigation',
			'drillnav_behavior',
			array(
				'key'   => 'mobile_toggle',
				'label' => __( 'On mobile (≤768 px) hide the navigation behind a hamburger icon that opens a side drawer.', 'drillnav-drilldown-navigation' ),
			)
		);

		add_settings_field(
			'animation',
			__( 'Animation', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_select' ),
			'drillnav-drilldown-navigation',
			'drillnav_behavior',
			array(
				'key'     => 'animation',
				'options' => array(
					'slide' => __( 'Slide (default)', 'drillnav-drilldown-navigation' ),
					'fade'  => __( 'Fade', 'drillnav-drilldown-navigation' ),
					'none'  => __( 'None', 'drillnav-drilldown-navigation' ),
				),
			)
		);

		add_settings_field(
			'drawer_effect',
			__( 'Drawer effect', 'drillnav-drilldown-navigation' ) . ' <span class="drillnav-pro-badge-inline">PRO</span>',
			array( $this, 'field_select_pro' ),
			'drillnav-drilldown-navigation',
			'drillnav_behavior',
			array(
				'key'     => 'drawer_effect',
				'options' => array(
					'default'        => __( 'Default (solid background)', 'drillnav-drilldown-navigation' ),
					'glassmorphism'  => __( 'Glassmorphism (blur + transparency)', 'drillnav-drilldown-navigation' ),
				),
				'help'    => __( 'Only applies when Mobile hamburger toggle is enabled.', 'drillnav-drilldown-navigation' ),
			)
		);

		add_settings_field(
			'drawer_position',
			__( 'Drawer position', 'drillnav-drilldown-navigation' ) . ' <span class="drillnav-pro-badge-inline">PRO</span>',
			array( $this, 'field_select_pro' ),
			'drillnav-drilldown-navigation',
			'drillnav_behavior',
			array(
				'key'     => 'drawer_position',
				'options' => array(
					'left' => __( 'Slide in from left (default)', 'drillnav-drilldown-navigation' ),
					'top'  => __( 'Slide in from top', 'drillnav-drilldown-navigation' ),
				),
			)
		);

		add_settings_field(
			'drawer_logo_url',
			__( 'Drawer logo URL', 'drillnav-drilldown-navigation' ) . ' <span class="drillnav-pro-badge-inline">PRO</span>',
			array( $this, 'field_text_pro' ),
			'drillnav-drilldown-navigation',
			'drillnav_behavior',
			array(
				'key'         => 'drawer_logo_url',
				'placeholder' => 'https://example.com/logo.png',
				'help'        => __( 'Image URL shown at the top of the drawer. Leave empty to hide.', 'drillnav-drilldown-navigation' ),
			)
		);

		// --- Performance section ---
		add_settings_section(
			'drillnav_performance',
			__( 'Performance', 'drillnav-drilldown-navigation' ),
			'__return_false',
			'drillnav-drilldown-navigation'
		);

		add_settings_field(
			'load_css',
			__( 'Load plugin CSS', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_checkbox' ),
			'drillnav-drilldown-navigation',
			'drillnav_performance',
			array( 'key' => 'load_css', 'label' => __( 'Disable if your theme provides its own DrillNav styles.', 'drillnav-drilldown-navigation' ) )
		);

		add_settings_field(
			'load_a11y_css',
			__( 'Load accessibility CSS', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_checkbox' ),
			'drillnav-drilldown-navigation',
			'drillnav_performance',
			array( 'key' => 'load_a11y_css', 'label' => __( 'Provides WCAG-compliant focus styles. Disable only if you supply equivalent styles.', 'drillnav-drilldown-navigation' ) )
		);

		// --- Blog / Posts section ---
		add_settings_section(
			'drillnav_blog',
			__( 'Blog &amp; Posts', 'drillnav-drilldown-navigation' ),
			function () {
				echo '<p class="description">' . esc_html__( 'Controls how DrillNav behaves on the blog index, category archives, tag archives, and single post pages.', 'drillnav-drilldown-navigation' ) . '</p>';
			},
			'drillnav-drilldown-navigation'
		);

		add_settings_field(
			'blog_show_posts',
			__( 'Show posts as navigation items', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_checkbox' ),
			'drillnav-drilldown-navigation',
			'drillnav_blog',
			array(
				'key'   => 'blog_show_posts',
				'label' => __( 'When enabled, individual posts appear as leaf items inside their category in the navigation.', 'drillnav-drilldown-navigation' ),
			)
		);

		add_settings_field(
			'blog_posts_per_page',
			__( 'Max. posts shown per category', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_number' ),
			'drillnav-drilldown-navigation',
			'drillnav_blog',
			array(
				'key'  => 'blog_posts_per_page',
				'min'  => 0,
				'max'  => 100,
				'help' => __( '0 = show all posts (may be slow for large categories). Only relevant when "Show posts" is enabled.', 'drillnav-drilldown-navigation' ),
			)
		);

		add_settings_field(
			'blog_hide_empty',
			__( 'Hide empty categories', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_checkbox' ),
			'drillnav-drilldown-navigation',
			'drillnav_blog',
			array(
				'key'   => 'blog_hide_empty',
				'label' => __( 'Do not show categories that have no published posts.', 'drillnav-drilldown-navigation' ),
			)
		);

		add_settings_field(
			'blog_label',
			__( 'Blog root label', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_text' ),
			'drillnav-drilldown-navigation',
			'drillnav_blog',
			array(
				'key'         => 'blog_label',
				'placeholder' => __( 'Auto (uses Posts page title)', 'drillnav-drilldown-navigation' ),
				'help'        => __( 'Label for the blog root link in back navigation. Leave empty to auto-detect from the Posts page title set in Settings > Reading.', 'drillnav-drilldown-navigation' ),
			)
		);

		// --- WooCommerce Pro section (visible but locked without licence) ---
		add_settings_section(
			'drillnav_woo_filters',
			__( 'WooCommerce Product Filters', 'drillnav-drilldown-navigation' ) . ' <span class="drillnav-pro-badge-inline">PRO</span>',
			function () {
				if ( $this->is_pro_active() ) {
					echo '<p class="description">' . esc_html__( 'Define attribute-based filter rules. Categories are hidden when none of their products pass the active rules.', 'drillnav-drilldown-navigation' ) . '</p>';
				} else {
					echo '<p class="description">';
					printf(
						/* translators: %s: link to Pro upgrade page */
						esc_html__( 'Available in %s. Filter product categories by attribute value – e.g. show only categories that carry products of a specific brand.', 'drillnav-drilldown-navigation' ),
						'<a href="' . esc_url( $this->upgrade_url() ) . '">' . esc_html__( 'DrillNav Pro', 'drillnav-drilldown-navigation' ) . '</a>'
					);
					echo '</p>';
				}
			},
			'drillnav-drilldown-navigation'
		);

		add_settings_field(
			'woo_attribute_filters',
			__( 'Attribute filter rules', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_woo_attribute_filters' ),
			'drillnav-drilldown-navigation',
			'drillnav_woo_filters',
			array( 'key' => 'woo_attribute_filters' )
		);

		// --- Customize (Pro) section ---
		add_settings_section(
			'drillnav_customize',
			__( 'Customize', 'drillnav-drilldown-navigation' ) . ' <span class="drillnav-pro-badge-inline">PRO</span>',
			function () {
				if ( $this->is_pro_active() ) {
					echo '<p class="description">' . esc_html__( 'Override individual CSS custom properties globally. Values set here apply to all navigation instances unless overridden per block.', 'drillnav-drilldown-navigation' ) . '</p>';
				} else {
					echo '<p class="description">';
					printf(
						/* translators: %s: link to Pro upgrade page */
						esc_html__( 'Granular styling options are available in %s.', 'drillnav-drilldown-navigation' ),
						'<a href="' . esc_url( $this->upgrade_url() ) . '">' . esc_html__( 'DrillNav Pro', 'drillnav-drilldown-navigation' ) . '</a>'
					);
					echo '</p>';
				}
			},
			'drillnav-drilldown-navigation'
		);

		$customize_fields = array(
			'custom_font_size'        => array(
				'label'       => __( 'Font size', 'drillnav-drilldown-navigation' ),
				'placeholder' => '1rem',
			),
			'custom_padding_y'        => array(
				'label'       => __( 'Padding top/bottom', 'drillnav-drilldown-navigation' ),
				'placeholder' => '0.5rem',
			),
			'custom_padding_x'        => array(
				'label'       => __( 'Padding left/right', 'drillnav-drilldown-navigation' ),
				'placeholder' => '0.75rem',
			),
			'custom_border_radius'    => array(
				'label'       => __( 'Border radius', 'drillnav-drilldown-navigation' ),
				'placeholder' => '4px',
			),
			'custom_transition_speed' => array(
				'label'       => __( 'Transition speed', 'drillnav-drilldown-navigation' ),
				'placeholder' => '200ms',
			),
			'custom_color_link'       => array(
				'label'       => __( 'Link colour', 'drillnav-drilldown-navigation' ),
				'placeholder' => 'inherit',
			),
			'custom_color_current_bg' => array(
				'label'       => __( 'Current item background', 'drillnav-drilldown-navigation' ),
				'placeholder' => 'rgba(0,0,0,0.06)',
			),
			'custom_color_hover'      => array(
				'label'       => __( 'Hover background', 'drillnav-drilldown-navigation' ),
				'placeholder' => 'rgba(0,0,0,0.08)',
			),
			'custom_color_arrow'      => array(
				'label'       => __( 'Arrow colour', 'drillnav-drilldown-navigation' ),
				'placeholder' => 'rgba(0,0,0,0.4)',
			),
		);

		foreach ( $customize_fields as $field_key => $field_cfg ) {
			add_settings_field(
				$field_key,
				esc_html( $field_cfg['label'] ),
				array( $this, 'field_text_pro' ),
				'drillnav-drilldown-navigation',
				'drillnav_customize',
				array(
					'key'         => $field_key,
					'placeholder' => $field_cfg['placeholder'],
				)
			);
		}

		// --- Tracking section (Pro) ---
		add_settings_section(
			'drillnav_tracking',
			__( 'Analytics & Event Tracking', 'drillnav-drilldown-navigation' ) . ' <span class="drillnav-pro-badge-inline">PRO</span>',
			'__return_false',
			'drillnav-drilldown-navigation'
		);

		add_settings_field(
			'tracking_enabled',
			__( 'Enable tracking', 'drillnav-drilldown-navigation' ),
			array( $this, 'field_checkbox_pro' ),
			'drillnav-drilldown-navigation',
			'drillnav_tracking',
			array(
				'key'   => 'tracking_enabled',
				'label' => __( 'Push navigation events to window.dataLayer (Google Tag Manager).', 'drillnav-drilldown-navigation' ),
			)
		);

		foreach ( array(
			'drilldown' => array(
				'label'       => __( 'Drill-down event', 'drillnav-drilldown-navigation' ),
				'name_key'    => 'tracking_event_drilldown_name',
				'enabled_key' => 'tracking_event_drilldown',
				'placeholder' => 'drillnav_drilldown',
			),
			'back'      => array(
				'label'       => __( 'Back event', 'drillnav-drilldown-navigation' ),
				'name_key'    => 'tracking_event_back_name',
				'enabled_key' => 'tracking_event_back',
				'placeholder' => 'drillnav_back',
			),
			'accordion' => array(
				'label'       => __( 'Accordion expand event', 'drillnav-drilldown-navigation' ),
				'name_key'    => 'tracking_event_accordion_name',
				'enabled_key' => 'tracking_event_accordion',
				'placeholder' => 'drillnav_accordion',
			),
		) as $ev_type => $ev_cfg ) {
			add_settings_field(
				$ev_cfg['enabled_key'],
				esc_html( $ev_cfg['label'] ),
				array( $this, 'field_tracking_event' ),
				'drillnav-drilldown-navigation',
				'drillnav_tracking',
				array(
					'enabled_key' => $ev_cfg['enabled_key'],
					'name_key'    => $ev_cfg['name_key'],
					'placeholder' => $ev_cfg['placeholder'],
				)
			);
		}
	}

	/** @param array<string,mixed> $args */
	public function field_tracking_event( array $args ): void {
		$enabled_key = $args['enabled_key'];
		$name_key    = $args['name_key'];
		$placeholder = $args['placeholder'] ?? '';

		if ( ! $this->is_pro_active() ) {
			printf(
				'<label><input type="checkbox" disabled> %s</label>
				 <input type="text" class="regular-text" disabled placeholder="%s" style="margin-left:.5rem;">',
				esc_html__( 'Enabled', 'drillnav-drilldown-navigation' ),
				esc_attr( $placeholder )
			);
			echo '<p class="description">' . esc_html__( 'Available in DrillNav Pro.', 'drillnav-drilldown-navigation' ) . '</p>';
			return;
		}

		$enabled = (bool) $this->settings->get( $enabled_key );
		$name    = (string) $this->settings->get( $name_key );
		printf(
			'<label><input type="checkbox" name="drillnav_settings[%s]" value="1" %s> %s</label>
			 <input type="text" name="drillnav_settings[%s]" value="%s" placeholder="%s" class="regular-text" style="margin-left:.5rem;">
			 <p class="description">%s</p>',
			esc_attr( $enabled_key ),
			checked( $enabled, true, false ),
			esc_html__( 'Enabled', 'drillnav-drilldown-navigation' ),
			esc_attr( $name_key ),
			esc_attr( $name ),
			esc_attr( $placeholder ),
			esc_html__( 'GTM event name pushed to window.dataLayer.', 'drillnav-drilldown-navigation' )
		);
	}

	/**
	 * Checks whether a Pro licence is active (used for conditional UI).
	 *
	 * @return bool
	 */
	private function is_pro_active(): bool {
		return function_exists( 'drillnav_fs' ) && ( drillnav_fs()->is_paying() || drillnav_fs()->is_trial() );
	}

	/** Renders the settings page. */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include DRILLNAV_PLUGIN_DIR . 'includes/admin/partials/settings-page.php';
	}

	/** Enqueues admin-only assets on the DrillNav settings page. */
	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_drillnav-drilldown-navigation' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'drillnav-admin',
			DRILLNAV_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			DRILLNAV_VERSION
		);

		// Load frontend CSS so the live preview renders correctly.
		wp_enqueue_style(
			'drillnav-frontend-preview',
			DRILLNAV_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			DRILLNAV_VERSION
		);

		wp_enqueue_script(
			'drillnav-admin',
			DRILLNAV_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			DRILLNAV_VERSION,
			true
		);

		wp_localize_script(
			'drillnav-admin',
			'drillnavAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'drillnav_clear_cache' ),
				'clearLabel' => __( 'Clear cache', 'drillnav-drilldown-navigation' ),
				'clearing'   => __( 'Clearing…', 'drillnav-drilldown-navigation' ),
				'cleared'    => __( 'Cache cleared successfully.', 'drillnav-drilldown-navigation' ),
				'error'      => __( 'Could not clear cache. Please try again.', 'drillnav-drilldown-navigation' ),
			)
		);
	}

	/** AJAX handler: clears all DrillNav transients. */
	public function ajax_clear_cache(): void {
		check_ajax_referer( 'drillnav_clear_cache', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
		}

		$count = $this->cache->flush();
		wp_send_json_success( array( 'deleted' => $count ) );
	}

	/** Displays a one-time onboarding notice after activation. */
	public function onboarding_notice(): void {
		if ( get_option( 'drillnav_onboarding_dismissed' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible" id="drillnav-onboarding-notice">
			<p>
				<strong><?php esc_html_e( 'DrillNav is active!', 'drillnav-drilldown-navigation' ); ?></strong>
				<?php esc_html_e( 'Add the DrillNav block in the editor, use the', 'drillnav-drilldown-navigation' ); ?>
				<code>[drillnav]</code>
				<?php esc_html_e( 'shortcode, or place the DrillNav widget in a sidebar.', 'drillnav-drilldown-navigation' ); ?>
				&nbsp;<a href="<?php echo esc_url( admin_url( 'options-general.php?page=drillnav-drilldown-navigation' ) ); ?>">
					<?php esc_html_e( 'Open settings →', 'drillnav-drilldown-navigation' ); ?>
				</a>
			</p>
		</div>
		<script>
		( function() {
			var notice = document.getElementById( 'drillnav-onboarding-notice' );
			if ( notice ) {
				notice.addEventListener( 'click', function( e ) {
					if ( e.target.classList.contains( 'notice-dismiss' ) ) {
						fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
							method: 'POST',
							credentials: 'same-origin',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'action=drillnav_dismiss_onboard&nonce=' + <?php echo wp_json_encode( wp_create_nonce( 'drillnav_dismiss_onboard' ) ); ?>
						} );
					}
				} );
			}
		} )();
		</script>
		<?php
	}

	/** AJAX handler: persists the dismissed state of the onboarding notice. */
	public function ajax_dismiss_onboarding(): void {
		check_ajax_referer( 'drillnav_dismiss_onboard', 'nonce' );
		update_option( 'drillnav_onboarding_dismissed', true );
		wp_send_json_success();
	}

	/* ------------------------------------------------------------------
	 * Settings field renderers
	 * ----------------------------------------------------------------- */

	/** @param array<string,mixed> $args */
	public function field_checkbox( array $args ): void {
		$value = $this->settings->get( $args['key'] );
		$id    = 'drillnav_' . $args['key'];
		printf(
			'<label><input type="checkbox" id="%s" name="drillnav_settings[%s]" value="1" %s> %s</label>',
			esc_attr( $id ),
			esc_attr( $args['key'] ),
			checked( (bool) $value, true, false ),
			isset( $args['label'] ) ? esc_html( $args['label'] ) : ''
		);
	}

	/** @param array<string,mixed> $args */
	public function field_text( array $args ): void {
		$value = (string) $this->settings->get( $args['key'] );
		$id    = 'drillnav_' . $args['key'];
		printf(
			'<input type="text" id="%s" name="drillnav_settings[%s]" value="%s" placeholder="%s" class="regular-text">',
			esc_attr( $id ),
			esc_attr( $args['key'] ),
			esc_attr( $value ),
			esc_attr( $args['placeholder'] ?? '' )
		);
		if ( ! empty( $args['help'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
		}
	}

	/** @param array<string,mixed> $args */
	public function field_number( array $args ): void {
		$value = (int) $this->settings->get( $args['key'] );
		$id    = 'drillnav_' . $args['key'];
		printf(
			'<input type="number" id="%s" name="drillnav_settings[%s]" value="%d" min="%d" max="%d" class="small-text">',
			esc_attr( $id ),
			esc_attr( $args['key'] ),
			(int) $value,
			(int) ( $args['min'] ?? 0 ),
			(int) ( $args['max'] ?? 99 )
		);
		if ( ! empty( $args['help'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
		}
	}

	/** @param array<string,mixed> $args */
	public function field_select( array $args ): void {
		$value   = (string) $this->settings->get( $args['key'] );
		$id      = 'drillnav_' . $args['key'];
		$options = $args['options'] ?? array();

		printf( '<select id="%s" name="drillnav_settings[%s]">', esc_attr( $id ), esc_attr( $args['key'] ) );
		foreach ( $options as $opt_value => $opt_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( (string) $opt_value ),
				selected( $value, (string) $opt_value, false ),
				esc_html( (string) $opt_label )
			);
		}
		echo '</select>';
	}

	/** @param array<string,mixed> $args */
	public function field_post_types( array $args ): void {
		$saved_types = (array) $this->settings->get( 'post_types' );
		$all_types   = $this->settings->get_hierarchical_post_types();

		foreach ( $all_types as $type_slug ) {
			$pto = get_post_type_object( $type_slug );
			if ( ! $pto ) {
				continue;
			}
			$id = 'drillnav_post_type_' . $type_slug;
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" id="%s" name="drillnav_settings[post_types][]" value="%s" %s> %s <code>(%s)</code></label>',
				esc_attr( $id ),
				esc_attr( $type_slug ),
				checked( in_array( $type_slug, $saved_types, true ), true, false ),
				esc_html( $pto->label ),
				esc_html( $type_slug )
			);
		}

		if ( ! empty( $args['help'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
		}
	}

	/**
	 * Returns the Freemius-generated upgrade/checkout URL.
	 * Falls back to '#' when the SDK is not initialised (e.g. stub mode).
	 */
	private function upgrade_url(): string {
		if ( function_exists( 'drillnav_fs' ) ) {
			$url = drillnav_fs()->get_upgrade_url();
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		return '#';
	}

	/** @param array<string,mixed> $args */
	public function field_select_pro( array $args ): void {
		if ( ! $this->is_pro_active() ) {
			$options = $args['options'] ?? array();
			echo '<select disabled>';
			foreach ( $options as $val => $lbl ) {
				printf( '<option value="%s">%s</option>', esc_attr( (string) $val ), esc_html( (string) $lbl ) );
			}
			echo '</select>';
			if ( ! empty( $args['help'] ) ) {
				printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
			}
			return;
		}
		$this->field_select( $args );
		if ( ! empty( $args['help'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
		}
	}

	/** @param array<string,mixed> $args */
	public function field_text_pro( array $args ): void {
		if ( ! $this->is_pro_active() ) {
			printf(
				'<input type="text" class="regular-text" disabled placeholder="%s">',
				esc_attr( $args['placeholder'] ?? '' )
			);
			return;
		}
		$value = (string) $this->settings->get( $args['key'] );
		$id    = 'drillnav_' . $args['key'];
		printf(
			'<input type="text" id="%s" name="drillnav_settings[%s]" value="%s" placeholder="%s" class="regular-text">',
			esc_attr( $id ),
			esc_attr( $args['key'] ),
			esc_attr( $value ),
			esc_attr( $args['placeholder'] ?? '' )
		);
	}

	/** @param array<string,mixed> $args */
	public function field_checkbox_pro( array $args ): void {
		$key   = $args['key'];
		$label = $args['label'] ?? '';
		if ( ! $this->is_pro_active() ) {
			printf(
				'<label><input type="checkbox" disabled> %s</label>',
				esc_html( $label )
			);
			printf(
				'<p class="description">%s</p>',
				wp_kses_post( $args['pro_hint'] ?? __( 'Available in DrillNav Pro.', 'drillnav-drilldown-navigation' ) )
			);
			return;
		}
		$value = (bool) $this->settings->get( $key );
		$id    = 'drillnav_' . $key;
		printf(
			'<label><input type="checkbox" id="%s" name="drillnav_settings[%s]" value="1" %s> %s</label>',
			esc_attr( $id ),
			esc_attr( $key ),
			checked( $value, true, false ),
			esc_html( $label )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/** @param array<string,mixed> $args */
	public function field_woo_attribute_filters( array $args ): void {
		// Non-Pro: show a locked placeholder (section description already explains why).
		if ( ! $this->is_pro_active() ) {
			printf(
				'<button type="button" class="button" disabled>%s</button>',
				esc_html__( '+ Add rule', 'drillnav-drilldown-navigation' )
			);
			return;
		}

		// Pro but WooCommerce not active.
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			echo '<p class="description">' . esc_html__( 'WooCommerce must be installed and active to use this feature.', 'drillnav-drilldown-navigation' ) . '</p>';
			return;
		}

		$saved_rules          = (array) $this->settings->get( 'woo_attribute_filters' );
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		// Pre-load all attribute terms for the JS-powered taxonomy→term cascade.
		$terms_data = array();
		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy = 'pa_' . $tax->attribute_name;
			$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}
			$terms_data[ $taxonomy ] = array_map(
				static function ( \WP_Term $term ): array {
					return array( 'id' => (int) $term->term_id, 'name' => $term->name );
				},
				$terms
			);
		}

		$empty_label = __( 'No rules yet. Click "+ Add rule" to create one.', 'drillnav-drilldown-navigation' );
		?>
		<div class="drillnav-filter-rules"
			 id="drillnav-woo-filters"
			 data-terms="<?php echo esc_attr( wp_json_encode( $terms_data ) ); ?>">

			<table class="widefat drillnav-filter-table" id="drillnav-filter-rules-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'drillnav-drilldown-navigation' ); ?></th>
						<th><?php esc_html_e( 'Term / Value', 'drillnav-drilldown-navigation' ); ?></th>
						<th><?php esc_html_e( 'Action', 'drillnav-drilldown-navigation' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="drillnav-filter-rules-body"
					   data-empty-label="<?php echo esc_attr( $empty_label ); ?>">
					<?php
					if ( empty( $saved_rules ) ) :
						?>
						<tr id="drillnav-filter-empty-row">
							<td colspan="4"><em><?php esc_html_e( 'No rules yet. Click "+ Add rule" to create one.', 'drillnav-drilldown-navigation' ); ?></em></td>
						</tr>
					<?php else :
						foreach ( $saved_rules as $index => $rule ) {
							$this->render_filter_rule_row( (int) $index, $rule, $attribute_taxonomies, $terms_data );
						}
					endif;
					?>
				</tbody>
			</table>

			<p style="margin-top:.75rem;">
				<button type="button" class="button" id="drillnav-add-filter-rule">
					<?php esc_html_e( '+ Add rule', 'drillnav-drilldown-navigation' ); ?>
				</button>
			</p>

			<p class="description">
				<?php esc_html_e( 'A category is shown only when at least one of its products (including subcategory products) passes all active rules.', 'drillnav-drilldown-navigation' ); ?>
			</p>
		</div>

		<template id="drillnav-filter-rule-template">
			<tr class="drillnav-filter-row">
				<td>
					<select name="drillnav_settings[woo_attribute_filters][__INDEX__][taxonomy]"
							class="drillnav-filter-taxonomy">
						<option value=""><?php esc_html_e( '— Select attribute —', 'drillnav-drilldown-navigation' ); ?></option>
						<?php foreach ( $attribute_taxonomies as $tax ) : ?>
							<option value="<?php echo esc_attr( 'pa_' . $tax->attribute_name ); ?>">
								<?php echo esc_html( $tax->attribute_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<select name="drillnav_settings[woo_attribute_filters][__INDEX__][term_id]"
							class="drillnav-filter-term"
							data-placeholder="<?php esc_attr_e( '— Select term —', 'drillnav-drilldown-navigation' ); ?>">
						<option value=""><?php esc_html_e( '— Select term —', 'drillnav-drilldown-navigation' ); ?></option>
					</select>
				</td>
				<td>
					<select name="drillnav_settings[woo_attribute_filters][__INDEX__][action]"
							class="drillnav-filter-action">
						<option value="exclude"><?php esc_html_e( 'Exclude categories without this term', 'drillnav-drilldown-navigation' ); ?></option>
						<option value="include"><?php esc_html_e( 'Include only categories with this term', 'drillnav-drilldown-navigation' ); ?></option>
					</select>
				</td>
				<td>
					<button type="button"
							class="button-link drillnav-remove-rule"
							aria-label="<?php esc_attr_e( 'Remove rule', 'drillnav-drilldown-navigation' ); ?>">
						&times;
					</button>
				</td>
			</tr>
		</template>
		<?php
	}

	/**
	 * Renders a single saved filter rule row.
	 *
	 * @param int                       $index                Row index.
	 * @param array<string,mixed>       $rule                 Saved rule (taxonomy, term_id, action).
	 * @param array<int,object>         $attribute_taxonomies WooCommerce attribute objects.
	 * @param array<string,array>       $terms_data           Pre-loaded terms by taxonomy.
	 */
	private function render_filter_rule_row( int $index, array $rule, array $attribute_taxonomies, array $terms_data ): void {
		$selected_tax    = sanitize_key( $rule['taxonomy'] ?? '' );
		$selected_term   = absint( $rule['term_id'] ?? 0 );
		$selected_action = in_array( $rule['action'] ?? '', array( 'include', 'exclude' ), true )
			? $rule['action']
			: 'exclude';
		$prefix          = "drillnav_settings[woo_attribute_filters][{$index}]";
		?>
		<tr class="drillnav-filter-row">
			<td>
				<select name="<?php echo esc_attr( $prefix ); ?>[taxonomy]"
						class="drillnav-filter-taxonomy">
					<option value=""><?php esc_html_e( '— Select attribute —', 'drillnav-drilldown-navigation' ); ?></option>
					<?php foreach ( $attribute_taxonomies as $tax ) :
						$tax_slug = 'pa_' . $tax->attribute_name;
					?>
						<option value="<?php echo esc_attr( $tax_slug ); ?>"
							<?php selected( $selected_tax, $tax_slug ); ?>>
							<?php echo esc_html( $tax->attribute_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select name="<?php echo esc_attr( $prefix ); ?>[term_id]"
						class="drillnav-filter-term"
						data-placeholder="<?php esc_attr_e( '— Select term —', 'drillnav-drilldown-navigation' ); ?>">
					<option value=""><?php esc_html_e( '— Select term —', 'drillnav-drilldown-navigation' ); ?></option>
					<?php
					if ( $selected_tax && isset( $terms_data[ $selected_tax ] ) ) {
						foreach ( $terms_data[ $selected_tax ] as $term ) {
							printf(
								'<option value="%d"%s>%s</option>',
								(int) $term['id'],
								selected( $selected_term, (int) $term['id'], false ),
								esc_html( $term['name'] )
							);
						}
					}
					?>
				</select>
			</td>
			<td>
				<select name="<?php echo esc_attr( $prefix ); ?>[action]"
						class="drillnav-filter-action">
					<option value="exclude"<?php selected( $selected_action, 'exclude' ); ?>><?php esc_html_e( 'Exclude categories without this term', 'drillnav-drilldown-navigation' ); ?></option>
					<option value="include"<?php selected( $selected_action, 'include' ); ?>><?php esc_html_e( 'Include only categories with this term', 'drillnav-drilldown-navigation' ); ?></option>
				</select>
			</td>
			<td>
				<button type="button"
						class="button-link drillnav-remove-rule"
						aria-label="<?php esc_attr_e( 'Remove rule', 'drillnav-drilldown-navigation' ); ?>">
					&times;
				</button>
			</td>
		</tr>
		<?php
	}
}
