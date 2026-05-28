<?php
/**
 * Elementor widget integration.
 *
 * Registers a native Elementor widget for the DrillNav contextual navigation.
 * Requires Elementor 3.5+. The widget renders via the shared Plugin::render_navigation()
 * helper so all caching, Pro guards, and template logic are identical to the
 * block and shortcode placements.
 *
 * @package DrillNav
 */

namespace DrillNav\Integrations\PageBuilders;

defined( 'ABSPATH' ) || exit;

use DrillNav\Loader;

/**
 * Registers the DrillNav Elementor widget.
 */
class Elementor {

	/**
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	/** @param \Elementor\Widgets_Manager $manager */
	public function register_widget( $manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		$manager->register( new ElementorWidget() );
	}
}

/**
 * The DrillNav Elementor widget.
 */
class ElementorWidget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'drillnav';
	}

	public function get_title(): string {
		return esc_html__( 'DrillNav', 'drillnav-drilldown-navigation' );
	}

	public function get_icon(): string {
		return 'eicon-navigation-horizontal';
	}

	public function get_categories(): array {
		return array( 'general' );
	}

	public function get_keywords(): array {
		return array( 'navigation', 'drilldown', 'hierarchy', 'pages', 'contextual' );
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_settings',
			array( 'label' => esc_html__( 'Settings', 'drillnav-drilldown-navigation' ) )
		);

		$this->add_control(
			'depth',
			array(
				'label'       => esc_html__( 'Max depth', 'drillnav-drilldown-navigation' ),
				'description' => esc_html__( '0 = unlimited levels.', 'drillnav-drilldown-navigation' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 10,
				'default'     => 0,
			)
		);

		$this->add_control(
			'show_home',
			array(
				'label'        => esc_html__( 'Show home link', 'drillnav-drilldown-navigation' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'drillnav-drilldown-navigation' ),
				'label_off'    => esc_html__( 'No', 'drillnav-drilldown-navigation' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => esc_html__( 'Layout', 'drillnav-drilldown-navigation' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'list',
				'options' => array(
					'list'       => esc_html__( 'List (default)', 'drillnav-drilldown-navigation' ),
					'horizontal' => esc_html__( 'Horizontal', 'drillnav-drilldown-navigation' ),
					'accordion'  => esc_html__( 'Accordion (Pro)', 'drillnav-drilldown-navigation' ),
					'mega'       => esc_html__( 'Mega (Pro)', 'drillnav-drilldown-navigation' ),
				),
			)
		);

		$this->add_control(
			'animation',
			array(
				'label'   => esc_html__( 'Animation', 'drillnav-drilldown-navigation' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'slide',
				'options' => array(
					'slide' => esc_html__( 'Slide', 'drillnav-drilldown-navigation' ),
					'fade'  => esc_html__( 'Fade', 'drillnav-drilldown-navigation' ),
					'none'  => esc_html__( 'None', 'drillnav-drilldown-navigation' ),
				),
			)
		);

		$this->add_control(
			'color_scheme',
			array(
				'label'   => esc_html__( 'Colour scheme', 'drillnav-drilldown-navigation' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'default',
				'options' => array(
					'default' => esc_html__( 'Default (inherits theme)', 'drillnav-drilldown-navigation' ),
					'auto'    => esc_html__( 'Auto (follows OS)', 'drillnav-drilldown-navigation' ),
					'light'   => esc_html__( 'Light', 'drillnav-drilldown-navigation' ),
					'dark'    => esc_html__( 'Dark', 'drillnav-drilldown-navigation' ),
				),
			)
		);

		$this->add_control(
			'style_preset',
			array(
				'label'   => esc_html__( 'Style preset', 'drillnav-drilldown-navigation' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'default',
				'options' => array(
					'default'     => esc_html__( 'Default', 'drillnav-drilldown-navigation' ),
					'compact'     => esc_html__( 'Compact', 'drillnav-drilldown-navigation' ),
					'comfortable' => esc_html__( 'Comfortable', 'drillnav-drilldown-navigation' ),
					'cards'       => esc_html__( 'Cards (Pro)', 'drillnav-drilldown-navigation' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		$args = array(
			'depth'        => absint( $s['depth'] ?? 0 ),
			'show_home'    => ( 'yes' === ( $s['show_home'] ?? 'yes' ) ),
			'layout'       => sanitize_key( $s['layout'] ?? 'list' ),
			'animation'    => sanitize_key( $s['animation'] ?? 'slide' ),
			'color_scheme' => sanitize_key( $s['color_scheme'] ?? 'default' ),
			'style_preset' => sanitize_key( $s['style_preset'] ?? 'default' ),
		);

		if ( function_exists( 'drillnav' ) ) {
			echo drillnav()->render_navigation( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
