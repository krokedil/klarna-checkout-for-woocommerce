import config from "./config.data.json";

export const adminData = config?.users?.admin;
export const customerData = config.users.customer;

export const customerKey = config.api.consumerKey;
export const customerSecret = config.api.consumerSecret;
