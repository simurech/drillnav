<?php
/**
 * Plugin settings management.
 *
 * @package DrillNav
 */

namespace DrillNav;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to all plugin options with sane defaults.
 * Every public getter sanitizes the stored value before returning it.
 */
class Settings {

	private const OPTION_KEY = 'drillnav_settings';

	/** @var array<string,mixed>|null Lazy-loaded option array. */
	private ?array $data = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// General.
			'show_home'          => true,
			'home_label'         => '',   // Empty = use blogname.
			'depth'              => 0,    // 0 = unlimited.

			// Appearance.
			'max_width'              => '',    // Empty = no max-width constraint.
			'show_back_button'       => true,
			'multiple_back_buttons'  => false, // Show one back button per drill level.
			'animation'          => 'slide', // 'slide' | 'fade' | 'none'.

			// Accessibility.
			'nav_label'          => '', // Empty = default translated string.

			'layout'             => 'list',    // 'list' | 'horizontal' | 'accordion' (Pro) | 'mega' (Pro).
			'style_preset'       => 'default', // 'default' | 'compact' | 'comfortable' | 'cards' (Pro).

			// Mobile.
			'mobile_toggle'      => false,

			// Advanced.
			'post_types'         => array( 'page' ), // Which post types to navigate.
			'load_css'           => true,
			'load_a11y_css'      => true,
			'color_scheme'       => 'default', // 'default' | 'light' | 'dark'.

			// Blog / Posts integration.
			'blog_show_posts'    => true,   // Show posts as leaf items in category nav.
			'blog_posts_per_page' => 10,    // 0 = no limit. Applied per category/tag.
			'blog_hide_empty'    => true,   // Hide categories with no published posts.
			'blog_label'         => '',     // Empty = auto-detect from posts page title.

			// Customize (Pro) – granular CSS overrides.
			'custom_font_size'        => '',
			'custom_padding_y'        => '',
			'custom_padding_x'        => '',
			'custom_border_radius'    => '',
			'custom_transition_speed' => '',
			'custom_color_link'       => '',
			'custom_color_current_bg' => '',
			'custom_color_hover'      => '',
			'custom_color_arrow'      => '',

			// WooCommerce Pro – product attribute filters.
			// Each entry: ['taxonomy' => 'pa_brand', 'term_id' => 15, 'action' => 'exclude']
			'woo_attribute_filters' => array(),
		);
	}

	/**
	 * Returns a single setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get( string $key ) {
		$data = $this->all();
		return $data[ $key ] ?? null;
	}

	/**
	 * Returns all settings, merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		if ( null === $this->data ) {
			$stored     = get_option( self::OPTION_KEY, array() );
			$this->data = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return $this->data;
	}

	/**
	 * Saves sanitized settings to the database.
	 *
	 * @param array<string,mixed> $raw Raw POST values.
	 * @return bool Whether the update succeeded.
	 */
	public function save( array $raw ): bool {
		$clean = $this->sanitize( $raw );
		$this->data = null; // Bust in-memory cache.
		return update_option( self::OPTION_KEY, $clean );
	}

	/**
	 * Sanitizes raw input before persisting.
	 *
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public function sanitize( array $raw ): array {
		$defaults = self::defaults();
		$clean    = array();

		$clean['show_home']          = ! empty( $raw['show_home'] );
		$clean['home_label']         = sanitize_text_field( $raw['home_label'] ?? '' );
		$clean['depth']              = absint( $raw['depth'] ?? 0 );

		$clean['max_width']             = sanitize_text_field( $raw['max_width'] ?? '' );
		$clean['show_back_button']      = ! empty( $raw['show_back_button'] );
		$clean['multiple_back_buttons'] = ! empty( $raw['multiple_back_buttons'] );
		$clean['animation']          = in_array( $raw['animation'] ?? '', array( 'slide', 'fade', 'none' ), true )
			? $raw['animation']
			: $defaults['animation'];

		$clean['nav_label']          = sanitize_text_field( $raw['nav_label'] ?? '' );

		// Validate post_types against registered hierarchical post types.
		$allowed_types = $this->get_hierarchical_post_types();
		$raw_types     = is_array( $raw['post_types'] ?? null ) ? $raw['post_types'] : array();
		$clean['post_types'] = array_values(
			array_intersect( array_map( 'sanitize_key', $raw_types ), $allowed_types )
		);
		if ( empty( $clean['post_types'] ) ) {
			$clean['post_types'] = array( 'page' );
		}

		$clean['mobile_toggle'] = ! empty( $raw['mobile_toggle'] );

		$clean['load_css']      = ! empty( $raw['load_css'] );
		$clean['load_a11y_css'] = ! empty( $raw['load_a11y_css'] );
		$clean['color_scheme']  = in_array( $raw['color_scheme'] ?? '', array( 'default', 'light', 'dark' ), true )
			? $raw['color_scheme']
			: 'default';
		$clean['layout']        = in_array( $raw['layout'] ?? '', array( 'list', 'horizontal', 'accordion', 'mega' ), true )
			? $raw['layout']
			: 'list';
		$clean['style_preset']  = in_array( $raw['style_preset'] ?? '', array( 'default', 'compact', 'comfortable', 'cards' ), true )
			? $raw['style_preset']
			: 'default';

		// Blog settings.
		$clean['blog_show_posts']     = ! empty( $raw['blog_show_posts'] );
		$clean['blog_posts_per_page'] = absint( $raw['blog_posts_per_page'] ?? 10 );
		$clean['blog_hide_empty']     = ! empty( $raw['blog_hide_empty'] );
		$clean['blog_label']          = sanitize_text_field( $raw['blog_label'] ?? '' );

		// Customize (Pro) – granular CSS overrides.
		foreach ( array(
			'custom_font_size',
			'custom_padding_y',
			'custom_padding_x',
			'custom_border_radius',
			'custom_transition_speed',
			'custom_color_link',
			'custom_color_current_bg',
			'custom_color_hover',
			'custom_color_arrow',
		) as $custom_key ) {
			$clean[ $custom_key ] = sanitize_text_field( $raw[ $custom_key ] ?? '' );
		}

		// WooCommerce Pro – attribute filter rules.
		$raw_filters = is_array( $raw['woo_attribute_filters'] ?? null ) ? $raw['woo_attribute_filters'] : array();
		$clean_filters = array();
		foreach ( $raw_filters as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$taxonomy = sanitize_key( $rule['taxonomy'] ?? '' );
			$term_id  = absint( $rule['term_id'] ?? 0 );
			$action   = in_array( $rule['action'] ?? '', array( 'include', 'exclude' ), true )
				? $rule['action']
				: 'exclude';
			if ( $taxonomy && $term_id > 0 ) {
				$clean_filters[] = array(
					'taxonomy' => $taxonomy,
					'term_id'  => $term_id,
					'action'   => $action,
				);
			}
		}
		$clean['woo_attribute_filters'] = $clean_filters;

		return $clean;
	}

	/**
	 * Returns slugs of all registered hierarchical public post types.
	 *
	 * @return string[]
	 */
	public function get_hierarchical_post_types(): array {
		$types = get_post_types(
			array(
				'public'       => true,
				'hierarchical' => true,
			),
			'names'
		);
		return array_values( $types );
	}
}
