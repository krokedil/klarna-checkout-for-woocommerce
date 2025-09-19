/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Wordpress/WooCommerce dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { useEffect } from '@wordpress/element';
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
// eslint-disable-next-line import/no-unresolved
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
// eslint-disable-next-line import/no-unresolved
import { getSetting } from '@woocommerce/settings';
import { useKcoIframe } from './hooks/useKcoIframe';

// Declare wc and _klarnaCheckout on the window object to avoid TypeScript errors when using them later.
// _klarnaCheckout is added by the Klarna Checkout script, and wc is added by WooCommerce blocks.
declare global {
	interface Window {
		_klarnaCheckout: any;
		wc: any;
	}
}

const settings: any = getSetting('kco_data', {});
const title: string = decodeEntities(settings.title || 'Kustom Checkout');
const description: string = decodeEntities(settings.description || '');
const iconUrl: string = decodeEntities(settings.iconUrl || '');
const features: string[] = settings.features || [];
/**
 * Checks if the Kustom Checkout can make a payment.
 *
 * @return {boolean} True if Kustom Checkout can make a payment, false otherwise.
 */
const canMakePayment = (): boolean => {
	if (settings.error || !settings.snippet) {
		// eslint-disable-next-line no-console
		console.error(
			'Failed to initialize Kustom Checkout: ' + settings.error
		);
	}

	return true;
};

/**
 * Kustom Checkout component properties.
 *
 * @property {string} [activePaymentMethod] - The currently active payment method.
 * @property {Object} [billing] - The billing information from WooCommerce.
 * @property {Object} [cartData] - The cart data containing items and totals from WooCommerce.
 */
type KustomCheckoutProps = {
	activePaymentMethod?: string;
	billing?: any;
	cartData?: any;
};

/**
 * Kustom Checkout component for WooCommerce blocks.
 *
 * Loads the useKcoIframe hook that manages the Kustom Checkout iframe, and its interaction with checkout page.
 *
 * @param {KustomCheckoutProps} props - The properties passed to the component.
 * @return {JSX.Element|null} The rendered component or null if no description is provided.
 */
const KustomCheckout = (props: KustomCheckoutProps): JSX.Element => {
	const { activePaymentMethod, billing, cartData } = props;
	const { isActive, suspendKCO, resumeKCO } = useKcoIframe(
		settings,
		activePaymentMethod,
		cartData
	);

	useEffect(() => {
		if (!isActive) return; // If Kustom Checkout we don't want to do anything.

		// Suspend and resume the Kustom Checkout iframe when the cart total items change, this forces the iframe to reload with the new cart data.
		suspendKCO();
		resumeKCO();
	}, [billing.cartTotalItems, isActive, resumeKCO, suspendKCO]);

	if (description === '') return null; // If the description is empty, we don't want to render anything.

	return (
		<div className="wc-block-components-klarna-checkout">
			<p>{description}</p>
		</div>
	);
};

/**
 * Label component for the Kustom Checkout payment method.
 *
 * @return {JSX.Element} - A label component for the Kustom Checkout payment method.
 */
const Label = (): JSX.Element => {
	return (
		<div
			style={{
				display: 'flex',
				gap: 16,
				width: '100%',
				justifyContent: 'space-between',
				paddingRight: 16,
			}}
		>
			<span>{title}</span>
			<img src={iconUrl} alt={title} />
		</div>
	);
};

/**
 * Options for registering the Kustom Checkout payment method.
 *
 * @see https://github.com/woocommerce/woocommerce/blob/9c8608c214bc9df3b28d5dbc766a3750da07ff42/docs/block-development/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration.md#registration
 */
const options = {
	name: 'kco',
	label: <Label />,
	content: <KustomCheckout />,
	edit: <KustomCheckout />,
	placeOrderButtonLabel: 'Pay with Kustom Checkout',
	canMakePayment,
	ariaLabel: title,
	supports: { features },
};

registerPaymentMethod(options);
