<?php
/**
 * WooCommerce product category navigation integration.
 *
 * Pro feature – guarded by Freemius licence check.
 * Loads only when WooCommerce is active (checked in Plugin::init_components).
 *
 * Adapted from the contextual-category-nav module in taurus-sports-core,
 * rewritten to fit the DrillNav architecture and coding standards.
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
 * Extends DrillNav with WooCommerce product category navigation.
 *
 * When active (Pro licence), this class:
 *  - Provides category data via dedicated methods
 *  - Filters `drillnav_current_context` to detect WooCommerce pages
 *  - Filters `drillnav_nav_items` to inject category items
 *  - Adds REST endpoint: GET /wp-json/drillnav/v1/woo-children
 *  - Appends `?from_cat=` to product links to preserve navigation context
 */
class Woocommerce {

	public function __construct(
		private readonly Navigator $navigator,
		private readonly Cache     $cache,
		private readonly Settings  $settings
	) {}

	/**
	 * Registers hooks (only called when WooCommerce is active).
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		// Only activate WooCommerce features for Pro licence holders.
		if ( ! $this->is_pro_active() ) {
			return;
		}

		$loader->add_filter( 'drillnav_current_context',    array( $this, 'filter_woo_context' ) );
		$loader->add_filter( 'drillnav_nav_items',          array( $this, 'filter_nav_items' ), 10, 2 );
		$loader->add_filter( 'drillnav_term_children_items', array( $this, 'filter_term_children' ), 10, 3 );
		$loader->add_filter( 'woocommerce_loop_product_link', array( $this, 'append_from_cat' ), 10, 2 );
		$loader->add_action( 'rest_api_init',            array( $this, 'register_rest_routes' ) );

		// Invalidate category caches on taxonomy changes.
		$loader->add_action( 'edited_product_cat',  array( $this, 'invalidate_category_cache' ) );
		$loader->add_action( 'created_product_cat', array( $this, 'invalidate_category_cache' ) );
		$loader->add_action( 'deleted_product_cat', array( $this, 'invalidate_category_cache' ) );
	}

	/* ------------------------------------------------------------------
	 * Context filter
	 * ----------------------------------------------------------------- */

