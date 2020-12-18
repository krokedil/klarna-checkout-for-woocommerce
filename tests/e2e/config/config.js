import config from "./config.data.json";

export const adminData = config?.users?.admin;
export const customerData = config.users.customer;
export const shippingTargets = customerData.shipping.targets;
export const paymentSelectedMethod = customerData.payment.selectedMethod;
export const customerKey = customerData.api.consumerKey;
export const customerSecret = customerData.api.consumerSecret;
export const klarnaAuth = customerData.klarnaCredentials;

export const freeShippingMethod = customerData.shipping.methods.freeShipping;
export const flatRateMethod = customerData.shipping.methods.flatRate;

export const freeShippingMethodTarget = shippingTargets.freeShippingTarget;
export const flatRateMethodTarget = shippingTargets.flatRateTarget;

export const creditPaymentMethod = paymentSelectedMethod.creditMethod;
export const debitPaymentMethod = paymentSelectedMethod.debitMethod;
export const invoicePaymentMethod = paymentSelectedMethod.invoiceMethod;

export const customerName = customerData.first_name;
export const customerLastname = customerData.last_name;
export const customerEmail = customerData.email;
export const customerUsername = customerData.username;

export const { pinNumber } = customerData;
export const { cardNumber } = customerData;

export const timeOutTime = config.timeoutTime;
export const { billingData } = config.users.customer;

export const userCredentials = config.users.customer.credentialsAndSelectors;

export const { klarnaOrderEndpoint } = config.users.admin;
