( function ( blocks, element, blockEditor, serverSideRender ) {
	'use strict';
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;

	// Both blocks are DYNAMIC (render.php). Attributes are PLAIN (not source:html),
	// so their values persist in the block's comment-delimiter JSON and arrive
	// populated in render.php server-side (WP does not source html attrs in PHP).
	// save() returns null: no stored markup → no block-validation/recovery churn;
	// render.php is the sole front-end authority.

	blocks.registerBlockType( 'signal-noise/sidenote', {
		edit: function ( props ) {
			var bp = useBlockProps( { className: 'sn-sidenote' } );
			return el( RichText, Object.assign( {}, bp, {
				tagName: 'p',
				value: props.attributes.content,
				onChange: function ( v ) { props.setAttributes( { content: v } ); },
				placeholder: 'Margin note…'
			} ) );
		},
		save: function () { return null; }
	} );

	blocks.registerBlockType( 'signal-noise/pull-quote', {
		edit: function ( props ) {
			var bp = useBlockProps( { className: 'sn-pull-quote' } );
			return el( 'aside', bp,
				el( RichText, { tagName: 'p', className: 'sn-pull-quote__body', value: props.attributes.body,
					onChange: function ( v ) { props.setAttributes( { body: v } ); }, placeholder: 'Thesis statement…' } ),
				el( RichText, { tagName: 'p', className: 'sn-pull-quote__attribution', value: props.attributes.attribution,
					onChange: function ( v ) { props.setAttributes( { attribution: v } ); }, placeholder: '— attribution' } )
			);
		},
		save: function () { return null; }
	} );

	// Owner-placeable pillar rail. No attributes: render.php derives the
	// live list server-side, and the editor previews the REAL render via
	// ServerSideRender (a bare text placeholder read as broken in v10.47.0,
	// owner-reported). Static text stays only as a fallback when the
	// server-side-render module is unavailable.
	blocks.registerBlockType( 'signal-noise/pillar-essays', {
		edit: function () {
			var bp = useBlockProps();
			if ( serverSideRender ) {
				return el( 'div', bp,
					el( serverSideRender, { block: 'signal-noise/pillar-essays' } ) );
			}
			return el( 'div', bp,
				'Pillar Essays: renders the live pillar essay rail from published designated Pages.' );
		},
		save: function () { return null; }
	} );

	// ── v12.4.0: the four block STYLES, surfaced as inserter VARIATIONS ──────
	// A style only appears once a block is already inserted, in the sidebar
	// Styles panel. A variation appears in the inserter and answers the `/`
	// slash menu, which is where a writer actually is. Measured across the 25
	// notes in /feed/json/ before this shipped: 339 paragraphs, 87 headings and
	// ZERO uses of any of these four.
	//
	// This table is the JS half of a parity contract —
	// tests/editor-variations-parity.php asserts it is SET-EQUAL to what
	// inc/block-styles.php registers. Adding a style there without a row here
	// fails the build, and vice versa. Keep each row on ONE line, block first
	// and style second — the test parses this table, so prose must never imitate
	// a row's shape (an earlier draft of this comment did, and parsed as a fifth).
	var SN_STYLE_VARIATIONS = [
		{ block: 'core/separator', style: 'hairline', title: 'Hairline', description: 'A sharp 1px concrete rule.' },
		{ block: 'core/quote', style: 'signal', title: 'Signal', description: 'Brutalist quote with the blood accent.' },
		{ block: 'core/quote', style: 'epigraph', title: 'Epigraph', description: 'An opening quotation, set above the argument.' },
		{ block: 'core/list', style: 'references', title: 'References', description: 'A source list set in mono.' }
	];

	SN_STYLE_VARIATIONS.forEach( function ( v ) {
		blocks.registerBlockVariation( v.block, {
			name: 'sn-' + v.style,
			title: v.title,
			description: v.description,
			// The style ships its own CSS already; the variation only presets
			// the class. Derived, never retyped — the test pins that too.
			attributes: { className: 'is-style-' + v.style },
			// INSERTER ONLY. Without this scope a variation can be treated as
			// the block's default insert, changing what a plain quote does.
			scope: [ 'inserter' ],
			keywords: [ v.style, v.title.toLowerCase() ]
		} );
	} );

} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender );
