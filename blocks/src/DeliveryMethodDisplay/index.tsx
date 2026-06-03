/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { registerKustomElementBlock } from '../shared/element-block';

registerKustomElementBlock( {
	name: 'kco/delivery-method-display',
	title: __( 'Kustom Delivery Methods', 'klarna-checkout-for-woocommerce' ),
	description: __(
		'Displays the available Kustom delivery methods. The list is rendered on the frontend by Kustom Elements.',
		'klarna-checkout-for-woocommerce'
	),
	tag: 'kustom-delivery-method-display',
	icon: 'airplane',
	placeholderText: __(
		'Delivery methods will be displayed here on the frontend.',
		'klarna-checkout-for-woocommerce'
	),
} );
