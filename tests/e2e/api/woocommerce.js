import urls from "../helpers/urls";
import { createRequest, post, put } from "./index";

const {
	API_ORDER_ENDPOINT,
	API_PRODUCTS_ENDPOINT,
	API_COUPON_ENDPOINT,
	API_TAXES_ENDPOINT,
	API_SHIPPING_ENDPOINT,
	API_CUSTOMER_ENDPOINT,
	API_SESSION_ENDPOINT,
	API_WC_OPTIONS,
	API_WC_PRICE_INC_EXC,
} = urls;

const getProducts = () => {
	return createRequest(API_PRODUCTS_ENDPOINT);
};

const getProductById = (id) => {
	return createRequest(`${API_PRODUCTS_ENDPOINT}${id}`);
};

const getOrders = () => {
	return createRequest(API_ORDER_ENDPOINT);
};

const getOrderById = (id) => {
	return createRequest(`${API_ORDER_ENDPOINT}${id}`);
};

const createCustomer = async (data) => {
	return createRequest(`${API_CUSTOMER_ENDPOINT}`, post, data);
};

const getCustomers = async () => {
	return createRequest(API_CUSTOMER_ENDPOINT);
};

const clearSession = async () => {
	return createRequest(API_SESSION_ENDPOINT, put, { confirm: true });
};

const updateOption = async (data) => {
	return createRequest(API_WC_OPTIONS, post, JSON.stringify(data));
};

const createProduct = async (data) => {
	return createRequest(API_PRODUCTS_ENDPOINT, post, data);
};

const createProductVariation = async (id, data) => {
	return createRequest(
		`${API_PRODUCTS_ENDPOINT}${id}/variations`,
		post,
		data
	);
};

const createProductAttribute = async (data) => {
	return createRequest(`${API_PRODUCTS_ENDPOINT}attributes`, post, data);
};

const createCoupons = async (data) => {
	return createRequest(`${API_COUPON_ENDPOINT}`, post, data);
};

const createTaxClass = async (data) => {
	return createRequest(`${API_TAXES_ENDPOINT}classes`, post, data);
};

const createTaxRate = async (data) => {
	return createRequest(`${API_TAXES_ENDPOINT}`, post, data);
};

const createShippingZone = async (data) => {
	return createRequest(`${API_SHIPPING_ENDPOINT}zones`, post, data);
};

const updateShippingZoneLocation = async (id, data) => {
	return createRequest(
		`${API_SHIPPING_ENDPOINT}zones/${id}/locations`,
		post,
		data
	);
};

const includeShippingZoneMethod = async (id, data) => {
	return createRequest(
		`${API_SHIPPING_ENDPOINT}zones/${id}/methods`,
		post,
		data
	);
};

const updateShippingZoneMethod = async (id, mid, data) => {
	return createRequest(
		`${API_SHIPPING_ENDPOINT}zones/${id}/methods/${mid}`,
		put,
		data
	);
};

const pricesIncludeTax = async (data) => {
	return createRequest(API_WC_PRICE_INC_EXC, put, data);
};

export default {
	getProducts,
	getProductById,
	getOrderById,
	getOrders,
	getCustomers,
	createCustomer,
	clearSession,
	updateOption,
	createProduct,
	createProductVariation,
	createProductAttribute,
	createCoupons,
	createTaxClass,
	createTaxRate,
	createShippingZone,
	updateShippingZoneLocation,
	includeShippingZoneMethod,
	updateShippingZoneMethod,
	pricesIncludeTax,
};
