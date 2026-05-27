/**
 * DrillNav – Admin scripts.
 * Handles cache-clear AJAX and the WooCommerce attribute filter rules UI.
 *
 * @package DrillNav
 */

( function () {
	'use strict';

	/* ---------------------------------------------------------------
	 * Cache clear
	 * ------------------------------------------------------------- */

	const clearBtn = document.getElementById( 'drillnav-clear-cache' );
	if ( clearBtn ) {
		clearBtn.addEventListener( 'click', function ( e ) {
			e.preventDefault();

			const url    = drillnavAdmin.ajaxUrl;
			const notice = document.getElementById( 'drillnav-cache-notice' );

			clearBtn.disabled    = true;
			clearBtn.textContent = drillnavAdmin.clearing;

			fetch( url, {
				method:      'POST',
				credentials: 'same-origin',
				headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:        new URLSearchParams( {
					action: 'drillnav_clear_cache',
					nonce:  drillnavAdmin.nonce,
				} ).toString(),
			} )
				.then( ( r ) => r.json() )
				.then( ( data ) => {
					if ( notice ) {
						notice.textContent = data.success ? drillnavAdmin.cleared : drillnavAdmin.error;
						notice.className   = 'drillnav-notice ' + ( data.success ? 'drillnav-notice--success' : 'drillnav-notice--error' );
						notice.removeAttribute( 'hidden' );
					}
				} )
				.catch( () => {
					if ( notice ) {
						notice.textContent = drillnavAdmin.error;
						notice.className   = 'drillnav-notice drillnav-notice--error';
						notice.removeAttribute( 'hidden' );
					}
				} )
				.finally( () => {
					clearBtn.disabled    = false;
					clearBtn.textContent = drillnavAdmin.clearLabel;
				} );
		} );
	}

	/* ---------------------------------------------------------------
	 * WooCommerce attribute filter rules
	 * ------------------------------------------------------------- */

	const filterContainer = document.getElementById( 'drillnav-woo-filters' );
	if ( ! filterContainer ) {
		return;
	}

	const termsData = JSON.parse( filterContainer.dataset.terms || '{}' );
	const tbody     = document.getElementById( 'drillnav-filter-rules-body' );
	const addBtn    = document.getElementById( 'drillnav-add-filter-rule' );
	const template  = document.getElementById( 'drillnav-filter-rule-template' );

	/**
	 * Repopulates the term <select> for the given taxonomy.
	 *
	 * @param {HTMLSelectElement} taxSelect
	 * @param {HTMLSelectElement} termSelect
	 * @param {number|null}       selectedTermId  Pre-select this term (null = leave unselected).
	 */
	function populateTermSelect( taxSelect, termSelect, selectedTermId ) {
		const taxonomy    = taxSelect.value;
		const terms       = termsData[ taxonomy ] || [];
		const placeholder = termSelect.dataset.placeholder || '';

		termSelect.innerHTML = '<option value="">' + placeholder + '</option>';

		terms.forEach( function ( term ) {
			const opt       = document.createElement( 'option' );
			opt.value       = String( term.id );
			opt.textContent = term.name;
			if ( selectedTermId !== null && selectedTermId === term.id ) {
				opt.selected = true;
			}
			termSelect.appendChild( opt );
		} );
	}

	/** Removes the empty-state row when the first real rule is added. */
	function removeEmptyRow() {
		const row = document.getElementById( 'drillnav-filter-empty-row' );
		if ( row ) {
			row.remove();
		}
	}

	/** Re-inserts an empty-state row when all rules have been removed. */
	function maybeShowEmptyRow() {
		if ( tbody.querySelector( '.drillnav-filter-row' ) ) {
			return;
		}
		const tr  = document.createElement( 'tr' );
		tr.id     = 'drillnav-filter-empty-row';
		const td  = document.createElement( 'td' );
		td.colSpan = 4;
		td.innerHTML = '<em>' + ( tbody.dataset.emptyLabel || 'No rules yet.' ) + '</em>';
		tr.appendChild( td );
		tbody.appendChild( tr );
	}

	/**
	 * Renumbers all row name-attributes after an insertion or removal so
	 * indices remain sequential (0, 1, 2 …).
	 */
	function renumberRows() {
		tbody.querySelectorAll( '.drillnav-filter-row' ).forEach( function ( row, idx ) {
			row.querySelectorAll( '[name]' ).forEach( function ( el ) {
				el.setAttribute(
					'name',
					el.getAttribute( 'name' ).replace(
						/woo_attribute_filters\]\[\d+\]/,
						'woo_attribute_filters][' + idx + ']'
					)
				);
			} );
		} );
	}

	/** Wires up event listeners for a single rule row. */
	function initRow( row ) {
		const taxSelect  = row.querySelector( '.drillnav-filter-taxonomy' );
		const termSelect = row.querySelector( '.drillnav-filter-term' );
		const removeBtn  = row.querySelector( '.drillnav-remove-rule' );

		if ( taxSelect && termSelect ) {
			taxSelect.addEventListener( 'change', function () {
				populateTermSelect( taxSelect, termSelect, null );
			} );
		}

		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				row.remove();
				renumberRows();
				maybeShowEmptyRow();
			} );
		}
	}

	// Initialise rows that were server-rendered (saved rules).
	tbody.querySelectorAll( '.drillnav-filter-row' ).forEach( initRow );

	// Add rule.
	if ( addBtn && template ) {
		addBtn.addEventListener( 'click', function () {
			const idx   = tbody.querySelectorAll( '.drillnav-filter-row' ).length;
			const clone = template.content.cloneNode( true );
			const row   = clone.querySelector( '.drillnav-filter-row' );

			// Swap the __INDEX__ placeholder with the real sequential index.
			row.querySelectorAll( '[name]' ).forEach( function ( el ) {
				el.setAttribute( 'name', el.getAttribute( 'name' ).replace( /__INDEX__/g, String( idx ) ) );
			} );

			removeEmptyRow();
			tbody.appendChild( clone );

			const newRow = tbody.lastElementChild;
			initRow( newRow );

			// Move keyboard focus to the first select so the user can pick an attribute immediately.
			const firstSelect = newRow.querySelector( 'select' );
			if ( firstSelect ) {
				firstSelect.focus();
			}
		} );
	}

} )();
