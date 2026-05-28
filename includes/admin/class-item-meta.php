<?php
/**
 * Per-page icon and badge meta box (Pro feature).
 *
 * @package DrillNav
 */

namespace DrillNav\Admin;

defined( 'ABSPATH' ) || exit;

use DrillNav\Loader;

/**
 * Registers a meta box on hierarchical post-type edit screens that lets editors
 * assign an icon and/or a highlight badge to individual navigation items.
 *
 * Both values are stored in post meta and injected into the nav-item arrays via
 * the drillnav_nav_items / drillnav_children_items filters, so they are cached
 * alongside the rest of the navigation data.
 */
class ItemMeta {

	private const META_ICON         = '_drillnav_icon';
	private const META_BADGE_TEXT   = '_drillnav_badge_text';
	private const META_BADGE_COLOR  = '_drillnav_badge_color';
	private const BADGE_COLORS      = array( 'red', 'green', 'blue', 'orange', 'gray' );

	/**
	 * Registers all hooks.
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'add_meta_boxes',             array( $this, 'add_meta_boxes' ) );
		$loader->add_action( 'save_post',                  array( $this, 'save_meta' ) );
		$loader->add_filter( 'drillnav_nav_items',         array( $this, 'enrich_nav_items' ), 10, 2 );
		$loader->add_filter( 'drillnav_children_items',    array( $this, 'enrich_children_items' ), 10, 3 );
	}

	/** Adds the DrillNav meta box to all public hierarchical post type edit screens. */
	public function add_meta_boxes(): void {
		$types = get_post_types( array( 'hierarchical' => true, 'public' => true ), 'names' );
		foreach ( $types as $type ) {
			add_meta_box(
				'drillnav_item_meta',
				__( 'DrillNav', 'drillnav-drilldown-navigation' ),
				array( $this, 'render_meta_box' ),
				$type,
				'side',
				'low'
			);
		}
	}

