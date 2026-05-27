/**
 * DrillNav – Gutenberg Block Editor script.
 *
 * Written with wp.element.createElement (no JSX, no build step required).
 * Runs in the block editor only. The frontend rendering is done server-side
 * via the PHP render_callback; this file only provides the editor UI.
 *
 * @package DrillNav
 */

( function () {
	'use strict';

	const { registerBlockType }                = wp.blocks;
	const { createElement: el, Fragment }      = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const ServerSideRender                     = wp.serverSideRender;
	const {
		PanelBody,
		PanelRow,
		ToggleControl,
		RangeControl,
		TextControl,
		SelectControl,
		Spinner,
		Notice,
	} = wp.components;
	const { __ } = wp.i18n;

	/* ------------------------------------------------------------------
	 * Helper: Pro feature badge
	 * ----------------------------------------------------------------- */
	function ProBadge() {
		return el(
			'span',
			{
				style: {
					display:       'inline-block',
					marginLeft:    '0.4em',
					padding:       '0.1em 0.4em',
					background:    '#f0b429',
					color:         '#000',
					borderRadius:  '3px',
					fontSize:      '0.7em',
					fontWeight:    700,
					verticalAlign: 'middle',
					textTransform: 'uppercase',
					letterSpacing: '0.05em',
				},
			},
			'PRO'
		);
	}

	/* ------------------------------------------------------------------
	 * Block definition
	 * ----------------------------------------------------------------- */
	registerBlockType( 'drillnav/contextual-nav', {

		/**
		 * Editor component.
		 */
		edit: function ( { attributes, setAttributes } ) {
			const {
				depth,
				showHome,
				homeLabel,
				showBreadcrumb,
				showBackButton,
				animation,
				colorScheme,
				mobileToggle,
				postType,
			} = attributes;

			const isPro = typeof drillnavBlock !== 'undefined' && drillnavBlock.isPro;

			const blockProps = useBlockProps( {
				className: 'drillnav-editor-preview',
			} );

			const inspectorPanel = el(
				InspectorControls,
				null,

				/* --- General --- */
				el( PanelBody,
					{
						title:       __( 'General', 'drillnav-drilldown-navigation' ),
						initialOpen: true,
					},
					el( PanelRow, null,
						el( ToggleControl, {
							label:    __( 'Show home link', 'drillnav-drilldown-navigation' ),
							help:     __( 'Adds a link to the site home as the first breadcrumb ancestor.', 'drillnav-drilldown-navigation' ),
							checked:  showHome,
							onChange: ( val ) => setAttributes( { showHome: val } ),
						} )
					),
					showHome && el( PanelRow, null,
						el( TextControl, {
							label:    __( 'Home label', 'drillnav-drilldown-navigation' ),
							help:     __( 'Leave empty to use the site name.', 'drillnav-drilldown-navigation' ),
							value:    homeLabel,
							onChange: ( val ) => setAttributes( { homeLabel: val } ),
						} )
					),
					el( PanelRow, null,
						el( RangeControl, {
							label:    __( 'Max depth (0 = unlimited)', 'drillnav-drilldown-navigation' ),
							value:    depth,
							min:      0,
							max:      10,
							onChange: ( val ) => setAttributes( { depth: val } ),
						} )
					),
				),

				/* --- Display --- */
				el( PanelBody,
					{
						title:       __( 'Display', 'drillnav-drilldown-navigation' ),
						initialOpen: false,
					},
					el( PanelRow, null,
						el( ToggleControl, {
							label:    __( 'Show breadcrumb', 'drillnav-drilldown-navigation' ),
							checked:  showBreadcrumb,
							onChange: ( val ) => setAttributes( { showBreadcrumb: val } ),
						} )
					),
					el( PanelRow, null,
						el( ToggleControl, {
							label:    __( 'Show back button', 'drillnav-drilldown-navigation' ),
							checked:  showBackButton,
							onChange: ( val ) => setAttributes( { showBackButton: val } ),
						} )
					),
					el( PanelRow, null,
						el( SelectControl, {
							label:    __( 'Animation', 'drillnav-drilldown-navigation' ),
							value:    animation,
							options:  [
								{ label: __( 'Slide (default)', 'drillnav-drilldown-navigation' ), value: 'slide' },
								{ label: __( 'Fade', 'drillnav-drilldown-navigation' ),            value: 'fade'  },
								{ label: __( 'None', 'drillnav-drilldown-navigation' ),            value: 'none'  },
							],
							onChange: ( val ) => setAttributes( { animation: val } ),
						} )
					),
					el( PanelRow, null,
						el( SelectControl, {
							label:    __( 'Colour scheme', 'drillnav-drilldown-navigation' ),
							value:    colorScheme,
							options:  [
								{ label: __( 'Default (inherits from theme)', 'drillnav-drilldown-navigation' ), value: 'default' },
								{ label: __( 'Light', 'drillnav-drilldown-navigation' ),                        value: 'light'   },
								{ label: __( 'Dark', 'drillnav-drilldown-navigation' ),                        value: 'dark'    },
							],
							onChange: ( val ) => setAttributes( { colorScheme: val } ),
						} )
					),
				),

				/* --- Mobile --- */
				el( PanelBody,
					{
						title:       __( 'Mobile', 'drillnav-drilldown-navigation' ),
						initialOpen: false,
					},
					el( PanelRow, null,
						el( ToggleControl, {
							label:    __( 'Mobile hamburger toggle', 'drillnav-drilldown-navigation' ),
							help:     __( 'On mobile (≤768 px) the navigation is hidden behind a hamburger icon and slides in as a side drawer.', 'drillnav-drilldown-navigation' ),
							checked:  mobileToggle,
							onChange: ( val ) => setAttributes( { mobileToggle: val } ),
						} )
					),
				),

				/* --- WooCommerce (Pro) --- */
				el( PanelBody,
					{
						title: el( Fragment, null,
							__( 'WooCommerce', 'drillnav-drilldown-navigation' ),
							el( ProBadge )
						),
						initialOpen: false,
					},
					isPro
						? el( 'p',
							{ style: { margin: '0', fontSize: '0.9em' } },
							__( 'WooCommerce product category navigation is active. Configure it on the DrillNav settings page.', 'drillnav-drilldown-navigation' )
						  )
						: el( Notice,
							{
								status:        'info',
								isDismissible: false,
							},
							el( 'span', null,
								__( 'WooCommerce product category navigation is available in ', 'drillnav-drilldown-navigation' ),
								el( 'strong', null, __( 'DrillNav Pro', 'drillnav-drilldown-navigation' ) ),
								'. ',
								el( 'a',
									{
										href:   ( typeof drillnavBlock !== 'undefined' && drillnavBlock.upgradeUrl ) ? drillnavBlock.upgradeUrl : '#',
										target: '_blank',
									},
									__( 'Learn more →', 'drillnav-drilldown-navigation' )
								)
							)
						  )
				),
			);

			// Server-side render preview.
			const preview = el(
				'div',
				blockProps,
				el( ServerSideRender, {
					block:      'drillnav/contextual-nav',
					attributes: attributes,
					EmptyResponsePlaceholder: () => el(
						'div',
						{ className: 'drillnav-editor-empty' },
						__( 'DrillNav: No pages to show at this level. Add child pages or visit a page that belongs to a hierarchy.', 'drillnav-drilldown-navigation' )
					),
					LoadingResponsePlaceholder: () => el(
						'div',
						{ className: 'drillnav-editor-loading' },
						el( Spinner )
					),
				} )
			);

			return el( Fragment, null, inspectorPanel, preview );
		},

		/**
		 * The block is fully server-side rendered; save returns null.
		 */
		save: function () {
			return null;
		},
	} );

} )();
