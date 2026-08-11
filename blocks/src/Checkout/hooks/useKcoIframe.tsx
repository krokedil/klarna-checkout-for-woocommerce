import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import {
	addIframe,
	getElementsToHide,
	hideElements,
	removeIframe,
	showElements,
	isKcoActive,
} from '../lib';
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
// eslint-disable-next-line import/no-unresolved
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';

type Settings = {
	snippet: string;
	shippingInIframe: boolean;
	countryCodes: any;
};

type AddressType = 'billing' | 'shipping';

/**
 * How long to wait for address events to settle before sending the change to WooCommerce.
 * Kustom can emit billing_address_change and shipping_address_change back to back, and we only
 * want a single cart update out of that burst.
 */
const ADDRESS_UPDATE_DEBOUNCE_MS = 100;

/**
 * Custom hook to manage the Kustom Checkout iframe in WooCommerce.
 * Handles the visibility of elements, iframe creation, and event registration and handling.
 *
 * @param {Settings} settings - The settings object containing the Kustom Checkout snippet, shippingInIframe flag, and country codes.
 * @param {string} selectedPaymentMethod - The currently selected payment method in WooCommerce.
 * @param {any} _cartData - The cart data containing items and totals from WooCommerce.
 * @return {Object} - An object containing the state and functions to manage the Kustom Checkout iframe.
 */
