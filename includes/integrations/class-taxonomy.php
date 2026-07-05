<?php
/**
 * General hierarchical taxonomy integration (Pro feature).
 *
 * Provides contextual navigation for any public hierarchical taxonomy
 * other than the built-in 'category' (which is handled by the Blog
 * integration) and 'post_tag' (flat, no hierarchy).
 *
 * Supported contexts:
 *   Taxonomy term archive  →  ancestors → sibling terms → child terms
 *   Single post with terms →  ancestors → sibling terms (closest term level)
 *
 * @package DrillNav
 */

namespace DrillNav\Integrations;

defined( 'ABSPATH' ) || exit;

use DrillNav\Cache;
use DrillNav\Loader;
use DrillNav\Navigator;
use DrillNav\Settings;

/**
 * Taxonomy navigation integration (Pro).
 */
class Taxonomy {

	public function __construct(
		private readonly Navigator $navigator,
		private readonly Cache     $cache,
		private readonly Settings  $settings
	) {}

	/**
	 * Registers hooks with the loader.
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_filter( 'drillnav_current_context', array( $this, 'filter_taxonomy_context' ) );
		$loader->add_filter( 'drillnav_nav_items',       array( $this, 'filter_nav_items' ), 15, 2 );
	}

	/* ------------------------------------------------------------------
	 * Context filter
	 * ----------------------------------------------------------------- */

