/**
 * Signal & Noise — Project Quoter
 * Private tool for generating branded client quotes.
 *
 * @package SignalNoise
 * @since   3.6.0
 */

( function() {
	'use strict';

	var defaults = {
		dayRate:          600,
		mixRate:          500,
		masterRate:       250,
		productionRate:   1000,
		songwritingRate:  750,
		consultingRate:   700,
		revisionOverage:  100,
		validityDays:     30
	};

	var rateMap = {
		mix:         500,
		master:      250,
		production:  1000,
		songwriting: 750,
		consulting:  700
	};

	/**
	 * Init — handles both normal and late-loading scenarios.
	 */
	function init() {
		var form = document.getElementById( 'sn-quoter-form' );
		if ( ! form ) {
			console.warn( 'Quoter: #sn-quoter-form not found.' );
			return;
		}

		var addBtn  = document.getElementById( 'sn-add-deliverable' );
		var delList = document.getElementById( 'sn-deliverables-list' );
		var pdfBtn  = document.getElementById( 'sn-generate-pdf' );

		if ( ! addBtn || ! delList || ! pdfBtn ) {
			console.warn( 'Quoter: Missing required elements.' );
			return;
		}

		// Live recalculation on any input
		form.addEventListener( 'input', recalculate );
		form.addEventListener( 'change', recalculate );

		// Add deliverable
		addBtn.addEventListener( 'click', function( e ) {
			e.preventDefault();
			addDeliverable();
		});

		// Remove deliverable (delegated)
		delList.addEventListener( 'click', function( e ) {
			var btn = e.target.closest( '.sn-remove-row' );
			if ( btn ) {
				e.preventDefault();
				btn.closest( '.sn-deliverable-row' ).remove();
				recalculate();
			}
		});

		// PDF
		pdfBtn.addEventListener( 'click', function( e ) {
			e.preventDefault();
			generatePDF();
		});

		// Seed first deliverable row
		addDeliverable();
		recalculate();

		console.log( 'Quoter: Initialized.' );
	}

	/**
	 * Add a deliverable row.
	 */
	function addDeliverable() {
		var list = document.getElementById( 'sn-deliverables-list' );
		if ( ! list ) return;

		var row = document.createElement( 'div' );
		row.className = 'sn-deliverable-row';

		var select = document.createElement( 'select' );
		select.className = 'sn-del-type';
		var types = [
			{ value: 'mix', label: 'Mix' },
			{ value: 'master', label: 'Master' },
			{ value: 'production', label: 'Production' },
			{ value: 'songwriting', label: 'Songwriting' },
			{ value: 'consulting', label: 'Consulting (day)' }
		];
		types.forEach( function( t ) {
			var opt = document.createElement( 'option' );
			opt.value = t.value;
			opt.textContent = t.label;
			select.appendChild( opt );
		});

		var qtyInput = document.createElement( 'input' );
		qtyInput.type = 'number';
		qtyInput.className = 'sn-del-qty';
		qtyInput.value = '1';
		qtyInput.min = '1';
		qtyInput.placeholder = 'Qty';

		var rateInput = document.createElement( 'input' );
		rateInput.type = 'number';
		rateInput.className = 'sn-del-rate';
		rateInput.value = String( rateMap.mix );
		rateInput.min = '0';
		rateInput.step = '50';
		rateInput.placeholder = 'Rate ($)';

		var subtotal = document.createElement( 'span' );
		subtotal.className = 'sn-del-subtotal';
		subtotal.textContent = '$0';

		var removeBtn = document.createElement( 'button' );
		removeBtn.type = 'button';
		removeBtn.className = 'sn-remove-row';
		removeBtn.title = 'Remove';
		removeBtn.innerHTML = '&times;';

		// Auto-fill rate when type changes
		select.addEventListener( 'change', function() {
			rateInput.value = String( rateMap[ select.value ] || 500 );
			recalculate();
		});

		row.appendChild( select );
		row.appendChild( qtyInput );
		row.appendChild( rateInput );
		row.appendChild( subtotal );
		row.appendChild( removeBtn );

		list.appendChild( row );
		recalculate();
	}

	/**
	 * Recalculate totals.
	 */
	function recalculate() {
		var daysEl    = document.getElementById( 'sn-session-days' );
		var dayRateEl = document.getElementById( 'sn-day-rate' );
		if ( ! daysEl || ! dayRateEl ) return;

		var days    = parseInt( daysEl.value, 10 ) || 0;
		var dayRate = parseFloat( dayRateEl.value ) || 0;
		var variableTotal = days * dayRate;

		var fixedTotal = 0;
		var rows = document.querySelectorAll( '.sn-deliverable-row' );
		for ( var i = 0; i < rows.length; i++ ) {
			var qty  = parseInt( rows[i].querySelector( '.sn-del-qty' ).value, 10 ) || 0;
			var rate = parseFloat( rows[i].querySelector( '.sn-del-rate' ).value ) || 0;
			var sub  = qty * rate;
			rows[i].querySelector( '.sn-del-subtotal' ).textContent = '$' + sub.toLocaleString();
			fixedTotal += sub;
		}

		var grandTotal = variableTotal + fixedTotal;
		var varPct     = grandTotal > 0 ? Math.round( ( variableTotal / grandTotal ) * 100 ) : 0;
		var fixPct     = grandTotal > 0 ? 100 - varPct : 0;

		setText( 'sn-var-total', '$' + variableTotal.toLocaleString() );
		setText( 'sn-fixed-total', '$' + fixedTotal.toLocaleString() );
		setText( 'sn-grand-total', '$' + grandTotal.toLocaleString() );
		setText( 'sn-split-ratio', varPct + '/' + fixPct );
	}

	function setText( id, text ) {
		var el = document.getElementById( id );
		if ( el ) el.textContent = text;
	}

	/**
	 * Generate branded PDF.
	 */
	function generatePDF() {
		if ( ! window.jspdf || ! window.jspdf.jsPDF ) {
			alert( 'PDF library not loaded. Try refreshing the page.' );
			return;
		}

		var jsPDF  = window.jspdf.jsPDF;
		var doc    = new jsPDF({ unit: 'mm', format: 'letter' });
		var pageW  = doc.internal.pageSize.getWidth();
		var pageH  = doc.internal.pageSize.getHeight();
		var margin = 25;
		var contentW = pageW - margin * 2;
		var y = margin;

		// Colors
		var red      = [ 224, 4, 4 ];
		var black    = [ 0, 0, 0 ];
		var gray     = [ 102, 102, 102 ];
		var lineGray = [ 217, 217, 217 ];

		// ── Header
		doc.setFontSize( 28 );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( 'JUAN LENTINO', margin, y );
		y += 6;

		doc.setFontSize( 8 );
		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'juanlentino.com  |  juan@juanlentino.com  |  (407) 733-5692', margin, y );
		y += 4;

		doc.setDrawColor( red[0], red[1], red[2] );
		doc.setLineWidth( 0.8 );
		doc.line( margin, y, pageW - margin, y );
		y += 10;

		// ── Quote title
		doc.setFontSize( 18 );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( 'PROJECT QUOTE', margin, y );
		y += 10;

		// ── Client info
		var clientName  = getVal( 'sn-client-name' ) || 'Client';
		var clientEmail = getVal( 'sn-client-email' ) || '';
		var projectName = getVal( 'sn-project-name' ) || 'Untitled Project';
		var today       = new Date();
		var dateStr     = today.toLocaleDateString( 'en-US', { year: 'numeric', month: 'long', day: 'numeric' } );
		var validDays   = parseInt( getVal( 'sn-validity' ), 10 ) || 30;
		var validDate   = new Date( today.getTime() + validDays * 86400000 );
		var validStr    = validDate.toLocaleDateString( 'en-US', { year: 'numeric', month: 'long', day: 'numeric' } );

		y = pdfLabelValue( doc, margin, y, 'Prepared for:', clientName );
		if ( clientEmail ) {
			y = pdfLabelValue( doc, margin, y, 'Email:', clientEmail );
		}
		y = pdfLabelValue( doc, margin, y, 'Project:', projectName );
		y = pdfLabelValue( doc, margin, y, 'Date:', dateStr );
		y = pdfLabelValue( doc, margin, y, 'Valid until:', validStr );
		y += 7;

		// ── Variable Component
		doc.setDrawColor( lineGray[0], lineGray[1], lineGray[2] );
		doc.setLineWidth( 0.3 );
		doc.line( margin, y, pageW - margin, y );
		y += 8;

		doc.setFontSize( 10 );
		doc.setTextColor( red[0], red[1], red[2] );
		doc.text( 'SESSION DAYS (VARIABLE)', margin, y );
		y += 7;

		var days    = parseInt( getVal( 'sn-session-days' ), 10 ) || 0;
		var dayRate = parseFloat( getVal( 'sn-day-rate' ) ) || 0;

		doc.setFontSize( 9 );
		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'Description', margin, y );
		doc.text( 'Days', margin + 90, y );
		doc.text( 'Rate', margin + 110, y );
		doc.text( 'Amount', pageW - margin, y, { align: 'right' } );
		y += 5;

		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( 'Studio / Session / Consulting', margin, y );
		doc.text( String( days ), margin + 90, y );
		doc.text( '$' + dayRate.toLocaleString(), margin + 110, y );
		doc.text( '$' + ( days * dayRate ).toLocaleString(), pageW - margin, y, { align: 'right' } );
		y += 10;

		// ── Fixed Component
		doc.setDrawColor( lineGray[0], lineGray[1], lineGray[2] );
		doc.line( margin, y, pageW - margin, y );
		y += 8;

		doc.setFontSize( 10 );
		doc.setTextColor( red[0], red[1], red[2] );
		doc.text( 'DELIVERABLES (FIXED)', margin, y );
		y += 7;

		doc.setFontSize( 9 );
		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'Deliverable', margin, y );
		doc.text( 'Qty', margin + 90, y );
		doc.text( 'Rate', margin + 110, y );
		doc.text( 'Amount', pageW - margin, y, { align: 'right' } );
		y += 5;

		var fixedTotal = 0;
		var rows = document.querySelectorAll( '.sn-deliverable-row' );
		for ( var i = 0; i < rows.length; i++ ) {
			var typeSelect = rows[i].querySelector( '.sn-del-type' );
			var label = typeSelect.options[ typeSelect.selectedIndex ].text;
			var qty   = parseInt( rows[i].querySelector( '.sn-del-qty' ).value, 10 ) || 0;
			var rate  = parseFloat( rows[i].querySelector( '.sn-del-rate' ).value ) || 0;
			var sub   = qty * rate;
			fixedTotal += sub;

			doc.setTextColor( black[0], black[1], black[2] );
			doc.text( label, margin, y );
			doc.text( String( qty ), margin + 90, y );
			doc.text( '$' + rate.toLocaleString(), margin + 110, y );
			doc.text( '$' + sub.toLocaleString(), pageW - margin, y, { align: 'right' } );
			y += 5;
		}
		y += 5;

		// ── Totals
		doc.setDrawColor( lineGray[0], lineGray[1], lineGray[2] );
		doc.line( margin, y, pageW - margin, y );
		y += 8;

		var variableTotal = days * dayRate;
		var grandTotal    = variableTotal + fixedTotal;

		doc.setFontSize( 9 );
		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'Variable (Sessions)', margin, y );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( '$' + variableTotal.toLocaleString(), pageW - margin, y, { align: 'right' } );
		y += 5;

		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'Fixed (Deliverables)', margin, y );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( '$' + fixedTotal.toLocaleString(), pageW - margin, y, { align: 'right' } );
		y += 7;

		doc.setDrawColor( red[0], red[1], red[2] );
		doc.setLineWidth( 0.8 );
		doc.line( margin + 80, y, pageW - margin, y );
		y += 7;

		doc.setFontSize( 12 );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( 'TOTAL', margin + 80, y );
		doc.text( '$' + grandTotal.toLocaleString(), pageW - margin, y, { align: 'right' } );
		y += 12;

		// ── Terms
		var revCap     = getVal( 'sn-revision-cap' ) || '2';
		var revOverage = getVal( 'sn-revision-overage' ) || String( defaults.revisionOverage );

		doc.setDrawColor( lineGray[0], lineGray[1], lineGray[2] );
		doc.setLineWidth( 0.3 );
		doc.line( margin, y, pageW - margin, y );
		y += 8;

		doc.setFontSize( 10 );
		doc.setTextColor( red[0], red[1], red[2] );
		doc.text( 'TERMS', margin, y );
		y += 7;

		doc.setFontSize( 9 );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( 'Revision rounds included per deliverable: ' + revCap, margin, y );
		y += 5;
		doc.text( 'Additional revision rounds: $' + parseInt( revOverage, 10 ).toLocaleString() + ' per round per deliverable', margin, y );
		y += 5;
		doc.text( 'A revision round = one consolidated pass of feedback, delivered together.', margin, y );
		y += 7;

		var termsEl    = document.getElementById( 'sn-payment-terms' );
		var termsLabel = termsEl ? termsEl.options[ termsEl.selectedIndex ].text : '50% upfront / 50% on delivery';
		doc.text( 'Payment: ' + termsLabel, margin, y );
		y += 7;

		// Notes
		var notes = getVal( 'sn-notes' );
		if ( notes && notes.trim() ) {
			doc.setTextColor( gray[0], gray[1], gray[2] );
			doc.text( 'Notes:', margin, y );
			y += 5;
			doc.setTextColor( black[0], black[1], black[2] );
			var splitNotes = doc.splitTextToSize( notes, contentW );
			doc.text( splitNotes, margin, y );
			y += splitNotes.length * 4.5 + 5;
		}

		// ── Footer
		y = pageH - 20;
		doc.setDrawColor( lineGray[0], lineGray[1], lineGray[2] );
		doc.setLineWidth( 0.3 );
		doc.line( margin, y, pageW - margin, y );
		y += 5;
		doc.setFontSize( 7 );
		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'Juan Lentino  |  juanlentino.com  |  Orlando, FL', margin, y );
		doc.text( 'Quote valid for ' + validDays + ' days from issue date.', pageW - margin, y, { align: 'right' } );

		// Save
		var filename = projectName.replace( /[^a-zA-Z0-9]/g, '_' ).replace( /_+/g, '_' );
		doc.save( 'Quote_' + filename + '.pdf' );
	}

	/**
	 * Helpers
	 */
	function getVal( id ) {
		var el = document.getElementById( id );
		return el ? el.value : '';
	}

	function pdfLabelValue( doc, margin, y, label, value ) {
		doc.setFontSize( 9 );
		doc.setTextColor( 102, 102, 102 );
		doc.text( label, margin, y );
		doc.setTextColor( 0, 0, 0 );
		doc.text( value, margin + 28, y );
		return y + 5;
	}

	/**
	 * Boot — handles DOMContentLoaded or late init.
	 */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		// DOM already ready (script loaded defer/async or late).
		init();
	}

})();
