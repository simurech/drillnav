/**
 * DrillNav – frontend drill-down navigation.
 *
 * Panel-replacement approach: each drill-down replaces the visible list with
 * the children of the clicked item. A back button returns to the previous level.
 *
 * Pure Vanilla ES6. No jQuery. No build step required.
 * Each .drillnav element on the page is an independent instance.
 *
 * @package DrillNav
 */

( function () {
	'use strict';

	/* -------------------------------------------------------------------------
	 * REST helper
	 * ---------------------------------------------------------------------- */

	async function fetchChildren( postId, postType ) {
		const base  = ( window.drillnavL10n && window.drillnavL10n.restUrl ) || '/wp-json/drillnav/v1/';
		const nonce = ( window.drillnavL10n && window.drillnavL10n.nonce ) || '';
		const url   = base + 'children?post_id=' + encodeURIComponent( postId ) +
		              '&post_type=' + encodeURIComponent( postType );

		const res = await fetch( url, {
			headers:     { 'X-WP-Nonce': nonce },
			credentials: 'same-origin',
		} );

		if ( ! res.ok ) {
			throw new Error( 'DrillNav REST error: ' + res.status );
		}

		return res.json();
	}

	/* -------------------------------------------------------------------------
	 * Item renderer
	 * ---------------------------------------------------------------------- */

	function buildItem( item ) {
		const li  = document.createElement( 'li' );
		const row = document.createElement( 'div' );
		li.setAttribute( 'role', 'listitem' );
		li.className  = 'drillnav__item';
		row.className = 'drillnav__row';

		if ( item.is_current ) {
			li.classList.add( 'drillnav__item--current' );
		}
		if ( item.has_children ) {
			li.classList.add( 'drillnav__item--has-children' );
		}

		const a       = document.createElement( 'a' );
		a.className   = 'drillnav__link';
		a.href        = item.url;
		a.textContent = item.title;
		if ( item.is_current ) {
			a.setAttribute( 'aria-current', 'page' );
		}
		row.appendChild( a );

		if ( item.has_children ) {
			const btn     = document.createElement( 'button' );
			btn.type      = 'button';
			btn.className = 'drillnav__expand-btn';
			btn.setAttribute(
				'aria-label',
				( window.drillnavL10n
					? window.drillnavL10n.showSubPages.replace( '%s', item.title )
					: 'Show sub-pages of ' + item.title )
			);
			btn.dataset.drillnavItemId   = String( item.id );
			btn.dataset.drillnavItemType = item.post_type || 'page';

			const arrow     = document.createElement( 'span' );
			arrow.className = 'drillnav__arrow';
			arrow.setAttribute( 'aria-hidden', 'true' );
			arrow.textContent = '›';
			btn.appendChild( arrow );
			row.appendChild( btn );
		}

		li.appendChild( row );
		return li;
	}

	/* -------------------------------------------------------------------------
	 * DrillNav instance class
	 * ---------------------------------------------------------------------- */

	class DrillNavInstance {

		/** @param {HTMLElement} root */
		constructor( root ) {
			this.root       = root;
			this.instanceId = root.dataset.drillnavInstance;
			this.animation  = root.dataset.drillnavAnimation || 'slide';
			this.loading    = false;

			// Each entry: { listHTML, backHTML, backWasHidden, triggerItemId, parentTitle }.
			this.levelStack = [];

			const dataEl = document.getElementById( this.instanceId + '-data' );
			try {
				this.data = dataEl ? JSON.parse( dataEl.textContent ) : {};
			} catch ( e ) {
				this.data = {};
			}

			this.multipleBackButtons = !! ( this.data && this.data.settings && this.data.settings.multiple_back_buttons );
			this.layout              = root.dataset.drillnavLayout || 'list';
			this.preloadCache        = new Map();
			this.accordionLazy       = !! ( this.data && this.data.settings && this.data.settings.accordion_lazy );

			this.panel = document.getElementById( this.instanceId + '-panel' );
			if ( ! this.panel ) {
				return;
			}

			this.list = this.panel.querySelector( '.drillnav__list' );
			if ( ! this.list ) {
				return;
			}

			// Accordion layout: different interaction model – no drill-down stack.
			if ( this.layout === 'accordion' ) {
				this._bindAccordionEvents();
				this._autoExpandAccordion();
				return;
			}

			this.backWrap = root.querySelector( '.drillnav__back-wrap' );

			// If the SSR template did not render a back-wrap, create a hidden placeholder.
			if ( ! this.backWrap ) {
				this.backWrap           = document.createElement( 'div' );
				this.backWrap.className = 'drillnav__back-wrap';
				this.backWrap.hidden    = true;
				this.panel.insertAdjacentElement( 'beforebegin', this.backWrap );
			}

			// Drawer support (mobile hamburger toggle).
			this.drawerWrap = root.closest( '[data-drillnav-drawer-wrap]' );

			this.searchInput  = root.querySelector( '.drillnav__search-input' );
			this.searchClear  = root.querySelector( '.drillnav__search-clear' );
			this._searchQuery = '';

			this._bindEvents();
			if ( this.drawerWrap ) {
				this._bindDrawerEvents();
			}
		}

		_bindEvents() {
			// Expand buttons – drill down into item's children.
			this.root.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( '[data-drillnav-item-id]' );
				if ( btn && this.root.contains( btn ) ) {
					e.preventDefault();
					this._drillDown( btn );
				}
			} );

			// Back button – JS intercepts only when we have pushed at least one level.
			// When levelStack is empty the SSR <a> tag navigates normally.
			this.root.addEventListener( 'click', ( e ) => {
				const back = e.target.closest( '[data-drillnav-back]' );
				if ( back && this.root.contains( back ) && this.levelStack.length > 0 ) {
					e.preventDefault();
					const levelAttr = back.dataset.drillnavLevel;
					if ( levelAttr !== undefined ) {
						this._goBackToLevel( parseInt( levelAttr, 10 ) );
					} else {
						this._goBack();
					}
				}
			} );

			// Keyboard: Escape navigates back one level (drawer close takes priority).
			this.root.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' && this.levelStack.length > 0 && ! this._isDrawerOpen() ) {
					e.preventDefault();
					this._goBack();
				}
			} );

			// Hover preloading: kick off a children fetch when user hovers an expand button.
			this.root.addEventListener( 'mouseover', ( e ) => {
				if ( this.layout === 'accordion' && ! this.accordionLazy ) {
					return;
				}
				const btn = e.target.closest( '[data-drillnav-item-id]' );
				if ( ! btn || ! this.root.contains( btn ) ) {
					return;
				}
				if ( this.layout === 'accordion' && ! btn.dataset.drillnavLazy ) {
					return;
				}
				const key = btn.dataset.drillnavItemId + ':' + ( btn.dataset.drillnavItemType || 'page' );
				if ( ! this.preloadCache.has( key ) ) {
					this.preloadCache.set(
						key,
						fetchChildren(
							parseInt( btn.dataset.drillnavItemId, 10 ),
							btn.dataset.drillnavItemType || 'page'
						).catch( () => null )
					);
				}
			} );

			// Search/filter input.
			if ( this.searchInput ) {
				let searchDebounce;
				this.searchInput.addEventListener( 'input', () => {
					clearTimeout( searchDebounce );
					searchDebounce = setTimeout( () => this._filterItems( this.searchInput.value.trim() ), 200 );
				} );
				if ( this.searchClear ) {
					this.searchClear.addEventListener( 'click', () => {
						this.searchInput.value = '';
						this._filterItems( '' );
						this.searchInput.focus();
					} );
				}
			}
		}

		/** @param {HTMLButtonElement} btn */
		async _drillDown( btn ) {
			if ( this.loading ) {
				return;
			}

			const postId      = parseInt( btn.dataset.drillnavItemId, 10 );
			const postType    = btn.dataset.drillnavItemType || 'page';
			const parentTitle = btn.closest( '.drillnav__row' )
				?.querySelector( '.drillnav__link' )
				?.textContent?.trim() ?? '';

			this.loading = true;
			btn.disabled = true;

			const cacheKey = postId + ':' + postType;
			let children;
			try {
				const preloaded = this.preloadCache.get( cacheKey );
				if ( preloaded !== undefined ) {
					children = await preloaded;
					if ( children === null ) {
						children = await fetchChildren( postId, postType );
					}
				} else {
					children = await fetchChildren( postId, postType );
				}
			} catch ( err ) {
				// eslint-disable-next-line no-console
				console.warn( 'DrillNav: failed to load children', err );
				return;
			} finally {
				this.loading = false;
				btn.disabled = false;
			}

			if ( ! children || ! children.length ) {
				// No children – navigate to the page directly.
				const link = btn.previousElementSibling;
				if ( link && link.href ) {
					window.location.href = link.href;
				}
				return;
			}

			// Save current state so _goBack() can fully restore it.
			this.levelStack.push( {
				listHTML:      this.list.innerHTML,
				backHTML:      this.backWrap.innerHTML,
				backWasHidden: this.backWrap.hidden,
				triggerItemId: String( postId ),
				parentTitle:   parentTitle,
			} );

			// Replace list with children.
			const frag = document.createDocumentFragment();
			children.forEach( ( item ) => frag.appendChild( buildItem( item ) ) );
			this.list.innerHTML = '';
			this.list.appendChild( frag );

			if ( this.searchInput ) {
				this.searchInput.value = '';
				this._filterItems( '' );
			}

			// Update back button(s).
			if ( this.multipleBackButtons ) {
				this._renderMultipleBackButtons();
			} else {
				this.backWrap.innerHTML = '';
				this.backWrap.hidden    = false;
				this.backWrap.appendChild( this._makeBackBtn( parentTitle ) );
			}

			// Animate: new list slides in from the right.
			this._animateList( this.list, 'in' );

			// A11Y: move focus to the first interactive element in the new list.
			requestAnimationFrame( () => {
				const first = this.list.querySelector( 'a[href], button:not([disabled])' );
				if ( first ) {
					first.focus( { preventScroll: true } );
				}
			} );
		}

		_goBack() {
			this._goBackToLevel( this.levelStack.length - 1 );
		}

		/**
		 * Jumps back to the state saved at targetIndex in the levelStack.
		 *
		 * @param {number} targetIndex  Stack index of the state to restore.
		 */
		_goBackToLevel( targetIndex ) {
			const state = this.levelStack[ targetIndex ];
			if ( ! state ) {
				return;
			}

			const triggerId = state.triggerItemId;
			this.levelStack = this.levelStack.slice( 0, targetIndex );

			this.list.innerHTML = state.listHTML;

			if ( this.searchInput ) {
				this.searchInput.value = '';
				this._filterItems( '' );
			}

			if ( this.multipleBackButtons ) {
				this._renderMultipleBackButtons();
			} else {
				this.backWrap.innerHTML = state.backHTML;
				this.backWrap.hidden    = state.backWasHidden;
			}

			// Animate: restored list slides in from the left.
			this._animateList( this.list, 'back' );

			// A11Y: return focus to the button that initiated the drill-down.
			requestAnimationFrame( () => {
				const btn = this.list.querySelector(
					'[data-drillnav-item-id="' + triggerId + '"]'
				);
				if ( btn ) {
					btn.focus( { preventScroll: true } );
				}
			} );
		}

		/**
		 * Renders all accumulated back buttons, one per stack entry (oldest first).
		 * Called only when multipleBackButtons mode is active.
		 */
		_renderMultipleBackButtons() {
			this.backWrap.innerHTML = '';
			this.backWrap.hidden    = this.levelStack.length === 0;
			this.levelStack.forEach( ( state, index ) => {
				this.backWrap.appendChild( this._makeBackBtn( state.parentTitle, index ) );
			} );
		}

		/**
		 * @param {string}      label       Parent page title shown in the back button.
		 * @param {number|null} levelIndex  Stack index this button jumps back to (null = single-mode).
		 */
		_makeBackBtn( label, levelIndex = null ) {
			const btn     = document.createElement( 'button' );
			btn.type      = 'button';
			btn.className = 'drillnav__back-btn';
			btn.dataset.drillnavBack = '';
			if ( levelIndex !== null ) {
				btn.dataset.drillnavLevel = String( levelIndex );
			}
			btn.setAttribute(
				'aria-label',
				( window.drillnavL10n
					? window.drillnavL10n.backTo.replace( '%s', label )
					: 'Back to ' + label )
			);

			const arrow     = document.createElement( 'span' );
			arrow.className = 'drillnav__back-arrow';
			arrow.setAttribute( 'aria-hidden', 'true' );
			arrow.textContent = '←';

			const lbl       = document.createElement( 'span' );
			lbl.className   = 'drillnav__back-label';
			lbl.textContent = label;

			btn.appendChild( arrow );
			btn.appendChild( lbl );
			return btn;
		}

		/** @param {string} query */
		_filterItems( query ) {
			this._searchQuery = query;
			const lower = query.toLowerCase();
			const panel = this.root.querySelector( '.drillnav__panel' );
			if ( ! panel ) {
				return;
			}
			panel.querySelectorAll( '.drillnav__item' ).forEach( ( item ) => {
				const link  = item.querySelector( '.drillnav__link' );
				const label = link ? link.textContent.toLowerCase() : '';
				item.classList.toggle( 'is-hidden', query !== '' && ! label.includes( lower ) );
			} );
			if ( this.searchClear ) {
				this.searchClear.hidden = query === '';
			}
		}

		/**
		 * @param {HTMLElement} ul
		 * @param {'in'|'back'} direction  'in' = from right, 'back' = from left.
		 */
		_animateList( ul, direction ) {
			if ( this.animation === 'none' ) {
				return;
			}
			const cls = 'drillnav--anim-' + this.animation + '-' + direction;
			ul.classList.add( cls );
			ul.addEventListener( 'animationend', () => ul.classList.remove( cls ), { once: true } );
		}

		/* -----------------------------------------------------------------------
		 * Accordion mode
		 * -------------------------------------------------------------------- */

		_bindAccordionEvents() {
			this.root.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( '[data-drillnav-item-id]' );
				if ( btn && this.root.contains( btn ) ) {
					e.preventDefault();
					this._toggleAccordionItem( btn ); // returns Promise; fire-and-forget is intentional
				}
			} );

			this.root.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' ) {
					const openItem = e.target.closest( '.drillnav__item.is-open' );
					if ( openItem ) {
						const btn = openItem.querySelector( '[data-drillnav-item-id]' );
						if ( btn ) {
							e.preventDefault();
							this._toggleAccordionItem( btn );
							btn.focus( { preventScroll: true } );
						}
					}
				}
			} );
		}

		/** @param {HTMLButtonElement} btn */
		async _toggleAccordionItem( btn ) {
			const li      = btn.closest( '.drillnav__item' );
			const sublist = li ? li.querySelector( '[data-drillnav-sub]' ) : null;
			if ( ! li || ! sublist ) {
				return;
			}
			const isOpen = li.classList.contains( 'is-open' );

			// Lazy-load children before opening if not yet loaded.
			if ( ! isOpen && this.accordionLazy && btn.dataset.drillnavLazy && ! sublist.children.length ) {
				btn.setAttribute( 'aria-busy', 'true' );
				const children = await this._lazyLoadAccordionChildren( btn );
				btn.removeAttribute( 'aria-busy' );
				if ( children && children.length ) {
					children.forEach( ( item ) => sublist.appendChild( this._buildAccordionItem( item ) ) );
				}
				delete btn.dataset.drillnavLazy;
			}

			if ( isOpen ) {
				sublist.style.maxHeight = '0';
				li.classList.remove( 'is-open' );
				btn.setAttribute( 'aria-expanded', 'false' );
				sublist.setAttribute( 'aria-hidden', 'true' );
			} else {
				sublist.style.maxHeight = sublist.scrollHeight + 'px';
				li.classList.add( 'is-open' );
				btn.setAttribute( 'aria-expanded', 'true' );
				sublist.setAttribute( 'aria-hidden', 'false' );
				this._recalcParentHeights( sublist );
			}
		}

		/** @param {HTMLButtonElement} btn */
		async _lazyLoadAccordionChildren( btn ) {
			const postId   = parseInt( btn.dataset.drillnavItemId, 10 );
			const postType = btn.dataset.drillnavItemType || 'page';
			const cacheKey = postId + ':' + postType;
			try {
				const preloaded = this.preloadCache.get( cacheKey );
				const children  = preloaded !== undefined
					? await preloaded
					: await fetchChildren( postId, postType );
				return children;
			} catch {
				return null;
			}
		}

		/** @param {object} item */
		_buildAccordionItem( item ) {
			const li  = document.createElement( 'li' );
			const row = document.createElement( 'div' );
			li.setAttribute( 'role', 'listitem' );
			li.className  = 'drillnav__item';
			row.className = 'drillnav__row';

			if ( item.is_current ) {
				li.classList.add( 'drillnav__item--current' );
			}
			if ( item.has_children ) {
				li.classList.add( 'drillnav__item--has-children' );
			}

			const a       = document.createElement( 'a' );
			a.className   = 'drillnav__link';
			a.href        = item.url;
			a.textContent = item.title;
			if ( item.is_current ) {
				a.setAttribute( 'aria-current', 'page' );
			}
			row.appendChild( a );

			if ( item.has_children ) {
				const btn = document.createElement( 'button' );
				btn.type  = 'button';
				btn.className = 'drillnav__expand-btn';
				btn.setAttribute( 'aria-expanded', 'false' );
				btn.setAttribute(
					'aria-label',
					( window.drillnavL10n
						? window.drillnavL10n.showSubPages.replace( '%s', item.title )
						: 'Show sub-pages of ' + item.title )
				);
				btn.setAttribute( 'data-drillnav-item-id',   String( item.id ) );
				btn.setAttribute( 'data-drillnav-item-type', item.post_type || 'page' );
				if ( this.accordionLazy ) {
					btn.setAttribute( 'data-drillnav-lazy', '1' );
				}

				const arrow     = document.createElement( 'span' );
				arrow.className = 'drillnav__arrow';
				arrow.setAttribute( 'aria-hidden', 'true' );
				arrow.textContent = '›';
				btn.appendChild( arrow );
				row.appendChild( btn );

				const sublist = document.createElement( 'ul' );
				sublist.className = 'drillnav__sublist';
				sublist.setAttribute( 'role', 'list' );
				sublist.setAttribute( 'data-drillnav-sub', '' );
				sublist.setAttribute( 'aria-hidden', 'true' );
				li.appendChild( row );
				li.appendChild( sublist );
				return li;
			}

			li.appendChild( row );
			return li;
		}

		/** Recalculates max-height on all open ancestor sublists after a nested open. */
		_recalcParentHeights( el ) {
			let node = el.parentElement;
			while ( node && node !== this.root ) {
				if ( node.dataset.drillnavSub !== undefined && node.style.maxHeight ) {
					node.style.maxHeight = node.scrollHeight + 'px';
				}
				node = node.parentElement;
			}
		}

		/** Opens the path to the current page on initial render. */
		_autoExpandAccordion() {
			const ancestorIds = new Set(
				( this.data.ancestors || [] ).map( ( a ) => String( a.id ) )
			);
			ancestorIds.forEach( ( id ) => {
				const btn = this.root.querySelector( '[data-drillnav-item-id="' + id + '"]' );
				if ( btn ) {
					this._toggleAccordionItem( btn );
				}
			} );
		}

		/** @returns {boolean} */
		_isDrawerOpen() {
			return !! ( this.drawerWrap && this.drawerWrap.classList.contains( 'is-open' ) );
		}

		_bindDrawerEvents() {
			const toggleBtn = this.drawerWrap.querySelector( '[data-drillnav-toggle]' );
			const backdrop  = this.drawerWrap.querySelector( '[data-drillnav-backdrop]' );

			if ( toggleBtn ) {
				toggleBtn.addEventListener( 'click', () => this._toggleDrawer() );
			}
			if ( backdrop ) {
				backdrop.addEventListener( 'click', () => this._closeDrawer() );
			}

			// Close on Escape (fires after the root keydown handler in bubble order).
			document.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' && this._isDrawerOpen() ) {
					e.preventDefault();
					this._closeDrawer();
				}
			} );
		}

		_openDrawer() {
			this.drawerWrap.classList.add( 'is-open' );
			const toggleBtn = this.drawerWrap.querySelector( '[data-drillnav-toggle]' );
			if ( toggleBtn ) {
				toggleBtn.setAttribute( 'aria-expanded', 'true' );
			}
			document.body.style.overflow = 'hidden';

			// Move focus to the first interactive element inside the nav.
			requestAnimationFrame( () => {
				const first = this.root.querySelector( 'a[href], button:not([disabled])' );
				if ( first ) {
					first.focus( { preventScroll: true } );
				}
			} );
		}

		_closeDrawer() {
			this.drawerWrap.classList.remove( 'is-open' );
			const toggleBtn = this.drawerWrap.querySelector( '[data-drillnav-toggle]' );
			if ( toggleBtn ) {
				toggleBtn.setAttribute( 'aria-expanded', 'false' );
				toggleBtn.focus( { preventScroll: true } );
			}
			document.body.style.overflow = '';
		}

		_toggleDrawer() {
			if ( this._isDrawerOpen() ) {
				this._closeDrawer();
			} else {
				this._openDrawer();
			}
		}
	}

	/* -------------------------------------------------------------------------
	 * Bootstrap: initialise every .drillnav element on the page
	 * ---------------------------------------------------------------------- */

	function init() {
		document.querySelectorAll( '.drillnav[data-drillnav-instance]' ).forEach( ( el ) => {
			new DrillNavInstance( el );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
