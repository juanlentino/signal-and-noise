/**
 * Signal & Noise — Project Quoter
 * Private tool for generating branded client quotes.
 *
 * @package SignalNoise
 * @since   3.6.0
 */

( function() {
	'use strict';

	/* ── State ── */
	const defaults = {
		dayRate:          600,
		mixRate:          500,
		masterRate:       250,
		productionRate:   1000,
		songwritingRate:  750,
		consultingRate:   700,
		revisionOverage:  100,
		validityDays:     30
	};

	/* ── DOM ready ── */
	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		const form = document.getElementById( 'sn-quoter-form' );
		if ( ! form ) return;

		form.addEventListener( 'input', recalculate );
		form.addEventListener( 'change', recalculate );

		// Add deliverable row
		document.getElementById( 'sn-add-deliverable' )
			.addEventListener( 'click', addDeliverable );

		// Remove deliverable delegation
		document.getElementById( 'sn-deliverables-list' )
			.addEventListener( 'click', function( e ) {
				if ( e.target.closest( '.sn-remove-row' ) ) {
					e.target.closest( '.sn-deliverable-row' ).remove();
					recalculate();
				}
			});

		// PDF button
		document.getElementById( 'sn-generate-pdf' )
			.addEventListener( 'click', generatePDF );

		// Seed one deliverable row
		addDeliverable();
		recalculate();
	}

	/* ── Deliverable rows ── */
	function addDeliverable() {
		const list = document.getElementById( 'sn-deliverables-list' );
		const row  = document.createElement( 'div' );
		row.className = 'sn-deliverable-row';
		row.innerHTML =
			'<select class="sn-del-type">' +
				'<option value="mix">Mix</option>' +
				'<option value="master">Master</option>' +
				'<option value="production">Production</option>' +
				'<option value="songwriting">Songwriting</option>' +
				'<option value="consulting">Consulting (day)</option>' +
			'</select>' +
			'<input type="number" class="sn-del-qty" value="1" min="1" placeholder="Qty">' +
			'<input type="number" class="sn-del-rate" value="' + defaults.mixRate + '" min="0" step="50" placeholder="Rate ($)">' +
			'<span class="sn-del-subtotal">$0</span>' +
			'<button type="button" class="sn-remove-row" title="Remove">&times;</button>';

		// Auto-fill rate on type change
		row.querySelector( '.sn-del-type' ).addEventListener( 'change', function() {
			const rateMap = {
				mix:         defaults.mixRate,
				master:      defaults.masterRate,
				production:  defaults.productionRate,
				songwriting: defaults.songwritingRate,
				consulting:  defaults.consultingRate
			};
			row.querySelector( '.sn-del-rate' ).value = rateMap[ this.value ] || 500;
			recalculate();
		});

		list.appendChild( row );
		recalculate();
	}

	/* ── Recalculate ── */
	function recalculate() {
		const days    = parseInt( document.getElementById( 'sn-session-days' ).value ) || 0;
		const dayRate = parseFloat( document.getElementById( 'sn-day-rate' ).value ) || 0;
		const variableTotal = days * dayRate;

		// Deliverables
		let fixedTotal = 0;
		const rows = document.querySelectorAll( '.sn-deliverable-row' );
		rows.forEach( function( row ) {
			const qty  = parseInt( row.querySelector( '.sn-del-qty' ).value ) || 0;
			const rate = parseFloat( row.querySelector( '.sn-del-rate' ).value ) || 0;
			const sub  = qty * rate;
			row.querySelector( '.sn-del-subtotal' ).textContent = '$' + sub.toLocaleString();
			fixedTotal += sub;
		});

		const grandTotal = variableTotal + fixedTotal;
		const varPct     = grandTotal > 0 ? Math.round( ( variableTotal / grandTotal ) * 100 ) : 0;
		const fixPct     = grandTotal > 0 ? 100 - varPct : 0;

		document.getElementById( 'sn-var-total' ).textContent   = '$' + variableTotal.toLocaleString();
		document.getElementById( 'sn-fixed-total' ).textContent = '$' + fixedTotal.toLocaleString();
		document.getElementById( 'sn-grand-total' ).textContent = '$' + grandTotal.toLocaleString();
		document.getElementById( 'sn-split-ratio' ).textContent = varPct + '/' + fixPct;
	}

	/* ── PDF Generation ── */
	function generatePDF() {
		var jsPDF = window.jspdf.jsPDF;
		var doc   = new jsPDF({ unit: 'mm', format: 'letter' });

		var pageW  = doc.internal.pageSize.getWidth();
		var margin = 25;
		var contentW = pageW - margin * 2;
		var y = margin;

		// Colors
		var red   = [ 224, 4, 4 ];
		var black = [ 0, 0, 0 ];
		var gray  = [ 102, 102, 102 ];
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

		// Red line
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
		var clientName  = document.getElementById( 'sn-client-name' ).value || 'Client';
		var clientEmail = document.getElementById( 'sn-client-email' ).value || '';
		var projectName = document.getElementById( 'sn-project-name' ).value || 'Untitled Project';
		var today       = new Date();
		var dateStr     = today.toLocaleDateString( 'en-US', { year: 'numeric', month: 'long', day: 'numeric' } );
		var validDays   = parseInt( document.getElementById( 'sn-validity' ).value ) || defaults.validityDays;
		var validDate   = new Date( today.getTime() + validDays * 86400000 );
		var validStr    = validDate.toLocaleDateString( 'en-US', { year: 'numeric', month: 'long', day: 'numeric' } );

		doc.setFontSize( 9 );
		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'Prepared for:', margin, y );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( clientName, margin + 28, y );
		y += 5;

		if ( clientEmail ) {
			doc.setTextColor( gray[0], gray[1], gray[2] );
			doc.text( 'Email:', margin, y );
			doc.setTextColor( black[0], black[1], black[2] );
			doc.text( clientEmail, margin + 28, y );
			y += 5;
		}

		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'Project:', margin, y );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( projectName, margin + 28, y );
		y += 5;

		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'Date:', margin, y );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( dateStr, margin + 28, y );
		y += 5;

		doc.setTextColor( gray[0], gray[1], gray[2] );
		doc.text( 'Valid until:', margin, y );
		doc.setTextColor( black[0], black[1], black[2] );
		doc.text( validStr, margin + 28, y );
		y += 12;

		// ── Variable Component
		doc.setDrawColor( lineGray[0], lineGray[1], lineGray[2] );
		doc.setLineWidth( 0.3 );
		doc.line( margin, y, pageW - margin, y );
		y += 8;

		doc.setFontSize( 10 );
		doc.setTextColor( red[0], red[1], red[2] );
		doc.text( 'SESSION DAYS (VARIABLE)', margin, y );
		y += 7;

		var days    = parseInt( document.getElementById( 'sn-session-days' ).value ) || 0;
		var dayRate = parseFloat( document.getElementById( 'sn-day-rate' ).value ) || 0;

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
		rows.forEach( function( row ) {
			var type = row.querySelector( '.sn-del-type' );
			var label = type.options[ type.selectedIndex ].text;
			var qty   = parseInt( row.querySelector( '.sn-del-qty' ).value ) || 0;
			var rate  = parseFloat( row.querySelector( '.sn-del-rate' ).value ) || 0;
			var sub   = qty * rate;
			fixedTotal += sub;

			doc.setTextColor( black[0], black[1], black[2] );
			doc.text( label, margin, y );
			doc.text( String( qty ), margin + 90, y );
			doc.text( '$' + rate.toLocaleString(), margin + 110, y );
			doc.text( '$' + sub.toLocaleString(), pageW - margin, y, { align: 'right' } );
			y += 5;
		});

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

		// ── Revision policy
		var revCap     = document.getElementById( 'sn-revision-cap' ).value || '2';
		var revOverage = document.getElementById( 'sn-revision-overage' ).value || defaults.revisionOverage;

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

		// Revision policy
		doc.text( 'Revision rounds included per deliverable: ' + revCap, margin, y );
		y += 5;
		doc.text( 'Additional revision rounds: $' + parseInt( revOverage ).toLocaleString() + ' per round per deliverable', margin, y );
		y += 5;
		doc.text( 'A revision round = one consolidated pass of feedback, delivered together.', margin, y );
		y += 7;

		// Payment terms
		var terms = document.getElementById( 'sn-payment-terms' );
		var termsLabel = terms.options[ terms.selectedIndex ].text;
		doc.text( 'Payment: ' + termsLabel, margin, y );
		y += 7;

		// Notes
		var notes = document.getElementById( 'sn-notes' ).value;
		if ( notes.trim() ) {
			doc.setTextColor( gray[0], gray[1], gray[2] );
			doc.text( 'Notes:', margin, y );
			y += 5;
			doc.setTextColor( black[0], black[1], black[2] );
			var splitNotes = doc.splitTextToSize( notes, contentW );
			doc.text( splitNotes, margin, y );
			y += splitNotes.length * 4.5 + 5;
		}

		// ── Footer
		y = doc.internal.pageSize.getHeight() - 20;
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

})();
