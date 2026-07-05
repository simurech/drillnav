<?php
/**
 * Caching layer for navigation data.
 *
 * @package DrillNav
 */

namespace DrillNav;

defined( 'ABSPATH' ) || exit;

/**
 * Two-tier cache:
 *  1. Runtime static array – prevents repeated DB queries within a single request.
 *  2. WordPress Transients – persists across requests (compatible with object cache plugins).
 *
 * All cache keys carry a version number that is incremented on flush. This makes
 * invalidation reliable even when transients live in an external object cache
 * (Redis/Memcached), where they cannot be deleted via the options table.
 */
class Cache {

	private const TTL = WEEK_IN_SECONDS; // 7 days.

	private const VERSION_OPTION = 'drillnav_cache_ver';

	/** @var array<string,mixed> Per-request in-memory cache. */
	private array $runtime = array();

	/** @var int|null Lazy-loaded cache generation number. */
	private ?int $version = null;

	/**
	 * Returns the current cache generation number.
	 *
	 * @return int
	 */
	private function version(): int {
		if ( null === $this->version ) {
			$this->version = max( 1, (int) get_option( self::VERSION_OPTION, 1 ) );
		}
		return $this->version;
	}

	/**
	 * Builds the fully prefixed, versioned transient name.
	 *
	 * @param string $key Cache key (without prefix).
	 * @return string
	 */
	private function prefixed( string $key ): string {
		return 'drillnav_' . $this->version() . '_' . $key;
	}

	/**
	 * Retrieves a cached value.
	 *
	 * Note: boolean false cannot be distinguished from a cache miss; callers
	 * that cache booleans should store them as integers (0/1).
	 *
	 * @param string $key Cache key (without prefix).
	 * @return mixed|false False on cache miss.
	 */
	public function get( string $key ) {
		$prefixed = $this->prefixed( $key );

		if ( array_key_exists( $prefixed, $this->runtime ) ) {
			return $this->runtime[ $prefixed ];
		}

		$value = get_transient( $prefixed );
		if ( false !== $value ) {
			$this->runtime[ $prefixed ] = $value;
		}
		return $value;
	}

	/**
	 * Stores a value in both tiers.
	 *
	 * @param string $key   Cache key (without prefix).
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   Time-to-live in seconds. Defaults to WEEK_IN_SECONDS.
	 */
	public function set( string $key, $value, int $ttl = self::TTL ): void {
		/**
		 * Filters the transient cache TTL in seconds.
		 *
		 * @param int $ttl Time-to-live in seconds (default: WEEK_IN_SECONDS).
		 */
		$ttl = (int) apply_filters( 'drillnav_cache_duration', $ttl );

		$prefixed                   = $this->prefixed( $key );
		$this->runtime[ $prefixed ] = $value;
		set_transient( $prefixed, $value, $ttl );
	}

	/**
	 * Deletes a single cached entry.
	 *
	 * @param string $key Cache key (without prefix).
	 */
	public function delete( string $key ): void {
		$prefixed = $this->prefixed( $key );
		unset( $this->runtime[ $prefixed ] );
		delete_transient( $prefixed );
	}

	/**
	 * Flushes ALL DrillNav caches by bumping the cache generation number.
	 * Old entries become unreachable immediately and expire via their TTL.
	 * On sites without an external object cache the stale rows are also
	 * removed from the options table right away.
	 *
	 * Called when posts/terms change or via the admin Cache-Clear button.
	 *
	 * @return int Number of transient rows deleted from the options table.
	 */
	public function flush(): int {
		$this->runtime = array();
		$this->version = $this->version() + 1;
		update_option( self::VERSION_OPTION, $this->version, true );

		if ( wp_using_ext_object_cache() ) {
			// Transients live in the object cache; the version bump above
			// already invalidated them. Old entries expire via TTL.
			return 0;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_drillnav_%'
			    OR option_name LIKE '_transient_timeout_drillnav_%'"
		);
	}

	/**
	 * Invalidates navigation caches when post data changes.
	 * Hooked to save_post, delete_post, and trashed_post.
	 *
	 * Flushes for every public post type: hierarchical types feed the page
	 * navigation, posts feed the blog integration, and products affect the
	 * WooCommerce stock-based category visibility.
	 *
	 * @param int $post_id
	 */
	public function invalidate_on_post_change( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return;
		}
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj || ! $post_type_obj->public ) {
			return;
		}
		$this->flush();
	}

	/**
	 * Invalidates navigation caches when a term changes.
	 * Hooked to created_term, edited_term, and delete_term.
	 *
	 * @param int    $term_id
	 * @param int    $tt_id
	 * @param string $taxonomy
	 */
	public function invalidate_on_term_change( int $term_id, int $tt_id = 0, string $taxonomy = '' ): void {
		if ( '' !== $taxonomy ) {
			$tax_obj = get_taxonomy( $taxonomy );
			if ( ! $tax_obj || ! $tax_obj->public ) {
				return;
			}
		}
		$this->flush();
	}
}
