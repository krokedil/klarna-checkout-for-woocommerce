import woocommerce from "./woocommerce";

let json = "";

const createTaxClasses = async () => {
	for(const taxes of json.taxes) {
		await woocommerce.createTaxClass({
			name: taxes.name,
		})
	}
};

const createTaxRates = async () => {
	for(const taxes of json.taxes) {
		await woocommerce.createTaxRate({
			country: "SE",
			state: "",
			cities: [],
			postcodes: [],
			rate: taxes.rate,
			name: taxes.name,
			shipping: true,
			class: taxes.name,
		})
	}
};

const createProducts = async () => {
	let i = 0;
	for( const simpleProduct of json.products.simple ) {
		let product = await woocommerce.createProduct({
			name: simpleProduct.name,
			sku: simpleProduct.sku,
			regular_price: simpleProduct.regular_price,
			type: "simple",
			virtual: simpleProduct.virtual,
			downloadable: simpleProduct.downloadable,
			tax_class: simpleProduct.tax_class,
		});

		json.products.simple[i].id = product.data.id;
		i++;
	}
};

const createAttributes = async () => {
	let i = 0;
	for( const variableProduct of json.products.attribute ) {
		const attr = await woocommerce.createProductAttribute({
			name: variableProduct.name,
			slug: variableProduct.name,
			type: "select",
			order_by: "menu_order",
			has_archives: true,
		});
		json.products.attribute[i].id = attr.data.id;
		i++;
	}
}

const createVariableProducts = async () => {
	let i = 0;
	for( const variableProduct of json.products.variable ) {
		let attributeId = 0;
		json.products.attribute.forEach(element => {
			if(element.name === json.products.variable[i].attribute.name) {
				attributeId = element.id
			}
		})
		let options = [];
		variableProduct.attribute.options.forEach(tmpOption => {
			options.push(tmpOption.option);
		})
		const product = await woocommerce.createProduct({
			name: variableProduct.name,
			sku: variableProduct.sku,
			type: "variable",
			virtual: variableProduct.virtual,
			downloadable: variableProduct.downloadable,
			attributes: [{
				id: attributeId,
				name: variableProduct.attribute.name,
				variation: true,
				visible: true,
				options: options,
			}],
			tax_class: variableProduct.tax_class,
		});

		json.products.variable[i].id = product.data.id;
		
		let x = 0;
		for( const option of variableProduct.attribute.options ) {
			const variation = await woocommerce.createProductVariation(product.data.id, {
				regular_price: variableProduct.regular_price,
				attributes: [
					{
						id: attributeId,
						option: option.option,
					},
				],
			})
			json.products.variable[i].attribute.options[x].id = variation.data.id;
			x++;
		}
		i++;
	}
};

const createCoupons = async () => {
	for(const coupon of json.coupons) {
		await woocommerce.createCoupons({
			code: coupon.code,
			discount_type: coupon.discountType,
			amount: coupon.amount,
			individual_use: false,
			exclude_sale_items: false,
		})
	}
};

const createShippingZones = async () => {
	for(const shippingZone of json.shipping) {
		const zone = await woocommerce.createShippingZone({
			name: shippingZone.name,
		});

		await woocommerce.updateShippingZoneLocation(zone.data.id, [
			{ code: shippingZone.location.code, type: shippingZone.location.type },
		]);

		for(const method of shippingZone.methods){
			const methodRes = await woocommerce.includeShippingZoneMethod(
				zone.data.id,
				{
					method_id: method.method,
				}
			);
			await woocommerce.updateShippingZoneMethod(zone.data.id, methodRes.data.id, {
				settings: { cost: method.amount },
			});
		}
	}
};

const setupStore = async (data) => {
	json = data;
	await createTaxClasses();
	await createTaxRates();
	await createShippingZones();
	await createAttributes();
	await createProducts();
	await createVariableProducts();
	await createCoupons();

	return json;
}

export default {
	setupStore
};
