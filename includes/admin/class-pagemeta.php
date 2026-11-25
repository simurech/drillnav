<?php
/**
 * Per-page navigation options meta box.
 *
 * @package DrillNav
 */

namespace DrillNav\Admin;

defined( 'ABSPATH' ) || exit;

use DrillNav\Cache;
use DrillNav\Loader;

/**
 * Adds a "DrillNav" box to the edit screen of hierarchical post types with a
 * "Hide from navigation" checkbox. DrillNav Pro adds further fields (icon,
 * badge, navigation label) to the same box via the
 * drillnav_page_meta_box_fields action.
 */
class PageMeta {

	/** Post meta key: '1' when the page is hidden from the navigation. */
	public const META_EXCLUDE = '_drillnav_exclude';

	public function __construct(
		private readonly Cache $cache
	) {}

	/**
	 * Registers all hooks.
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		$loader->add_action( 'save_post', array( $this, 'save_meta' ) );
	}

	/** Adds the meta box to all public hierarchical post types. */
	public function add_meta_boxes(): void {
		$types = get_post_types(
			array(
				'hierarchical' => true,
				'public'       => true,
			),
			'names'
		);
		foreach ( $types as $type ) {
			add_meta_box(
				'drillnav_page_meta',
				__( 'DrillNav', 'drillnav-drilldown-navigation' ),
				array( $this, 'render_meta_box' ),
				$type,
				'side',
				'low'
			);
		}
	}

	/**
	 * Renders the meta box.
	 *
	 * @param \WP_Post $post
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'drillnav_save_page_meta_' . $post->ID, 'drillnav_page_meta_nonce' );
		$excluded = '1' === get_post_meta( $post->ID, self::META_EXCLUDE, true );
		?>
		<p>
			<label>
				<input type="checkbox" name="drillnav_exclude" value="1" <?php checked( $excluded ); ?>>
				<?php esc_html_e( 'Hide from navigation', 'drillnav-drilldown-navigation' ); ?>
			</label>
			<br>
			<span class="description">
				<?php esc_html_e( 'Sub-pages of this page are hidden as well.', 'drillnav-drilldown-navigation' ); ?>
			</span>
		</p>
		<?php
		/**
		 * Fires inside the DrillNav meta box to add further per-page fields.
		 *
		 * @param \WP_Post $post
		 */
		do_action( 'drillnav_page_meta_box_fields', $post );
	}

	/**
	 * Saves the checkbox and clears the navigation cache when it changed.
	 *
	 * @param int $post_id
	 */
	public function save_meta( int $post_id ): void {
		if (
			! isset( $_POST['drillnav_page_meta_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['drillnav_page_meta_nonce'] ) ),
				'drillnav_save_page_meta_' . $post_id
			) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			wp_is_post_revision( $post_id ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$was_excluded = '1' === get_post_meta( $post_id, self::META_EXCLUDE, true );
		$is_excluded  = ! empty( $_POST['drillnav_exclude'] );

		if ( $is_excluded === $was_excluded ) {
			return;
		}

		if ( $is_excluded ) {
			update_post_meta( $post_id, self::META_EXCLUDE, '1' );
		} else {
			delete_post_meta( $post_id, self::META_EXCLUDE );
		}

		// The block editor saves meta boxes in a separate request after the
		// post itself, so the post-save cache flush has already happened.
		$this->cache->flush();
	}
}
