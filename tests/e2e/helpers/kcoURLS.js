const BASE_URL = "http://localhost:8000";

const API_BASE_URL = `${BASE_URL}/wp-json`;

const CHECKOUT = `${BASE_URL}/checkout`;

const SHOP = `${BASE_URL}/shop`;

const MY_ACCOUNT = `${BASE_URL}/my-account`;

const API_ORDER_ENDPOINT = "/wc/v3/orders/";

const API_PRODUCTS_ENDPOINT = "/wc/v3/products/";

const API_CUSTOMER_ENDPOINT = "/wc/v3/customers";

const API_SESSION_ENDPOINT = "/wc/v3/system_status/tools/clear_sessions";

export default {
	BASE_URL,
	CHECKOUT,
	SHOP,
	MY_ACCOUNT,
	API_BASE_URL,
	API_ORDER_ENDPOINT,
	API_PRODUCTS_ENDPOINT,
	API_CUSTOMER_ENDPOINT,
	API_SESSION_ENDPOINT,
};
