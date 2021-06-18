import kcoURLS from "../helpers/kcoURLS";
import { createRequest, post, put } from "./index";

const {
	API_ORDER_ENDPOINT,
	API_PRODUCTS_ENDPOINT,
	API_CUSTOMER_ENDPOINT,
	API_SESSION_ENDPOINT,
	API_WC_OPTIONS,
	API_WC_PRICE_INC_EXC,
} = kcoURLS;

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
	pricesIncludeTax,
};
