<?php
/**
 * Detects the current page context for navigation rendering.
 *
 * @package DrillNav
 */

namespace DrillNav;

defined( 'ABSPATH' ) || exit;

/**
 * Provides a normalised description of the current page so the Navigator
 * can build the correct hierarchy without knowing WordPress internals.
 *
 * Resolved lazily on first access; safe to instantiate before wp fires.
 */
class Context {

	/** @var array<string,mixed>|null */
	private ?array $resolved = null;

	/**
	 * Returns the full context array.
	 * Keys: post_id, post_type, parent_id, ancestors (oldest-first), is_front_page.
	 *
	 * @return array<string,mixed>
	 */
	public function get(): array {
		if ( null !== $this->resolved ) {
			return $this->resolved;
		}

		$this->resolved = $this->resolve();
		return $this->resolved;
	}

	/** Shorthand – returns the current post/page ID, or 0. */
	public function post_id(): int {
		return (int) ( $this->get()['post_id'] ?? 0 );
	}

	/** Shorthand – returns the current post type slug, or empty string. */
	public function post_type(): string {
		return (string) ( $this->get()['post_type'] ?? '' );
	}

	/** Shorthand – returns the immediate parent post ID, or 0. */
	public function parent_id(): int {
		return (int) ( $this->get()['parent_id'] ?? 0 );
	}

	/**
	 * Returns ancestors from top (root) to immediate parent.
	 *
	 * @return int[]
	 */
	public function ancestors(): array {
		return $this->get()['ancestors'] ?? array();
	}

	/** Whether this is the site's front page. */
	public function is_front_page(): bool {
		return (bool) ( $this->get()['is_front_page'] ?? false );
	}

	/** Resets the resolved context (useful in tests). */
	public function reset(): void {
		$this->resolved = null;
	}

	/**
	 * Builds the context array from the current WordPress query.
	 *
	 * @return array<string,mixed>
	 */
	private function resolve(): array {
		$context = array(
			'post_id'       => 0,
			'post_type'     => '',
			'parent_id'     => 0,
			'ancestors'     => array(),
			'is_front_page' => false,
		);

		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		if ( ! did_action( 'wp' ) && ! is_admin() && ! $is_rest ) {
			// Too early to resolve – will be resolved again on next access.
			return $context;
		}

		// In REST context (e.g. block editor ServerSideRender preview), WordPress
		// calls setup_postdata() before the render callback, so get_the_ID() is reliable.
		$post_id = $is_rest ? (int) get_the_ID() : (int) get_queried_object_id();

		if ( $post_id <= 0 ) {
			$context['is_front_page'] = ! $is_rest && is_front_page();
			return $context;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $context;
		}

		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || ! $post_type_obj->hierarchical ) {
			// Non-hierarchical post type – no contextual nav possible.
			$context['is_front_page'] = is_front_page();
			return $context;
		}

		// get_ancestors() returns [immediate_parent, grandparent, ..., root_parent].
		$ancestors_reversed = array_map( 'intval', get_ancestors( $post_id, $post->post_type, 'post_type' ) );
		// Reverse so we get [root, ..., immediate_parent] (top-down order).
		$ancestors = array_reverse( $ancestors_reversed );

		$context['post_id']       = $post_id;
		$context['post_type']     = $post->post_type;
		$context['parent_id']     = (int) $post->post_parent;
		$context['ancestors']     = $ancestors;
		$context['is_front_page'] = ! $is_rest && is_front_page();

		/**
		 * Filters the resolved page context.
		 *
		 * @param array<string,mixed> $context
		 */
		return (array) apply_filters( 'drillnav_current_context', $context );
	}
}
