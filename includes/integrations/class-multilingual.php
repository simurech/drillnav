<?php
/**
 * Multilingual integration (WPML / Polylang).
 *
 * Free feature – no Freemius gate required.
 *
 * Hooks into DrillNav filter hooks so that cache keys include the active
 * language, post IDs are translated to the current language version, and
 * user-defined label strings are translatable via the multilingual plugin's
 * string translation interface:
 *
 *   drillnav_language          – returns the active language code
 *   drillnav_translate_post_id – returns the translated post ID
 *   drillnav_translate_string  – returns the translated label string
 *
 * The class does nothing when neither WPML nor Polylang is active.
 *
 * @package DrillNav
 */

namespace DrillNav\Integrations;

defined( 'ABSPATH' ) || exit;

use DrillNav\Loader;

/**
 * Provides WPML and Polylang compatibility for DrillNav.
 */
class Multilingual {

	/**
	 * Registers hooks – only when a supported multilingual plugin is active.
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$loader->add_filter( 'drillnav_language',          array( $this, 'get_language' ) );
		$loader->add_filter( 'drillnav_translate_post_id', array( $this, 'translate_post_id' ), 10, 2 );
		$loader->add_filter( 'drillnav_translate_string',  array( $this, 'translate_string' ), 10, 2 );
		$loader->add_action( 'init',                       array( $this, 'register_strings' ), 20 );
	}

	/* ------------------------------------------------------------------
	 * Filter callbacks
	 * ----------------------------------------------------------------- */

	/**
	 * Returns the current language code for use in cache keys.
	 *
	 * @param string $lang Default language string (empty).
	 * @return string
	 */
	public function get_language( string $lang ): string {
		if ( $this->is_wpml() ) {
			return (string) apply_filters( 'wpml_current_language', null );
		}
		if ( $this->is_polylang() ) {
			return (string) pll_current_language();
		}
		return $lang;
	}

	/**
	 * Translates a post ID to its equivalent in the current language.
	 *
	 * @param int    $post_id   Original post ID.
	 * @param string $post_type Post type of the post.
	 * @return int
	 */
	public function translate_post_id( int $post_id, string $post_type ): int {
		if ( $post_id <= 0 ) {
			return $post_id;
		}
		if ( $this->is_wpml() ) {
			return (int) apply_filters( 'wpml_object_id', $post_id, $post_type, true );
		}
		if ( $this->is_polylang() ) {
			return (int) ( pll_get_post( $post_id ) ?: $post_id );
		}
		return $post_id;
	}

	/**
	 * Translates a user-defined plugin label string.
	 *
	 * Empty strings are passed through unchanged – the caller handles the
	 * fallback (e.g. site name or a translated default).
	 *
	 * @param string $value Original label value.
	 * @param string $key   Setting key (e.g. 'home_label', 'nav_label').
	 * @return string
	 */
	public function translate_string( string $value, string $key ): string {
		if ( '' === $value ) {
			return $value;
		}
		if ( $this->is_wpml() ) {
			return (string) apply_filters( 'wpml_translate_single_string', $value, 'drillnav', $key );
		}
		if ( $this->is_polylang() && function_exists( 'pll__' ) ) {
			return (string) pll__( $value );
		}
		return $value;
	}

	/**
	 * Registers user-defined label strings with the active multilingual plugin
	 * so translators can provide per-language versions via the string
	 * translation interface (WPML String Translation / Polylang).
	 *
	 * Runs on init priority 20 so plugin settings are available.
	 */
	public function register_strings(): void {
		$settings = get_option( 'drillnav_settings', array() );

		$labels = array(
			'home_label' => (string) ( $settings['home_label'] ?? '' ),
			'nav_label'  => (string) ( $settings['nav_label'] ?? '' ),
			'blog_label' => (string) ( $settings['blog_label'] ?? '' ),
		);

		foreach ( $labels as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			if ( $this->is_wpml() ) {
				do_action( 'wpml_register_single_string', 'drillnav', $key, $value );
			}
			if ( $this->is_polylang() && function_exists( 'pll_register_string' ) ) {
				pll_register_string( $key, $value, 'DrillNav' );
			}
		}
	}

	/* ------------------------------------------------------------------
	 * Detection helpers
	 * ----------------------------------------------------------------- */

	/** Whether any supported multilingual plugin is active. */
	private function is_active(): bool {
		return $this->is_wpml() || $this->is_polylang();
	}

	/** Whether WPML is active. */
	private function is_wpml(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

	/** Whether Polylang is active. */
	private function is_polylang(): bool {
		return function_exists( 'pll_current_language' );
	}
}
