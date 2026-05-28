<?php
/**
 * [drillnav] shortcode handler.
 *
 * @package DrillNav
 */

namespace DrillNav;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the [drillnav] shortcode.
 *
 * Usage: [drillnav depth="0" show_home="yes" title="Navigation"]
 */
class Shortcode {

	/** Tracks whether assets have been enqueued this request. */
	private static bool $assets_enqueued = false;

	public function __construct(
		private readonly Navigator $navigator,
		private readonly Settings  $settings
	) {}

	/**
	 * Registers the shortcode and asset hooks with the loader.
	 *
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'init', array( $this, 'register_shortcode' ) );
		$loader->add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/** Registers the shortcode with WordPress. */
	public function register_shortcode(): void {
		add_shortcode( 'drillnav', array( $this, 'render' ) );
	}

	/** Registers (but does not enqueue) the frontend assets. */
	public function register_assets(): void {
		if ( $this->settings->get( 'load_css' ) ) {
			wp_register_style(
				'drillnav-frontend',
				DRILLNAV_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				DRILLNAV_VERSION
			);
		}

		if ( $this->settings->get( 'load_a11y_css' ) ) {
			wp_register_style(
				'drillnav-a11y',
				DRILLNAV_PLUGIN_URL . 'assets/css/a11y.css',
				array( 'drillnav-frontend' ),
				DRILLNAV_VERSION
			);
		}

		wp_register_script(
			'drillnav-frontend',
			DRILLNAV_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			DRILLNAV_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Localised strings for JS (translated, safe for JS).
		wp_localize_script(
			'drillnav-frontend',
			'drillnavL10n',
			array(
				'back'         => __( 'Back', 'drillnav-drilldown-navigation' ),
				/* translators: %s: parent page title */
				'backTo'       => __( 'Back to %s', 'drillnav-drilldown-navigation' ),
				/* translators: %s: page title */
				'showSubPages' => __( 'Show sub-pages of %s', 'drillnav-drilldown-navigation' ),
				'restUrl'      => esc_url_raw( rest_url( 'drillnav/v1/' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'depth'         => '',
				'show_home'     => '',
				'home_label'    => '',
				'title'         => '',
				'post_type'     => '',
				'mobile_toggle' => '',
				'max_width'             => '',
				'multiple_back_buttons' => '',
				'style_preset'          => '',
			),
			$atts,
			'drillnav'
		);

		$args = $this->parse_atts( $atts );

		$this->enqueue_assets();

		return $this->render_navigation( $args );
	}

	/**
	 * Converts raw shortcode attributes to Navigator args.
	 *
	 * @param array<string,string> $atts
	 * @return array<string,mixed>
	 */
	private function parse_atts( array $atts ): array {
		$args = array();

		if ( '' !== $atts['depth'] ) {
			$args['depth'] = absint( $atts['depth'] );
		}
		if ( '' !== $atts['show_home'] ) {
			$args['show_home'] = in_array( strtolower( $atts['show_home'] ), array( 'yes', '1', 'true' ), true );
		}
		if ( '' !== $atts['home_label'] ) {
			$args['home_label'] = sanitize_text_field( $atts['home_label'] );
		}
		if ( '' !== $atts['post_type'] && post_type_exists( $atts['post_type'] ) ) {
			$args['post_type'] = sanitize_key( $atts['post_type'] );
		}
		if ( '' !== $atts['mobile_toggle'] ) {
			$args['mobile_toggle'] = in_array( strtolower( $atts['mobile_toggle'] ), array( 'yes', '1', 'true' ), true );
		}
		if ( '' !== $atts['max_width'] ) {
			$args['max_width'] = sanitize_text_field( $atts['max_width'] );
		}
		if ( '' !== $atts['multiple_back_buttons'] ) {
			$args['multiple_back_buttons'] = in_array( strtolower( $atts['multiple_back_buttons'] ), array( 'yes', '1', 'true' ), true );
		}
		if ( '' !== $atts['style_preset'] ) {
			$args['style_preset'] = sanitize_key( $atts['style_preset'] );
		}

		return $args;
	}

	/**
	 * Renders the navigation HTML.
	 *
	 * @param array<string,mixed> $args
	 * @return string
	 */
	public function render_navigation( array $args = array() ): string {
		$mobile_toggle = ! empty( $args['mobile_toggle'] );
		unset( $args['mobile_toggle'] );

		$nav_data = $this->navigator->get_nav_data( $args );

		if ( empty( $nav_data['current_level'] ) ) {
			return '';
		}

		$instance = 'drillnav-' . uniqid();

		ob_start();
		include DRILLNAV_PLUGIN_DIR . 'includes/views/navigation.php';
		return ob_get_clean() ?: '';
	}

	/** Enqueues assets once per page load. */
	private function enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;

		if ( $this->settings->get( 'load_css' ) ) {
			wp_enqueue_style( 'drillnav-frontend' );
		}
		if ( $this->settings->get( 'load_a11y_css' ) ) {
			wp_enqueue_style( 'drillnav-a11y' );
		}
		wp_enqueue_script( 'drillnav-frontend' );
	}
}
