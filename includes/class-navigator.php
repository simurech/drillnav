<?php
/**
 * Builds the hierarchical navigation data for the current page.
 *
 * @package DrillNav
 */

namespace DrillNav;

defined( 'ABSPATH' ) || exit;

/**
 * Assembles the tree of items the frontend needs:
 *  - ancestors (path from root, used for back navigation)
 *  - current level items (siblings of current page + current page itself)
 *  - children of the current page (if any)
 *
 * All data is cached via the Cache class.
 */
class Navigator {

	public function __construct(
		private readonly Context  $context,
		private readonly Cache    $cache,
		private readonly Settings $settings
	) {}

	/**
	 * Returns the complete navigation data array for the frontend.
	 *
	 * @param array<string,mixed> $args Override defaults (post_type, depth, show_home).
	 * @return array<string,mixed>
	 */
	public function get_nav_data( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'post_type'              => $this->settings->get( 'post_types' )[0] ?? 'page',
				'depth'                  => (int) $this->settings->get( 'depth' ),
				'show_home'              => (bool) $this->settings->get( 'show_home' ),
				'home_label'             => (string) $this->settings->get( 'home_label' ),
				'show_back'              => (bool) $this->settings->get( 'show_back_button' ),
				'animation'              => (string) $this->settings->get( 'animation' ),
				'color_scheme'           => (string) $this->settings->get( 'color_scheme' ),
				'nav_label'              => (string) $this->settings->get( 'nav_label' ),
				'max_width'              => (string) $this->settings->get( 'max_width' ),
				'multiple_back_buttons'  => (bool) $this->settings->get( 'multiple_back_buttons' ),
				'layout'                 => (string) $this->settings->get( 'layout' ),
				'style_preset'           => (string) $this->settings->get( 'style_preset' ),
				'custom_font_size'        => (string) ( $this->settings->get( 'custom_font_size' ) ?? '' ),
				'custom_padding_y'        => (string) ( $this->settings->get( 'custom_padding_y' ) ?? '' ),
				'custom_padding_x'        => (string) ( $this->settings->get( 'custom_padding_x' ) ?? '' ),
				'custom_border_radius'    => (string) ( $this->settings->get( 'custom_border_radius' ) ?? '' ),
				'custom_transition_speed' => (string) ( $this->settings->get( 'custom_transition_speed' ) ?? '' ),
				'custom_color_link'       => (string) ( $this->settings->get( 'custom_color_link' ) ?? '' ),
				'custom_color_current_bg' => (string) ( $this->settings->get( 'custom_color_current_bg' ) ?? '' ),
				'custom_color_hover'      => (string) ( $this->settings->get( 'custom_color_hover' ) ?? '' ),
				'custom_color_arrow'      => (string) ( $this->settings->get( 'custom_color_arrow' ) ?? '' ),
				'search_filter'           => (bool) ( $this->settings->get( 'search_filter' ) ?? false ),
				'accordion_lazy'          => (bool) ( $this->settings->get( 'accordion_lazy' ) ?? false ),
				'menu_id'                 => (int) ( $this->settings->get( 'menu_id' ) ?? 0 ),
				'ajax_content'            => (bool) ( $this->settings->get( 'ajax_content' ) ?? false ),
				'content_selector'        => (string) ( $this->settings->get( 'content_selector' ) ?? 'main' ),
			)
		);

		$ctx      = $this->context->get();
		$post_id  = (int) $ctx['post_id'];
		$lang     = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'nav_' . md5( wp_json_encode( $args ) . '_' . $post_id . '_' . $lang );

		$cached = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Pro feature guards – reset flags for non-Pro users.
		$is_pro = function_exists( 'drillnav_fs' ) && drillnav_fs()->can_use_premium_code__premium_only();
		if ( ! empty( $args['search_filter'] ) && ! $is_pro ) {
			$args['search_filter'] = false;
		}
		if ( ! empty( $args['accordion_lazy'] ) && ! $is_pro ) {
			$args['accordion_lazy'] = false;
		}
		if ( ! empty( $args['ajax_content'] ) && ! $is_pro ) {
			$args['ajax_content'] = false;
		}

		$data = array(
			'post_id'         => $post_id,
			'post_type'       => $ctx['post_type'] ?: $args['post_type'],
			'ancestors'       => $this->get_ancestor_items( $ctx, $args ),
			'current_level'   => $this->get_current_level_items( $ctx, $args ),
			'children'        => $post_id > 0 ? $this->get_children( $post_id, $args ) : array(),
			'has_children'    => false,
			'settings'        => array(
				'show_home'              => $args['show_home'],
				'show_back'              => $args['show_back'],
				'animation'              => $args['animation'],
				'color_scheme'           => $args['color_scheme'],
				'home_label'             => (string) apply_filters( 'drillnav_translate_string', $args['home_label'], 'home_label' ) ?: get_bloginfo( 'name' ),
				'nav_label'              => (string) apply_filters( 'drillnav_translate_string', $args['nav_label'], 'nav_label' ),
				'max_width'              => $args['max_width'],
				'multiple_back_buttons'  => $args['multiple_back_buttons'],
				'layout'                 => $args['layout'],
				'style_preset'           => $args['style_preset'],
				'custom_font_size'        => $args['custom_font_size'],
				'custom_padding_y'        => $args['custom_padding_y'],
				'custom_padding_x'        => $args['custom_padding_x'],
				'custom_border_radius'    => $args['custom_border_radius'],
				'custom_transition_speed' => $args['custom_transition_speed'],
				'custom_color_link'       => $args['custom_color_link'],
				'custom_color_current_bg' => $args['custom_color_current_bg'],
				'custom_color_hover'      => $args['custom_color_hover'],
				'custom_color_arrow'      => $args['custom_color_arrow'],
				'search_filter'           => $args['search_filter'],
				'accordion_lazy'          => $args['accordion_lazy'],
				'ajax_content'            => $args['ajax_content'],
				'content_selector'        => $args['content_selector'],
			),
		);

		$data['has_children'] = ! empty( $data['children'] );

		/**
		 * Filters the assembled navigation data.
		 *
		 * @param array<string,mixed> $data
		 * @param array<string,mixed> $args
		 */
		$data = (array) apply_filters( 'drillnav_nav_items', $data, $args );

		if ( 'accordion' === $args['layout'] && ! isset( $data['tree'] ) ) {
			$data['tree'] = $this->get_subtree( 0, $args );
		}

		$this->cache->set( $cache_key, $data );
		return $data;
	}

	/**
	 * Returns ancestor items (path from root to parent, used for back navigation).
	 *
	 * @param array<string,mixed> $ctx
	 * @param array<string,mixed> $args
	 * @return array<int,array<string,mixed>>
	 */
	private function get_ancestor_items( array $ctx, array $args ): array {
		$items = array();

		if ( $args['show_home'] ) {
			$items[] = $this->make_home_item( $args['home_label'] );
		}

		foreach ( $ctx['ancestors'] as $ancestor_id ) {
			$post = get_post( $ancestor_id );
			if ( $post ) {
				$items[] = $this->post_to_item( $post, false );
			}
		}

		return $items;
	}

	/**
	 * Returns the items that should be visible at the current navigation level
	 * (i.e. siblings of the current page, including the current page itself).
	 * Falls back to children of the current page if it has no siblings.
	 *
	 * @param array<string,mixed> $ctx
	 * @param array<string,mixed> $args
	 * @return array<int,array<string,mixed>>
	 */
	private function get_current_level_items( array $ctx, array $args ): array {
		$post_id   = (int) $ctx['post_id'];
		$parent_id = (int) $ctx['parent_id'];

		if ( $post_id <= 0 ) {
			// Front page or non-singular: show top-level pages.
			return $this->get_children( 0, $args );
		}

		$siblings = $this->get_children( $parent_id, $args );

		if ( ! empty( $siblings ) ) {
			return $siblings;
		}

		// Edge case: page has no siblings — show children of current page.
		return $this->get_children( $post_id, $args );
	}

	/**
	 * Returns direct children of a given post.
	 *
	 * @param int                 $parent_id 0 = top-level.
	 * @param array<string,mixed> $args
	 * @return array<int,array<string,mixed>>
	 */
	public function get_children( int $parent_id, array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'post_type' => $this->settings->get( 'post_types' )[0] ?? 'page',
				'depth'     => 0,
			)
		);

		$lang      = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'children_' . $args['post_type'] . '_' . $parent_id . '_' . $lang;
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$query_args = array(
			'post_type'        => sanitize_key( $args['post_type'] ),
			'post_parent'      => $parent_id,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => 'menu_order title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		);

		$posts = get_posts( $query_args );
		$items = array_map( fn( $p ) => $this->post_to_item( $p, false ), $posts );

		// Tag items that have children (for arrow indicator, ARIA).
		foreach ( $items as &$item ) {
			$item['has_children'] = $this->has_children( $item['id'], $args['post_type'] );
		}
		unset( $item );

		/**
		 * Filters the children item list.
		 *
		 * @param array<int,array<string,mixed>> $items
		 * @param int                            $parent_id
		 * @param array<string,mixed>            $args
		 */
		$items = (array) apply_filters( 'drillnav_children_items', $items, $parent_id, $args );

		$this->cache->set( $cache_key, $items );
		return $items;
	}

	/**
	 * Checks whether a post has published children.
	 *
	 * @param int    $post_id
	 * @param string $post_type
	 * @return bool
	 */
	public function has_children( int $post_id, string $post_type = 'page' ): bool {
		$lang      = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'has_children_' . $post_type . '_' . $post_id . '_' . $lang;
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$children = get_posts(
			array(
				'post_type'        => sanitize_key( $post_type ),
				'post_parent'      => $post_id,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);

		$result = ! empty( $children );
		$this->cache->set( $cache_key, $result );
		return $result;
	}

	/**
	 * Converts a WP_Post to a normalised navigation item array.
	 *
	 * @param \WP_Post $post
	 * @param bool     $is_current Whether this is the active page.
	 * @return array<string,mixed>
	 */
	private function post_to_item( \WP_Post $post, bool $is_current = false ): array {
		$is_current = $is_current || ( (int) get_queried_object_id() === $post->ID );

		return array(
			'id'           => $post->ID,
			'title'        => get_the_title( $post ),
			'url'          => get_permalink( $post ),
			'parent_id'    => (int) $post->post_parent,
			'post_type'    => $post->post_type,
			'menu_order'   => (int) $post->menu_order,
			'is_current'   => $is_current,
			'has_children' => false, // Filled in by get_children().
		);
	}

	/**
	 * Returns the synthetic "Home" navigation item.
	 *
	 * @param string $label
	 * @return array<string,mixed>
	 */
	private function make_home_item( string $label ): array {
		return array(
			'id'           => 0,
			'title'        => (string) apply_filters( 'drillnav_translate_string', $label, 'home_label' ) ?: get_bloginfo( 'name' ),
			'url'          => home_url( '/' ),
			'parent_id'    => -1,
			'post_type'    => '',
			'menu_order'   => 0,
			'is_current'   => is_front_page(),
			'has_children' => true,
		);
	}

	/**
	 * Returns a recursive tree of items for the accordion layout.
	 *
	 * @param int                 $parent_id Starting parent (0 = top-level).
	 * @param array<string,mixed> $args
	 * @param int                 $level     Current recursion depth (0-based).
	 * @return array<int,array<string,mixed>>
	 */
	private function get_subtree( int $parent_id, array $args, int $level = 0 ): array {
		$items     = $this->get_children( $parent_id, $args );
		$max_depth = (int) ( $args['depth'] ?? 0 );
		$lazy      = ! empty( $args['accordion_lazy'] );

		foreach ( $items as &$item ) {
			if ( $item['has_children'] && ( $max_depth === 0 || $level < $max_depth - 1 ) ) {
				// Lazy mode: omit children – JS loads them on demand via REST.
				$item['children'] = $lazy
					? array()
					: $this->get_subtree( (int) $item['id'], $args, $level + 1 );
			} else {
				$item['children'] = array();
			}
		}
		unset( $item );

		return $items;
	}
}
