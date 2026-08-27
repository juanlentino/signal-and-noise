/**
 * Signal & Noise — internal note-link hover previews (reader side).
 *
 * Progressive enhancement over the data-sn-preview-* attributes stamped
 * server-side by inc/note-link-previews.php. Hover (after a short intent
 * delay) or keyboard focus on a stamped link shows a small card — title,
 * opening, date · reading time — built entirely with createElement +
 * textContent: no innerHTML, no fetch, no network. Without this JS the
 * links are just links.
 *
 * Mobile / coarse pointer: skipped entirely (tap navigates). The card is
 * pointer-events: none and aria-hidden — a visual supplement; screen
 * readers already have the link text and the destination. Mirrors
 * assets/js/footnotes-popover.js (v9.3.0), including its focusin/focusout
 * keyboard parity.
 *
 * @since theme v12.10.0
 */
( function () {
	'use strict';

	if ( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches ) {
		return;
	}

	var HOVER_INTENT_MS = 120;
	var activeCard = null;
	var showTimer = null;

	function clearTimer() {
		if ( showTimer ) {
			window.clearTimeout( showTimer );
			showTimer = null;
		}
	}

	function removeActive() {
		clearTimer();
		if ( activeCard && activeCard.parentNode ) {
			activeCard.parentNode.removeChild( activeCard );
		}
		activeCard = null;
	}

	function buildCard( link ) {
		var title = link.getAttribute( 'data-sn-preview-title' );
		if ( ! title ) {
			return null;
		}
		var card = document.createElement( 'div' );
		card.className = 'sn-link-preview';
		card.setAttribute( 'aria-hidden', 'true' );

		var titleEl = document.createElement( 'strong' );
		titleEl.className = 'sn-link-preview__title';
		titleEl.textContent = title;
		card.appendChild( titleEl );

		var summary = link.getAttribute( 'data-sn-preview-summary' );
		if ( summary ) {
			var summaryEl = document.createElement( 'p' );
			summaryEl.className = 'sn-link-preview__summary';
			summaryEl.textContent = summary;
			card.appendChild( summaryEl );
		}

		var meta = link.getAttribute( 'data-sn-preview-meta' );
		if ( meta ) {
			var metaEl = document.createElement( 'span' );
			metaEl.className = 'sn-link-preview__meta';
			metaEl.textContent = meta;
			card.appendChild( metaEl );
		}

		return card;
	}

	// Same placement logic as the footnote popover: below the anchor when it
	// fits, above otherwise; clamped to the viewport's horizontal bounds.
	function positionCard( card, anchorRect ) {
		document.body.appendChild( card );
		var cardRect = card.getBoundingClientRect();
		var spaceBelow = window.innerHeight - anchorRect.bottom;
		var top;
		if ( spaceBelow >= cardRect.height + 16 ) {
			top = anchorRect.bottom + window.scrollY + 6;
		} else {
			top = anchorRect.top + window.scrollY - cardRect.height - 6;
		}
		var left = anchorRect.left + window.scrollX;
		var maxLeft = window.scrollX + window.innerWidth - cardRect.width - 8;
		if ( left > maxLeft ) { left = maxLeft; }
		if ( left < 8 ) { left = 8; }
		card.style.top = top + 'px';
		card.style.left = left + 'px';
	}

	function showFor( link ) {
		removeActive();
		var card = buildCard( link );
		if ( ! card ) {
			return;
		}
		activeCard = card;
		positionCard( card, link.getBoundingClientRect() );
	}

	function onEnter( event ) {
		if ( ! event.target || 1 !== event.target.nodeType ) {
			return;
		}
		var link = event.target.closest( 'a[data-sn-preview-title]' );
		if ( ! link ) {
			return;
		}
		// Intent delay: sweeping the pointer across a paragraph should not
		// spray cards. The timer dies on pointerleave before it fires.
		clearTimer();
		showTimer = window.setTimeout( function () {
			showFor( link );
		}, HOVER_INTENT_MS );
	}

	function onLeave( event ) {
		if ( ! event.target || 1 !== event.target.nodeType ) {
			return;
		}
		if ( event.target.closest( 'a[data-sn-preview-title]' ) ) {
			removeActive();
		}
	}

	// Keyboard parity: focus shows immediately (no intent delay — Tab IS the
	// intent), blur dismisses. Escape dismisses without moving focus.
	function onFocusIn( event ) {
		var link = event.target.closest && event.target.closest( 'a[data-sn-preview-title]' );
		if ( link ) {
			showFor( link );
		}
	}

	function onFocusOut( event ) {
		if ( event.target.closest && event.target.closest( 'a[data-sn-preview-title]' ) ) {
			removeActive();
		}
	}

	function onKeyDown( event ) {
		if ( 'Escape' === event.key && activeCard ) {
			removeActive();
		}
	}

	function init() {
		document.addEventListener( 'pointerenter', onEnter, true );
		document.addEventListener( 'pointerleave', onLeave, true );
		document.addEventListener( 'focusin', onFocusIn );
		document.addEventListener( 'focusout', onFocusOut );
		document.addEventListener( 'keydown', onKeyDown );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
