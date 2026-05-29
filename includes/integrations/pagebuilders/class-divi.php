<?php
/**
 * Divi Builder module integration.
 *
 * Registers a native Divi module for the DrillNav contextual navigation.
 * Requires Divi theme or Divi Builder plugin (ET_Builder_Module class).
 *
 * @package DrillNav
 */

namespace DrillNav\Integrations\PageBuilders;

defined( 'ABSPATH' ) || exit;

use DrillNav\Loader;

/**
 * Registers the DrillNav Divi module.
 */
class Divi {

	/**
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'et_builder_ready', array( $this, 'register_module' ) );
	}

	/** Instantiates the Divi module class, which auto-registers it. */
	public function register_module(): void {
		if ( ! class_exists( '\ET_Builder_Module' ) ) {
			return;
		}
		new DiviModule();
	}
}

/**
 * DrillNav Divi module.
 * Must extend ET_Builder_Module which registers itself on instantiation.
 */
class DiviModule extends \ET_Builder_Module {

	/** @var string */
	public $slug = 'et_pb_drillnav';

	/** @var string */
	public $vb_support = 'on';

	/** @var array<string,mixed> */
	protected $module_credits = array(
		'module_uri' => '',
		'author'     => 'DrillNav',
		'author_uri' => '',
	);

	public function init(): void {
		$this->name                        = esc_html__( 'DrillNav', 'drillnav-drilldown-navigation' );
		$this->main_css_element            = '%%order_class%%.et_pb_drillnav';
		$this->settings_modal_toggles      = array(
			'general' => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Navigation Settings', 'drillnav-drilldown-navigation' ),
				),
			),
		);
	}

	/**
	 * Returns the module's field definitions.
	 *
	 * @return array<string,mixed>
	 */
	public function get_fields(): array {
		return array(
			'depth'        => array(
				'label'            => esc_html__( 'Max depth', 'drillnav-drilldown-navigation' ),
				'type'             => 'range',
				'range_settings'   => array( 'min' => 0, 'max' => 10, 'step' => 1 ),
				'default'          => 0,
				'description'      => esc_html__( '0 = unlimited levels.', 'drillnav-drilldown-navigation' ),
				'toggle_slug'      => 'main_content',
			),
			'show_home'    => array(
				'label'            => esc_html__( 'Show home link', 'drillnav-drilldown-navigation' ),
				'type'             => 'yes_no_button',
				'options'          => array(
					'on'  => esc_html__( 'Yes', 'drillnav-drilldown-navigation' ),
					'off' => esc_html__( 'No', 'drillnav-drilldown-navigation' ),
				),
				'default'          => 'on',
				'toggle_slug'      => 'main_content',
			),
			'layout'       => array(
				'label'            => esc_html__( 'Layout', 'drillnav-drilldown-navigation' ),
				'type'             => 'select',
				'options'          => array(
					'list'       => esc_html__( 'List (default)', 'drillnav-drilldown-navigation' ),
					'horizontal' => esc_html__( 'Horizontal', 'drillnav-drilldown-navigation' ),
					'accordion'  => esc_html__( 'Accordion (Pro)', 'drillnav-drilldown-navigation' ),
				),
				'default'          => 'list',
				'toggle_slug'      => 'main_content',
			),
			'animation'    => array(
				'label'            => esc_html__( 'Animation', 'drillnav-drilldown-navigation' ),
				'type'             => 'select',
				'options'          => array(
					'slide' => esc_html__( 'Slide', 'drillnav-drilldown-navigation' ),
					'fade'  => esc_html__( 'Fade', 'drillnav-drilldown-navigation' ),
					'none'  => esc_html__( 'None', 'drillnav-drilldown-navigation' ),
				),
				'default'          => 'slide',
				'toggle_slug'      => 'main_content',
			),
			'color_scheme' => array(
				'label'            => esc_html__( 'Colour scheme', 'drillnav-drilldown-navigation' ),
				'type'             => 'select',
				'options'          => array(
					'default' => esc_html__( 'Default (inherits theme)', 'drillnav-drilldown-navigation' ),
					'auto'    => esc_html__( 'Auto (follows OS)', 'drillnav-drilldown-navigation' ),
					'light'   => esc_html__( 'Light', 'drillnav-drilldown-navigation' ),
					'dark'    => esc_html__( 'Dark', 'drillnav-drilldown-navigation' ),
				),
				'default'          => 'default',
				'toggle_slug'      => 'main_content',
			),
			'style_preset' => array(
				'label'            => esc_html__( 'Style preset', 'drillnav-drilldown-navigation' ),
				'type'             => 'select',
				'options'          => array(
					'default'     => esc_html__( 'Default', 'drillnav-drilldown-navigation' ),
					'compact'     => esc_html__( 'Compact', 'drillnav-drilldown-navigation' ),
					'comfortable' => esc_html__( 'Comfortable', 'drillnav-drilldown-navigation' ),
					'cards'       => esc_html__( 'Cards (Pro)', 'drillnav-drilldown-navigation' ),
				),
				'default'          => 'default',
				'toggle_slug'      => 'main_content',
			),
		);
	}

	/**
	 * @param array<string,mixed> $attrs
	 * @param string|null         $content
	 * @param string              $render_slug
	 * @return string
	 */
	public function render( $attrs, $content = null, $render_slug = '' ): string {
		if ( ! function_exists( 'drillnav' ) ) {
			return '';
		}

		$args = array(
			'depth'        => absint( $this->props['depth'] ?? 0 ),
			'show_home'    => 'on' === ( $this->props['show_home'] ?? 'on' ),
			'layout'       => sanitize_key( $this->props['layout'] ?? 'list' ),
			'animation'    => sanitize_key( $this->props['animation'] ?? 'slide' ),
			'color_scheme' => sanitize_key( $this->props['color_scheme'] ?? 'default' ),
			'style_preset' => sanitize_key( $this->props['style_preset'] ?? 'default' ),
		);

		return drillnav()->render_navigation( $args );
	}
}
