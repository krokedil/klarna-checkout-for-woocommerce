/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Wordpress/WooCommerce dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { useEffect, useState } from '@wordpress/element';
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
// eslint-disable-next-line import/no-unresolved
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
// eslint-disable-next-line import/no-unresolved
import { getSetting } from '@woocommerce/settings';
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
// eslint-disable-next-line import/no-unresolved
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';

/**
 * A paired Kustom POS device the sale can be sent to.
 */
type Device = {
	id: string;
	name: string;
};

/**
 * The field the chosen device is posted in. Matches Gateway::DEVICE_FIELD, so the
 * Store API hands it to the same gateway the shortcode checkout posts to.
 */
const DEVICE_FIELD = 'kco_ipp_device_id';

const settings: any = getSetting('kco_ipp_data', {});
const title: string = decodeEntities(settings.title || 'In-Person Payment');
const devices: Device[] = settings.devices || [];
const features: string[] = settings.features || ['products'];

/**
 * Checks whether the method can be offered.
 *
 * A store with no reachable device has nothing to send the sale to, so the method is
 * hidden rather than shown as a dead end.
 *
 * @return {boolean} True if there is a device to take the payment on.
 */
const canMakePayment = (): boolean => devices.length > 0;

/**
 * In-person payment component properties.
 *
 * @property {Object} [eventRegistration] - The checkout event callbacks from WooCommerce.
 * @property {Object} [emitResponse]      - The response types WooCommerce expects back.
 */
type InPersonPaymentProps = {
	eventRegistration?: any;
	emitResponse?: any;
};

/**
 * The device picker the salesperson chooses from.
 *
 * @param {InPersonPaymentProps} props - The properties passed to the component.
 * @return {JSX.Element} The rendered device picker.
 */
const InPersonPayment = (props: InPersonPaymentProps): JSX.Element => {
	const { eventRegistration, emitResponse } = props;
	const [deviceId, setDeviceId] = useState<string>(devices[0]?.id || '');

	useEffect(() => {
		const unsubscribe = eventRegistration.onPaymentSetup(() => {
			if (!deviceId) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: decodeEntities(settings.noDeviceMessage || ''),
				};
			}

			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						[DEVICE_FIELD]: deviceId,
					},
				},
			};
		});

		return () => unsubscribe();
	}, [eventRegistration, emitResponse, deviceId]);

	// The component is only mounted while the method is selected, so mounting is what
	// tells WooCommerce that this sale is handed over the counter and needs no shipping.
	useEffect(() => {
		extensionCartUpdate({
			namespace: 'kco-block',
			data: { action: 'ipp_selected' },
		}).catch(() => {});

		return () => {
			extensionCartUpdate({
				namespace: 'kco-block',
				data: { action: 'ipp_deselected' },
			}).catch(() => {});
		};
	}, []);

	return (
		<div className="wc-block-components-kustom-in-person-payment">
			<label htmlFor={DEVICE_FIELD}>
				{decodeEntities(settings.deviceLabel || 'Device')}
			</label>
			<select
				id={DEVICE_FIELD}
				name={DEVICE_FIELD}
				value={deviceId}
				onChange={(event: React.ChangeEvent<HTMLSelectElement>) =>
					setDeviceId(event.target.value)
				}
			>
				{devices.map((device: Device) => (
					<option key={device.id} value={device.id}>
						{decodeEntities(device.name)}
					</option>
				))}
			</select>
		</div>
	);
};

/**
 * What the block editor shows in place of the device picker, which has no devices to
 * offer and must not touch the cart.
 *
 * @return {JSX.Element} The rendered placeholder.
 */
const Edit = (): JSX.Element => (
	<div className="wc-block-components-kustom-in-person-payment">
		<p>{title}</p>
	</div>
);

/**
 * Label component for the in-person payment method.
 *
 * @return {JSX.Element} A label component for the in-person payment method.
 */
const Label = (): JSX.Element => <span>{title}</span>;

/**
 * Options for registering the in-person payment method.
 *
 * @see https://github.com/woocommerce/woocommerce/blob/trunk/docs/block-development/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration.md#registration
 */
const options = {
	name: 'kco_ipp',
	label: <Label />,
	content: <InPersonPayment />,
	edit: <Edit />,
	placeOrderButtonLabel: decodeEntities(
		settings.placeOrderLabel || 'Take payment on device'
	),
	canMakePayment,
	ariaLabel: title,
	supports: { features },
};

registerPaymentMethod(options);
