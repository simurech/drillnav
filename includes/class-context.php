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

		$resolved = $this->resolve();
		if ( null === $resolved ) {
			// Too early to resolve – return defaults without memoizing so the
			// context is resolved again on the next access.
			return $this->defaults();
		}

		$this->resolved = $resolved;
		return $resolved;
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
	 * Returns the default (empty) context array.
	 *
	 * @return array<string,mixed>
	 */
	private function defaults(): array {
		return array(
			'post_id'       => 0,
			'post_type'     => '',
			'parent_id'     => 0,
			'ancestors'     => array(),
			'is_front_page' => false,
		);
	}

	/**
	 * Builds the context array from the current WordPress query.
	 *
	 * The drillnav_current_context filter runs on every resolved context – also
	 * for archives and non-hierarchical singulars – so integrations (blog,
	 * taxonomy, WooCommerce) can inject their own context data.
	 *
	 * @return array<string,mixed>|null Null when it is too early to resolve.
	 */
	private function resolve(): ?array {
		$context = $this->defaults();

		$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		if ( ! did_action( 'wp' ) && ! is_admin() && ! $is_rest ) {
			return null;
		}

		// In REST context (e.g. block editor ServerSideRender preview), WordPress
		// calls setup_postdata() before the render callback, so get_the_ID() is
		// reliable. On the frontend only singular views carry a post ID – on
		// archives get_queried_object_id() returns a term ID that must not be
		// mistaken for a post ID.
		if ( $is_rest ) {
			$post_id = (int) get_the_ID();
		} else {
			$post_id = is_singular() ? (int) get_queried_object_id() : 0;
		}

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$post_type_obj = get_post_type_object( $post->post_type );
				if ( $post_type_obj && $post_type_obj->hierarchical ) {
					// get_ancestors() returns [immediate_parent, ..., root_parent];
					// reverse so we get [root, ..., immediate_parent] (top-down).
					$ancestors = array_reverse(
						array_map( 'intval', get_ancestors( $post_id, $post->post_type, 'post_type' ) )
					);

					$context['post_id']   = $post_id;
					$context['post_type'] = $post->post_type;
					$context['parent_id'] = (int) $post->post_parent;
					$context['ancestors'] = $ancestors;
				}
			}
		}

		$context['is_front_page'] = ! $is_rest && is_front_page();

		/**
		 * Filters the resolved page context.
		 *
		 * @param array<string,mixed> $context
		 */
		return (array) apply_filters( 'drillnav_current_context', $context );
	}
}
