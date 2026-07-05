<?php
/**
 * Blog / Posts integration.
 *
 * Free feature – no Freemius gate required.
 *
 * Provides contextual navigation for WordPress blog pages:
 *
 *   Blog index (is_home)
 *     └── Category archive (is_category)
 *           └── Sub-category archive
 *                 └── Post list (leaf-level items)
 *   Single post (is_single, post_type=post)
 *     → shows sibling posts in same category
 *   Tag archive (is_tag)
 *     → shows posts with this tag
 *
 * The "blog page" set in Settings > Reading acts as the navigation root.
 * Post categories form the hierarchy. Individual posts appear at the leaf level.
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
 * Blog navigation integration.
 */
class Blog {

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
		$loader->add_filter( 'drillnav_current_context',    array( $this, 'filter_blog_context' ) );
		$loader->add_filter( 'drillnav_nav_items',          array( $this, 'filter_nav_items' ), 10, 2 );
		$loader->add_filter( 'drillnav_term_children_items', array( $this, 'filter_term_children' ), 10, 3 );

		// Append ?from_cat= to post links when browsing a category archive.
		$loader->add_filter( 'the_permalink', array( $this, 'append_from_cat' ), 10, 2 );
	}

	/* ------------------------------------------------------------------
	 * Context filter
	 * ----------------------------------------------------------------- */

	/**
	 * Extends the page context with blog-specific information.
	 *
	 * @param array<string,mixed> $context
	 * @return array<string,mixed>
	 */
	public function filter_blog_context( array $context ): array {
		if ( is_home() ) {
			$context['blog_type']    = 'blog_index';
			$context['blog_page_id'] = (int) get_option( 'page_for_posts' );

		} elseif ( is_category() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$context['blog_type']      = 'post_category';
				$context['blog_cat_id']    = (int) $term->term_id;
				$context['blog_cat_parent'] = (int) $term->parent;
				$context['blog_cat_ancestors'] = array_reverse(
					array_map( 'intval', get_ancestors( $term->term_id, 'category', 'taxonomy' ) )
				);
			}

		} elseif ( is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$context['blog_type']   = 'post_tag';
				$context['blog_tag_id'] = (int) $term->term_id;
			}

		} elseif ( is_singular( 'post' ) ) {
			$post_id  = (int) get_queried_object_id();
			$cat_ids  = wp_get_post_categories( $post_id );
			// Prefer category passed via ?from_cat= (mirrors WooCommerce approach).
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$from_cat = isset( $_GET['from_cat'] ) ? absint( $_GET['from_cat'] ) : 0;
			$primary  = ( $from_cat && in_array( $from_cat, array_map( 'intval', $cat_ids ), true ) )
				? $from_cat
				: (int) ( reset( $cat_ids ) ?: 0 );

			if ( $primary > 0 ) {
				$term = get_term( $primary, 'category' );
				if ( $term instanceof \WP_Term ) {
					$context['blog_type']      = 'single_post';
					$context['blog_cat_id']    = $primary;
					$context['blog_cat_parent'] = (int) $term->parent;
					$context['blog_cat_ancestors'] = array_reverse(
						array_map( 'intval', get_ancestors( $primary, 'category', 'taxonomy' ) )
					);
					$context['blog_post_id']   = $post_id;
				}
			}
		}

		return $context;
	}

	/* ------------------------------------------------------------------
	 * Nav items filter
	 * ----------------------------------------------------------------- */

	/**
	 * Replaces the default nav data with blog-aware data on blog pages.
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function filter_nav_items( array $data, array $args ): array {
		$context   = \drillnav()->context->get();
		$blog_type = $context['blog_type'] ?? '';

		if ( ! $blog_type ) {
			return $data;
		}

		$blog_settings = $this->get_blog_settings();

		switch ( $blog_type ) {
			case 'blog_index':
				$data['ancestors']     = $this->build_blog_ancestors( $context, $blog_settings );
				$data['current_level'] = $this->get_categories( 0, $blog_settings );
				$data['children']      = array();
				$data['has_children']  = false;
				break;

			case 'post_category':
				$cat_id = (int) ( $context['blog_cat_id'] ?? 0 );
				$data['ancestors']     = $this->build_blog_ancestors( $context, $blog_settings );
				$data['current_level'] = $this->mark_current_items(
					$this->get_categories( (int) ( $context['blog_cat_parent'] ?? 0 ), $blog_settings ),
					$cat_id
				);
				// Show subcategories if they exist, otherwise show posts (leaf level).
				$sub_cats = $this->get_categories( $cat_id, $blog_settings );
				if ( ! empty( $sub_cats ) ) {
					$data['children']     = $sub_cats;
					$data['has_children'] = true;
				} elseif ( $blog_settings['show_posts'] ) {
					$data['children']     = $this->get_posts_for_category( $cat_id, $blog_settings );
					$data['has_children'] = ! empty( $data['children'] );
				}
				break;

			case 'single_post':
				$cat_id = (int) ( $context['blog_cat_id'] ?? 0 );
				$data['ancestors']     = $this->build_blog_ancestors( $context, $blog_settings );
				$data['current_level'] = $this->get_categories( (int) ( $context['blog_cat_parent'] ?? 0 ), $blog_settings );
				if ( $blog_settings['show_posts'] ) {
					$data['children']     = $this->mark_current_items(
						$this->get_posts_for_category( $cat_id, $blog_settings ),
						(int) ( $context['blog_post_id'] ?? 0 )
					);
					$data['has_children'] = ! empty( $data['children'] );
				}
				break;

			case 'post_tag':
				$tag_id = (int) ( $context['blog_tag_id'] ?? 0 );
				$data['ancestors']     = $this->build_blog_ancestors( $context, $blog_settings );
				$data['current_level'] = $blog_settings['show_posts']
					? $this->get_posts_for_tag( $tag_id, $blog_settings )
					: array();
				break;
		}

		return $data;
	}

	/**
	 * Supplies child items for the REST drill-down when expanding a category.
	 * Shows subcategories, or posts at the leaf level (mirrors the SSR logic).
	 *
	 * @param array<int,array<string,mixed>>|null $items
	 * @param string                              $taxonomy
	 * @param int                                 $parent_id
	 * @return array<int,array<string,mixed>>|null
	 */
	public function filter_term_children( $items, string $taxonomy, int $parent_id ) {
		if ( 'category' !== $taxonomy || is_array( $items ) ) {
			return $items;
		}

		$blog_settings = $this->get_blog_settings();

		$categories = $this->get_categories( $parent_id, $blog_settings );
		if ( ! empty( $categories ) || ! $blog_settings['show_posts'] ) {
			return $categories;
		}

		return $this->get_posts_for_category( $parent_id, $blog_settings );
	}

	/**
	 * Marks the item with the given ID as current.
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

	/* ------------------------------------------------------------------
	 * Data retrieval
	 * ----------------------------------------------------------------- */

	/**
	 * Returns post categories for a given parent (0 = top-level).
	 *
	 * @param int                 $parent_id
	 * @param array<string,mixed> $blog_settings
	 * @return array<int,array<string,mixed>>
	 */
	public function get_categories( int $parent_id, array $blog_settings ): array {
		$hide_empty = ! empty( $blog_settings['hide_empty'] );
		$lang       = (string) apply_filters( 'drillnav_language', '' );
		$cache_key  = 'blog_cats_' . $parent_id . '_' . (int) $hide_empty . '_' . $lang;

		$cached = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'parent'     => $parent_id,
				'hide_empty' => $hide_empty,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$this->cache->set( $cache_key, array() );
			return array();
		}

		$items = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$has_children = $this->category_has_children( $term->term_id );

			$items[] = array(
				'id'           => $term->term_id,
				'title'        => $term->name,
				'url'          => get_term_link( $term ),
				'parent_id'    => $parent_id,
				'post_type'    => 'category',
				'menu_order'   => 0,
				// Applied at retrieval time – never baked into the shared cache.
				'is_current'   => false,
				'has_children' => $has_children || ( $blog_settings['show_posts'] && $term->count > 0 ),
				'count'        => (int) $term->count,
			);
		}

		$this->cache->set( $cache_key, $items );
		return $items;
	}

	/**
	 * Returns published posts for a given category.
	 *
	 * @param int                 $cat_id
	 * @param array<string,mixed> $blog_settings
	 * @return array<int,array<string,mixed>>
	 */
	public function get_posts_for_category( int $cat_id, array $blog_settings ): array {
		$limit     = (int) ( $blog_settings['posts_per_page'] ?? 10 );
		$lang      = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'blog_posts_cat_' . $cat_id . '_' . $limit . '_' . $lang;

		$cached = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		$query = new \WP_Query(
			array(
				'cat'            => $cat_id,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit > 0 ? $limit : -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$items[] = array(
				'id'           => $post->ID,
				'title'        => get_the_title( $post ),
				'url'          => add_query_arg( 'from_cat', $cat_id, get_permalink( $post ) ),
				'parent_id'    => $cat_id,
				'post_type'    => 'post',
				'menu_order'   => 0,
				// Applied at retrieval time – never baked into the shared cache.
				'is_current'   => false,
				'has_children' => false,
			);
		}

		wp_reset_postdata();

		$this->cache->set( $cache_key, $items );
		return $items;
	}

	/**
	 * Returns published posts for a given tag.
	 *
	 * @param int                 $tag_id
	 * @param array<string,mixed> $blog_settings
	 * @return array<int,array<string,mixed>>
	 */
	public function get_posts_for_tag( int $tag_id, array $blog_settings ): array {
		$limit     = (int) ( $blog_settings['posts_per_page'] ?? 10 );
		$lang      = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'blog_posts_tag_' . $tag_id . '_' . $limit . '_' . $lang;

		$cached = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		$query = new \WP_Query(
			array(
				'tag__in'        => array( $tag_id ),
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit > 0 ? $limit : -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$items[] = array(
				'id'           => $post->ID,
				'title'        => get_the_title( $post ),
				'url'          => get_permalink( $post ),
				'parent_id'    => $tag_id,
				'post_type'    => 'post',
				'menu_order'   => 0,
				'is_current'   => false,
				'has_children' => false,
			);
		}

		wp_reset_postdata();

		$this->cache->set( $cache_key, $items );
		return $items;
	}

	/**
	 * Checks whether a category has direct child categories.
	 *
	 * @param int $cat_id
	 * @return bool
	 */
	public function category_has_children( int $cat_id ): bool {
		$lang      = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'blog_has_children_' . $cat_id . '_' . $lang;
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$children = get_terms(
			array(
				'taxonomy'   => 'category',
				'parent'     => $cat_id,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
			)
		);

		$result = ! is_wp_error( $children ) && ! empty( $children );
		// Stored as int: boolean false would be indistinguishable from a cache miss.
		$this->cache->set( $cache_key, (int) $result );
		return $result;
	}

	/* ------------------------------------------------------------------
	 * from_cat injection
	 * ----------------------------------------------------------------- */

	/**
	 * Appends ?from_cat= to post permalinks when viewed from a category archive.
	 * This preserves navigation context on the single post page.
	 *
	 * @param string   $permalink
	 * @param \WP_Post $post
	 * @return string
	 */
	public function append_from_cat( string $permalink, $post ): string {
		if ( ! is_category() ) {
			return $permalink;
		}
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return $permalink;
		}
		return add_query_arg( 'from_cat', $term->term_id, $permalink );
	}

	/* ------------------------------------------------------------------
	 * Ancestor builder
	 * ----------------------------------------------------------------- */

	/**
	 * Builds the ancestor breadcrumb trail for blog pages.
	 * Root is the blog/posts page (or home URL if no posts page is set).
	 *
	 * @param array<string,mixed> $context
	 * @param array<string,mixed> $blog_settings
	 * @return array<int,array<string,mixed>>
	 */
	private function build_blog_ancestors( array $context, array $blog_settings ): array {
		$items      = array();
		$blog_type  = $context['blog_type'] ?? '';

		// Root: blog index / posts page.
		if ( 'blog_index' !== $blog_type ) {
			$blog_page_id = (int) apply_filters( 'drillnav_translate_post_id', (int) get_option( 'page_for_posts' ), 'page' );
			$blog_url     = $blog_page_id > 0 ? get_permalink( $blog_page_id ) : get_home_url();
			$blog_label   = $blog_page_id > 0
				? get_the_title( $blog_page_id )
				: ( (string) apply_filters( 'drillnav_translate_string', $blog_settings['blog_label'], 'blog_label' ) ?: __( 'Blog', 'drillnav-drilldown-navigation' ) );

			$items[] = array(
				'id'           => $blog_page_id ?: 0,
				'title'        => $blog_label,
				'url'          => $blog_url,
				'parent_id'    => -1,
				'post_type'    => 'blog_root',
				'menu_order'   => 0,
				'is_current'   => false,
				'has_children' => true,
			);
		}

		// Category ancestors (top-down).
		foreach ( ( $context['blog_cat_ancestors'] ?? array() ) as $ancestor_id ) {
			$term = get_term( (int) $ancestor_id, 'category' );
			if ( $term instanceof \WP_Term ) {
				$items[] = array(
					'id'           => $term->term_id,
					'title'        => $term->name,
					'url'          => get_term_link( $term ),
					'parent_id'    => (int) $term->parent,
					'post_type'    => 'category',
					'menu_order'   => 0,
					'is_current'   => false,
					'has_children' => true,
				);
			}
		}

		return $items;
	}

	/* ------------------------------------------------------------------
	 * Settings helper
	 * ----------------------------------------------------------------- */

	/**
	 * Returns blog-specific settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_blog_settings(): array {
		$all = $this->settings->all();
		return array(
			'show_posts'     => ! empty( $all['blog_show_posts'] ),
			'posts_per_page' => (int) ( $all['blog_posts_per_page'] ?? 10 ),
			'hide_empty'     => ! empty( $all['blog_hide_empty'] ),
			'blog_label'     => (string) ( $all['blog_label'] ?? '' ),
		);
	}
}