	/**
	 * Extends the page context with taxonomy-specific information when
	 * on a hierarchical taxonomy archive or a related single post.
	 *
	 * @param array<string,mixed> $context
	 * @return array<string,mixed>
	 */
	public function filter_taxonomy_context( array $context ): array {
		// Skip if Blog integration already handled this context.
		if ( ! empty( $context['blog_type'] ) ) {
			return $context;
		}

		if ( is_tax() ) {
			$term     = get_queried_object();
			$taxonomy = $term instanceof \WP_Term ? $term->taxonomy : '';

			if ( $taxonomy && $this->is_supported_taxonomy( $taxonomy ) ) {
				$context['tax_type']      = 'tax_archive';
				$context['tax_taxonomy']  = $taxonomy;
				$context['tax_term_id']   = (int) $term->term_id;
				$context['tax_parent_id'] = (int) $term->parent;
				$context['tax_ancestors'] = array_reverse(
					array_map( 'intval', get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) )
				);
			}
		} elseif ( is_singular() ) {
			$post_id   = (int) get_queried_object_id();
			$post_type = get_post_type( $post_id ) ?: 'post';

			foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
				$tax_obj = get_taxonomy( $taxonomy );
				if ( ! $tax_obj || ! in_array( $post_type, (array) $tax_obj->object_type, true ) ) {
					continue;
				}

				$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'orderby' => 'parent', 'order' => 'ASC' ) );
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					continue;
				}

				// Use the deepest term as primary.
				$primary = $this->get_deepest_term( $terms );
				if ( ! $primary ) {
					continue;
				}

				$context['tax_type']      = 'tax_single';
				$context['tax_taxonomy']  = $taxonomy;
				$context['tax_term_id']   = (int) $primary->term_id;
				$context['tax_parent_id'] = (int) $primary->parent;
				$context['tax_ancestors'] = array_reverse(
					array_map( 'intval', get_ancestors( $primary->term_id, $taxonomy, 'taxonomy' ) )
				);
				$context['tax_post_id']   = $post_id;
				break;
			}
		}

		return $context;
	}

	/* ------------------------------------------------------------------
	 * Nav items filter
	 * ----------------------------------------------------------------- */

	/**
	 * Replaces default nav data with taxonomy-hierarchy data.
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function filter_nav_items( array $data, array $args ): array {
		$context  = \drillnav()->context->get();
		$tax_type = $context['tax_type'] ?? '';

		if ( ! $tax_type ) {
			return $data;
		}

		$taxonomy  = (string) ( $context['tax_taxonomy'] ?? '' );
		$term_id   = (int) ( $context['tax_term_id'] ?? 0 );
		$parent_id = (int) ( $context['tax_parent_id'] ?? 0 );
		$ancestors = (array) ( $context['tax_ancestors'] ?? array() );

		// Build ancestors list.
		$data['ancestors'] = $this->build_ancestors( $taxonomy, $ancestors );

		// Current level = siblings (children of the parent term).
		$data['current_level'] = $this->get_term_items( $taxonomy, $parent_id, $term_id );

		// Children = direct child terms of the current term.
		$children              = $this->get_term_items( $taxonomy, $term_id, 0 );
		$data['children']      = $children;
		$data['has_children']  = ! empty( $children );

		return $data;
	}

	/* ------------------------------------------------------------------
	 * Data helpers
	 * ----------------------------------------------------------------- */

	/**
	 * Returns the nav items (terms) for the given parent term.
	 *
	 * @param string $taxonomy
	 * @param int    $parent_id   Parent term ID (0 = top level).
	 * @param int    $current_id  Term ID to mark as current (0 = none).
	 * @return array<int,array<string,mixed>>
	 */
	private function get_term_items( string $taxonomy, int $parent_id, int $current_id ): array {
		$lang      = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'tax_terms_' . $taxonomy . '_' . $parent_id . '_' . $lang;
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return $this->mark_current_items( (array) $cached, $current_id );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'parent'     => $parent_id,
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

			$translated_id = (int) apply_filters( 'drillnav_translate_post_id', $term->term_id, $taxonomy );
			$translated    = ( $translated_id !== $term->term_id ) ? get_term( $translated_id, $taxonomy ) : $term;
			if ( ! $translated instanceof \WP_Term ) {
				$translated = $term;
			}

			$has_children = (int) $translated->count > 0 || ! empty( get_terms( array(
				'taxonomy' => $taxonomy,
				'parent'   => $translated->term_id,
				'number'   => 1,
				'fields'   => 'ids',
			) ) );

			$items[] = array(
				'id'           => $translated->term_id,
				'title'        => $translated->name,
				'url'          => get_term_link( $translated, $taxonomy ),
				// Applied at retrieval time – never baked into the shared cache.
				'is_current'   => false,
				'has_children' => $has_children,
				'post_type'    => $taxonomy,
			);
		}

		$this->cache->set( $cache_key, $items );
		return $this->mark_current_items( $items, $current_id );
	}

	/**
	 * Marks the term matching the given ID as current.
	 *
	 * Applied after cache retrieval so the flag is never stored in cache
	 * entries shared between pages.
	 *
	 * @param array<int,array<string,mixed>> $items
	 * @param int                            $current_id
	 * @return array<int,array<string,mixed>>
	 */
	private function mark_current_items( array $items, int $current_id ): array {
		if ( $current_id <= 0 ) {
			return $items;
		}
		foreach ( $items as &$item ) {
			$item['is_current'] = isset( $item['id'] ) && (int) $item['id'] === $current_id;
		}
		unset( $item );
		return $items;
	}

	/**
	 * Builds the ancestors list (root → immediate parent) as nav items.
	 *
	 * @param string $taxonomy
	 * @param int[]  $ancestor_ids  Ordered root → parent.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_ancestors( string $taxonomy, array $ancestor_ids ): array {
		$items = array();
		foreach ( $ancestor_ids as $ancestor_id ) {
			$term = get_term( $ancestor_id, $taxonomy );
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$items[] = array(
				'id'           => $term->term_id,
				'title'        => $term->name,
				'url'          => get_term_link( $term, $taxonomy ),
				'is_current'   => false,
				'has_children' => true,
				'post_type'    => $taxonomy,
			);
		}
		return $items;
	}

	/**
	 * Returns all public hierarchical taxonomies except those handled
	 * by the Blog integration (category, post_tag).
	 *
	 * @return string[]
	 */
	private function get_supported_taxonomies(): array {
		$all = get_taxonomies(
			array(
				'public'       => true,
				'hierarchical' => true,
			),
			'names'
		);

		return array_values( array_diff( $all, array( 'category' ) ) );
	}

	/**
	 * Checks if a taxonomy is supported by this integration.
	 *
	 * @param string $taxonomy
	 * @return bool
	 */
	private function is_supported_taxonomy( string $taxonomy ): bool {
		return in_array( $taxonomy, $this->get_supported_taxonomies(), true );
	}

	/**
	 * Returns the deepest term from a list (most specific).
	 *
	 * @param \WP_Term[] $terms
	 * @return \WP_Term|null
	 */
	private function get_deepest_term( array $terms ): ?\WP_Term {
		$deepest = null;
		$max     = -1;

		foreach ( $terms as $term ) {
			$depth = count( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
			if ( $depth > $max ) {
				$max     = $depth;
				$deepest = $term;
			}
		}

		return $deepest;
	}
}
