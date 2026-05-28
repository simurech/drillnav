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
	const { createElement: el, Fragment, useState, useEffect } = wp.element;
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
				layout,
				depth,
				showHome,
				homeLabel,
				showBackButton,
				animation,
				colorScheme,
				mobileToggle,
				postType,
				maxWidth,
				multipleBackButtons,
				stylePreset,
				customFontSize,
				customPaddingY,
				customPaddingX,
				customBorderRadius,
				customTransitionSpeed,
				customColorLink,
				customColorCurrentBg,
				customColorHover,
				customColorArrow,
				searchFilter,
				accordionLazy,
				menuId,
			} = attributes;

			// Fetch WP nav menus for the Navigation Source selector (Pro).
			const [ menus, setMenus ] = useState( [] );
			useEffect( () => {
				if ( ! isPro ) { return; }
				wp.apiFetch( { path: '/wp/v2/menus?per_page=100' } )
					.then( ( data ) => { if ( Array.isArray( data ) ) { setMenus( data ); } } )
					.catch( () => {} );
			}, [] );

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
							help:     __( 'Adds a link to the site home as the first back-navigation step.', 'drillnav-drilldown-navigation' ),
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

				/* --- Layout --- */
				el( PanelBody,
					{
						title:       __( 'Layout', 'drillnav-drilldown-navigation' ),
						initialOpen: true,
					},
					el( PanelRow, null,
						el( SelectControl, {
							label:    __( 'Display layout', 'drillnav-drilldown-navigation' ),
							value:    layout,
							options:  [
								{ label: __( 'List (default)', 'drillnav-drilldown-navigation' ),  value: 'list'        },
								{ label: __( 'Horizontal', 'drillnav-drilldown-navigation' ),       value: 'horizontal'  },
								{ label: __( 'Accordion (Pro)', 'drillnav-drilldown-navigation' ),  value: 'accordion'   },
								{ label: __( 'Mega (Pro)', 'drillnav-drilldown-navigation' ),       value: 'mega'        },
							],
							onChange: ( val ) => setAttributes( { layout: val } ),
						} )
					),
					[ 'accordion', 'mega' ].includes( layout ) && ! isPro && el( Notice,
						{
							status:        'warning',
							isDismissible: false,
						},
						el( 'span', null,
							__( 'This layout requires ', 'drillnav-drilldown-navigation' ),
							el( 'strong', null, __( 'DrillNav Pro', 'drillnav-drilldown-navigation' ) ),
							'. ',
							el( 'a',
								{
									href:   ( typeof drillnavBlock !== 'undefined' && drillnavBlock.upgradeUrl ) ? drillnavBlock.upgradeUrl : '#',
									target: '_blank',
								},
								__( 'Upgrade →', 'drillnav-drilldown-navigation' )
							)
						)
					),
					layout === 'accordion' && el( PanelRow, null,
						el( ToggleControl, {
							label:    el( Fragment, null, __( 'Lazy-load levels', 'drillnav-drilldown-navigation' ), el( ProBadge ) ),
							help:     __( 'Load child levels on demand instead of server-side rendering the full tree.', 'drillnav-drilldown-navigation' ),
							checked:  !! ( accordionLazy && isPro ),
							onChange: ( val ) => setAttributes( { accordionLazy: val } ),
							disabled: ! isPro,
						} )
					)
				),

				/* --- Style preset --- */
				el( PanelBody,
					{
						title:       __( 'Style preset', 'drillnav-drilldown-navigation' ),
						initialOpen: false,
					},
					el( PanelRow, null,
						el( SelectControl, {
							label:    __( 'Preset', 'drillnav-drilldown-navigation' ),
							value:    stylePreset,
							options:  [
								{ label: __( 'Default', 'drillnav-drilldown-navigation' ),       value: 'default'     },
								{ label: __( 'Compact', 'drillnav-drilldown-navigation' ),       value: 'compact'     },
								{ label: __( 'Comfortable', 'drillnav-drilldown-navigation' ),   value: 'comfortable' },
								{ label: __( 'Cards (Pro)', 'drillnav-drilldown-navigation' ),   value: 'cards'       },
							],
							onChange: ( val ) => setAttributes( { stylePreset: val } ),
						} )
					),
					stylePreset === 'cards' && ! isPro && el( Notice,
						{
							status:        'warning',
							isDismissible: false,
						},
						el( 'span', null,
							__( 'The Cards preset requires ', 'drillnav-drilldown-navigation' ),
							el( 'strong', null, __( 'DrillNav Pro', 'drillnav-drilldown-navigation' ) ),
							'. ',
							el( 'a',
								{
									href:   ( typeof drillnavBlock !== 'undefined' && drillnavBlock.upgradeUrl ) ? drillnavBlock.upgradeUrl : '#',
									target: '_blank',
								},
								__( 'Upgrade →', 'drillnav-drilldown-navigation' )
							)
						)
					)
				),

				/* --- Display --- */
				el( PanelBody,
					{
						title:       __( 'Display', 'drillnav-drilldown-navigation' ),
						initialOpen: false,
					},
					el( PanelRow, null,
						el( ToggleControl, {
							label:    __( 'Show back button', 'drillnav-drilldown-navigation' ),
							checked:  showBackButton,
							onChange: ( val ) => setAttributes( { showBackButton: val } ),
						} )
					),
					showBackButton && el( PanelRow, null,
						el( ToggleControl, {
							label:    __( 'Multiple back buttons', 'drillnav-drilldown-navigation' ),
							help:     __( 'Shows one back button per drilled level (oldest first). Click any to jump directly to that level.', 'drillnav-drilldown-navigation' ),
							checked:  multipleBackButtons,
							onChange: ( val ) => setAttributes( { multipleBackButtons: val } ),
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
								{ label: __( 'Auto (follows OS preference)', 'drillnav-drilldown-navigation' ),  value: 'auto'    },
								{ label: __( 'Light', 'drillnav-drilldown-navigation' ),                        value: 'light'   },
								{ label: __( 'Dark', 'drillnav-drilldown-navigation' ),                        value: 'dark'    },
							],
							onChange: ( val ) => setAttributes( { colorScheme: val } ),
						} )
					),
					el( PanelRow, null,
						el( TextControl, {
							label:       __( 'Max width', 'drillnav-drilldown-navigation' ),
							help:        __( 'e.g. 300px or 60%. Leave empty for full width.', 'drillnav-drilldown-navigation' ),
							value:       maxWidth,
							onChange:    ( val ) => setAttributes( { maxWidth: val } ),
							placeholder: '',
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
					el( PanelRow, null,
						el( ToggleControl, {
							label:    el( Fragment, null, __( 'Search / Filter', 'drillnav-drilldown-navigation' ), el( ProBadge ) ),
							help:     __( 'Show a live text filter above the navigation items (List and Horizontal layouts only).', 'drillnav-drilldown-navigation' ),
							checked:  !! ( searchFilter && isPro ),
							onChange: ( val ) => setAttributes( { searchFilter: val } ),
							disabled: ! isPro,
						} )
					),
				),

				/* --- Customize (Pro) --- */
				el( PanelBody,
					{
						title: el( Fragment, null,
							__( 'Customize', 'drillnav-drilldown-navigation' ),
							el( ProBadge )
						),
						initialOpen: false,
					},
					isPro
						? el( Fragment, null,
							el( PanelRow, null,
								el( TextControl, {
									label:       __( 'Font size', 'drillnav-drilldown-navigation' ),
									value:       customFontSize,
									onChange:    ( val ) => setAttributes( { customFontSize: val } ),
									placeholder: '1rem',
								} )
							),
							el( PanelRow, null,
								el( TextControl, {
									label:       __( 'Padding top/bottom', 'drillnav-drilldown-navigation' ),
									value:       customPaddingY,
									onChange:    ( val ) => setAttributes( { customPaddingY: val } ),
									placeholder: '0.5rem',
								} )
							),
							el( PanelRow, null,
								el( TextControl, {
									label:       __( 'Padding left/right', 'drillnav-drilldown-navigation' ),
									value:       customPaddingX,
									onChange:    ( val ) => setAttributes( { customPaddingX: val } ),
									placeholder: '0.75rem',
								} )
							),
							el( PanelRow, null,
								el( TextControl, {
									label:       __( 'Border radius', 'drillnav-drilldown-navigation' ),
									value:       customBorderRadius,
									onChange:    ( val ) => setAttributes( { customBorderRadius: val } ),
									placeholder: '4px',
								} )
							),
							el( PanelRow, null,
								el( TextControl, {
									label:       __( 'Transition speed', 'drillnav-drilldown-navigation' ),
									value:       customTransitionSpeed,
									onChange:    ( val ) => setAttributes( { customTransitionSpeed: val } ),
									placeholder: '200ms',
								} )
							),
							el( PanelRow, null,
								el( TextControl, {
									label:       __( 'Link colour', 'drillnav-drilldown-navigation' ),
									value:       customColorLink,
									onChange:    ( val ) => setAttributes( { customColorLink: val } ),
									placeholder: 'inherit',
								} )
							),
							el( PanelRow, null,
								el( TextControl, {
									label:       __( 'Current item background', 'drillnav-drilldown-navigation' ),
									value:       customColorCurrentBg,
									onChange:    ( val ) => setAttributes( { customColorCurrentBg: val } ),
									placeholder: 'rgba(0,0,0,0.06)',
								} )
							),
							el( PanelRow, null,
								el( TextControl, {
									label:       __( 'Hover background', 'drillnav-drilldown-navigation' ),
									value:       customColorHover,
									onChange:    ( val ) => setAttributes( { customColorHover: val } ),
									placeholder: 'rgba(0,0,0,0.08)',
								} )
							),
							el( PanelRow, null,
								el( TextControl, {
									label:       __( 'Arrow colour', 'drillnav-drilldown-navigation' ),
									value:       customColorArrow,
									onChange:    ( val ) => setAttributes( { customColorArrow: val } ),
									placeholder: 'rgba(0,0,0,0.4)',
								} )
							)
						  )
						: el( Notice,
							{
								status:        'info',
								isDismissible: false,
							},
							el( 'span', null,
								__( 'Granular styling options are available in ', 'drillnav-drilldown-navigation' ),
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

				/* --- Navigation Source (Pro) --- */
				el( PanelBody,
					{
						title: el( Fragment, null,
							__( 'Navigation Source', 'drillnav-drilldown-navigation' ),
							el( ProBadge )
						),
						initialOpen: false,
					},
					isPro
						? el( PanelRow, null,
							el( SelectControl, {
								label:    __( 'Use WP menu as source', 'drillnav-drilldown-navigation' ),
								help:     __( 'Replace the page hierarchy with a WordPress nav menu. Hybrid: WooCommerce product-category items gain sub-categories automatically.', 'drillnav-drilldown-navigation' ),
								value:    String( menuId || 0 ),
								options:  [
									{ label: __( '— Page hierarchy (default) —', 'drillnav-drilldown-navigation' ), value: '0' },
									...menus.map( ( m ) => ( { label: m.name, value: String( m.id ) } ) ),
								],
								onChange: ( val ) => setAttributes( { menuId: parseInt( val, 10 ) || 0 } ),
							} )
						)
						: el( Notice,
							{
								status:        'info',
								isDismissible: false,
							},
							el( 'span', null,
								__( 'Using a WP navigation menu as the data source requires ', 'drillnav-drilldown-navigation' ),
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

	/* ------------------------------------------------------------------
	 * Block Variations
	 * ----------------------------------------------------------------- */
	const { registerBlockVariation } = wp.blocks;

	const variations = [
		{
			name:        'drillnav-horizontal',
			title:       __( 'Horizontal Navigation', 'drillnav-drilldown-navigation' ),
			description: __( 'Navigation items arranged in a horizontal row.', 'drillnav-drilldown-navigation' ),
			icon:        'align-wide',
			attributes:  { layout: 'horizontal' },
			scope:       [ 'inserter', 'transform' ],
			isDefault:   false,
		},
		{
			name:        'drillnav-compact',
			title:       __( 'Compact List', 'drillnav-drilldown-navigation' ),
			description: __( 'Smaller font and reduced padding for tight sidebar use.', 'drillnav-drilldown-navigation' ),
			icon:        'list-view',
			attributes:  { stylePreset: 'compact' },
			scope:       [ 'inserter', 'transform' ],
			isDefault:   false,
		},
		{
			name:        'drillnav-dark',
			title:       __( 'Dark Navigation', 'drillnav-drilldown-navigation' ),
			description: __( 'Dark colour scheme – works on any background.', 'drillnav-drilldown-navigation' ),
			icon:        'admin-appearance',
			attributes:  { colorScheme: 'dark' },
			scope:       [ 'inserter', 'transform' ],
			isDefault:   false,
		},
		{
			name:        'drillnav-accordion',
			title:       __( 'Accordion (Pro)', 'drillnav-drilldown-navigation' ),
			description: __( 'Full page tree with expandable levels. Requires DrillNav Pro.', 'drillnav-drilldown-navigation' ),
			icon:        'menu-alt',
			attributes:  { layout: 'accordion' },
			scope:       [ 'inserter', 'transform' ],
			isDefault:   false,
		},
		{
			name:        'drillnav-mega',
			title:       __( 'Mega Navigation (Pro)', 'drillnav-drilldown-navigation' ),
			description: __( 'CSS-Grid multi-column layout. Requires DrillNav Pro.', 'drillnav-drilldown-navigation' ),
			icon:        'grid-view',
			attributes:  { layout: 'mega' },
			scope:       [ 'inserter', 'transform' ],
			isDefault:   false,
		},
	];

	variations.forEach( function ( v ) {
		registerBlockVariation( 'drillnav/contextual-nav', v );
	} );

} )();
