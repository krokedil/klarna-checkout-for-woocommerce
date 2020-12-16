import axios from "axios";
import woocommerce from "./woocommerce";

/**
 * @param page
 * @param endpoint
 * @param id
 * @returns {Promise<AxiosResponse<any>>}
 */
const getKlarnaOrderById = async (page, endpoint, id) => {
	const encodedKey = await page.evaluate(() => {
		return btoa("PK08164_dbc5171dedd2:e7kG49n1SCJRh1rJ");
	});

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