	/**
	 * Renders the meta box HTML.
	 *
	 * @param \WP_Post $post
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'drillnav_save_item_meta_' . $post->ID, 'drillnav_item_meta_nonce' );

		$icon        = (string) ( get_post_meta( $post->ID, self::META_ICON, true ) ?: '' );
		$badge_text  = (string) ( get_post_meta( $post->ID, self::META_BADGE_TEXT, true ) ?: '' );
		$badge_color = (string) ( get_post_meta( $post->ID, self::META_BADGE_COLOR, true ) ?: 'red' );
		?>
		<p style="margin-top:.5rem;">
			<label for="drillnav_icon" style="font-weight:600;"><?php esc_html_e( 'Icon', 'drillnav-drilldown-navigation' ); ?></label><br>
			<input
				type="text"
				id="drillnav_icon"
				name="drillnav_icon"
				value="<?php echo esc_attr( $icon ); ?>"
				placeholder="🏠 <?php esc_attr_e( 'or dashicons-admin-home', 'drillnav-drilldown-navigation' ); ?>"
				class="widefat"
				style="margin-top:.25rem;"
			>
			<span style="font-size:11px;color:#757575;">
				<?php esc_html_e( 'Emoji or Dashicon class (e.g. dashicons-star-filled).', 'drillnav-drilldown-navigation' ); ?>
			</span>
		</p>
		<p>
			<label for="drillnav_badge_text" style="font-weight:600;"><?php esc_html_e( 'Badge', 'drillnav-drilldown-navigation' ); ?></label><br>
			<input
				type="text"
				id="drillnav_badge_text"
				name="drillnav_badge_text"
				value="<?php echo esc_attr( $badge_text ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. New', 'drillnav-drilldown-navigation' ); ?>"
				class="widefat"
				style="margin-top:.25rem;"
				maxlength="20"
			>
		</p>
		<p>
			<label for="drillnav_badge_color" style="font-weight:600;"><?php esc_html_e( 'Badge colour', 'drillnav-drilldown-navigation' ); ?></label><br>
			<select id="drillnav_badge_color" name="drillnav_badge_color" class="widefat" style="margin-top:.25rem;">
				<?php
				$color_labels = array(
					'red'    => __( 'Red', 'drillnav-drilldown-navigation' ),
					'green'  => __( 'Green', 'drillnav-drilldown-navigation' ),
					'blue'   => __( 'Blue', 'drillnav-drilldown-navigation' ),
					'orange' => __( 'Orange', 'drillnav-drilldown-navigation' ),
					'gray'   => __( 'Gray', 'drillnav-drilldown-navigation' ),
				);
				foreach ( $color_labels as $val => $lbl ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $val ),
						selected( $badge_color, $val, false ),
						esc_html( $lbl )
					);
				}
				?>
			</select>
		</p>
		<?php
		if ( '' !== $icon || '' !== $badge_text ) {
			echo '<p style="font-size:11px;color:#757575;">';
			esc_html_e( 'Preview:', 'drillnav-drilldown-navigation' );
			echo ' ';
			if ( '' !== $icon ) {
				if ( str_starts_with( $icon, 'dashicons-' ) ) {
					printf( '<span class="dashicons %s" aria-hidden="true" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> ', esc_attr( $icon ) );
				} else {
					echo esc_html( $icon ) . ' ';
				}
			}
			echo esc_html( get_the_title( $post->ID ) );
			if ( '' !== $badge_text ) {
				$colors_css = array(
					'red'    => 'background:#dc2626;color:#fff;',
					'green'  => 'background:#16a34a;color:#fff;',
					'blue'   => 'background:#2563eb;color:#fff;',
					'orange' => 'background:#ea580c;color:#fff;',
					'gray'   => 'background:#6b7280;color:#fff;',
				);
				$inline = $colors_css[ $badge_color ] ?? $colors_css['red'];
				printf(
					' <span style="display:inline-block;padding:.1em .4em;border-radius:3px;font-size:.7em;font-weight:700;%s">%s</span>',
					esc_attr( $inline ),
					esc_html( $badge_text )
				);
			}
			echo '</p>';
		}
	}

	/**
	 * Saves meta box data on post save.
	 *
	 * @param int $post_id
	 */
	public function save_meta( int $post_id ): void {
		if (
			! isset( $_POST['drillnav_item_meta_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['drillnav_item_meta_nonce'] ) ),
				'drillnav_save_item_meta_' . $post_id
			) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		update_post_meta( $post_id, self::META_ICON, sanitize_text_field( wp_unslash( $_POST['drillnav_icon'] ?? '' ) ) );
		update_post_meta( $post_id, self::META_BADGE_TEXT, sanitize_text_field( wp_unslash( $_POST['drillnav_badge_text'] ?? '' ) ) );

		$color = sanitize_key( wp_unslash( $_POST['drillnav_badge_color'] ?? 'red' ) );
		update_post_meta( $post_id, self::META_BADGE_COLOR, in_array( $color, self::BADGE_COLORS, true ) ? $color : 'red' );
	}

	/* ------------------------------------------------------------------
	 * Nav-item enrichment filters
	 * ----------------------------------------------------------------- */

	/**
	 * Enriches all items in get_nav_data() result with icon/badge meta.
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function enrich_nav_items( array $data, array $args ): array {
		$ids = array();
		foreach ( $data['current_level'] ?? array() as $item ) {
			$ids[] = (int) ( $item['id'] ?? 0 );
		}
		foreach ( $data['ancestors'] ?? array() as $item ) {
			$ids[] = (int) ( $item['id'] ?? 0 );
		}
		$this->collect_tree_ids( $data['tree'] ?? array(), $ids );

		$ids = array_filter( array_unique( $ids ) );
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
		}

		$data['current_level'] = array_map( array( $this, 'enrich_item' ), $data['current_level'] ?? array() );
		$data['ancestors']     = array_map( array( $this, 'enrich_item' ), $data['ancestors'] ?? array() );
		if ( ! empty( $data['tree'] ) ) {
			$data['tree'] = $this->enrich_tree( $data['tree'] );
		}

		return $data;
	}

	/**
	 * Enriches lazy-loaded children items with icon/badge meta.
	 *
	 * @param array<int,array>    $items
	 * @param int                 $parent_id
	 * @param array<string,mixed> $args
	 * @return array<int,array>
	 */
	public function enrich_children_items( array $items, int $parent_id, array $args ): array {
		if ( ! $items ) {
			return $items;
		}
		$ids = array_filter( array_map( fn( $i ) => (int) ( $i['id'] ?? 0 ), $items ) );
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
		}
		return array_map( array( $this, 'enrich_item' ), $items );
	}

	/**
	 * Adds icon/badge fields to a single item array.
	 *
	 * @param array<string,mixed> $item
	 * @return array<string,mixed>
	 */
	private function enrich_item( array $item ): array {
		$id = (int) ( $item['id'] ?? 0 );
		if ( ! $id ) {
			return $item;
		}

		$icon       = (string) ( get_post_meta( $id, self::META_ICON, true ) ?: '' );
		$badge_text = (string) ( get_post_meta( $id, self::META_BADGE_TEXT, true ) ?: '' );

		if ( '' !== $icon ) {
			$item['icon'] = $icon;
		}
		if ( '' !== $badge_text ) {
			$item['badge']       = $badge_text;
			$item['badge_color'] = (string) ( get_post_meta( $id, self::META_BADGE_COLOR, true ) ?: 'red' );
		}

		return $item;
	}

	/**
	 * Recursively enriches an accordion tree with icon/badge meta.
	 *
	 * @param array<int,array> $items
	 * @return array<int,array>
	 */
	private function enrich_tree( array $items ): array {
		return array_map(
			function ( array $item ): array {
				$item = $this->enrich_item( $item );
				if ( ! empty( $item['children'] ) ) {
					$item['children'] = $this->enrich_tree( $item['children'] );
				}
				return $item;
			},
			$items
		);
	}

	/**
	 * Collects all post IDs from a recursive tree structure.
	 *
	 * @param array<int,array> $items
	 * @param int[]            &$ids
	 */
	private function collect_tree_ids( array $items, array &$ids ): void {
		foreach ( $items as $item ) {
			$ids[] = (int) ( $item['id'] ?? 0 );
			if ( ! empty( $item['children'] ) ) {
				$this->collect_tree_ids( $item['children'], $ids );
			}
		}
	}
}
