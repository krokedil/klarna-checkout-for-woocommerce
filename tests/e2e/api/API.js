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

const getWoocommerceOrderById = async (id) => woocommerce.getOrderById(id);

export default {
	getKlarnaOrderById,
	getWoocommerceOrderById,
};
