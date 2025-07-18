import { useEffect, useState } from "@wordpress/element";
import {
  addIframe,
  getElementsToHide,
  hideElements,
  removeIframe,
  showElements,
} from "../lib";
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
import { extensionCartUpdate } from "@woocommerce/blocks-checkout";

type Settings = {
  snippet: string;
  shippingInIframe: boolean;
  countryCodes: any;
};

/**
 * Custom hook to manage the Kustom Checkout iframe in WooCommerce.
 * Handles the visibility of elements, iframe creation, and event registration and handling.
 *
 * @param {Settings} settings
 * @param {string} selectedPaymentMethod
 * @param {any} cartData
 */
export const useKcoIframe = (
  settings: Settings,
  selectedPaymentMethod: string,
  cartData: any
) => {
  const [isActive, setIsActive] = useState(selectedPaymentMethod === "kco");
  const { snippet, shippingInIframe, countryCodes } = settings;
  const elementsToHide = getElementsToHide(shippingInIframe);

  // Return the snippet, but since its an iframe we need to ensure react prints it properly.
  const scriptMatch = snippet.match(/<script.*?>([\s\S]*?)<\/script>/);
  const scriptContent = scriptMatch ? scriptMatch[1] : "";
  const htmlContent = snippet.replace(/<script.*<\/script>/, "");

  /**
   * Register the Kustom Checkout events needed for the integration.
   *
   * @returns {boolean} - True if Kustom Checkout is active, false otherwise.
   */
  const registerKCOEvents = () => {
    // Register listeners for the Klarna Checkout events.
    if ("function" !== typeof window._klarnaCheckout) {
      return;
    }

    window._klarnaCheckout(function (api: any) {
      api.on({
        load: ( data: any) => { hideElements(elementsToHide) }, // When the Kustom Checkout iframe is loaded, hide the elements.
        shipping_address_change: onShippingAddressChanged, // Listen for the shipping address change event and update the shipping address in the WooCommerce cart.
        shipping_option_change: onShippingOptionChanged, // Listen for the shipping option change event and update the shipping option in the WooCommerce cart.

        // The other events are not used for now, but can be used later if needed.
        change: (data: any) => {},
        user_interacted: (data: any) => {},
        customer: (data: any) => {},
        billing_address_change: (data: any) => {},
        shipping_address_update_error: (data: any) => {},
        order_total_change: (data: any) => {},
        checkbox_change: (data: any) => {},
        can_not_complete_order: (data: any) => {},
        network_error: (data: any) => {},
        load_confirmation: (data: any) => {},
        redirect_initiated: (data: any) => {},
      });
    });
  };

  /**
   * Suspend the Kustom Checkout iframe.
   *
   * @param {boolean} autoResume - Whether to automatically resume the Kustom Checkout iframe after suspending it.
   * @returns {void}
   */
  const suspendKCO = (autoResume: boolean = true): void => {
    window._klarnaCheckout(function (api: any) {
      api.suspend({ autoResume: autoResume });
    });
  };

  /**
   * Resume the Kustom Checkout iframe.
   *
   * @returns {void}
   */
  const resumeKCO = (): void => {
    window._klarnaCheckout(function (api: any) {
      api.resume();
    });
  };

  /**
   * Convert an alpha3 country code to an alpha2 country code.
   *
   * @param {string} countryCode - The alpha3 country code to convert to alpha2.
   * @returns {string} - The alpha2 country code, or an empty string if not found.
   */
  const getAlpha2CountryCodeFromAlpha3 = (countryCode: string): string => {
    // Find the key for the value that matches the country code passed.
    const alpha2CountryCode = Object.keys(countryCodes).find(
      (key) => countryCodes[key] === countryCode.toUpperCase()
    );

    return alpha2CountryCode || "";
  };

  /**
   * Handle changes to the shipping address in the Kustom Checkout iframe.
   * Sends a request to update the shipping address in the WooCommerce cart,
   * using the extensionCartUpdate function.
   *
   * @param {any} address - The shipping address object containing country and other details.
   * @returns {Promise<void>}
   */
  const onShippingAddressChanged = async (address: any): Promise<void> => {
    suspendKCO();

    // Convert the country in the address to an alpha2 country code.
    const countryCode = getAlpha2CountryCodeFromAlpha3(address.country);
    address.country = countryCode;

    const response = extensionCartUpdate({
      namespace: "kco-block",
      data: {
        action: "shipping_address_changed",
        ...address,
      },
    })
      .then((response: any) => {})
      .catch((error: any) => {})
      .finally(() => {});

    return response;
  };

  /**
   * Handle changes to the shipping option in the Kustom Checkout iframe.
   * Sends a request to update the shipping option in the WooCommerce cart,
   * using the extensionCartUpdate function.
   *
   * @param {any} option - The selected shipping option.
   * @returns {Promise<void>}
   */
  const onShippingOptionChanged = async (option: any): Promise<void> => {
    suspendKCO();

    const response = extensionCartUpdate({
      namespace: "kco-block",
      data: {
        action: "shipping_option_changed",
        ...option,
      },
    })
      .then((response: any) => {})
      .catch((error: any) => {})
      .finally(() => {});

    return response;
  };

  useEffect(() => {
    if (!isActive) return; // If Kustom Checkout is not active, don't load the script or iframe.

    if (htmlContent) {
      // Add the iframe and script to the WooCommerce checkout page.
      const kcoWrapper = addIframe(htmlContent);
      const script = document.createElement("script");
      script.textContent = scriptContent;
      document.body.appendChild(script);

      // On unmount.
      return () => {
        // Show the WC form again and remove the iframe.
        removeIframe(kcoWrapper);
        showElements(elementsToHide);
        document.body.removeChild(script);
      };
    }
  }, [htmlContent]);

  useEffect(() => {
    if (!isActive) return; // If Kustom Checkout is not active, do not register events.
    if (htmlContent) registerKCOEvents(); // Register the Kustom Checkout events only if the HTML content is available.
  }, [cartData]);

  // If the payment method is active, hide the elements that are not needed from the WooCommerce checkout page.
  if (isActive) {
    hideElements(elementsToHide);
  }

  return { isActive, elementsToHide, suspendKCO, resumeKCO };
};
