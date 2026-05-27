/**
 * Signal & Noise — Footnote hover-popover.
 *
 * Progressive enhancement on core/footnotes (WP 6.3+). Footnotes work
 * without this JS via WP's default scroll-to-footnote-list behavior.
 * This module adds a hover-preview popover so readers don't lose their
 * reading position.
 *
 * Security: uses safe DOM cloning (cloneNode + appendChild) — never
 * innerHTML. Source content (the footnote li) is already-sanitized WP
 * post_content, but we avoid innerHTML to eliminate the XSS surface.
 *
 * Mobile / coarse pointer: skipped entirely. Tap on sup falls back to
 * default scroll behavior.
 *
 * @since theme v9.3.0
 */
( function () {
	'use strict';

	if ( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches ) {
		return;
	}

	var activePopover = null;

	function buildPopover( anchorLink ) {
		var href = anchorLink.getAttribute( 'href' );
		if ( ! href || href.charAt( 0 ) !== '#' ) {
			return null;
		}
		var targetId = href.slice( 1 );
		var targetLi = document.getElementById( targetId );
		if ( ! targetLi ) {
			return null;
		}

		var popover = document.createElement( 'div' );
		popover.className = 'sn-footnote-popover';

		// Safe DOM clone: iterate child nodes, cloneNode each, appendChild.
		// Skip back-link anchors (we're at the source). NO innerHTML.
		var children = Array.prototype.slice.call( targetLi.childNodes );
		for ( var i = 0; i < children.length; i++ ) {
			var node = children[ i ].cloneNode( true );
			if ( node.nodeType === 1
				&& node.tagName === 'A'
				&& node.getAttribute( 'href' )
				&& node.getAttribute( 'href' ).indexOf( '#footnote-ref-' ) === 0 ) {
				continue;
			}
			popover.appendChild( node );
		}

		return popover;
	}

	function positionPopover( popover, anchorRect ) {
		document.body.appendChild( popover );
		var popoverRect = popover.getBoundingClientRect();
		var spaceBelow = window.innerHeight - anchorRect.bottom;
		var top;
		if ( spaceBelow >= popoverRect.height + 16 ) {
			top = anchorRect.bottom + window.scrollY + 4;
		} else {
			top = anchorRect.top + window.scrollY - popoverRect.height - 4;
		}
		var left = anchorRect.left + window.scrollX;
		var maxLeft = window.scrollX + window.innerWidth - popoverRect.width - 8;
		if ( left > maxLeft ) { left = maxLeft; }
		if ( left < 8 ) { left = 8; }
		popover.style.top = top + 'px';
		popover.style.left = left + 'px';
	}

	function removeActive() {
		if ( activePopover && activePopover.parentNode ) {
			activePopover.parentNode.removeChild( activePopover );
		}
		activePopover = null;
	}

	function onEnter( event ) {
		var sup = event.target.closest( 'sup' );
		if ( ! sup ) { return; }
		var anchor = sup.querySelector( 'a[href^="#footnote-"]' );
		if ( ! anchor ) { return; }
		removeActive();
		var popover = buildPopover( anchor );
		if ( ! popover ) { return; }
		activePopover = popover;
		positionPopover( popover, sup.getBoundingClientRect() );
		popover.addEventListener( 'pointerleave', removeActive );
	}

	function onLeave( event ) {
		var sup = event.target.closest( 'sup' );
		if ( ! sup ) { return; }
		var relatedTarget = event.relatedTarget;
		if ( activePopover && relatedTarget && activePopover.contains( relatedTarget ) ) {
			return;
		}
		removeActive();
	}

	/**
	 * Keyboard parity: show the popover when the footnote anchor gains focus
	 * (Tab key navigation), dismiss when it blurs. Mirrors the hover behavior
	 * for keyboard users. Audit D PA-11.
	 */
	function onFocusIn( event ) {
		var sup = event.target.closest( 'sup' );
		if ( ! sup ) { return; }
		var anchor = sup.querySelector( 'a[href^="#footnote-"]' );
		if ( ! anchor || anchor !== event.target ) { return; }
		removeActive();
		var popover = buildPopover( anchor );
		if ( ! popover ) { return; }
		activePopover = popover;
		positionPopover( popover, sup.getBoundingClientRect() );
	}

	function onFocusOut( event ) {
		var sup = event.target.closest( 'sup' );
		if ( ! sup ) { return; }
		removeActive();
	}

	function init() {
		document.addEventListener( 'pointerenter', onEnter, true );
		document.addEventListener( 'pointerleave', onLeave, true );
		document.addEventListener( 'focusin', onFocusIn );
		document.addEventListener( 'focusout', onFocusOut );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
