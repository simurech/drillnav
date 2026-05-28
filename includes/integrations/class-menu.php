<?php
/**
 * WordPress nav-menu as navigation data source (Pro feature).
 *
 * When a menu_id arg is provided, the plugin replaces the automatic page-hierarchy
 * navigation with the structure of a WordPress nav menu.  The current page is
 * identified by matching the queried object ID or current URL to a menu item.
 *
 * Hybrid mode: if a matched menu item links to a WooCommerce product-category and
 * the menu has no explicit sub-items for it, child terms are fetched automatically.
 *
 * @package DrillNav
 */

namespace DrillNav\Integrations;

defined( 'ABSPATH' ) || exit;

use DrillNav\Cache;
use DrillNav\Loader;
use DrillNav\Settings;

/**
 * WP nav-menu navigation source (Pro).
 */
class Menu {

	public function __construct(
		private readonly Cache    $cache,
		private readonly Settings $settings
	) {}

	/**
	 * Registers hooks with the loader.
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_filter( 'drillnav_nav_items', array( $this, 'filter_nav_items' ), 15, 2 );
	}

	/* ------------------------------------------------------------------
	 * Nav items filter
	 * ----------------------------------------------------------------- */

	/**
	 * Replaces hierarchy-based nav data with menu-based navigation.
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function filter_nav_items( array $data, array $args ): array {
		$menu_id = (int) ( $args['menu_id'] ?? $this->settings->get( 'menu_id' ) ?? 0 );
		if ( $menu_id <= 0 ) {
			return $data;
		}

		$menu_items = wp_get_nav_menu_items( $menu_id );
		if ( ! is_array( $menu_items ) || empty( $menu_items ) ) {
			return $data;
		}

		// Index items by ID for O(1) parent lookups.
		$indexed = array();
		foreach ( $menu_items as $item ) {
			$indexed[ (int) $item->ID ] = $item;
		}

		$current_item = $this->find_current_item( $menu_items, (int) $data['post_id'] );

		if ( ! $current_item ) {
			// No matching item – show top-level menu items.
			$data['ancestors']     = array();
			$data['current_level'] = $this->items_at_parent( $menu_items, 0 );
			$data['children']      = array();
			$data['has_children']  = false;
			return $data;
		}

		$parent_id = (int) $current_item->menu_item_parent;

		$data['ancestors']     = $this->build_ancestors( $indexed, $parent_id );
		$data['current_level'] = $this->items_at_parent( $menu_items, $parent_id, (int) $current_item->ID );

		$children = $this->items_at_parent( $menu_items, (int) $current_item->ID );
		if ( empty( $children ) && $this->is_woo_category_item( $current_item ) ) {
			$children = $this->get_woo_children( (int) $current_item->object_id );
		}

		$data['children']     = $children;
		$data['has_children'] = ! empty( $children );

		// Accordion layout: build tree from menu structure if not already set.
		if ( 'accordion' === ( $args['layout'] ?? '' ) && ! isset( $data['tree'] ) ) {
			$data['tree'] = $this->build_menu_tree( $menu_items, 0 );
		}

		return $data;
	}

	/* ------------------------------------------------------------------
	 * Menu navigation helpers
	 * ----------------------------------------------------------------- */

