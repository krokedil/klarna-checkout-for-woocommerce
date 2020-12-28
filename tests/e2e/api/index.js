import Oauth from "oauth-1.0a";
import crypto from "crypto";
import axios from "axios";
import { customerKey, customerSecret } from "../config/config";
import kcoURLS from "../helpers/kcoURLS";

const { API_BASE_URL } = kcoURLS;

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
const createPostRequest = (endpoint, data, method = "POST") => {
	const headers = {
		"Access-Control-Allow-Origin": "*",
		"Content-Type": "application/json",
	};
	const requestData = {
		url: API_BASE_URL + endpoint,
		method,
	};
	return axios.post(requestData.url, data, {
		params: oauth.authorize(requestData),
		headers,
	});
};

export { createRequest, createPostRequest };
