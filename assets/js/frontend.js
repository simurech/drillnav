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

			// Each entry: { listHTML, backHTML, backWasHidden, triggerItemId }.
			this.levelStack = [];

			const dataEl = document.getElementById( this.instanceId + '-data' );
			try {
				this.data = dataEl ? JSON.parse( dataEl.textContent ) : {};
			} catch ( e ) {
				this.data = {};
			}

			this.panel = document.getElementById( this.instanceId + '-panel' );
			if ( ! this.panel ) {
				return;
			}

			this.list = this.panel.querySelector( '.drillnav__list' );
			if ( ! this.list ) {
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
					this._goBack();
				}
			} );

			// Keyboard: Escape navigates back one level (drawer close takes priority).
			this.root.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' && this.levelStack.length > 0 && ! this._isDrawerOpen() ) {
					e.preventDefault();
					this._goBack();
				}
			} );
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

			let children;
			try {
				children = await fetchChildren( postId, postType );
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
			} );

			// Replace list with children.
			const frag = document.createDocumentFragment();
			children.forEach( ( item ) => frag.appendChild( buildItem( item ) ) );
			this.list.innerHTML = '';
			this.list.appendChild( frag );

			// Replace back-wrap content with a labelled back button.
			this.backWrap.innerHTML = '';
			this.backWrap.hidden    = false;
			this.backWrap.appendChild( this._makeBackBtn( parentTitle ) );

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
			const state = this.levelStack.pop();
			if ( ! state ) {
				return;
			}

			this.list.innerHTML     = state.listHTML;
			this.backWrap.innerHTML = state.backHTML;
			this.backWrap.hidden    = state.backWasHidden;

			// Animate: restored list slides in from the left.
			this._animateList( this.list, 'back' );

			// A11Y: return focus to the button that initiated the drill-down.
			requestAnimationFrame( () => {
				const btn = this.list.querySelector(
					'[data-drillnav-item-id="' + state.triggerItemId + '"]'
				);
				if ( btn ) {
					btn.focus( { preventScroll: true } );
				}
			} );
		}

		/** @param {string} label  Parent page title shown in the back button. */
		_makeBackBtn( label ) {
			const btn     = document.createElement( 'button' );
			btn.type      = 'button';
			btn.className = 'drillnav__back-btn';
			btn.dataset.drillnavBack = '';
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
