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
		// Schedule cache flush after activation.
		wp_cache_flush();
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

		// WooCommerce Pro integration. The entire block is auto-stripped from the
		// free version by Freemius because of can_use_premium_code__premium_only().
		if ( drillnav_fs()->can_use_premium_code__premium_only() && $this->is_woocommerce_active() ) {
			$woo = new Integrations\Woocommerce( $this->navigator, $this->cache, $this->settings );
			$woo->register( $this->loader );
		}

		// Run all registered hooks.
		$this->loader->run();
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
