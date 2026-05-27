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
 * All cache keys use the 'drillnav_' prefix so the uninstall cleanup can wipe them with a LIKE query.
 */
class Cache {

	private const TTL = WEEK_IN_SECONDS; // 7 days.

	/** @var array<string,mixed> Per-request in-memory cache. */
	private array $runtime = array();

	/**
	 * Retrieves a cached value.
	 *
	 * @param string $key Cache key (without prefix).
	 * @return mixed|false False on cache miss.
	 */
	public function get( string $key ) {
		$prefixed = 'drillnav_' . $key;

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
		$prefixed                       = 'drillnav_' . $key;
		$this->runtime[ $prefixed ]     = $value;
		set_transient( $prefixed, $value, $ttl );
	}

	/**
	 * Deletes a single cached entry.
	 *
	 * @param string $key Cache key (without prefix).
	 */
	public function delete( string $key ): void {
		$prefixed = 'drillnav_' . $key;
		unset( $this->runtime[ $prefixed ] );
		delete_transient( $prefixed );
	}

	/**
	 * Flushes ALL DrillNav transients from the database and the runtime cache.
	 * Called when pages are saved/deleted or via the admin Cache-Clear button.
	 *
	 * @return int Number of transients deleted.
	 */
	public function flush(): int {
		$this->runtime = array();

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = (int) $wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_drillnav_%'
			    OR option_name LIKE '_transient_timeout_drillnav_%'"
		);

		wp_cache_flush_group( 'drillnav' );

		return $deleted;
	}

	/**
	 * Invalidates navigation caches when post data changes.
	 * Hooked to save_post, delete_post, and trashed_post.
	 *
	 * @param int $post_id
	 */
	public function invalidate_on_post_change( int $post_id ): void {
		// Only flush for hierarchical post types we actually care about.
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return;
		}
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj || ! $post_type_obj->hierarchical ) {
			return;
		}
		$this->flush();
	}
}
