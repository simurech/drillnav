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
 *  - ancestors (breadcrumb path from root)
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
				'post_type'       => $this->settings->get( 'post_types' )[0] ?? 'page',
				'depth'           => (int) $this->settings->get( 'depth' ),
				'show_home'       => (bool) $this->settings->get( 'show_home' ),
				'home_label'      => (string) $this->settings->get( 'home_label' ),
				'show_breadcrumb' => (bool) $this->settings->get( 'show_breadcrumb' ),
				'show_back'       => (bool) $this->settings->get( 'show_back_button' ),
				'animation'       => (string) $this->settings->get( 'animation' ),
				'color_scheme'    => (string) $this->settings->get( 'color_scheme' ),
				'nav_label'       => (string) $this->settings->get( 'nav_label' ),
			)
		);

		$ctx      = $this->context->get();
		$post_id  = (int) $ctx['post_id'];
		$cache_key = 'nav_' . md5( wp_json_encode( $args ) . '_' . $post_id );

		$cached = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$data = array(
			'post_id'         => $post_id,
			'post_type'       => $ctx['post_type'] ?: $args['post_type'],
			'ancestors'       => $this->get_ancestor_items( $ctx, $args ),
			'current_level'   => $this->get_current_level_items( $ctx, $args ),
			'children'        => $post_id > 0 ? $this->get_children( $post_id, $args ) : array(),
			'has_children'    => false,
			'settings'        => array(
				'show_home'       => $args['show_home'],
				'show_breadcrumb' => $args['show_breadcrumb'],
				'show_back'       => $args['show_back'],
				'animation'       => $args['animation'],
				'color_scheme'    => $args['color_scheme'],
				'home_label'      => $args['home_label'] ?: get_bloginfo( 'name' ),
				'nav_label'       => $args['nav_label'],
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

		$this->cache->set( $cache_key, $data );
		return $data;
	}

	/**
	 * Returns ancestor items (breadcrumb trail) from root to parent.
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

		$cache_key = 'children_' . $args['post_type'] . '_' . $parent_id;
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$query_args = array(
			'post_type'      => sanitize_key( $args['post_type'] ),
			'post_parent'    => $parent_id,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
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
		$cache_key = 'has_children_' . $post_type . '_' . $post_id;
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$children = get_posts(
			array(
				'post_type'      => sanitize_key( $post_type ),
				'post_parent'    => $post_id,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
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
			'title'        => $label ?: get_bloginfo( 'name' ),
			'url'          => home_url( '/' ),
			'parent_id'    => -1,
			'post_type'    => '',
			'menu_order'   => 0,
			'is_current'   => is_front_page(),
			'has_children' => true,
		);
	}
}
