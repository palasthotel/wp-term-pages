/**
 * Page autocomplete for the "Overriding page" term field.
 *
 * The visible input searches published pages, the adjacent hidden input carries
 * the page ID that is submitted with the term. Typing without picking a
 * suggestion clears the ID, so a half-typed title never silently keeps the
 * previously connected page.
 */
( function () {
	'use strict';

	var config = window.termPagesAdmin || {};
	var minChars = config.minChars || 2;
	var counter = 0;

	/**
	 * Page titles come from get_the_title() and may carry HTML entities.
	 * DOMParser does not run scripts, so this is a safe way to get plain text.
	 */
	function toText( html ) {
		return new DOMParser().parseFromString( String( html ), 'text/html' )
			.documentElement.textContent;
	}

	function debounce( fn, wait ) {
		var timer;
		return function () {
			var args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function () {
				fn.apply( null, args );
			}, wait );
		};
	}

	function setup( search ) {
		var form = search.form;
		var id = search.parentNode.querySelector( '.term-pages-page-id' );

		if ( ! id || search.dataset.termPagesReady ) {
			return;
		}

		search.dataset.termPagesReady = '1';

		var listId = 'term-pages-results-' + ++counter;
		var list = document.createElement( 'ul' );
		var items = [];
		var active = -1;
		var request = 0;

		list.id = listId;
		list.className = 'term-pages-results';
		list.setAttribute( 'role', 'listbox' );
		list.hidden = true;
		search.insertAdjacentElement( 'afterend', list );

		search.setAttribute( 'role', 'combobox' );
		search.setAttribute( 'aria-autocomplete', 'list' );
		search.setAttribute( 'aria-controls', listId );
		search.setAttribute( 'aria-expanded', 'false' );

		function close() {
			list.hidden = true;
			list.textContent = '';
			items = [];
			active = -1;
			search.setAttribute( 'aria-expanded', 'false' );
			search.removeAttribute( 'aria-activedescendant' );
		}

		function highlight( index ) {
			var options = list.children;

			if ( active > -1 && options[ active ] ) {
				options[ active ].classList.remove( 'is-active' );
				options[ active ].setAttribute( 'aria-selected', 'false' );
			}

			active = index;

			if ( active > -1 && options[ active ] ) {
				options[ active ].classList.add( 'is-active' );
				options[ active ].setAttribute( 'aria-selected', 'true' );
				options[ active ].scrollIntoView( { block: 'nearest' } );
				search.setAttribute( 'aria-activedescendant', options[ active ].id );
			} else {
				search.removeAttribute( 'aria-activedescendant' );
			}
		}

		function choose( index ) {
			var item = items[ index ];

			if ( ! item ) {
				return;
			}

			search.value = toText( item.value );
			id.value = String( item.id );
			close();
		}

		function render( results ) {
			close();
			items = Array.isArray( results ) ? results : [];

			if ( ! items.length ) {
				return;
			}

			items.forEach( function ( item, index ) {
				var option = document.createElement( 'li' );
				option.id = listId + '-' + index;
				option.setAttribute( 'role', 'option' );
				option.setAttribute( 'aria-selected', 'false' );
				option.textContent = toText( item.label );
				// mousedown instead of click, so the input does not lose focus first.
				option.addEventListener( 'mousedown', function ( event ) {
					event.preventDefault();
					choose( index );
				} );
				list.appendChild( option );
			} );

			list.hidden = false;
			search.setAttribute( 'aria-expanded', 'true' );
		}

		var lookup = debounce( function ( term ) {
			var current = ++request;
			var body = new URLSearchParams( {
				action: config.action,
				_ajax_nonce: config.nonce,
				taxonomy: search.dataset.taxonomy || '',
				term: term,
			} );

			fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					// Ignore answers that arrive after a newer request was sent.
					if ( current === request ) {
						render( result && result.success ? result.data : [] );
					}
				} )
				.catch( function () {
					if ( current === request ) {
						close();
					}
				} );
		}, 250 );

		// Any manual edit invalidates the selection until a page is picked again.
		search.addEventListener( 'input', function () {
			id.value = '';

			if ( search.value.trim().length < minChars ) {
				request++;
				close();
				return;
			}

			lookup( search.value.trim() );
		} );

		search.addEventListener( 'keydown', function ( event ) {
			if ( list.hidden ) {
				return;
			}

			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				highlight( ( active + 1 ) % items.length );
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				highlight( active > 0 ? active - 1 : items.length - 1 );
			} else if ( 'Enter' === event.key && active > -1 ) {
				// Pick the suggestion instead of submitting the term form.
				event.preventDefault();
				choose( active );
			} else if ( 'Escape' === event.key ) {
				event.preventDefault();
				close();
			}
		} );

		search.addEventListener( 'blur', close );

		if ( ! form ) {
			return;
		}

		// An empty search field means "no page". After adding a term, WordPress
		// empties the visible inputs but keeps hidden ones, so without this the
		// next term would silently get the previous term's page. The click
		// listener runs in the capture phase, before core serializes the form.
		function syncEmpty() {
			if ( '' === search.value.trim() ) {
				id.value = '';
			}
		}

		form.addEventListener(
			'click',
			function ( event ) {
				if ( event.target.closest( '[type="submit"]' ) ) {
					syncEmpty();
				}
			},
			true
		);
		form.addEventListener( 'submit', syncEmpty, true );
	}

	function init() {
		document.querySelectorAll( '.term-pages-page-search' ).forEach( setup );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
