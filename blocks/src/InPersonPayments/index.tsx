/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Wordpress/WooCommerce dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { useEffect, useState } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
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

/**
 * The field the chosen payment channel is posted in. Matches Gateway::CHANNEL_FIELD.
 */
const CHANNEL_FIELD = 'kco_ipp_payment_channel';

/**
 * The checkout sections whose details are not added to an in-person order.
 */
const ADDRESS_FORMS =
	'.wc-block-checkout__contact-fields, .wc-block-checkout__billing-fields, .wc-block-checkout__shipping-fields';

/**
 * Block or unblock the address forms with WooCommerce's blockUI, without its spinner.
 *
 * @param {boolean} block Whether to block them.
 */
const blockAddressForms = (block: boolean): void => {
	const $ = (window as any).jQuery;
	if (!$?.fn?.block) {
		return;
	}

	if (!document.getElementById('kco-ipp-no-address-style')) {
		// No spinner: the forms are blocked, not loading. The values are hidden so the
		// salesperson does not think they end up on the order.
		$(
			'<style id="kco-ipp-no-address-style">.kco-ipp-no-address .blockUI.blockOverlay::before { display: none !important; } .kco-ipp-no-address input, .kco-ipp-no-address select, .kco-ipp-no-address textarea, .kco-ipp-no-address .select2-selection__rendered, .kco-ipp-no-address .wc-block-components-address-card address, .kco-ipp-no-address .wc-block-components-address-card address * { color: transparent !important; -webkit-text-fill-color: transparent !important; text-shadow: none !important; }</style>'
		).appendTo('head');
	}

	const $forms = $(ADDRESS_FORMS);
	if (block) {
		$forms
			.not('.kco-ipp-no-address')
			.addClass('kco-ipp-no-address')
			.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
					cursor: 'not-allowed',
				},
			});
	} else {
		$forms
			.filter('.kco-ipp-no-address')
			.removeClass('kco-ipp-no-address')
			.unblock();
	}
};

const settings: any = getSetting('kco_ipp_data', {});
const title: string = decodeEntities(settings.title || 'In-Person Payment');
const devices: Device[] = settings.devices || [];
const channels: Record<string, string> = settings.channels || {};
const features: string[] = settings.features || ['products'];

/**
 * Show why the last in-person session failed, as a checkout error, then drop the
 * status from the URL so a reload does not show it again.
 *
 * The status comes back in the URL rather than as a session notice: the block checkout
 * never clears those, and a leftover one makes the Store API refuse the next sale. Only
 * the status is in the URL, so a crafted link cannot show text of its own.
 */
const showFailedSession = (): void => {
	const failedArg: string = settings.failedArg || 'kco_ipp_failed';
	const url = new URL(window.location.href);
	const status = url.searchParams.get(failedArg);
	if (null === status) {
		return;
	}

	const messages: Record<string, string> = settings.failureMessages || {};
	(dispatch('core/notices') as any).createErrorNotice(
		decodeEntities(messages[status] || messages.default || ''),
		{ context: 'wc/checkout' }
	);

	url.searchParams.delete(failedArg);
	window.history.replaceState(window.history.state, '', url.toString());
};

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
	const [channel, setChannel] = useState<string>('');

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
						[CHANNEL_FIELD]: channel,
					},
				},
			};
		});

		return () => unsubscribe();
	}, [eventRegistration, emitResponse, deviceId, channel]);

	// The component is only mounted while the method is selected, so mounting is what
	// tells WooCommerce that this sale is handed over the counter and needs no shipping.
	useEffect(() => {
		blockAddressForms(true);

		// The checkout re-renders its address sections, e.g. when shipping drops out, so keep blocking the new ones.
		const observer = new MutationObserver(() => blockAddressForms(true));
		observer.observe(
			document.querySelector('.wc-block-checkout') || document.body,
			{ childList: true, subtree: true }
		);

		extensionCartUpdate({
			namespace: 'kco-block',
			data: { action: 'ipp_selected' },
		}).catch(() => {});

		return () => {
			observer.disconnect();
			blockAddressForms(false);
			extensionCartUpdate({
				namespace: 'kco-block',
				data: { action: 'ipp_deselected' },
			}).catch(() => {});
		};
	}, []);

	return (
		<div className="wc-block-components-kustom-in-person-payment">
			<div className="wc-block-components-notice-banner is-info">
				<div className="wc-block-components-notice-banner__content">
					{decodeEntities(settings.noAddressNotice || '')}
				</div>
			</div>
			<label htmlFor={DEVICE_FIELD} style={{ marginRight: '0.5em' }}>
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
			<div>
				<label htmlFor={CHANNEL_FIELD} style={{ marginRight: '0.5em' }}>
					{decodeEntities(settings.channelLabel || 'Payment type')}
				</label>
				<select
					id={CHANNEL_FIELD}
					name={CHANNEL_FIELD}
					value={channel}
					onChange={(event: React.ChangeEvent<HTMLSelectElement>) =>
						setChannel(event.target.value)
					}
				>
					{Object.entries(channels).map(([value, label]) => (
						<option key={value} value={value}>
							{decodeEntities(label)}
						</option>
					))}
				</select>
			</div>
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
showFailedSession();
