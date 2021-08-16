# End to End testing

## Installation
To use this E2E testing suite you will need a few things installed.
- Docker
- NodeJS
- Yarn

After cloning the repository, open the `/test/e2e` folder in your command line and trigger the `yarn install` command. This will install the needed node packages that is needed.

## Usage
To run the tests in your environment, you can do so by opening the folder `/test/e2e` in your terminal. Then entering the command `yarn e2e:test`. This will start the process to create the docker container, install WordPress and WooCommerce, and run the tests. After the tests are done, the docker container will be destroyed and the results of the tests will be printed out in the terminal.

## Writing tests
The core of the testing is around placing orders and making sure that the process goes well. This is done by the main test file that we have in the `/test/e2e/tests` folder. This will loop through a list of tests that are described by the `tests.json` file that we have in the `/test/e2e/config` folder.

A test will look like this:
````
	{
		"name": "Test name",
		"loggedIn": bool,
		"inclusiveTax": "yes",
		"shippingInIframe": "yes",
		"products": ["Product 1 name", "Product 2 name"],
		"shippingMethod": "shipping_method",
		"coupons": ["coupon code"],
		"customerType": "person",
		"expectedTotal": float,
		"expectedOrderLines": int
	}
````
To create a new test, simply add a line to the tests.json file and include the following keys with the values you need for your tests.
- name: The name that you want the test to have in the logs that are printed out after the test is done. It is good to mention in the name what the test actually includes.
- loggedIn: Set this to `true` or `false`. True for if the customer is not a guest, but should be logged in to an account. And false if the customer is a guest for this test.
- inclusiveTax: `"yes"` or `"no"` depending on if you want prices to be entered inclusive of tax in WooCommerce. This can be good to change depending on what you want to test for different calculation results. A product that costs 100 with taxes inclusive will always be 100. But with this set to no, the end price in the cart will be 125 if the tax amount is 25%.
- shippingInIframe: `"yes"` or `"no"`. For Klarna checkout we have the option to include shipping options in the iframe or not.
- products: An array of the names of the products you wish to add to the cart for the test.
- shippingMethod: The shipping method name/id that you wish to use for the test. Send an empty string ( `""` ) if you don't wish to add one.
- coupons: Same as products, but send in the coupon codes that you want to apply to the cart during checkout.
- customerType: `"person"` or `"company"`. Person for B2C tests, and Company for B2B tests.
- expectedTotal: A float value of the expected price that you expect the test to return. We will check the Klarna order to make sure this matches at the end.
- expectedOrderLines: A int of how many order lines you expect the test to return. We will check the Klarna order to make sure this matches at the end.

## Adding products, shipping methods and coupons to the environment.
Similarly to the tests above, we have a json file for all the products, coupons, shipping methods an more that the environment needs to run the tests. These can be expanded on in the same way as above.

### Products
````
{
	"id": null,
	"name": "Product name",
	"sku": "product-sku",
	"regular_price": "100.00",
	"virtual": bool,
	"downloadable": bool,
	"tax_class": "25"
}
````
- id: Always add this as null. This will be set automatically by our code later.
- name: The name of the product to be added.
- sku: The SKU of the product to be added.
- regular_price: The price of the product sent as a string but formated as a float.
- virtual: `true` or `false` based on if this is a virtual product or not.
- downloadable: `true` or `false` based on if this is a downloadable product or not.
- tax_class: The name of the tax class you wish to apply to the product. We currently have 25, 12, 06 and 00 who all add the tax rate similar to their names.

### Shipping methods
````
	{
		"name": "Sweden",
		"location": { "code": "SE", "type": "country" },
		"methods": [
			{ "method": "flat_rate", "amount": 49 },
			{ "method": "free_shipping", "amount": 0 }
		]
	}
````
- name: The name of the shipping zone.
- location: The location object for the shipping zone. The code is for the country/region/zip code that you want to add, and the type is either country, region or zipcode.
- methods: An array of methods to add to the shipping zone. Method is the id for the method type being added. And amount is how much the option should cost.

### Coupons
````
	{ 
		"code": "coupon_code",
		"amount": "10", 
		"discountType": "fixed_cart" 
	}
````
- code: The coupon code to be used.
- amount: The amount that the coupon should apply.
- discountType: The different discount types of coupons that WooCommerce offers. Check the WooCommerce documentation for the different types. But the most common are fixed_cart and percent