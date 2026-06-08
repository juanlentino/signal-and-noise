/**
 * Signal & Noise — reader-facing Notes-scoped command palette.
 *
 * Open with ⌘/Ctrl-K, "/" (outside form fields), or the visible trigger button.
 * Three action types:
 *   1. Search notes → location.assign( notesUrl + '?s=' + encodeURIComponent(q) )
 *   2. Jump to a recent note (window.SN_CMDK.recent)
 *   3. Jump to a pillar page (window.SN_CMDK.pillars)
 *
 * Buildless ES5 (no JSX, no build step). Distinct from the plugin's wp-admin
 * @wordpress/commands palette — these never coexist on one document.
 *
 * Accessibility = APG dialog + combobox:
 *   - role="dialog" aria-modal="true", focus trap, Escape restores trigger focus.
 *   - input is role="combobox" with aria-activedescendant tracking the active
 *     option — REAL DOM focus stays on the input; options are never focused.
 *
 * XSS: option labels come from window.SN_CMDK (JSON_HEX_TAG-encoded server-side)
 * and are written with textContent only — never innerHTML.
 *
 * @since theme v9.11.0
 */
( function () {
	'use strict';

	var data = window.SN_CMDK || { notesUrl: '/notes/', recent: [], pillars: [] };

	var root, backdrop, input, list, trigger, lastFocus;
	var items = [];      // The flat, currently-rendered model.
	var activeIndex = -1;
	var built = false;
	var isOpen = false;

	// ── Model ──────────────────────────────────────────────────────────────

	/**
	 * Build the item model for a query. Row 0 is always the synthetic "search"
	 * action; pillars + recent notes follow, substring-filtered when there's a
	 * query. Each item: { type, label, sub, url, query }.
	 */
	function buildItems( q ) {
		var out = [];
		var trimmed = ( q || '' ).replace( /^\s+|\s+$/g, '' );

		out.push( {
			type: 'search',
			label: trimmed ? 'Search notes for “' + trimmed + '”' : 'Search all notes',
			sub: 'Enter',
			query: trimmed
		} );

		var needle = trimmed.toLowerCase();
		function matches( label ) {
			return ! needle || label.toLowerCase().indexOf( needle ) !== -1;
		}

		var i;
		for ( i = 0; i < data.pillars.length; i++ ) {
			if ( matches( data.pillars[ i ].t ) ) {
				out.push( { type: 'pillar', label: data.pillars[ i ].t, sub: 'Pillar', url: data.pillars[ i ].u } );
			}
		}
		for ( i = 0; i < data.recent.length; i++ ) {
			if ( matches( data.recent[ i ].t ) ) {
				out.push( { type: 'note', label: data.recent[ i ].t, sub: 'Note', url: data.recent[ i ].u } );
			}
		}
		return out;
	}

	// ── Render ─────────────────────────────────────────────────────────────

	function render( q ) {
		items = buildItems( q );
		list.textContent = '';
		for ( var i = 0; i < items.length; i++ ) {
			var li = document.createElement( 'li' );
			li.id = 'sn-cmdk-opt-' + i;
			li.className = 'sn-cmdk-option';
			li.setAttribute( 'role', 'option' );
			li.setAttribute( 'data-index', String( i ) );

			var label = document.createElement( 'span' );
			label.className = 'sn-cmdk-option__label';
			label.textContent = items[ i ].label;
			li.appendChild( label );

			if ( items[ i ].sub ) {
				var sub = document.createElement( 'span' );
				sub.className = 'sn-cmdk-option__sub';
				sub.textContent = items[ i ].sub;
				li.appendChild( sub );
			}
			list.appendChild( li );
		}
		// Reset active to the first row (the search action) on every keystroke.
		setActive( items.length ? 0 : -1 );
	}

	function setActive( index ) {
		var opts = list.children;
		var i;
		for ( i = 0; i < opts.length; i++ ) {
			opts[ i ].classList.remove( 'is-active' );
			opts[ i ].removeAttribute( 'aria-selected' );
		}
		activeIndex = index;
		if ( index >= 0 && index < opts.length ) {
			var el = opts[ index ];
			el.classList.add( 'is-active' );
			el.setAttribute( 'aria-selected', 'true' );
			input.setAttribute( 'aria-activedescendant', el.id );
			// Keep the active row in view without moving DOM focus off the input.
			if ( el.scrollIntoView ) {
				el.scrollIntoView( { block: 'nearest' } );
			}
		} else {
			input.setAttribute( 'aria-activedescendant', '' );
		}
	}

	function move( delta ) {
		if ( ! items.length ) { return; }
		var next = activeIndex + delta;
		if ( next < 0 ) { next = items.length - 1; }
		if ( next >= items.length ) { next = 0; }
		setActive( next );
	}

	// ── Activate ───────────────────────────────────────────────────────────

	function activate( index ) {
		var item = items[ index ];
		if ( ! item ) { return; }
		if ( item.type === 'search' ) {
			var q = item.query || input.value.replace( /^\s+|\s+$/g, '' );
			var url = data.notesUrl + ( q ? '?s=' + encodeURIComponent( q ) : '' );
			window.location.assign( url );
			return;
		}
		if ( item.url ) {
			window.location.assign( item.url );
		}
	}

	// ── Open / close ───────────────────────────────────────────────────────

	function open( fromTrigger ) {
		ensureBuilt();
		if ( isOpen ) { return; }
		lastFocus = fromTrigger || document.activeElement;
		isOpen = true;
		root.hidden = false;
		input.value = '';
		render( '' );
		// Defer focus so the unhide has applied (Safari focus-on-hidden quirk).
		window.setTimeout( function () { input.focus(); }, 0 );
		document.addEventListener( 'keydown', onTrapKeydown, true );
	}

	function close() {
		if ( ! isOpen ) { return; }
		isOpen = false;
		root.hidden = true;
		document.removeEventListener( 'keydown', onTrapKeydown, true );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	// Focus trap: the dialog has exactly one tabbable control (the input), so
	// Tab/Shift-Tab simply re-focus it. Escape restores focus to the opener.
	function onTrapKeydown( e ) {
		if ( ! isOpen ) { return; }
		if ( e.key === 'Escape' || e.keyCode === 27 ) {
			e.preventDefault();
			close();
			return;
		}
		if ( e.key === 'Tab' || e.keyCode === 9 ) {
			e.preventDefault();
			input.focus();
		}
	}

	// ── Build the DOM once ───────────────────────────────────────────────────

	function ensureBuilt() {
		if ( built ) { return; }
		built = true;

		root = document.createElement( 'div' );
		root.id = 'sn-cmdk';
		root.className = 'sn-cmdk';
		root.setAttribute( 'role', 'dialog' );
		root.setAttribute( 'aria-modal', 'true' );
		root.setAttribute( 'aria-label', 'Search and navigate' );
		root.hidden = true;

		backdrop = document.createElement( 'div' );
		backdrop.className = 'sn-cmdk-backdrop';
		backdrop.setAttribute( 'data-close', '' );
		root.appendChild( backdrop );

		var panel = document.createElement( 'div' );
		panel.className = 'sn-cmdk-panel';
		panel.setAttribute( 'role', 'search' );

		input = document.createElement( 'input' );
		input.className = 'sn-cmdk-input';
		input.type = 'text';
		input.setAttribute( 'role', 'combobox' );
		input.setAttribute( 'aria-expanded', 'true' );
		input.setAttribute( 'aria-controls', 'sn-cmdk-list' );
		input.setAttribute( 'aria-autocomplete', 'list' );
		input.setAttribute( 'aria-activedescendant', '' );
		input.setAttribute( 'aria-label', 'Search notes' );
		input.setAttribute( 'placeholder', 'Search notes, jump to a page…' );
		input.setAttribute( 'autocomplete', 'off' );
		input.setAttribute( 'autocapitalize', 'off' );
		input.setAttribute( 'spellcheck', 'false' );
		panel.appendChild( input );

		list = document.createElement( 'ul' );
		list.id = 'sn-cmdk-list';
		list.className = 'sn-cmdk-list';
		list.setAttribute( 'role', 'listbox' );
		list.setAttribute( 'aria-label', 'Results' );
		panel.appendChild( list );

		root.appendChild( panel );
		document.body.appendChild( root );

		// Events.
		input.addEventListener( 'input', function () { render( input.value ); } );

		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowDown' || e.keyCode === 40 ) {
				e.preventDefault();
				move( 1 );
			} else if ( e.key === 'ArrowUp' || e.keyCode === 38 ) {
				e.preventDefault();
				move( -1 );
			} else if ( e.key === 'Enter' || e.keyCode === 13 ) {
				e.preventDefault();
				activate( activeIndex >= 0 ? activeIndex : 0 );
			}
		} );

		backdrop.addEventListener( 'click', close );

		// Hover/click on options: track + activate via pointer.
		list.addEventListener( 'mousemove', function ( e ) {
			var li = closestOption( e.target );
			if ( li ) { setActive( parseInt( li.getAttribute( 'data-index' ), 10 ) ); }
		} );
		list.addEventListener( 'click', function ( e ) {
			var li = closestOption( e.target );
			if ( li ) { activate( parseInt( li.getAttribute( 'data-index' ), 10 ) ); }
		} );
	}

	function closestOption( node ) {
		while ( node && node !== list ) {
			if ( node.getAttribute && node.getAttribute( 'role' ) === 'option' ) { return node; }
			node = node.parentNode;
		}
		return null;
	}

	// ── Global key + trigger wiring ──────────────────────────────────────────

	function isFormField( el ) {
		if ( ! el ) { return false; }
		var tag = el.tagName;
		if ( tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' ) { return true; }
		if ( el.isContentEditable ) { return true; }
		return false;
	}

	function onGlobalKeydown( e ) {
		// ⌘K / Ctrl-K — works everywhere.
		var k = e.key ? e.key.toLowerCase() : '';
		if ( ( e.metaKey || e.ctrlKey ) && ( k === 'k' || e.keyCode === 75 ) ) {
			e.preventDefault();
			isOpen ? close() : open();
			return;
		}
		// "/" — only when not typing into a form field, and the palette is closed.
		if ( ! isOpen && ( e.key === '/' || e.keyCode === 191 ) && ! e.metaKey && ! e.ctrlKey && ! e.altKey ) {
			if ( isFormField( document.activeElement ) ) { return; }
			e.preventDefault();
			open();
		}
	}

	function init() {
		document.addEventListener( 'keydown', onGlobalKeydown );

		trigger = document.querySelector( '.sn-cmdk-trigger' );
		if ( trigger ) {
			trigger.addEventListener( 'click', function () {
				isOpen ? close() : open( trigger );
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
