import axios from "axios";
import Oauth from "oauth-1.0a";
import crypto from "crypto";
import kcoURLS from "../helpers/kcoURLS";
import { customerKey, customerSecret } from "../config/config";

const { API_BASE_URL, API_ORDER_ENDPOINT, API_PRODUCTS_ENDPOINT } = kcoURLS;

const oauth = Oauth({
	consumer: {
		key: customerKey,
		secret: customerSecret,
	},
	signature_method: "HMAC-SHA1",
	// eslint-disable-next-line camelcase
	hash_function(base_string, key) {
		return crypto
			.createHmac("sha1", key)
			.update(base_string)
			.digest("base64");
	},
});

const createRequest = (endpoint, method = "GET") => {
	const requestData = {
		url: API_BASE_URL + endpoint,
		method,
	};
	return axios.get(requestData.url, { params: oauth.authorize(requestData) });
};
const getProducts = () => {
	return createRequest(API_ORDER_ENDPOINT);
};
const getProductById = (id) => {
	return createRequest(`${API_PRODUCTS_ENDPOINT}${id}`);
};
const getOrderById = (id) => {
	return createRequest(`${API_ORDER_ENDPOINT}${id}`);
};

export default {
	getProducts,
	getProductById,
	getOrderById,
};
