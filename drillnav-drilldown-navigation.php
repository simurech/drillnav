<?php
/**
 * Plugin Name:       DrillNav – Smart Contextual Navigation for Deeply Nested Sites
 * Plugin URI:        https://github.com/simurech/drillnav
 * Description:       Contextual drill-down navigation that adapts to the current page. Perfect for deeply nested WordPress site hierarchies. WooCommerce category navigation available in DrillNav Pro.
 * Version:           1.6.2
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * Author:            urech.dev
 * Author URI:        https://urech.dev/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       drillnav-drilldown-navigation
 * Domain Path:       /languages
 *
 * @package           DrillNav
 * @fs_premium_only   /includes/integrations/class-woocommerce.php, /includes/admin/class-itemmeta.php, /includes/integrations/class-taxonomy.php, /includes/integrations/class-menu.php
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'drillnav_fs' ) ) {
	/**
	 * Free version is already loaded. Tell Freemius this is the premium version
	 * so it can auto-deactivate the free one.
	 *
	 * DO NOT REMOVE THIS IF — it is essential for the function_exists() check
	 * above to work correctly when both versions are present simultaneously.
	 */
	drillnav_fs()->set_basename( true, __FILE__ );
} else {

	// Plugin constants.
	define( 'DRILLNAV_VERSION', '1.6.2' );
	define( 'DRILLNAV_PLUGIN_FILE', __FILE__ );
	define( 'DRILLNAV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'DRILLNAV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	define( 'DRILLNAV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

	if ( ! function_exists( 'drillnav_fs' ) ) {
		/**
		 * Returns the Freemius SDK instance (or a no-op stub when the SDK is absent).
		 *
		 * Placed directly in the main file per Freemius documentation so activation
		 * and deactivation hooks are registered at the earliest possible point.
		 *
		 * NOTE: This is the PREMIUM build. Freemius auto-generates a free version for
		 * WordPress.org where is_premium = false and wp_org_gatekeeper is removed.
		 *
		 * @return object
		 */
		function drillnav_fs(): object {
			global $drillnav_fs;

			if ( ! isset( $drillnav_fs ) ) {
				$sdk = dirname( __FILE__ ) . '/vendor/freemius/start.php';

				if ( ! file_exists( $sdk ) ) {
					// SDK absent – safe no-op so the plugin loads without errors.
					$drillnav_fs = new class() { // phpcs:ignore Generic.Files.OneObjectStructurePerFile
						public function is_paying(): bool { return false; }
						public function is_trial(): bool { return false; }
						public function __call( string $name, array $args ): mixed { return null; }
					};
					return $drillnav_fs;
				}

				require_once $sdk;

				$drillnav_fs = fs_dynamic_init(
					array(
						'id'                  => '30662',
						'slug'                => 'drillnav-drilldown-navigation',
						'premium_slug'        => 'drillnav-drilldown-navigation-pro',
						'type'                => 'plugin',
						'public_key'          => 'pk_639c7d0bd48fda98d23d49f738b9d',
						'is_premium'          => true,
						'premium_suffix'      => '(Pro)',
						'has_premium_version' => true,
						'has_addons'          => false,
						'has_paid_plans'      => true,
						'is_org_compliant'    => true,
						// Automatically removed in the free version. If you're not using the
						// auto-generated free version, delete this line before uploading to wp.org.
						'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
						'trial'               => array(
							'days'               => 7,
							'is_require_payment' => false,
						),
						'menu'                => array(
							'slug'    => 'drillnav-drilldown-navigation',
							'support' => false,
							'parent'  => array(
								'slug' => 'options-general.php',
							),
						),
					)
				);
			}

			return $drillnav_fs;
		}

		// Init Freemius.
		drillnav_fs();
		// Signal that SDK was initiated.
		do_action( 'drillnav_fs_loaded' );
	}

	/**
	 * Cleans up all plugin data on uninstall.
	 *
	 * Hooked to Freemius's after_uninstall action so that the uninstall event
	 * (including user feedback) is reported to Freemius before data is removed.
	 * Do NOT use register_uninstall_hook() or uninstall.php alongside this.
	 */
	function drillnav_fs_uninstall_cleanup(): void {
		$option_keys = array(
			'drillnav_version',
			'drillnav_settings',
			'drillnav_onboarding_dismissed',
		);
		foreach ( $option_keys as $key ) {
			delete_option( $key );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_drillnav_%' OR option_name LIKE '_transient_timeout_drillnav_%'"
		);

		wp_cache_flush();
	}

	drillnav_fs()->add_action( 'after_uninstall', 'drillnav_fs_uninstall_cleanup' );

	/**
	 * PSR-4 style autoloader for the DrillNav\ namespace.
	 * Maps DrillNav\Admin\ClassName → includes/admin/class-classname.php
	 * Maps DrillNav\Blocks\ClassName → includes/blocks/class-classname.php
	 * Maps DrillNav\Integrations\ClassName → includes/integrations/class-classname.php
	 * Maps DrillNav\ClassName → includes/class-classname.php
	 */
	spl_autoload_register(
		function ( $class ) {
			$prefix = 'DrillNav\\';
			$len    = strlen( $prefix );

			if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				return;
			}

			$relative = substr( $class, $len );
			$parts    = explode( '\\', $relative );

			// Convert sub-namespace segments to directory path (lowercase).
			$filename_parts = array_map( 'strtolower', $parts );
			$class_name     = array_pop( $filename_parts );
			$sub_dir        = ! empty( $filename_parts ) ? implode( '/', $filename_parts ) . '/' : '';

			$file = DRILLNAV_PLUGIN_DIR . 'includes/' . $sub_dir . 'class-' . str_replace( '_', '-', $class_name ) . '.php';

			if ( file_exists( $file ) ) {
				require $file;
			}
		}
	);

	/**
	 * Returns the main plugin instance.
	 *
	 * @return \DrillNav\Plugin
	 */
	function drillnav() {
		return \DrillNav\Plugin::instance();
	}

	// Bootstrap on plugins_loaded so all plugins (incl. WooCommerce) are available.
	add_action( 'plugins_loaded', 'drillnav' );

	// Activation / deactivation hooks must be registered before plugins_loaded fires.
	register_activation_hook( __FILE__, array( '\DrillNav\Plugin', 'activate' ) );
	register_deactivation_hook( __FILE__, array( '\DrillNav\Plugin', 'deactivate' ) );

} // end else
