/**
 * Shared Kustom express buttons logic, used by the block express payment method and the page script.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

declare global {
	interface Window {
		kustomElements: any;
	}
}

export type ExpressConfig = {
	restUrl: string;
	nonce: string;
	restNonce: string;
	locale: string;
	currency: string;
	sdk: { src: string; publicApiKey: string };
	i18n: { error: string };
};

export type OrderLine = {
	name: string;
	quantity: number;
	totalAmount: number;
	taxAmount: number;
};

export type CreateOrderBody = {
	context: 'cart' | 'product';
	product_id?: number;
	variation_id?: number;
	quantity?: number;
	variation?: Record<string, string>;
};

type InitializeArgs = {
	container: HTMLElement;
	config: ExpressConfig;
	orderLines: OrderLine[];
	getBody: () => CreateOrderBody;
	onError: (message: string) => void;
};

const SDK_SCRIPT_ID = 'kustom-elements-script';
let buttonCount = 0;

/**
 * Load the Kustom Elements SDK when the page did not enqueue it, e.g. when WooCommerce lazy loads the script.
 *
 * @param {ExpressConfig['sdk']} sdk The SDK URL and public API key.
 */
export const ensureSdk = (sdk: ExpressConfig['sdk']): void => {
	if (document.getElementById(SDK_SCRIPT_ID) || !sdk?.src) {
		return;
	}

	// The installation snippet's queue, so calls made before the SDK has loaded are replayed.
	if (!window.kustomElements) {
		const internal: Record<string, unknown> = {
			q: [],
			snippetVersion: '1.0.0',
		};
		/**
		 * Queue a call until the SDK has loaded.
		 *
		 * @param {string}    method The SDK method.
		 * @param {unknown[]} args   The method arguments.
		 * @return {Promise<unknown>} Settled by the SDK once it has run the call.
		 */
		const kustomElements: any = (method: string, ...args: unknown[]) =>
			new Promise((resolve, reject) => {
				(internal.q as unknown[]).push({
					method,
					args,
					resolve,
					reject,
				});
			});
		kustomElements._internal = internal;
		kustomElements.load = new Promise((resolve, reject) => {
			internal.loadResolve = resolve;
			internal.loadReject = reject;
		});
		window.kustomElements = kustomElements;
	}

	const script = document.createElement('script');
	script.id = SDK_SCRIPT_ID;
	script.async = true;
	script.src = sdk.src;
	script.setAttribute('data-public-api-key', sdk.publicApiKey);
	document.head.appendChild(script);
};

/**
 * Ask the server to create the Kustom order for the createOrder hook.
 *
 * @param {ExpressConfig}   config The express configuration.
 * @param {CreateOrderBody} body   What to buy.
 * @return {Promise<string>} The Kustom order id.
 */
export const createOrder = async (
	config: ExpressConfig,
	body: CreateOrderBody
): Promise<string> => {
	const response = await fetch(config.restUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': config.restNonce,
			'X-KCO-Express-Nonce': config.nonce,
		},
		body: JSON.stringify(body),
	});

	const data = await response.json().catch(() => ({}));
	if (!response.ok || !data.order_id) {
		throw new Error(data.message || config.i18n.error);
	}

	return data.order_id;
};

/**
 * Render a fresh express button in the container and initialize it. Called again whenever the order lines change.
 *
 * @param {InitializeArgs} args The container, configuration, order lines and callbacks.
 */
export const initializeExpress = ({
	container,
	config,
	orderLines,
	getBody,
	onError,
}: InitializeArgs): void => {
	ensureSdk(config.sdk);

	buttonCount++;
	const button = document.createElement('kustom-express-buttons');
	button.id = `kco-express-${getBody().context}-${buttonCount}`;
	button.setAttribute('locale', config.locale);
	container.replaceChildren(button);

	window
		.kustomElements('express.initialize', `#${button.id}`, {
			currency: config.currency,
			orderLines,
			shippingAddressRequired: true,
			/**
			 * Create the Kustom order on the server when the shopper opens the sheet.
			 *
			 * @param {(order: {orderId: string}) => void} resolve Takes the created order id.
			 * @param {() => void}                         reject  Aborts the sheet.
			 */
			createOrder: (
				resolve: (order: { orderId: string }) => void,
				reject: () => void
			) => {
				createOrder(config, getBody())
					.then((orderId) => resolve({ orderId }))
					.catch((error: Error) => {
						onError(error.message || config.i18n.error);
						reject();
					});
			},
			/**
			 * Send the shopper to our confirmation URL once Kustom has completed the purchase.
			 *
			 * @param {Object} response             The SDK response.
			 * @param {string} response.redirectUrl The merchant confirmation URL.
			 */
			onConfirm: (response: { redirectUrl?: string }) => {
				if (response?.redirectUrl) {
					window.location.href = response.redirectUrl;
				}
			},
		})
		.catch((error: unknown) => {
			// eslint-disable-next-line no-console
			console.error('Failed to initialize Kustom express buttons', error);
		});
};
