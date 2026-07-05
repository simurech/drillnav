<?php
/**
 * Main plugin class.
 *
 * @package DrillNav
 */

namespace DrillNav;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton that bootstraps all plugin components.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Loader */
	public $loader;

	/** @var Settings */
	public $settings;

	/** @var Cache */
	public $cache;

	/** @var Navigator */
	public $navigator;

	/** @var Context */
	public $context;

	private function __construct() {
		$this->init_components();
	}

	/**
	 * Returns (and creates) the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Runs on plugin activation. */
	public static function activate(): void {
		// Set a version flag so we can run migrations later.
		update_option( 'drillnav_version', DRILLNAV_VERSION );
	}

	/** Runs on plugin deactivation. */
	public static function deactivate(): void {
		// Nothing to do on deactivation (data is kept intentionally).
	}

	/** Instantiates and wires up all components. */
	private function init_components(): void {

		$this->loader    = new Loader();
		$this->settings  = new Settings();
		$this->cache     = new Cache();
		$this->context   = new Context();
		$this->navigator = new Navigator( $this->context, $this->cache, $this->settings );

		// Flush stale caches once after a plugin update (activation hooks do
		// not run reliably on updates).
		if ( DRILLNAV_VERSION !== get_option( 'drillnav_version' ) ) {
			update_option( 'drillnav_version', DRILLNAV_VERSION );
			$this->cache->flush();
		}

		// Cache invalidation must work in every context – the block editor
		// saves via the REST API where is_admin() is false, and WP-CLI or
		// importers never enter wp-admin at all.
		$this->loader->add_action( 'save_post',          array( $this->cache, 'invalidate_on_post_change' ) );
		$this->loader->add_action( 'delete_post',        array( $this->cache, 'invalidate_on_post_change' ) );
		$this->loader->add_action( 'trashed_post',       array( $this->cache, 'invalidate_on_post_change' ) );
		$this->loader->add_action( 'created_term',       array( $this->cache, 'invalidate_on_term_change' ), 10, 3 );
		$this->loader->add_action( 'edited_term',        array( $this->cache, 'invalidate_on_term_change' ), 10, 3 );
		$this->loader->add_action( 'delete_term',        array( $this->cache, 'invalidate_on_term_change' ), 10, 3 );
		$this->loader->add_action( 'wp_update_nav_menu', array( $this->cache, 'flush' ) );

		// Frontend.
		$shortcode = new Shortcode( $this->navigator, $this->settings );
		$shortcode->register( $this->loader );

		$widget = new Widget( $this->navigator, $this->settings );
		$widget->register( $this->loader );

		// Gutenberg block.
		$block = new Blocks\Block( $this->navigator, $this->settings );
		$block->register( $this->loader );

		// Admin (only in wp-admin context).
		if ( is_admin() ) {
			$admin = new Admin\Admin( $this->settings, $this->cache );
			$admin->register( $this->loader );
		}

		// Blog / Posts integration – active whenever posts are enabled.
		$blog = new Integrations\Blog( $this->navigator, $this->cache, $this->settings );
		$blog->register( $this->loader );

		// Multilingual integration – WPML / Polylang compatibility.
		$multilingual = new Integrations\Multilingual();
		$multilingual->register( $this->loader );

		// Pro-only features – files excluded from free build via @fs_premium_only.
		if ( drillnav_fs()->is__premium_only() ) {
			// Per-page icon & badge meta box.
			$item_meta = new Admin\ItemMeta();
			$item_meta->register( $this->loader );

			// General hierarchical taxonomy navigation.
			$taxonomy = new Integrations\Taxonomy( $this->navigator, $this->cache, $this->settings );
			$taxonomy->register( $this->loader );

			// WP nav-menu as navigation source (+ Hybrid WooCommerce mode).
			$menu = new Integrations\Menu( $this->cache, $this->settings );
			$menu->register( $this->loader );
		}

		// WooCommerce Pro integration – file excluded from free build via @fs_premium_only.
		if ( drillnav_fs()->is__premium_only() && $this->is_woocommerce_active() ) {
			$woo = new Integrations\Woocommerce( $this->navigator, $this->cache, $this->settings );
			$woo->register( $this->loader );
		}

		// Page builder integrations (Free + Pro).
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = new Integrations\PageBuilders\Elementor();
			$elementor->register( $this->loader );
		}
		if ( class_exists( '\FLBuilder' ) ) {
			$bb = new Integrations\PageBuilders\BeaverBuilder();
			$bb->register( $this->loader );
		}
		if ( class_exists( '\ET_Builder_Module' ) ) {
			$divi = new Integrations\PageBuilders\Divi();
			$divi->register( $this->loader );
		}

		// Run all registered hooks.
		$this->loader->run();
	}

	/**
	 * Shared render helper used by page-builder integrations.
	 * Enqueues frontend assets and returns the navigation HTML.
	 *
	 * @param array<string,mixed> $args         Navigator args.
	 * @param bool                $mobile_toggle Whether to render the hamburger drawer wrapper.
	 * @return string HTML or empty string when nothing to show.
	 */
	public function render_navigation( array $args = array(), bool $mobile_toggle = false ): string {
		$nav_data = $this->navigator->get_nav_data( $args );

		if ( empty( $nav_data['current_level'] ) ) {
			return '';
		}

		if ( $this->settings->get( 'load_css' ) ) {
			wp_enqueue_style( 'drillnav-frontend' );
		}
		if ( $this->settings->get( 'load_a11y_css' ) ) {
			wp_enqueue_style( 'drillnav-a11y' );
		}
		if ( drillnav_fs()->is__premium_only() ) {
			wp_enqueue_style( 'dashicons' ); // Per-item icon support (Pro).
		}
		wp_enqueue_script( 'drillnav-frontend' );

		$instance = 'drillnav-' . uniqid();

		ob_start();
		include DRILLNAV_PLUGIN_DIR . 'includes/views/navigation.php';
		return ob_get_clean() ?: '';
	}

	/**
	 * Checks whether WooCommerce is active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	// Prevent cloning / unserialization of the singleton.
	private function __clone() {}
	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}
