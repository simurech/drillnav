<?php
/**
 * Beaver Builder module integration.
 *
 * Registers a native Beaver Builder module for the DrillNav contextual navigation.
 * Requires Beaver Builder 2.0+.
 *
 * @package DrillNav
 */

namespace DrillNav\Integrations\PageBuilders;

defined( 'ABSPATH' ) || exit;

use DrillNav\Loader;

/**
 * Registers the DrillNav Beaver Builder module.
 */
class BeaverBuilder {

	/**
	 * @param Loader $loader
	 */
	public function register( Loader $loader ): void {
		$loader->add_action( 'init', array( $this, 'register_module' ) );
	}

	/** Registers the FLBuilder module. */
	public function register_module(): void {
		if ( ! class_exists( '\FLBuilder' ) || ! class_exists( '\FLBuilderModule' ) ) {
			return;
		}

		\FLBuilder::register_module(
			DrillNavBBModule::class,
			array(
				'general' => array(
					'title'    => __( 'General', 'drillnav-drilldown-navigation' ),
					'sections' => array(
						'settings' => array(
							'title'  => '',
							'fields' => array(
								'depth'        => array(
									'type'          => 'unit',
									'label'         => __( 'Max depth (0 = unlimited)', 'drillnav-drilldown-navigation' ),
									'default'       => 0,
									'units'         => array( '' ),
									'slider'        => array(
										'' => array( 'min' => 0, 'max' => 10, 'step' => 1 ),
									),
								),
								'show_home'    => array(
									'type'    => 'select',
									'label'   => __( 'Show home link', 'drillnav-drilldown-navigation' ),
									'default' => '1',
									'options' => array(
										'1' => __( 'Yes', 'drillnav-drilldown-navigation' ),
										'0' => __( 'No', 'drillnav-drilldown-navigation' ),
									),
								),
								'layout'       => array(
									'type'    => 'select',
									'label'   => __( 'Layout', 'drillnav-drilldown-navigation' ),
									'default' => 'list',
									'options' => array(
										'list'       => __( 'List (default)', 'drillnav-drilldown-navigation' ),
										'horizontal' => __( 'Horizontal', 'drillnav-drilldown-navigation' ),
										'accordion'  => __( 'Accordion (Pro)', 'drillnav-drilldown-navigation' ),
										'mega'       => __( 'Mega (Pro)', 'drillnav-drilldown-navigation' ),
									),
								),
								'animation'    => array(
									'type'    => 'select',
									'label'   => __( 'Animation', 'drillnav-drilldown-navigation' ),
									'default' => 'slide',
									'options' => array(
										'slide' => __( 'Slide', 'drillnav-drilldown-navigation' ),
										'fade'  => __( 'Fade', 'drillnav-drilldown-navigation' ),
										'none'  => __( 'None', 'drillnav-drilldown-navigation' ),
									),
								),
								'color_scheme' => array(
									'type'    => 'select',
									'label'   => __( 'Colour scheme', 'drillnav-drilldown-navigation' ),
									'default' => 'default',
									'options' => array(
										'default' => __( 'Default', 'drillnav-drilldown-navigation' ),
										'auto'    => __( 'Auto (follows OS)', 'drillnav-drilldown-navigation' ),
										'light'   => __( 'Light', 'drillnav-drilldown-navigation' ),
										'dark'    => __( 'Dark', 'drillnav-drilldown-navigation' ),
									),
								),
								'style_preset' => array(
									'type'    => 'select',
									'label'   => __( 'Style preset', 'drillnav-drilldown-navigation' ),
									'default' => 'default',
									'options' => array(
										'default'     => __( 'Default', 'drillnav-drilldown-navigation' ),
										'compact'     => __( 'Compact', 'drillnav-drilldown-navigation' ),
										'comfortable' => __( 'Comfortable', 'drillnav-drilldown-navigation' ),
										'cards'       => __( 'Cards (Pro)', 'drillnav-drilldown-navigation' ),
									),
								),
							),
						),
					),
				),
			)
		);
	}
}

/**
 * DrillNav Beaver Builder module class.
 */
class DrillNavBBModule extends \FLBuilderModule {

	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'DrillNav', 'drillnav-drilldown-navigation' ),
				'description'     => __( 'Contextual drill-down navigation for page hierarchies.', 'drillnav-drilldown-navigation' ),
				'group'           => __( 'Basic', 'drillnav-drilldown-navigation' ),
				'category'        => __( 'Navigation', 'drillnav-drilldown-navigation' ),
				'dir'             => DRILLNAV_PLUGIN_DIR . 'includes/integrations/pagebuilders/bb-module/',
				'url'             => DRILLNAV_PLUGIN_URL . 'includes/integrations/pagebuilders/bb-module/',
				'icon'            => 'list-view.svg',
				'partial_refresh' => true,
			)
		);
	}
}
