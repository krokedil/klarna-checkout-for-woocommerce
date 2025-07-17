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

  const registerKCOEvents = () => {
    // Register listeners for the Klarna Checkout events.
    if ("function" !== typeof window._klarnaCheckout) {
      return;
    }

    window._klarnaCheckout(function (api: any) {
      api.on({
        change: function (data: any) {console.log( 'Change event', data );},
        load: function (data: any) {
          console.log("Klarna Checkout loaded", data);
          // Ensure the WC Fields are hidden on load.
          hideElements(elementsToHide);
        },
        user_interacted: function (data: any) {console.log( 'User interacted', data );},
        customer: function (data: any) {console.log( 'Customer data', data );},
        billing_address_change: function (data: any) {console.log( 'Billing address changed', data );},
        shipping_address_change: function (data: any) {
          console.log("Shipping address changed", data);
          onShippingAddressChanged(data);
        },
        shipping_option_change: function (data: any) {
          console.log("Shipping option changed", data);
          onShippingOptionChanged(data);
        },
        shipping_address_update_error: function (data: any) {console.log( 'Shipping address update error', data );},
        order_total_change: function (data: any) {console.log( 'Order total changed', data );},
        checkbox_change: function (data: any) {console.log( 'Checkbox changed', data );},
        can_not_complete_order: function (data: any) {console.log( 'Can not complete order', data );},
        network_error: function (data: any) {console.log( 'Network error', data );},
        load_confirmation: function (data: any) {console.log( 'Load confirmation', data );},
        redirect_initiated: function (data: any) {console.log( 'Redirect initiated', data );},
      });
    });
  };

  const suspendKCO = (autoResume: boolean = true) => {
    window._klarnaCheckout(function (api: any) {
      api.suspend({ autoResume: autoResume });
    });
  };

  const resumeKCO = () => {
    window._klarnaCheckout(function (api: any) {
      api.resume();
    });
  };

  const getAlpha2CountryCodeFromAlpha3 = (countryCode: string) => {
    // Find the key for the value that matches the country code passed.
    const alpha2CountryCode = Object.keys(countryCodes).find(
      (key) => countryCodes[key] === countryCode.toUpperCase()
    );

    return alpha2CountryCode || "";
  };

  const onShippingAddressChanged = async (address: any) => {
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

  const onShippingOptionChanged = async (option: any) => {
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
    if (!isActive) {
      return;
    }

    if (htmlContent) {
      // Hide the WC form and show the iframe.
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
    if (!isActive) {
      return;
    }

    if (htmlContent) {
      registerKCOEvents();
    }
  }, [cartData]);

  if (isActive) {
    hideElements(elementsToHide);
  }

  return { isActive, elementsToHide, suspendKCO, resumeKCO };
};
