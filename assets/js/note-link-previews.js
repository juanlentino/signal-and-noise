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
 * aria-hidden, a visual supplement: screen readers already have the link
 * text and the destination. It is hoverable (WCAG 1.4.13, 2026-10-31):
 * leaving the link starts a short grace, moving onto the card cancels it,
 * leaving the card starts it again, so a reader can magnify or re-read it.
 * Mirrors assets/js/footnotes-popover.js (v9.3.0), including its
 * focusin/focusout keyboard parity.
 *
 * @since theme v12.10.0
 */
( function () {
	'use strict';

	if ( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches ) {
		return;
	}

	var HOVER_INTENT_MS = 120;
	var HIDE_GRACE_MS = 300; // time to cross from the link onto the card.
	var activeCard = null;
	var activeLink = null;
	var showTimer = null;
	var hideTimer = null;

	function clearTimer() {
		if ( showTimer ) {
			window.clearTimeout( showTimer );
			showTimer = null;
		}
	}

	function clearHide() {
		if ( hideTimer ) {
			window.clearTimeout( hideTimer );
			hideTimer = null;
		}
	}

	// The grace removes the card only: a pending show for the next link must
	// survive it (leave A, reach B inside the grace, B still shows).
	function scheduleHide() {
		clearHide();
		hideTimer = window.setTimeout( removeCard, HIDE_GRACE_MS );
	}

	function removeCard() {
		clearHide();
		if ( activeCard && activeCard.parentNode ) {
			activeCard.parentNode.removeChild( activeCard );
		}
		activeCard = null;
		activeLink = null;
	}

	function removeActive() {
		clearTimer();
		removeCard();
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
		activeLink = link;
		positionCard( card, link.getBoundingClientRect() );
	}

	// pointerenter/pointerleave fire on EVERY element the pointer crosses, and
	// the document captures them all: only the link itself and the card itself
	// count. Moving from a card's line onto its padding, or from a link's <em>
	// back onto its text, is not leaving (review 2026-10-31).
	function onEnter( event ) {
		if ( ! event.target || 1 !== event.target.nodeType ) {
			return;
		}
		if ( activeCard && event.target === activeCard ) {
			clearHide(); // the pointer reached the card: keep it.
			return;
		}
		var link = event.target.closest( 'a[data-sn-preview-title]' );
		if ( ! link || link !== event.target ) {
			return;
		}
		if ( link === activeLink ) {
			clearHide(); // back onto the same link: same card, no rebuild.
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
		if ( activeCard && event.target === activeCard ) {
			scheduleHide();
			return;
		}
		var link = event.target.closest( 'a[data-sn-preview-title]' );
		if ( link && link === event.target ) {
			clearTimer(); // a sweep that left before the intent delay shows nothing.
			if ( activeCard ) {
				scheduleHide();
			}
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
