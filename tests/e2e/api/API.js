import axios from "axios";
import woocommerce from "./woocommerce";
import { klarnaAuth } from "../config/config";

/**
 * @param page
 * @param endpoint
 * @param id
 * @returns {Promise<AxiosResponse<any>>}
 */
const getKlarnaOrderById = async (page, endpoint, id) => {
	const encodedKey = await page.evaluate((auth) => {
		const merchant = auth.test_merchant_id_eu;
		const secret = auth.test_shared_secret_eu;
		return btoa(`${merchant}:${secret}`);
	}, klarnaAuth);

	return axios.get(`${endpoint}/${id}`, {
		headers: {
			Authorization: `Basic ${encodedKey}`,
		},
	});
};

const getWCOrderById = async (id) => woocommerce.getOrderById(id);
const createWCCustomer = async (data) => woocommerce.createCustomer(data);
const getWCCustomers = async () => woocommerce.getCustomers();
const clearWCSession = async () => woocommerce.clearSession();
const updateOptions = async (data) => woocommerce.updateOption(data);
const createWCProduct = async (data) => woocommerce.createProduct(data);

export default {
	getKlarnaOrderById,
	getWCOrderById,
	getWCCustomers,
	createWCCustomer,
	clearWCSession,
	updateOptions,
	createWCProduct,
};