	/**
	 * Extends the standard page context with WooCommerce category info.
	 *
	 * @param array<string,mixed> $context
	 * @return array<string,mixed>
	 */
	public function filter_woo_context( array $context ): array {
		if ( is_product_category() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$context['woo_cat_id']       = (int) $term->term_id;
				$context['woo_cat_parent']   = (int) $term->parent;
				$context['woo_cat_ancestors'] = array_reverse(
					array_map( 'intval', get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) )
				);
				$context['is_woo_category']  = true;
			}
		} elseif ( is_product() ) {
			$cat_id = (int) ( isset( $_GET['from_cat'] ) ? absint( $_GET['from_cat'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( $cat_id > 0 && term_exists( $cat_id, 'product_cat' ) ) {
				$context['woo_cat_id']      = $cat_id;
				$context['is_woo_product']  = true;
			}
		} elseif ( is_shop() ) {
			$context['is_woo_shop'] = true;
		}

		return $context;
	}

	/* ------------------------------------------------------------------
	 * Nav items filter
	 * ----------------------------------------------------------------- */

	/**
	 * Injects WooCommerce category items into the navigation data when on a
	 * WooCommerce page.
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function filter_nav_items( array $data, array $args ): array {
		$context = \drillnav()->context->get();

		if ( empty( $context['is_woo_category'] ) && empty( $context['is_woo_shop'] ) && empty( $context['is_woo_product'] ) ) {
			return $data;
		}

		$settings = $this->get_woo_settings();

		if ( ! empty( $context['is_woo_category'] ) || ! empty( $context['is_woo_product'] ) ) {
			$parent_cat_id = $context['woo_cat_id'] ?? 0;

			// Ancestors.
			$data['ancestors'] = $this->build_woo_ancestors( $context, $settings );

			// Current level = siblings of the current category.
			$current_parent = $context['woo_cat_parent'] ?? 0;
			$data['current_level'] = $this->get_woo_categories( (int) $current_parent, $settings );
		} elseif ( ! empty( $context['is_woo_shop'] ) ) {
			$data['current_level'] = $this->get_woo_categories( 0, $settings );
		}

		return $data;
	}

	/* ------------------------------------------------------------------
	 * Category data
	 * ----------------------------------------------------------------- */

	/**
	 * Returns formatted category items for a given parent.
	 *
	 * @param int                 $parent_id 0 = top-level.
	 * @param array<string,mixed> $settings  WooCommerce-specific settings.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_woo_categories( int $parent_id, array $settings ): array {
		$excluded   = $settings['excluded_categories'] ?? array();
		$hide_empty = ! empty( $settings['hide_empty'] );
		$lang       = (string) apply_filters( 'drillnav_language', '' );
		$cache_key  = 'woo_cats_' . $parent_id . '_' . (int) $hide_empty . '_' . $lang;

		$cached = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return $this->mark_current_items( (array) $cached );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $parent_id,
				'hide_empty' => false, // We filter manually to check stock.
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$items = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			// Skip excluded categories.
			if ( in_array( $term->term_id, $excluded, true ) ) {
				continue;
			}

			// Skip "Uncategorized" unless configured to show.
			if ( 'uncategorized' === $term->slug && empty( $settings['show_uncategorized'] ) ) {
				continue;
			}

			// When hide_empty is on: skip if no visible products.
			if ( $hide_empty && ! $this->category_has_visible_products( $term->term_id ) ) {
				continue;
			}

			$has_children = $this->category_has_children( $term->term_id );

			$item = array(
				'id'           => $term->term_id,
				'title'        => $term->name,
				'url'          => get_term_link( $term ),
				'parent_id'    => $parent_id,
				'post_type'    => 'product_cat',
				'menu_order'   => 0,
				// Applied at retrieval time – never baked into the shared cache.
				'is_current'   => false,
				'has_children' => $has_children,
			);

			if ( ! empty( $settings['show_count'] ) ) {
				$item['count']      = (int) $term->count;
				$item['show_count'] = true; // Opt-in flag read by the template and JS.
			}

			$items[] = $item;
		}

		$this->cache->set( $cache_key, $items );
		return $this->mark_current_items( $items );
	}

	/**
	 * Marks the category matching the currently viewed category archive as current.
	 *
	 * Applied after cache retrieval so the flag is never stored in cache
	 * entries shared between pages.
	 *
	 * @param array<int,array<string,mixed>> $items
	 * @return array<int,array<string,mixed>>
	 */
	private function mark_current_items( array $items ): array {
		$current_id = is_product_category() ? (int) get_queried_object_id() : 0;
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
	 * Supplies child categories for the REST drill-down when expanding a
	 * product category item.
	 *
	 * @param array<int,array<string,mixed>>|null $items
	 * @param string                              $taxonomy
	 * @param int                                 $parent_id
	 * @return array<int,array<string,mixed>>|null
	 */
	public function filter_term_children( $items, string $taxonomy, int $parent_id ) {
		if ( 'product_cat' !== $taxonomy || is_array( $items ) ) {
			return $items;
		}
		return $this->get_woo_categories( $parent_id, $this->get_woo_settings() );
	}

	/**
	 * Checks whether a product category has any visible (in-stock/backorder) products,
	 * optionally applying Pro attribute filters (include/exclude by attribute term).
	 *
	 * Filter logic:
	 *  - 'exclude' rule: a category is hidden when ALL its matching products belong to
	 *                    the excluded attribute term (i.e. no product without that term exists).
	 *  - 'include' rule: a category is hidden when NONE of its products belong to the
	 *                    required attribute term.
	 *
	 * @param int                 $cat_id
	 * @param array<int,array<string,mixed>> $attribute_filters Optional override; defaults to settings.
	 * @return bool
	 */
	public function category_has_visible_products( int $cat_id, array $attribute_filters = array() ): bool {
		if ( empty( $attribute_filters ) ) {
			$attribute_filters = (array) $this->settings->get( 'woo_attribute_filters' );
		}

		// Build a cache key that includes the active filter rules.
		$filter_hash = empty( $attribute_filters ) ? 'none' : md5( wp_json_encode( $attribute_filters ) );
		$lang        = (string) apply_filters( 'drillnav_language', '' );
		$cache_key   = 'woo_has_visible_' . $cat_id . '_' . $filter_hash . '_' . $lang;
		$cached      = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$descendant_ids   = $this->get_descendant_category_ids( $cat_id );
		$descendant_ids[] = $cat_id;

		$cat_placeholders = implode( ',', array_fill( 0, count( $descendant_ids ), '%d' ) );

		global $wpdb;

		// Base query: published, in-stock / on-backorder products in this category tree.
		$base_sql = "SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id
			INNER JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id
			  AND pm_stock.meta_key = '_stock_status'
			WHERE tt_cat.term_id IN ($cat_placeholders)
			  AND tt_cat.taxonomy = 'product_cat'
			  AND p.post_type = 'product'
			  AND p.post_status = 'publish'
			  AND pm_stock.meta_value IN ('instock', 'onbackorder')";

		if ( empty( $attribute_filters ) ) {
			// Fast path: no filters applied.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ({$base_sql}) AS base", ...$descendant_ids ) );
			$result = $count > 0;
		} else {
			// Filtered path: retrieve matching product IDs, then apply filter rules.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$product_ids = $wpdb->get_col( $wpdb->prepare( $base_sql, ...$descendant_ids ) );

			if ( empty( $product_ids ) ) {
				$result = false;
			} else {
				$result = $this->apply_attribute_filters( array_map( 'intval', $product_ids ), $attribute_filters );
			}
		}

		// Stored as int: boolean false would be indistinguishable from a cache miss.
		$this->cache->set( $cache_key, (int) $result );
		return $result;
	}

	/**
	 * Applies attribute filter rules to a list of product IDs.
	 *
	 * Returns true when at least one product passes all filter rules.
	 *
	 * @param int[]                          $product_ids
	 * @param array<int,array<string,mixed>> $filters
	 * @return bool
	 */
	private function apply_attribute_filters( array $product_ids, array $filters ): bool {
		if ( empty( $product_ids ) || empty( $filters ) ) {
			return ! empty( $product_ids );
		}

		global $wpdb;
		$id_placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

		$passing = $product_ids;

		foreach ( $filters as $rule ) {
			$taxonomy = sanitize_key( $rule['taxonomy'] ?? '' );
			$term_id  = (int) ( $rule['term_id'] ?? 0 );
			$action   = $rule['action'] ?? 'exclude';

			if ( ! $taxonomy || ! $term_id ) {
				continue;
			}

			// Get product IDs that ARE linked to this attribute term.
			$current_placeholders = implode( ',', array_fill( 0, count( $passing ), '%d' ) );
			$args = array_merge( $passing, array( $taxonomy, $term_id ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$with_attr = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT object_id
					 FROM {$wpdb->term_relationships} tr
					 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					 WHERE tr.object_id IN ($current_placeholders)
					   AND tt.taxonomy = %s
					   AND tt.term_id  = %d",
					...$args
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$with_attr = array_map( 'intval', $with_attr );

			if ( 'exclude' === $action ) {
				// Keep products NOT associated with this term.
				$passing = array_values( array_diff( $passing, $with_attr ) );
			} else {
				// 'include': keep ONLY products associated with this term.
				$passing = array_values( array_intersect( $passing, $with_attr ) );
			}

			if ( empty( $passing ) ) {
				return false;
			}
		}

		return ! empty( $passing );
	}

	/**
	 * Returns all descendant category IDs (recursive).
	 *
	 * @param int $cat_id
	 * @return int[]
	 */
	public function get_descendant_category_ids( int $cat_id ): array {
		$lang      = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'woo_descendants_' . $cat_id . '_' . $lang;
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $cat_id,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $children ) || empty( $children ) ) {
			$this->cache->set( $cache_key, array() );
			return array();
		}

		$all = array();
		foreach ( (array) $children as $child_id ) {
			$all[] = (int) $child_id;
			$all   = array_merge( $all, $this->get_descendant_category_ids( (int) $child_id ) );
		}

		$all = array_unique( $all );
		$this->cache->set( $cache_key, $all );
		return $all;
	}

	/**
	 * Checks whether a category has any direct child categories.
	 *
	 * @param int $cat_id
	 * @return bool
	 */
	public function category_has_children( int $cat_id ): bool {
		$lang      = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'woo_has_children_' . $cat_id . '_' . $lang;
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
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
	 * from_cat link injection
	 * ----------------------------------------------------------------- */

	/**
	 * Appends ?from_cat= to product links so the navigation context is
	 * preserved when the user navigates to a product page.
	 *
	 * @param string      $link
	 * @param \WC_Product $product
	 * @return string
	 */
	public function append_from_cat( string $link, \WC_Product $product ): string {
		if ( ! is_product_category() ) {
			return $link;
		}
		$term = get_queried_object();
		if ( ! $term instanceof \WP_Term ) {
			return $link;
		}
		return add_query_arg( 'from_cat', $term->term_id, $link );
	}

	/* ------------------------------------------------------------------
	 * REST endpoint for children
	 * ----------------------------------------------------------------- */

	/** Registers the WooCommerce-specific REST endpoint. */
	public function register_rest_routes(): void {
		register_rest_route(
			'drillnav/v1',
			'/woo-children',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_woo_children' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'cat_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * REST handler: returns direct child categories as JSON.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_woo_children( \WP_REST_Request $request ) {
		$cat_id   = (int) $request->get_param( 'cat_id' );
		$settings = $this->get_woo_settings();
		$items    = $this->get_woo_categories( $cat_id, $settings );
		return rest_ensure_response( $items );
	}

	/* ------------------------------------------------------------------
	 * Cache invalidation
	 * ----------------------------------------------------------------- */

	/** @param int $term_id */
	public function invalidate_category_cache( int $term_id ): void {
		$this->cache->flush();
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------- */

	/**
	 * Returns WooCommerce-specific settings from the plugin options.
	 *
	 * @return array<string,mixed>
	 */
	private function get_woo_settings(): array {
		$all = $this->settings->all();
		return array(
			'excluded_categories' => array_map( 'absint', (array) ( $all['woo_excluded_categories'] ?? array() ) ),
			'show_count'          => ! empty( $all['woo_show_count'] ),
			'hide_empty'          => ! empty( $all['woo_hide_empty'] ),
			'show_uncategorized'  => ! empty( $all['woo_show_uncategorized'] ),
			'group_title'         => (string) ( $all['woo_group_title'] ?? '' ),
		);
	}

	/**
	 * Builds the ancestor item array for a WooCommerce category page.
	 *
	 * @param array<string,mixed> $context
	 * @param array<string,mixed> $settings
	 * @return array<int,array<string,mixed>>
	 */
	private function build_woo_ancestors( array $context, array $settings ): array {
		$items = array();

		$group_title = (string) apply_filters( 'drillnav_translate_string', $settings['group_title'], 'woo_group_title' );
		if ( '' === $group_title ) {
			$group_title = __( 'Shop', 'drillnav-drilldown-navigation' );
		}

		$shop_page_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_page_id > 0 ) {
			$items[] = array(
				'id'         => 0,
				'title'      => $group_title,
				'url'        => get_permalink( $shop_page_id ) ?: get_post_type_archive_link( 'product' ),
				'parent_id'  => -1,
				'post_type'  => '',
				'menu_order' => 0,
				'is_current' => false,
			);
		}

		foreach ( ( $context['woo_cat_ancestors'] ?? array() ) as $ancestor_id ) {
			$term = get_term( (int) $ancestor_id, 'product_cat' );
			if ( $term instanceof \WP_Term ) {
				$items[] = array(
					'id'         => $term->term_id,
					'title'      => $term->name,
					'url'        => get_term_link( $term ),
					'parent_id'  => (int) $term->parent,
					'post_type'  => 'product_cat',
					'menu_order' => 0,
					'is_current' => false,
				);
			}
		}

		return $items;
	}

	/**
	 * Checks whether a Freemius Pro licence is active.
	 *
	 * @return bool
	 */
	private function is_pro_active(): bool {
		if ( ! function_exists( 'drillnav_fs' ) ) {
			return false;
		}
		return (bool) drillnav_fs()->is__premium_only();
	}
}
