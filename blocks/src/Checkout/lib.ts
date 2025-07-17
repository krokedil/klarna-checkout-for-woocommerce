export const getElementsToHide = (shippingInIframe: boolean) => {
  return [
    "#shipping-fields",
    "#billing-fields",
    "#contact-fields",
    ".wc-block-components-checkout-place-order-button",
    ".wp-block-woocommerce-checkout-terms-block",
    shippingInIframe ? "#shipping-option" : "",
  ];
};

export const hideElements = (toHide: string[]) => {
  // Hide the parts we don't want to show.
  toHide.forEach((selector) => {
    if (selector === "") {
      return;
    }
    const element = document.querySelector(selector);
    if (element) {
      element.setAttribute("style", "display: none;");
    }
  });
};

export const showElements = (toHide: string[]) => {
  // Show the hidden parts.
  toHide.forEach((selector) => {
    if (selector === "") {
      return;
    }
    const element = document.querySelector(selector);
    if (element) {
      element.setAttribute("style", "display: block;");
    }
  });
};

export const addIframe = (htmlContent: string) => {
  const iframeWrapper = document.createElement("div");
  iframeWrapper.setAttribute("srcdoc", htmlContent);
  iframeWrapper.setAttribute(
    "style",
    "width: 100%; height: 100%; border: none;"
  );

  // Create a new div for the htmlContent, and set it as the inner HTML.
  const kcoWrapper = document.createElement("div");
  kcoWrapper.className =
    "wc-block-checkout__klarna-checkout wc-block-components-klarna-checkout-block";
  kcoWrapper.innerHTML = htmlContent;

  // Insert the iframe before the terms block.
  const paymentMethodsBlock = document.querySelector(
    ".wc-block-checkout__payment-method"
  );
  if (paymentMethodsBlock) {
    paymentMethodsBlock.after(kcoWrapper);
  }

  return kcoWrapper;
};

export const removeIframe = (kcoWrapper: HTMLDivElement) => {
  kcoWrapper.remove();
};
