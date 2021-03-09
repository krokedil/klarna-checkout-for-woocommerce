const BASE_URL = "http://localhost:8000";

const API_BASE_URL = `${BASE_URL}/wp-json`;

const CHECKOUT = `${BASE_URL}/checkout`;

const SHOP = `${BASE_URL}/shop`;

const ADD_TO_CART = `${BASE_URL}/shop/?add-to-cart=`

const MY_ACCOUNT = `${BASE_URL}/my-account`;

const API_ORDER_ENDPOINT = "/wc/v3/orders/";

const API_PRODUCTS_ENDPOINT = "/wc/v3/products/";

const API_CUSTOMER_ENDPOINT = "/wc/v3/customers";

const API_SESSION_ENDPOINT = "/wc/v3/system_status/tools/clear_sessions";

const API_WC_OPTIONS = "/wc-admin/options";

export default {
	BASE_URL,
	CHECKOUT,
	SHOP,
	ADD_TO_CART,
	MY_ACCOUNT,
	API_BASE_URL,
	API_ORDER_ENDPOINT,
	API_PRODUCTS_ENDPOINT,
	API_CUSTOMER_ENDPOINT,
	API_SESSION_ENDPOINT,
	API_WC_OPTIONS,
};