export const useKcoIframe = (
	settings: Settings,
	selectedPaymentMethod: string,
	_cartData: any
) => {
	const [isActive, setIsActive] = useState(
		isKcoActive() || selectedPaymentMethod === 'kco'
	);
	const [htmlContent, setHtmlContent] = useState<string | null>(null);
	const [scriptContent, setScriptContent] = useState<string | null>(null);
	const { snippet, shippingInIframe, countryCodes } = settings;
	const elementsToHide = getElementsToHide(shippingInIframe);

	// Keep track of previous active state to avoid unnecessary updates.
	const prevIsActive = useRef<boolean>();

	// Refs to store the iframe wrapper and script elements for cleanup.
	const kcoWrapperRef = useRef<HTMLDivElement | null>(null);
	const scriptRef = useRef<HTMLScriptElement | null>(null);

	// The latest address of each type received from Kustom, waiting to be sent to WooCommerce.
	const pendingAddressRef = useRef<Record<AddressType, any>>({
		billing: null,
		shipping: null,
	});
	const addressUpdateTimerRef = useRef<ReturnType<typeof setTimeout> | null>(
		null
	);
	// Signature of the last address update we sent, so an unchanged address is not sent twice.
	const lastSentAddressRef = useRef<string>('');

	/**
	 * Extracts HTML content and script content from the Kustom Checkout snippet.
	 *
	 * @return {Object} containing htmlContent and scriptContent extracted from the snippet.
	 */
	const getHtmlAndScriptContent = useCallback(() => {
		// Return the snippet, but since its an iframe we need to ensure react prints it properly.
		const scriptMatch = snippet.match(/<script.*?>([\s\S]*?)<\/script>/);
		const scriptContentText = scriptMatch ? scriptMatch[1] : '';
		const htmlContentText = snippet.replace(/<script.*<\/script>/, '');
		return { htmlContentText, scriptContentText };
	}, [snippet]);

	/**
	 * Suspend the Kustom Checkout iframe.
	 *
	 * @param {boolean} autoResume - Whether to automatically resume the Kustom Checkout iframe after suspending it.
	 * @return {void}
	 */
	const suspendKCO = useCallback((autoResume: boolean = true): void => {
		// If the Kustom Checkout script hasn't loaded yet, do nothing.
		if ('function' !== typeof window._klarnaCheckout) {
			return;
		}

		window._klarnaCheckout(function (api: any) {
			api.suspend({ autoResume });
		});
	}, []);

	/**
	 * Resume the Kustom Checkout iframe.
	 *
	 * @return {void}
	 */
	const resumeKCO = useCallback((): void => {
		// If the Kustom Checkout script hasn't loaded yet, do nothing.
		if ('function' !== typeof window._klarnaCheckout) {
			return;
		}

		window._klarnaCheckout(function (api: any) {
			api.resume();
		});
	}, []);

	/**
	 * Convert an alpha3 country code to an alpha2 country code.
	 *
	 * @param {string} countryCode - The alpha3 country code to convert to alpha2.
	 * @return {string} - The alpha2 country code, or an empty string if not found.
	 */
	const getAlpha2CountryCodeFromAlpha3 = useCallback(
		(countryCode: string): string => {
			// Find the key for the value that matches the country code passed.
			const alpha2CountryCode = Object.keys(countryCodes).find(
				(key) => countryCodes[key] === countryCode.toUpperCase()
			);

			return alpha2CountryCode || '';
		},
		[countryCodes]
	);

	/**
	 * Convert a Kustom address into the shape WooCommerce expects, i.e. with an alpha2 country code.
	 *
	 * @param {any} address - The address object from Kustom.
	 * @return {any} - The address with an alpha2 country code.
	 */
	const toWooCommerceAddress = useCallback(
		(address: any): any => ({
			...address,
			country: address?.country
				? getAlpha2CountryCodeFromAlpha3(address.country)
				: '',
		}),
		[getAlpha2CountryCodeFromAlpha3]
	);

	/**
	 * Send the addresses collected from the Kustom address events to the WooCommerce cart,
	 * using the extensionCartUpdate function.
	 *
	 * @return {Promise<void>}
	 */
	const sendPendingAddresses = useCallback(async (): Promise<void> => {
		const { billing, shipping } = pendingAddressRef.current;
		pendingAddressRef.current = { billing: null, shipping: null };

		// Kustom only emits shipping_address_change once the customer has entered a separate
		// shipping address. When "same as billing" is used we only get the billing address, so
		// that is what WooCommerce has to calculate shipping and taxes from.
		const shippingAddress = shipping || billing;

		if (!shippingAddress) {
			return;
		}

		const data = {
			action: 'address_changed',
			billing: billing || {},
			shipping: shippingAddress,
		};

		// Both events can describe the same address, so skip the round trip if nothing changed.
		const signature = JSON.stringify(data);
		if (signature === lastSentAddressRef.current) {
			resumeKCO();
			return;
		}
		lastSentAddressRef.current = signature;

		const response = extensionCartUpdate({
			namespace: 'kco-block',
			data,
		})
			.then(() => {})
			.catch((_error: any) => {
				// Allow the same address to be sent again if the update failed.
				lastSentAddressRef.current = '';
			})
			.finally(() => {});

		return response;
	}, [resumeKCO]);

	/**
	 * Record an address received from Kustom and schedule it to be sent to WooCommerce.
	 * The update is debounced so a billing and a shipping event fired in quick succession
	 * result in a single cart update rather than two competing ones.
	 *
	 * @param {AddressType} type    - Which address was changed, 'billing' or 'shipping'.
	 * @param {any}         address - The address object from Kustom.
	 * @return {void}
	 */
	const queueAddressUpdate = useCallback(
		(type: AddressType, address: any): void => {
			suspendKCO();

			pendingAddressRef.current[type] = toWooCommerceAddress(address);

			if (addressUpdateTimerRef.current) {
				clearTimeout(addressUpdateTimerRef.current);
			}

			addressUpdateTimerRef.current = setTimeout(() => {
				addressUpdateTimerRef.current = null;
				sendPendingAddresses();
			}, ADDRESS_UPDATE_DEBOUNCE_MS);
		},
		[suspendKCO, toWooCommerceAddress, sendPendingAddresses]
	);

	/**
	 * Handle changes to the shipping address in the Kustom Checkout iframe.
	 *
	 * @param {any} address - The shipping address object containing country and other details.
	 * @return {void}
	 */
	const onShippingAddressChanged = useCallback(
		(address: any): void => queueAddressUpdate('shipping', address),
		[queueAddressUpdate]
	);

	/**
	 * Handle changes to the billing address in the Kustom Checkout iframe.
	 *
	 * @param {any} address - The billing address object containing country and other details.
	 * @return {void}
	 */
	const onBillingAddressChanged = useCallback(
		(address: any): void => queueAddressUpdate('billing', address),
		[queueAddressUpdate]
	);

	/**
	 * Handle changes to the shipping option in the Kustom Checkout iframe.
	 * Sends a request to update the shipping option in the WooCommerce cart,
	 * using the extensionCartUpdate function.
	 *
	 * @param {any} option - The selected shipping option.
	 * @return {Promise<void>}
	 */
	const onShippingOptionChanged = useCallback(
		async (option: any): Promise<void> => {
			suspendKCO();

			const response = extensionCartUpdate({
				namespace: 'kco-block',
				data: {
					action: 'shipping_option_changed',
					...option,
				},
			})
				.then((_response: any) => {})
				.catch((_error: any) => {})
				.finally(() => {
					resumeKCO();
				});

			return response;
		},
		[suspendKCO, resumeKCO]
	);

	/**
	 * Handle changes to the shipping option in the Kustom Checkout iframe.
	 * Sends a request to update the shipping option in the WooCommerce cart,
	 * using the extensionCartUpdate function.
	 *
	 * @param {any} option - The selected shipping option.
	 * @return {Promise<void>}
	 */
	const onLoad = useCallback(
		async (option: any): Promise<void> => {
			suspendKCO();

			const response = extensionCartUpdate({
				namespace: 'kco-block',
				data: {
					action: 'load',
					...option,
				},
			})
				.then((_response: any) => {})
				.catch((_error: any) => {})
				.finally(() => {
					resumeKCO();
				});

			return response;
		},
		[suspendKCO, resumeKCO]
	);

	useEffect(() => {
		const { htmlContentText, scriptContentText } =
			getHtmlAndScriptContent();
		setHtmlContent(htmlContentText);
		setScriptContent(scriptContentText);
	}, [snippet, getHtmlAndScriptContent]);

	/**
	 * Register the Kustom Checkout events needed for the integration.
	 *
	 * @return {void}
	 */
	const registerKCOEvents = useCallback(() => {
		// Register listeners for the Kustom Checkout events.
		if ('function' !== typeof window._klarnaCheckout) {
			return;
		}

		window._klarnaCheckout(function (api: any) {
			api.on({
				/**
				 * This event is triggered when the Kustom Checkout iframe is loaded.
				 *
				 * @param {any} _data - The data passed by the Kustom Checkout iframe.
				 * @return {void}
				 */
				load: onLoad,
				/**
				 * This event is triggered when the shipping address is changed in the Kustom Checkout iframe.
				 * It updates the shipping address in the WooCommerce cart.
				 *
				 * @param {any} address - The shipping address object containing country and other details.
				 * @return {Promise<void>}
				 */
				shipping_address_change: onShippingAddressChanged,
				/**
				 * This event is triggered when the billing address is changed in the Kustom Checkout iframe.
				 * Kustom does not emit shipping_address_change while the customer ships to their
				 * billing address, so this is the only address event we get in that case.
				 *
				 * @param {any} address - The billing address object containing country and other details.
				 * @return {void}
				 */
				billing_address_change: onBillingAddressChanged,
				/**
				 * This event is triggered when the shipping option is changed in the Kustom Checkout iframe.
				 * It updates the shipping option in the WooCommerce cart.
				 *
				 * @param {any} option - The selected shipping option.
				 * @return {Promise<void>}
				 */
				shipping_option_change: onShippingOptionChanged,

				/* eslint-disable jsdoc/require-jsdoc */
				change: (_data: any) => {},
				user_interacted: (_data: any) => {},
				customer: (_data: any) => {},
				shipping_address_update_error: (_data: any) => {},
				order_total_change: (_data: any) => {},
				checkbox_change: (_data: any) => {},
				can_not_complete_order: (_data: any) => {},
				network_error: (_data: any) => {},
				load_confirmation: (_data: any) => {},
				redirect_initiated: (_data: any) => {},
				/* eslint-enable jsdoc/require-jsdoc */
			});
		});
	}, [
		onShippingAddressChanged,
		onBillingAddressChanged,
		onShippingOptionChanged,
		onLoad,
	]);

	useEffect(() => {
		// Make sure a queued address update cannot fire after the component is gone.
		return () => {
			if (addressUpdateTimerRef.current) {
				clearTimeout(addressUpdateTimerRef.current);
				addressUpdateTimerRef.current = null;
			}
		};
	}, []);

	useEffect(() => {
		// Only show the iframe if the Kustom Checkout is active, we have the content and it was not active before.
		if (isActive && htmlContent && !prevIsActive.current) {
			hideElements(elementsToHide);
			// Add the iframe and script to the WooCommerce checkout page.
			kcoWrapperRef.current = addIframe(htmlContent);
			scriptRef.current = document.createElement('script');
			scriptRef.current.textContent = scriptContent;
			document.body.appendChild(scriptRef.current);
			prevIsActive.current = isActive;
		}

		// Always attempt to register the events.
		registerKCOEvents();

		// On unmount, if Kustom Checkout is not active, we need to remove the iframe and show the WC form again.
		return () => {
			if (isKcoActive()) return; // If KCO is the selected payment method we don't need to do anything.
			// Show the WC form again and remove the iframe.
			if (kcoWrapperRef.current) removeIframe(kcoWrapperRef.current);

			showElements(elementsToHide);
			document.body.removeChild(scriptRef.current);
			kcoWrapperRef.current = null;
			scriptRef.current = null;
			prevIsActive.current = isActive;
		};
	}, [
		isActive,
		htmlContent,
		scriptContent,
		elementsToHide,
		registerKCOEvents,
	]);

	return { isActive, elementsToHide, suspendKCO, resumeKCO };
};
