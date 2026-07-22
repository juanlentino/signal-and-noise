( function ( blocks, element, blockEditor ) {
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
	// live list server-side, so the editor shows a static placeholder.
	blocks.registerBlockType( 'signal-noise/pillar-essays', {
		edit: function () {
			var bp = useBlockProps( { className: 'sn-notes-pillars-section' } );
			return el( 'div', bp,
				'Pillar Essays: renders the live pillar essay rail from published designated Pages.' );
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
