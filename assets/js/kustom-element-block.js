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
			locale: {
				type: 'string',
				default: '',
			},
			include: {
				type: 'string',
				default: '',
			},
			exclude: {
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
							label: __( 'Locale', 'klarna-checkout-for-woocommerce' ),
							help: __( 'Market/language code, e.g. sv-SE, en-GB. Leave empty to use the global setting.', 'klarna-checkout-for-woocommerce' ),
							value: attributes.locale,
							onChange: function ( val ) {
								setAttributes( { locale: val } );
							},
						} ),
						el( TextControl, {
							label: __( 'Include payment methods', 'klarna-checkout-for-woocommerce' ),
							help: __( 'Optional. Comma-separated IDs to always show. Leave empty to use global setting.', 'klarna-checkout-for-woocommerce' ),
							value: attributes.include,
							onChange: function ( val ) {
								setAttributes( { include: val } );
							},
						} ),
						el( TextControl, {
							label: __( 'Exclude payment methods', 'klarna-checkout-for-woocommerce' ),
							help: __( 'Optional. Comma-separated IDs to hide. Leave empty to use global setting.', 'klarna-checkout-for-woocommerce' ),
							value: attributes.exclude,
							onChange: function ( val ) {
								setAttributes( { exclude: val } );
							},
						} )
					)
				),
				el(
					'div',
					{ key: 'preview', className: 'kco-kustom-element-block-preview' },
					el(
						'p',
						{ style: { padding: '8px', background: '#f0f0f0', borderRadius: '4px' } },
						__( 'Kustom Element', 'klarna-checkout-for-woocommerce' ),
						attributes.locale ? ' — ' + attributes.locale : ''
					)
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
