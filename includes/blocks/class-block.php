<?php
/**
 * Gutenberg block registration and server-side rendering.
 *
 * @package DrillNav
 */

namespace DrillNav\Blocks;

defined( 'ABSPATH' ) || exit;

use DrillNav\Cache;
use DrillNav\Loader;
use DrillNav\Navigator;
use DrillNav\Settings;

/**
 * Registers the drillnav/contextual-nav block and its REST preview endpoint.
 */
class Block {

	public function __construct(
		private readonly Navigator $navigator,
		private readonly Settings  $settings,
		private readonly Cache     $cache
	) {}

	/**
	 * Registers hooks with the loader.
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'init', array( $this, 'register_block' ) );
		$loader->add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/** Registers the block type using block.json as the source of truth. */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$block_args = array(
			'render_callback' => array( $this, 'render' ),
		);

		// Block themes render the template before wp_head, so styles attached
		// to the block type are printed in the <head> (no flash of unstyled
		// content) – and only on pages that actually contain the block.
		// Classic themes would load block styles on every page instead, so
		// they keep the on-demand enqueue in render().
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			$style_handles = array();
			if ( $this->settings->get( 'load_css' ) ) {
				$style_handles[] = 'drillnav-frontend';
			}
			if ( $this->settings->get( 'load_a11y_css' ) ) {
				$style_handles[] = 'drillnav-a11y';
			}
			if ( $style_handles ) {
				$block_args['style_handles'] = $style_handles;
			}
		}

		register_block_type(
			DRILLNAV_PLUGIN_DIR . 'blocks/drillnav/block.json',
			$block_args
		);

		// Enqueue the editor script separately so it loads in the block editor.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/** Enqueues the block-editor.js script in the editor. */
	public function enqueue_editor_assets(): void {
		wp_enqueue_script(
			'drillnav-block-editor',
			DRILLNAV_PLUGIN_URL . 'assets/js/block-editor.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-server-side-render',
			),
			DRILLNAV_VERSION,
			true
		);

		wp_set_script_translations(
			'drillnav-block-editor',
			'drillnav-drilldown-navigation',
			DRILLNAV_PLUGIN_DIR . 'languages'
		);

		$is_pro      = \DrillNav\Plugin::is_pro();
		$upgrade_url = ( ! $is_pro && function_exists( 'drillnav_fs' ) ) ? drillnav_fs()->get_upgrade_url() : '';
		wp_localize_script(
			'drillnav-block-editor',
			'drillnavBlock',
			array(
				'isPro'      => $is_pro,
				'upgradeUrl' => is_string( $upgrade_url ) && '' !== $upgrade_url ? $upgrade_url : '#',
			)
		);
	}

	/**
	 * Server-side render callback for the block.
	 *
	 * @param array<string,mixed> $attributes Block attributes from block.json.
	 * @param string              $content    Block inner content (unused – dynamic block).
	 * @return string HTML output.
	 */
	public function render( array $attributes, string $content ): string {
		$args          = $this->attributes_to_args( $attributes );
		$mobile_toggle = ! empty( $args['mobile_toggle'] );
		unset( $args['mobile_toggle'] );

		$nav_data = $this->navigator->get_nav_data( $args );

		if ( empty( $nav_data['current_level'] ) ) {
			return '';
		}

		// Ensure frontend assets are enqueued (for non-shortcode placements).
		if ( $this->settings->get( 'load_css' ) ) {
			wp_enqueue_style( 'drillnav-frontend' );
		}
		if ( $this->settings->get( 'load_a11y_css' ) ) {
			wp_enqueue_style( 'drillnav-a11y' );
		}
		if ( \DrillNav\Plugin::is_pro() ) {
			wp_enqueue_style( 'dashicons' ); // Per-item icon support (Pro).
		}
		wp_enqueue_script( 'drillnav-frontend' );

		$instance          = 'drillnav-block-' . uniqid();
		$use_block_wrapper = true;

		ob_start();
		include DRILLNAV_PLUGIN_DIR . 'includes/views/navigation.php';
		return ob_get_clean() ?: '';
	}

	/**
	 * Registers the REST routes used by the block editor preview and the
	 * frontend JS for lazy-loading children.
	 */
	public function register_rest_routes(): void {
		// AJAX content loading is a Pro feature – don't expose the endpoint otherwise.
		if ( \DrillNav\Plugin::is_pro() ) {
			register_rest_route(
				'drillnav/v1',
				'/content',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_content' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'url' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						),
					),
				)
			);
		}

		register_rest_route(
			'drillnav/v1',
			'/children',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_children' ),
				'permission_callback' => '__return_true', // Public endpoint; data is already public.
				'args'                => array(
					'post_id'   => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'post_type' => array(
						'type'              => 'string',
						'default'           => 'page',
						'sanitize_callback' => 'sanitize_key',
					),
					'exclude'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST handler: returns rendered page content for AJAX content loading.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_content( \WP_REST_Request $request ) {
		$url     = (string) $request->get_param( 'url' );
		$post_id = url_to_postid( $url );

		if ( ! $post_id ) {
			return rest_ensure_response( null );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || post_password_required( $post ) ) {
			// Null makes the frontend fall back to a regular page load.
			return rest_ensure_response( null );
		}

		$pto = get_post_type_object( $post->post_type );
		if ( ! $pto || ! $pto->public ) {
			return rest_ensure_response( null );
		}

		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- invoking WordPress core's own filter, not defining a new one.
		$content = apply_filters( 'the_content', get_the_content( null, false, $post ) );
		wp_reset_postdata();

		return rest_ensure_response(
			array(
				'post_id'   => $post_id,
				'title'     => get_the_title( $post ),
				'content'   => $content,
				'permalink' => get_permalink( $post ),
			)
		);
	}

	/**
	 * REST handler: returns the direct children of a post or term as JSON.
	 *
	 * The post_type parameter carries the item type from the navigation data –
	 * either a hierarchical post type (page hierarchy) or a hierarchical
	 * taxonomy (blog categories, product categories, custom taxonomies).
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_children( \WP_REST_Request $request ) {
		$post_id   = (int) $request->get_param( 'post_id' );
		$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );

		// Hierarchical, publicly accessible post type → post children.
		$pto = get_post_type_object( $post_type );
		if ( $pto && $pto->hierarchical && $pto->public ) {
			$child_args = array( 'post_type' => $post_type );
			// Instance-specific exclusions (block/shortcode); without the
			// parameter the global setting applies.
			$exclude = (string) $request->get_param( 'exclude' );
			if ( '' !== $exclude ) {
				$child_args['exclude_ids'] = Settings::id_list( $exclude );
			}
			return rest_ensure_response(
				$this->navigator->get_children( $post_id, $child_args )
			);
		}

		// Hierarchical, public taxonomy → term children.
		$tax = get_taxonomy( $post_type );
		if ( $tax && $tax->hierarchical && $tax->public ) {
			/**
			 * Allows integrations (blog, WooCommerce) to supply the child items
			 * for a taxonomy term. Return an array to short-circuit the generic
			 * term lookup.
			 *
			 * @param array<int,array<string,mixed>>|null $items
			 * @param string                              $taxonomy
			 * @param int                                 $parent_id
			 */
			$items = apply_filters( 'drillnav_term_children_items', null, $post_type, $post_id );
			if ( ! is_array( $items ) ) {
				$items = $this->term_children( $post_type, $post_id );
			}
			return rest_ensure_response( $items );
		}

		return new \WP_Error(
			'drillnav_invalid_post_type',
			__( 'Post type not supported.', 'drillnav-drilldown-navigation' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Generic term-children lookup used when no integration supplies the items.
	 *
	 * @param string $taxonomy
	 * @param int    $parent_id
	 * @return array<int,array<string,mixed>>
	 */
	private function term_children( string $taxonomy, int $parent_id ): array {
		$lang      = (string) apply_filters( 'drillnav_language', '' );
		$cache_key = 'term_children_' . $taxonomy . '_' . $parent_id . '_' . $lang;
		$cached    = $this->cache->get( $cache_key );
		if ( false !== $cached ) {
			return (array) $cached;
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

		// One lightweight query (id => parent map) instead of one query per
		// term to find out which terms have children.
		$parents_with_children = array();
		if ( $terms ) {
			$all_parents = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'id=>parent',
				)
			);
			if ( ! is_wp_error( $all_parents ) && is_array( $all_parents ) ) {
				$parents_with_children = array_flip( array_map( 'intval', array_values( $all_parents ) ) );
			}
		}

		$items = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$url = get_term_link( $term, $taxonomy );
			$items[] = array(
				'id'           => $term->term_id,
				'title'        => $term->name,
				'url'          => is_wp_error( $url ) ? '' : $url,
				'parent_id'    => $parent_id,
				'post_type'    => $taxonomy,
				'menu_order'   => 0,
				'is_current'   => false,
				'has_children' => isset( $parents_with_children[ (int) $term->term_id ] ),
			);
		}

		$this->cache->set( $cache_key, $items );
		return $items;
	}

	/**
	 * Maps block attributes (camelCase from block.json) to Navigator args (snake_case).
	 *
	 * @param array<string,mixed> $attributes
	 * @return array<string,mixed>
	 */
	private function attributes_to_args( array $attributes ): array {
		$args = array();

		if ( isset( $attributes['depth'] ) ) {
			$args['depth'] = (int) $attributes['depth'];
		}
		if ( isset( $attributes['showHome'] ) ) {
			$args['show_home'] = (bool) $attributes['showHome'];
		}
		if ( isset( $attributes['homeLabel'] ) ) {
			$args['home_label'] = sanitize_text_field( (string) $attributes['homeLabel'] );
		}
		if ( isset( $attributes['postType'] ) && in_array( $attributes['postType'], $this->settings->get_hierarchical_post_types(), true ) ) {
			$args['post_type'] = sanitize_key( (string) $attributes['postType'] );
		}
		if ( isset( $attributes['showBackButton'] ) ) {
			$args['show_back'] = (bool) $attributes['showBackButton'];
		}
		if ( isset( $attributes['animation'] ) && in_array( $attributes['animation'], array( 'slide', 'fade', 'none' ), true ) ) {
			$args['animation'] = $attributes['animation'];
		}
		if ( isset( $attributes['colorScheme'] ) && in_array( $attributes['colorScheme'], array( 'default', 'auto', 'light', 'dark' ), true ) ) {
			$args['color_scheme'] = $attributes['colorScheme'];
		}
		if ( isset( $attributes['mobileToggle'] ) ) {
			$args['mobile_toggle'] = (bool) $attributes['mobileToggle'];
		}
		if ( isset( $attributes['maxWidth'] ) && '' !== $attributes['maxWidth'] ) {
			$args['max_width'] = Settings::css_value( (string) $attributes['maxWidth'] );
		}
		if ( isset( $attributes['multipleBackButtons'] ) ) {
			$args['multiple_back_buttons'] = (bool) $attributes['multipleBackButtons'];
		}
		if ( isset( $attributes['layout'] ) && in_array( $attributes['layout'], array( 'list', 'horizontal', 'accordion' ), true ) ) {
			$args['layout'] = sanitize_key( (string) $attributes['layout'] );
		}
		if ( isset( $attributes['stylePreset'] ) && in_array( $attributes['stylePreset'], array( 'default', 'compact', 'comfortable', 'cards' ), true ) ) {
			$args['style_preset'] = sanitize_key( (string) $attributes['stylePreset'] );
		}

		$granular_map = array(
			'customFontSize'        => 'custom_font_size',
			'customPaddingY'        => 'custom_padding_y',
			'customPaddingX'        => 'custom_padding_x',
			'customBorderRadius'    => 'custom_border_radius',
			'customTransitionSpeed' => 'custom_transition_speed',
			'customColorLink'       => 'custom_color_link',
			'customColorCurrentBg'  => 'custom_color_current_bg',
			'customColorHover'      => 'custom_color_hover',
			'customColorArrow'      => 'custom_color_arrow',
		);
		foreach ( $granular_map as $camel => $snake ) {
			if ( isset( $attributes[ $camel ] ) && '' !== $attributes[ $camel ] ) {
				$args[ $snake ] = Settings::css_value( (string) $attributes[ $camel ] );
			}
		}

		if ( isset( $attributes['searchFilter'] ) ) {
			$args['search_filter'] = (bool) $attributes['searchFilter'];
		}
		if ( isset( $attributes['accordionLazy'] ) ) {
			$args['accordion_lazy'] = (bool) $attributes['accordionLazy'];
		}
		if ( isset( $attributes['menuId'] ) && (int) $attributes['menuId'] > 0 ) {
			$args['menu_id'] = (int) $attributes['menuId'];
		}
		if ( isset( $attributes['ajaxContent'] ) ) {
			$args['ajax_content'] = (bool) $attributes['ajaxContent'];
		}
		if ( isset( $attributes['title'] ) && '' !== $attributes['title'] ) {
			$args['nav_title'] = sanitize_text_field( (string) $attributes['title'] );
		}
		if ( isset( $attributes['rootId'] ) && is_numeric( $attributes['rootId'] ) ) {
			$args['root_id'] = max( -1, (int) $attributes['rootId'] );
		}
		if ( isset( $attributes['excludeIds'] ) && '' !== trim( (string) $attributes['excludeIds'] ) ) {
			$args['exclude_ids'] = Settings::id_list( (string) $attributes['excludeIds'] );
		}
		if ( isset( $attributes['contentSelector'] ) && '' !== $attributes['contentSelector'] ) {
			$args['content_selector'] = sanitize_text_field( (string) $attributes['contentSelector'] );
		}

		return $args;
	}
}
