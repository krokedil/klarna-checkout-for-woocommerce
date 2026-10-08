/**
 * Kustom express buttons on the product page, the classic cart and wherever the shortcode or block is used.
 */

/**
 * Internal dependencies
 */
import {
	CreateOrderBody,
	ExpressConfig,
	OrderLine,
	initializeExpress,
} from '../shared/express';

/* eslint-disable @typescript-eslint/no-explicit-any */

declare global {
	interface Window {
		kcoExpressParams: ExpressConfig;
		jQuery?: any;
	}
}

type UnitAmounts = { unitAmount: number; unitTax: number };

type ButtonData = Partial<UnitAmounts> & {
	context: 'cart' | 'product';
	orderLines?: OrderLine[];
	productId?: number;
	name?: string;
	variable?: boolean;
};

const config = window.kcoExpressParams;

/**
 * Show a WooCommerce style error notice above the button.
 *
 * @param {HTMLElement} container The button container.
 * @param {string}      message   The message.
 */
const showError = (container: HTMLElement, message: string): void => {
	const notice = document.createElement('ul');
	notice.className = 'woocommerce-error';
	notice.setAttribute('role', 'alert');
	const item = document.createElement('li');
	item.textContent = message;
	notice.appendChild(item);

	const wrapper = document.querySelector('.woocommerce-notices-wrapper');
	if (wrapper) {
		wrapper.replaceChildren(notice);
		wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
	} else {
		container.parentNode?.insertBefore(notice, container);
	}
};

/**
 * Initialize a cart context button with the order lines rendered by the server.
 *
 * @param {HTMLElement} container The button container.
 * @param {ButtonData}  data      The button data.
 */
const setupCart = (container: HTMLElement, data: ButtonData): void => {
	initializeExpress({
		container,
		config,
		orderLines: data.orderLines || [],
		/**
		 * Cart context buttons buy the whole cart.
		 *
		 * @return {CreateOrderBody} The create order body.
		 */
		getBody: () => ({ context: 'cart' }),
		/**
		 * Show the failure as a WooCommerce notice.
		 *
		 * @param {string} message The error message.
		 */
		onError: (message) => showError(container, message),
	});
};

/**
 * Initialize a product context button that follows the add to cart form's quantity and variation.
 *
 * @param {HTMLElement} container The button container.
 * @param {ButtonData}  data      The button data.
 */
const setupProduct = (container: HTMLElement, data: ButtonData): void => {
	const form =
		(container
			.closest('.product')
			?.querySelector('form.cart') as HTMLFormElement | null) ||
		(document.querySelector('form.cart') as HTMLFormElement | null);

	let unit: UnitAmounts | null = data.variable
		? null
		: { unitAmount: data.unitAmount || 0, unitTax: data.unitTax || 0 };
	let variationId = 0;
	let timer: number | undefined;

	/**
	 * The quantity in the add to cart form.
	 *
	 * @return {number} The quantity, at least 1.
	 */
	const getQuantity = (): number => {
		const input = form?.querySelector(
			'input[name="quantity"]'
		) as HTMLInputElement | null;
		const quantity = input ? parseFloat(input.value) : 1;
		return quantity > 0 ? quantity : 1;
	};

	/**
	 * The selected variation attributes in the add to cart form.
	 *
	 * @return {Record<string, string>} The attributes, keyed by attribute_* name.
	 */
	const getVariation = (): Record<string, string> => {
		const variation: Record<string, string> = {};
		form?.querySelectorAll('[name^="attribute_"]').forEach((field) => {
			const input = field as HTMLInputElement | HTMLSelectElement;
			variation[input.name] = input.value;
		});
		return variation;
	};

	/**
	 * What to buy: this product with the form's quantity and variation.
	 *
	 * @return {CreateOrderBody} The create order body.
	 */
	const getBody = (): CreateOrderBody => ({
		context: 'product',
		product_id: data.productId,
		variation_id: variationId,
		quantity: getQuantity(),
		variation: getVariation(),
	});

	/**
	 * Initialize the button with order lines for the current quantity and variation.
	 */
	const render = (): void => {
		// A variable product is disabled until a purchasable variation is chosen.
		container.dataset.disabled = unit ? 'false' : 'true';
		container.inert = !unit;
		container.setAttribute('aria-disabled', unit ? 'false' : 'true');

		const amounts = unit || {
			unitAmount: data.unitAmount || 0,
			unitTax: data.unitTax || 0,
		};
		const quantity = getQuantity();

		initializeExpress({
			container,
			config,
			orderLines: [
				{
					name: data.name || '',
					quantity,
					totalAmount: Math.round(amounts.unitAmount * quantity),
					taxAmount: Math.round(amounts.unitTax * quantity),
				},
			],
			getBody,
			/**
			 * Show the failure as a WooCommerce notice.
			 *
			 * @param {string} message The error message.
			 */
			onError: (message) => showError(container, message),
		});
	};

	/**
	 * Re-render once the shopper has stopped changing the quantity or variation.
	 */
	const renderSoon = (): void => {
		window.clearTimeout(timer);
		timer = window.setTimeout(render, 300);
	};

	// WooCommerce, and theme quantity buttons, trigger their events through jQuery only.
	if (form && window.jQuery) {
		window
			.jQuery(form)
			.on('change input', 'input[name="quantity"]', renderSoon)
			.on('found_variation', (_event: unknown, variation: any) => {
				const purchasable =
					variation?.is_purchasable &&
					variation?.is_in_stock &&
					variation?.kco_express;
				unit = purchasable ? variation.kco_express : null;
				variationId = purchasable ? variation.variation_id : 0;
				renderSoon();
			})
			.on('reset_data hide_variation', () => {
				unit = null;
				variationId = 0;
				renderSoon();
			});
	}

	render();
};

/**
 * Initialize every express button on the page that has not been initialized yet.
 */
const setupAll = (): void => {
	if (!config) {
		return;
	}

	document
		.querySelectorAll<HTMLElement>(
			'.kco-express[data-kco-express]:not([data-kco-ready])'
		)
		.forEach((container) => {
			container.dataset.kcoReady = 'true';

			let data: ButtonData;
			try {
				data = JSON.parse(container.dataset.kcoExpress || '');
			} catch (error) {
				return;
			}

			if ('product' === data.context) {
				setupProduct(container, data);
			} else {
				setupCart(container, data);
			}
		});
};

if ('loading' === document.readyState) {
	document.addEventListener('DOMContentLoaded', setupAll);
} else {
	setupAll();
}

// The classic cart replaces its totals, and the button with it, after every update.
window.jQuery?.(document.body).on('updated_cart_totals', setupAll);
