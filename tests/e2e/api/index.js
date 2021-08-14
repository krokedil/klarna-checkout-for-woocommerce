import Oauth from "oauth-1.0a";
import crypto from "crypto";
import axios from "axios";
import { customerKey, customerSecret } from "../config/config";
import kcoURLS from "../helpers/kcoURLS";

const { API_BASE_URL } = kcoURLS;
const httpMethods = {
	get: "GET",
	post: "POST",
	put: "PUT",
};
const { get, post, put } = httpMethods;
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

const createRequest = async (endpoint, method = "GET", data = null) => {
	const headers = {
		"Access-Control-Allow-Origin": "*",
		"Content-Type": "application/json",
	};
	const requestData = {
		url: API_BASE_URL + endpoint,
		method,
	};
	const config = {
		params: oauth.authorize(requestData),
		headers,
	};
	let response = null;
	switch (method) {
		case get:
			response = axios.get(requestData.url, config);
			break;
		case post:
			response = axios.post(requestData.url, data, config).catch(e => console.log(e));
			break;
		case put:
			response = axios.put(requestData.url, data, config);
			break;
		default:
			return Promise.reject(new Error("Unsupported method")).then(
				(result) => console.log(result)
			);
	}
	return response;
};

export { createRequest, get, post, put };
