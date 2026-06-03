/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { registerKustomElementBlock } from '../shared/element-block';

registerKustomElementBlock( {
	name: 'kco/payment-method-display',
	title: __( 'Kustom Payment Methods', 'klarna-checkout-for-woocommerce' ),
	description: __(
		'Displays the available Kustom payment methods. The list is rendered on the frontend by Kustom Elements.',
		'klarna-checkout-for-woocommerce'
	),
	tag: 'kustom-payment-method-display',
	icon: 'money-alt',
	placeholderText: __(
		'Payment methods will be displayed here on the frontend.',
		'klarna-checkout-for-woocommerce'
	),
} );
