( function ( blocks, element, blockEditor ) {
	'use strict';
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;

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
		save: function ( props ) {
			var bp = useBlockProps.save( { className: 'sn-sidenote' } );
			return el( RichText.Content, Object.assign( {}, bp, { tagName: 'p', value: props.attributes.content } ) );
		}
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
		save: function ( props ) {
			var bp = useBlockProps.save( { className: 'sn-pull-quote' } );
			return el( 'aside', bp,
				el( RichText.Content, { tagName: 'p', className: 'sn-pull-quote__body', value: props.attributes.body } ),
				el( RichText.Content, { tagName: 'p', className: 'sn-pull-quote__attribution', value: props.attributes.attribution } )
			);
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
