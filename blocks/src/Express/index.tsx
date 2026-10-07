/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Wordpress/WooCommerce dependencies
 */
import { useEffect, useMemo, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
// eslint-disable-next-line import/no-unresolved
import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
// eslint-disable-next-line import/no-unresolved
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { ExpressConfig, OrderLine, initializeExpress } from '../shared/express';

const config: ExpressConfig = getSetting('kco_express_data', {});

/* eslint-disable @typescript-eslint/no-explicit-any */

/**
 * Convert a Store API amount to the two-decimal minor units Kustom expects.
 *
 * @param {string} value     The amount in the store currency's minor unit.
 * @param {number} minorUnit The number of decimals in the store currency.
 * @return {number} The amount in Kustom minor units.
 */
const toKustomAmount = (value: string, minorUnit: number): number =>
	Math.round(parseInt(value || '0', 10) * Math.pow(10, 2 - minorUnit));

/**
 * Build the express element's order lines from the block cart: items and fees, with shipping left to KSA.
 *
 * @param {any} cart The cart data from the wc/store/cart store.
 * @return {OrderLine[]} The order lines.
 */
const getOrderLines = (cart: any): OrderLine[] => {
	const items: OrderLine[] = (cart?.items || []).map((item: any) => {
		const minorUnit = item.totals?.currency_minor_unit ?? 2;
		const tax = toKustomAmount(item.totals?.line_total_tax, minorUnit);
		return {
			name: item.name,
			quantity: item.quantity,
			totalAmount:
				toKustomAmount(item.totals?.line_total, minorUnit) + tax,
			taxAmount: tax,
		};
	});

	const fees: OrderLine[] = (cart?.fees || []).map((fee: any) => {
		const minorUnit = fee.totals?.currency_minor_unit ?? 2;
		const tax = toKustomAmount(fee.totals?.total_tax, minorUnit);
		return {
			name: fee.name,
			quantity: 1,
			totalAmount: toKustomAmount(fee.totals?.total, minorUnit) + tax,
			taxAmount: tax,
		};
	});

	return [...items, ...fees];
};

/**
 * The express buttons in the block cart and checkout express area. Re-initialised when the cart changes.
 *
 * @param {any} props The express payment method props from WooCommerce.
 * @return {JSX.Element} The button container.
 */
const KustomExpress = (props: any): JSX.Element => {
	const { setExpressPaymentError } = props;
	const container = useRef<HTMLDivElement>(null);
	const cart = useSelect(
		(select: any) => select('wc/store/cart').getCartData(),
		[]
	);
	const orderLines = useMemo(() => getOrderLines(cart), [cart]);
	const linesKey = JSON.stringify(orderLines);

	useEffect(() => {
		if (!container.current || !orderLines.length) {
			return;
		}

		initializeExpress({
			container: container.current,
			config,
			orderLines,
			/**
			 * The block cart and checkout always buy the cart.
			 *
			 * @return {Object} The create order body.
			 */
			getBody: () => ({ context: 'cart' }),
			/**
			 * Show the failure in WooCommerce's express payment area.
			 *
			 * @param {string} message The error message.
			 */
			onError: (message: string) => setExpressPaymentError?.(message),
		});
	}, [linesKey]); // eslint-disable-line react-hooks/exhaustive-deps

	return <div ref={container} className="kco-express" />;
};

/**
 * Editor preview: the buttons need a shopper and the SDK, so show the name only.
 *
 * @return {JSX.Element} The placeholder.
 */
const KustomExpressPreview = (): JSX.Element => (
	<div className="kco-express">Kustom Express</div>
);

registerExpressPaymentMethod({
	name: 'kco_express',
	title: 'Kustom Express',
	description: 'Kustom Express',
	gatewayId: 'kco',
	paymentMethodId: 'kco',
	content: <KustomExpress />,
	edit: <KustomExpressPreview />,
	/**
	 * Available whenever the server sent its configuration.
	 *
	 * @return {boolean} Whether to show the buttons.
	 */
	canMakePayment: (): boolean => !!config.restUrl,
	supports: {
		features: ['products'],
	},
});
