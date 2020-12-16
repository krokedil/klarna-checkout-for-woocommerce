import config from "./config.data.json";

export const adminData = config?.users?.admin;
export const customerData = config.users.customer;

export const customerKey = config.users.customer.api.consumerKey;
export const customerSecret = config.users.customer.api.consumerSecret;
export const klarnaAuth = config.users.customer.klarnaCredentials;