	/**
	 * Finds the menu item matching the current page.
	 *
	 * @param \WP_Post[] $items   All nav menu items.
	 * @param int        $post_id Current post ID.
	 * @return \WP_Post|null
	 */
	private function find_current_item( array $items, int $post_id ): ?\WP_Post {
		// 1. Post ID match (most reliable for post_type menu items).
		if ( $post_id > 0 ) {
			foreach ( $items as $item ) {
				if ( 'post_type' === $item->type && (int) $item->object_id === $post_id ) {
					return $item;
				}
			}
		}

		// 2. Taxonomy term match (category/tag/custom taxonomy archives).
		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				foreach ( $items as $item ) {
					if ( 'taxonomy' === $item->type
						&& $item->object === $term->taxonomy
						&& (int) $item->object_id === $term->term_id ) {
						return $item;
					}
				}
			}
		}

		// 3. URL match fallback (custom links, post-type archives).
		$current_url = trailingslashit( strtok( home_url( add_query_arg( array() ) ), '?' ) );
		foreach ( $items as $item ) {
			if ( $item->url && trailingslashit( $item->url ) === $current_url ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Returns drillnav items that are direct menu children of $parent_id.
	 *
	 * @param \WP_Post[] $all_items  All menu items.
	 * @param int        $parent_id  0 = top-level menu items.
	 * @param int        $current_id Item ID to mark as current (0 = none).
	 * @return array<int,array<string,mixed>>
	 */
	private function items_at_parent( array $all_items, int $parent_id, int $current_id = 0 ): array {
		$result = array();
		foreach ( $all_items as $item ) {
			if ( (int) $item->menu_item_parent !== $parent_id ) {
				continue;
			}
			$has_children = $this->item_has_menu_children( $all_items, (int) $item->ID );
			if ( ! $has_children && $this->is_woo_category_item( $item ) ) {
				$has_children = $this->woo_category_has_children( (int) $item->object_id );
			}
			$result[] = array(
				'id'           => (int) $item->ID,
				'title'        => $item->title,
				'url'          => $item->url,
				'is_current'   => ( (int) $item->ID === $current_id ),
				'has_children' => $has_children,
				'post_type'    => $item->object,
			);
		}
		return $result;
	}

	/**
	 * Builds the ancestor chain (root → immediate parent) from the indexed menu items.
	 *
	 * @param array<int,\WP_Post> $indexed   Items indexed by ID.
	 * @param int                 $parent_id Starting parent ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_ancestors( array $indexed, int $parent_id ): array {
		$ancestors = array();
		$current   = $parent_id;
		$guard     = 0;
		while ( $current > 0 && isset( $indexed[ $current ] ) && $guard++ < 20 ) {
			$item = $indexed[ $current ];
			array_unshift(
				$ancestors,
				array(
					'id'           => (int) $item->ID,
					'title'        => $item->title,
					'url'          => $item->url,
					'is_current'   => false,
					'has_children' => true,
					'post_type'    => $item->object,
				)
			);
			$current = (int) $item->menu_item_parent;
		}
		return $ancestors;
	}

	/**
	 * Builds a recursive accordion tree from menu items.
	 *
	 * @param \WP_Post[] $all_items
	 * @param int        $parent_id
	 * @return array<int,array<string,mixed>>
	 */
	private function build_menu_tree( array $all_items, int $parent_id ): array {
		$result = array();
		foreach ( $all_items as $item ) {
			if ( (int) $item->menu_item_parent !== $parent_id ) {
				continue;
			}
			$children = $this->build_menu_tree( $all_items, (int) $item->ID );
			$result[] = array(
				'id'           => (int) $item->ID,
				'title'        => $item->title,
				'url'          => $item->url,
				'is_current'   => false,
				'has_children' => ! empty( $children ),
				'post_type'    => $item->object,
				'children'     => $children,
			);
		}
		return $result;
	}

	/**
	 * Checks whether a menu item has direct children in the same menu.
	 *
	 * @param \WP_Post[] $all_items
	 * @param int        $item_id
	 * @return bool
	 */
	private function item_has_menu_children( array $all_items, int $item_id ): bool {
		foreach ( $all_items as $item ) {
			if ( (int) $item->menu_item_parent === $item_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks whether an item links to a WooCommerce product category.
	 *
	 * @param \WP_Post $item
	 * @return bool
	 */
	private function is_woo_category_item( \WP_Post $item ): bool {
		return class_exists( 'WooCommerce' )
			&& 'taxonomy' === $item->type
			&& 'product_cat' === $item->object;
	}

	/**
	 * Returns WooCommerce product sub-categories as drillnav items (Hybrid mode).
	 *
	 * @param int $term_id Parent product category term ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_woo_children( int $term_id ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $term_id,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$items = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$has_children = ! empty( get_terms(
				array(
					'taxonomy' => 'product_cat',
					'parent'   => $term->term_id,
					'number'   => 1,
					'fields'   => 'ids',
				)
			) );
			$items[] = array(
				'id'           => $term->term_id,
				'title'        => $term->name,
				'url'          => get_term_link( $term, 'product_cat' ),
				'is_current'   => false,
				'has_children' => $has_children,
				'post_type'    => 'product_cat',
			);
		}
		return $items;
	}

	/**
	 * Checks whether a WooCommerce product category has child categories.
	 *
	 * @param int $term_id
	 * @return bool
	 */
	private function woo_category_has_children( int $term_id ): bool {
		return ! empty( get_terms(
			array(
				'taxonomy' => 'product_cat',
				'parent'   => $term_id,
				'number'   => 1,
				'fields'   => 'ids',
			)
		) );
	}
}
