( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;

	blocks.registerBlockType( 'kco/kustom-element', {
		title: __( 'Kustom Element', 'klarna-checkout-for-woocommerce' ),
		icon: 'store',
		category: 'woocommerce',
		attributes: {
			dataKey: {
				type: 'string',
				default: '',
			},
			purchaseAmount: {
				type: 'string',
				default: '',
			},
		},

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return [
				el(
					InspectorControls,
					{ key: 'inspector' },
					el(
						PanelBody,
						{ title: __( 'Kustom Element settings', 'klarna-checkout-for-woocommerce' ) },
						el( TextControl, {
							label: __( 'Data key', 'klarna-checkout-for-woocommerce' ),
							help: __( 'The data-key provided by Kustom (required).', 'klarna-checkout-for-woocommerce' ),
							value: attributes.dataKey,
							onChange: function ( val ) {
								setAttributes( { dataKey: val } );
							},
						} ),
						el( TextControl, {
							label: __( 'Purchase amount (minor units)', 'klarna-checkout-for-woocommerce' ),
							help: __( 'Optional. Amount in minor units, e.g. 9900 for 99.00.', 'klarna-checkout-for-woocommerce' ),
							value: attributes.purchaseAmount,
							onChange: function ( val ) {
								setAttributes( { purchaseAmount: val } );
							},
						} )
					)
				),
				el(
					'div',
					{ key: 'preview', className: 'kco-kustom-element-block-preview' },
					attributes.dataKey
						? el( 'p', null, __( 'Kustom Element: ', 'klarna-checkout-for-woocommerce' ) + attributes.dataKey )
						: el( 'p', { style: { color: '#999' } }, __( 'Set a data-key in the block settings.', 'klarna-checkout-for-woocommerce' ) )
				),
			];
		},

		save: function () {
			// Server-side rendered — return null so WP uses the PHP render callback.
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
