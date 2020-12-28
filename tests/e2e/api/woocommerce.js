import kcoURLS from "../helpers/kcoURLS";
import { createRequest, post } from "./index";

const {
	API_ORDER_ENDPOINT,
	API_PRODUCTS_ENDPOINT,
	API_CUSTOMER_ENDPOINT,
} = kcoURLS;

const getProducts = () => {
	return createRequest(API_ORDER_ENDPOINT);
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

export default {
	getProducts,
	getProductById,
	getOrderById,
	getOrders,
	getCustomers,
	createCustomer,
};
