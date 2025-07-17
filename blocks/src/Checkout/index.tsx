/**
 * External dependencies
 */
import * as React from "react";

/**
 * Wordpress/WooCommerce dependencies
 */
import { decodeEntities } from "@wordpress/html-entities";
import { useEffect } from "@wordpress/element";
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
import { registerPaymentMethod } from "@woocommerce/blocks-registry";
// @ts-ignore - Cant avoid this issue, but its loaded in by Webpack
import { getSetting } from "@woocommerce/settings";
import { useKcoIframe } from "./hooks/useKcoIframe";
import { decode } from "punycode";

declare global {
  interface Window {
    _klarnaCheckout: any;
    wc: any;
  }
}

const settings: any = getSetting("kco_data", {});
const title: string = decodeEntities(settings.title || "Kustom Checkout");
const description: string = decodeEntities(settings.description || "");
const iconUrl: string = decodeEntities(settings.iconUrl || "");
const features: string[] = settings.features  || [];

const canMakePayment = (): Boolean => {
  if (settings.error || !settings.snippet) {
    console.error("Failed to initialize Kustom Checkout: " + settings.error);
  }

  return true;
};

type KustomCheckoutProps = {
  activePaymentMethod?: string;
  billing?: any;
  cartData?: any;
};

const KustomCheckout = (props: KustomCheckoutProps): JSX.Element => {
  const { activePaymentMethod, billing, cartData } = props;
  const { isActive, suspendKCO, resumeKCO } = useKcoIframe(
    settings,
    activePaymentMethod,
    cartData
  );
  useEffect(() => {
    if (!isActive) {
      return;
    }
    suspendKCO();
    resumeKCO();
  }, [billing.cartTotalItems]);

  if (description !== "") {
    return (
      <div className="wc-block-components-klarna-checkout">
        <p>{description}</p>
      </div>
    );
  }

  return null;
};

const Label = (): JSX.Element => {
  return (
    <div
      style={{
        display: "flex",
        gap: 16,
        width: "100%",
        justifyContent: "space-between",
        paddingRight: 16,
      }}
    >
      <span>{title}</span>
      <img src={iconUrl} alt={title} />
    </div>
  );
};

const options = {
  name: "kco",
  label: <Label />,
  content: <KustomCheckout />,
  edit: <KustomCheckout />,
  placeOrderButtonLabel: "Pay with Kustom Checkout",
  canMakePayment: canMakePayment,
  ariaLabel: title,
  supports: features
};

registerPaymentMethod(options);
