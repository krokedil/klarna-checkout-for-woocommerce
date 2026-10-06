# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

### `kco_customer_type_changed`

*Triggers when the customer changes the customer type in Kustom Checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$customer_type` | `string` | The new customer type, e.g. 'person' or 'organization'.

Examples: 
- [Customer type changed](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#customer-type-changed)

Source: [./classes/class-kco-ajax.php](../classes/class-kco-ajax.php), [line 296](../classes/class-kco-ajax.php#L296-L302)


---
### `kco_checkbox_changed`

*Triggers when a checkbox is changed in Kustom Checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$checkbox` | `array` | The checkbox data, with the keys 'key' (string) and 'checked' (bool).

Examples: 
- [Add a fee using a custom checkbox](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#add-a-fee-using-a-custom-checkbox)

Source: [./classes/class-kco-ajax.php](../classes/class-kco-ajax.php), [line 318](../classes/class-kco-ajax.php#L318-L330)


---
### `wc_klarna_push_cb`

*Fires when a push notification is received from Kustom, before the WooCommerce order is processed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$klarna_order_id` | `string` | The Kustom order ID.

Source: [./classes/class-kco-api-callbacks.php](../classes/class-kco-api-callbacks.php), [line 67](../classes/class-kco-api-callbacks.php#L67-L72)


---
### `kco_wc_payment_complete`

*Triggers after an accepted Kustom order has been completed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$klarna_order` | `array` | The Kustom order data.

Examples: 
- [Add additional checkboxes](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#add-additional-checkboxes)

Source: [./classes/class-kco-api-callbacks.php](../classes/class-kco-api-callbacks.php), [line 111](../classes/class-kco-api-callbacks.php#L111-L118)


---
### `kco_checkout_shipping_error`

*Fires when the shipping method was changed by WooCommerce during checkout, to throw a shipping error later in the process.*


Source: [./classes/class-kco-checkout.php](../classes/class-kco-checkout.php), [line 207](../classes/class-kco-checkout.php#L207-L210)


---
### `kco_wc_show_snippet`

*Fires before the Kustom Checkout iframe snippet is output.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$klarna_order` | `array` | The Kustom order data.

Source: [./includes/kco-functions.php](../includes/kco-functions.php), [line 111](../includes/kco-functions.php#L111-L116)


---
### `kco_wc_before_extra_fields`

*Fires before the extra checkout fields container.*


Source: [./includes/kco-functions.php](../includes/kco-functions.php), [line 198](../includes/kco-functions.php#L198-L201)


---
### `kco_wc_after_extra_fields`

*Fires after the extra checkout fields container.*


Source: [./includes/kco-functions.php](../includes/kco-functions.php), [line 206](../includes/kco-functions.php#L206-L209)


---
### `kco_wc_confirm_klarna_order`

*Triggers when a Kustom order is being confirmed, before it is acknowledged in Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$klarna_order` | `array` | The Kustom order data.

Source: [./includes/kco-functions.php](../includes/kco-functions.php), [line 650](../includes/kco-functions.php#L650-L656)


---
### `kco_wc_payment_complete`

*Triggers after an accepted Kustom order has been completed.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$klarna_order` | `array` | The Kustom order data.

Examples: 
- [Add additional checkboxes](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#add-additional-checkboxes)

Source: [./includes/kco-functions.php](../includes/kco-functions.php), [line 672](../includes/kco-functions.php#L672-L679)


---
### `kco_update_shipping_data`

*Triggers when the shipping data selected in Kustom Checkout is updated.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | The selected shipping option data from Kustom, including the currency.

Source: [./includes/kco-functions.php](../includes/kco-functions.php), [line 974](../includes/kco-functions.php#L974-L979)


---
### `kco_wc_before_wrapper`

*Fires before the Kustom Checkout wrapper.*


Source: [./templates/klarna-checkout.php](../templates/klarna-checkout.php), [line 23](../templates/klarna-checkout.php#L23-L26)


---
### `kco_wc_before_order_review`

*Fires before the order review in the Kustom Checkout page.*


Source: [./templates/klarna-checkout.php](../templates/klarna-checkout.php), [line 31](../templates/klarna-checkout.php#L31-L34)


---
### `kco_wc_after_order_review`

*Fires after the order review in the Kustom Checkout page.*


Source: [./templates/klarna-checkout.php](../templates/klarna-checkout.php), [line 42](../templates/klarna-checkout.php#L42-L45)


---
### `kco_wc_before_snippet`

*Fires before the Kustom Checkout iframe snippet.*


Source: [./templates/klarna-checkout.php](../templates/klarna-checkout.php), [line 51](../templates/klarna-checkout.php#L51-L54)


---
### `kco_wc_after_snippet`

*Fires after the Kustom Checkout iframe snippet.*


Source: [./templates/klarna-checkout.php](../templates/klarna-checkout.php), [line 58](../templates/klarna-checkout.php#L58-L61)


---
### `kco_wc_after_wrapper`

*Fires after the Kustom Checkout wrapper.*


Source: [./templates/klarna-checkout.php](../templates/klarna-checkout.php), [line 66](../templates/klarna-checkout.php#L66-L69)


---
### `kco_wc_process_payment`

*Triggers when a WooCommerce order is being processed for payment with Kustom Checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$klarna_order` | `array\|false` | The Kustom order data, or false if updating the Kustom order failed.

Source: [./src/ShippingAssistant/FreeOrders.php](../src/ShippingAssistant/FreeOrders.php), [line 94](../src/ShippingAssistant/FreeOrders.php#L94-L100)


---
### `kom_meta_action_options`

*Triggers when the action options are output in the order management metabox actions dropdown.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$klarna_order` | `object` | The Kustom order object.
`$actions` | `array` | The available order management actions, keyed by action (capture, cancel, sync, any) with boolean values.

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 208](../src/OrderManagement/MetaBox.php#L208-L215)


---
### `kom_meta_action_tips`

*Triggers when the help tip for the order management metabox actions is output.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$klarna_order` | `object` | The Kustom order object.
`$actions` | `array` | The available order management actions, keyed by action (capture, cancel, sync, any) with boolean values.

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 223](../src/OrderManagement/MetaBox.php#L223-L230)


---
### `kom_meta_no_actions`

*Triggers in the order management metabox when no manual order management actions are available.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$klarna_order` | `object` | The Kustom order object.
`$actions` | `array` | The available order management actions, keyed by action (capture, cancel, sync, any) with boolean values.

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 237](../src/OrderManagement/MetaBox.php#L237-L244)


---
### `kco_wc_process_payment`

*Triggers when a WooCommerce order is being processed for payment with Kustom Checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$klarna_order` | `array` | The Kustom order data.

Source: [./src/CheckoutFlow/EmbeddedBlockFlow.php](../src/CheckoutFlow/EmbeddedBlockFlow.php), [line 31](../src/CheckoutFlow/EmbeddedBlockFlow.php#L31-L37)


---
### `kco_wc_process_payment`

*Triggers when a WooCommerce order is being processed for payment with Kustom Checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$order_id` | `int` | The WooCommerce order ID.
`$klarna_order` | `array\|false` | The Kustom order data, or false if updating the Kustom order failed.

Source: [./src/CheckoutFlow/EmbeddedFlow.php](../src/CheckoutFlow/EmbeddedFlow.php), [line 31](../src/CheckoutFlow/EmbeddedFlow.php#L31-L37)


---
## Filters

### `kco_ajax_refresh_checkout`

*Filters whether the checkout should be refreshed after a checkbox is changed in Kustom Checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$refresh_checkout` | `bool` | Whether to refresh the checkout. Default false.

Source: [./classes/class-kco-ajax.php](../classes/class-kco-ajax.php), [line 332](../classes/class-kco-ajax.php#L332-L337)


---
### `kco_wc_credentials_from_session`

*Filters the Kustom API credentials used for the current request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$credentials` | `array` | The credentials, with the keys 'merchant_id' and 'shared_secret'.
`$testmode` | `string` | Whether test mode is enabled, 'yes' or 'no'.

Examples: 
- [Change the API keys on the fly](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#change-the-api-keys-on-the-fly)

Source: [./classes/class-kco-credentials.php](../classes/class-kco-credentials.php), [line 54](../classes/class-kco-credentials.php#L54-L61)


---
### `kco_wc_acknowledge_order`

*Filters the request arguments for a Kustom order management request, such as acknowledging an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/order-management/post/class-kco-request-acknowledge-order.php](../classes/requests/order-management/post/class-kco-request-acknowledge-order.php), [line 25](../classes/requests/order-management/post/class-kco-request-acknowledge-order.php#L25-L30)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/order-management/post/class-kco-request-acknowledge-order.php](../classes/requests/order-management/post/class-kco-request-acknowledge-order.php), [line 48](../classes/requests/order-management/post/class-kco-request-acknowledge-order.php#L48-L53)


---
### `kco_wc_get_order`

*Filters the request arguments for retrieving an order from Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/order-management/get/class-kco-request-get-order.php](../classes/requests/order-management/get/class-kco-request-get-order.php), [line 25](../classes/requests/order-management/get/class-kco-request-get-order.php#L25-L30)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/order-management/get/class-kco-request-get-order.php](../classes/requests/order-management/get/class-kco-request-get-order.php), [line 48](../classes/requests/order-management/get/class-kco-request-get-order.php#L48-L53)


---
### `kco_wc_acknowledge_order`

*Filters the request arguments for a Kustom order management request, such as acknowledging an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/order-management/patch/class-kco-request-upsell-order.php](../classes/requests/order-management/patch/class-kco-request-upsell-order.php), [line 27](../classes/requests/order-management/patch/class-kco-request-upsell-order.php#L27-L32)


---
### `kco_wc_api_request_args`

*Filters the request body sent to Kustom when creating or updating an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$body` | `array` | The request body.
`$order_id` | `int` | The WooCommerce order ID.
`$upsell_uuid` | `string` | The unique ID of the upsell request.

Examples: 
- [Modify order data sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#modify-order-data-sent-to-kustom)
- [Anonymize product names sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#anonymize-product-names-sent-to-kustom)
- [Set a forced purchase country](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#set-a-forced-purchase-country)
- [Only accept purchases from customer over 18 years of age](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#only-accept-purchases-from-customer-over-18-years-of-age)

Source: [./classes/requests/order-management/patch/class-kco-request-upsell-order.php](../classes/requests/order-management/patch/class-kco-request-upsell-order.php), [line 34](../classes/requests/order-management/patch/class-kco-request-upsell-order.php#L34-L45)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/order-management/patch/class-kco-request-upsell-order.php](../classes/requests/order-management/patch/class-kco-request-upsell-order.php), [line 83](../classes/requests/order-management/patch/class-kco-request-upsell-order.php#L83-L88)


---
### `kco_wc_acknowledge_order`

*Filters the request arguments for a Kustom order management request, such as acknowledging an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/order-management/patch/class-kco-request-set-merchant-reference.php](../classes/requests/order-management/patch/class-kco-request-set-merchant-reference.php), [line 26](../classes/requests/order-management/patch/class-kco-request-set-merchant-reference.php#L26-L31)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/order-management/patch/class-kco-request-set-merchant-reference.php](../classes/requests/order-management/patch/class-kco-request-set-merchant-reference.php), [line 64](../classes/requests/order-management/patch/class-kco-request-set-merchant-reference.php#L64-L69)


---
### `kco_wc_create_order`

*Filters the request arguments for creating a Kustom Checkout order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/checkout/post/class-kco-request-create.php](../classes/requests/checkout/post/class-kco-request-create.php), [line 27](../classes/requests/checkout/post/class-kco-request-create.php#L27-L32)


---
### `kco_locale`

*Filters the locale sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$locale` | `string` | The locale, derived from the WordPress locale (for example "en-US").

Examples: 
- [Change the locale sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#change-the-locale-sent-to-kustom)

Source: [./classes/requests/checkout/post/class-kco-request-create.php](../classes/requests/checkout/post/class-kco-request-create.php), [line 55](../classes/requests/checkout/post/class-kco-request-create.php#L55-L61)


---
### `kco_wc_api_request_args`

*Filters the request body sent to Kustom when creating or updating an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$body` | `array` | The request body.
`$order_id` | `int\|null` | The WooCommerce order ID, or null when no order exists yet.

Examples: 
- [Modify order data sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#modify-order-data-sent-to-kustom)
- [Anonymize product names sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#anonymize-product-names-sent-to-kustom)
- [Set a forced purchase country](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#set-a-forced-purchase-country)
- [Only accept purchases from customer over 18 years of age](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#only-accept-purchases-from-customer-over-18-years-of-age)

Source: [./classes/requests/checkout/post/class-kco-request-create.php](../classes/requests/checkout/post/class-kco-request-create.php), [line 387](../classes/requests/checkout/post/class-kco-request-create.php#L387-L397)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/checkout/post/class-kco-request-create.php](../classes/requests/checkout/post/class-kco-request-create.php), [line 399](../classes/requests/checkout/post/class-kco-request-create.php#L399-L404)


---
### `kco_wc_update_order`

*Filters the request arguments for updating a Kustom Checkout order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/checkout/post/class-kco-request-update.php](../classes/requests/checkout/post/class-kco-request-update.php), [line 27](../classes/requests/checkout/post/class-kco-request-update.php#L27-L32)


---
### `kco_locale`

*Filters the locale sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$locale` | `string` | The locale, derived from the WordPress locale (for example "en-US").

Examples: 
- [Change the locale sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#change-the-locale-sent-to-kustom)

Source: [./classes/requests/checkout/post/class-kco-request-update.php](../classes/requests/checkout/post/class-kco-request-update.php), [line 62](../classes/requests/checkout/post/class-kco-request-update.php#L62-L68)


---
### `kco_wc_api_request_args`

*Filters the request body sent to Kustom when creating or updating an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$body` | `array` | The request body.
`$order_id` | `int\|null` | The WooCommerce order ID, or null when no order exists yet.

Examples: 
- [Modify order data sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#modify-order-data-sent-to-kustom)
- [Anonymize product names sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#anonymize-product-names-sent-to-kustom)
- [Set a forced purchase country](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#set-a-forced-purchase-country)
- [Only accept purchases from customer over 18 years of age](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#only-accept-purchases-from-customer-over-18-years-of-age)

Source: [./classes/requests/checkout/post/class-kco-request-update.php](../classes/requests/checkout/post/class-kco-request-update.php), [line 140](../classes/requests/checkout/post/class-kco-request-update.php#L140-L150)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/checkout/post/class-kco-request-update.php](../classes/requests/checkout/post/class-kco-request-update.php), [line 152](../classes/requests/checkout/post/class-kco-request-update.php#L152-L157)


---
### `wc_klarna_checkout_create_hpp_args`

*Filters the request arguments for creating a Kustom Hosted Payment Page session.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/checkout/post/class-kco-request-create-hpp.php](../classes/requests/checkout/post/class-kco-request-create-hpp.php), [line 26](../classes/requests/checkout/post/class-kco-request-create-hpp.php#L26-L31)


---
### `kco_wc_api_hpp_request_args`

*Filters the request body sent to Kustom when creating a Hosted Payment Page session.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$body` | `array` | The request body.
`$order_id` | `int` | The WooCommerce order ID.
`$session_id` | `string` | The Kustom Checkout session ID.

Source: [./classes/requests/checkout/post/class-kco-request-create-hpp.php](../classes/requests/checkout/post/class-kco-request-create-hpp.php), [line 53](../classes/requests/checkout/post/class-kco-request-create-hpp.php#L53-L60)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/checkout/post/class-kco-request-create-hpp.php](../classes/requests/checkout/post/class-kco-request-create-hpp.php), [line 62](../classes/requests/checkout/post/class-kco-request-create-hpp.php#L62-L67)


---
### `kco_wc_create_recurring_order`

*Filters the request arguments for creating a recurring Kustom order from a customer token.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/checkout/post/class-kco-request-create-recurring.php](../classes/requests/checkout/post/class-kco-request-create-recurring.php), [line 26](../classes/requests/checkout/post/class-kco-request-create-recurring.php#L26-L31)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/checkout/post/class-kco-request-create-recurring.php](../classes/requests/checkout/post/class-kco-request-create-recurring.php), [line 95](../classes/requests/checkout/post/class-kco-request-create-recurring.php#L95-L100)


---
### `kco_wc_test_credentials`

*Filters the request arguments used to test the Kustom API credentials.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/checkout/post/class-kco-request-test-credentials.php](../classes/requests/checkout/post/class-kco-request-test-credentials.php), [line 27](../classes/requests/checkout/post/class-kco-request-test-credentials.php#L27-L32)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/checkout/post/class-kco-request-test-credentials.php](../classes/requests/checkout/post/class-kco-request-test-credentials.php), [line 80](../classes/requests/checkout/post/class-kco-request-test-credentials.php#L80-L85)


---
### `kco_wc_update_order`

*Filters the request arguments for updating a Kustom Checkout order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/checkout/post/class-kco-request-update-confirmation.php](../classes/requests/checkout/post/class-kco-request-update-confirmation.php), [line 27](../classes/requests/checkout/post/class-kco-request-update-confirmation.php#L27-L32)


---
### `kco_wc_api_request_args`

*Filters the request body sent to Kustom when creating or updating an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$body` | `array` | The request body.
`$order_id` | `int` | The WooCommerce order ID.

Examples: 
- [Modify order data sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#modify-order-data-sent-to-kustom)
- [Anonymize product names sent to Kustom](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#anonymize-product-names-sent-to-kustom)
- [Set a forced purchase country](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#set-a-forced-purchase-country)
- [Only accept purchases from customer over 18 years of age](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#only-accept-purchases-from-customer-over-18-years-of-age)

Source: [./classes/requests/checkout/post/class-kco-request-update-confirmation.php](../classes/requests/checkout/post/class-kco-request-update-confirmation.php), [line 94](../classes/requests/checkout/post/class-kco-request-update-confirmation.php#L94-L104)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/checkout/post/class-kco-request-update-confirmation.php](../classes/requests/checkout/post/class-kco-request-update-confirmation.php), [line 106](../classes/requests/checkout/post/class-kco-request-update-confirmation.php#L106-L111)


---
### `kco_wc_get_order`

*Filters the request arguments for retrieving an order from Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_args` | `array` | The request arguments passed to wp_remote_request().

Source: [./classes/requests/checkout/get/class-kco-request-retrieve.php](../classes/requests/checkout/get/class-kco-request-retrieve.php), [line 25](../classes/requests/checkout/get/class-kco-request-retrieve.php#L25-L30)


---
### `kco_wc_request_timeout`

*Filters the timeout, in seconds, for requests to the Kustom API.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./classes/requests/checkout/get/class-kco-request-retrieve.php](../classes/requests/checkout/get/class-kco-request-retrieve.php), [line 48](../classes/requests/checkout/get/class-kco-request-retrieve.php#L48-L53)


---
### `kco_wc_merchant_urls`

*Filters the merchant URLs sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$merchant_urls` | `array` | The merchant URLs, keyed by terms, checkout, confirmation and push.

Source: [./classes/requests/helpers/class-kco-merchant-urls.php](../classes/requests/helpers/class-kco-merchant-urls.php), [line 33](../classes/requests/helpers/class-kco-merchant-urls.php#L33-L38)


---
### `kco_wc_terms_url`

*Filters the terms and conditions URL sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$terms_url` | `string\|false` | The terms and conditions page URL, or false if it could not be found.

Source: [./classes/requests/helpers/class-kco-merchant-urls.php](../classes/requests/helpers/class-kco-merchant-urls.php), [line 51](../classes/requests/helpers/class-kco-merchant-urls.php#L51-L56)


---
### `kco_wc_checkout_url`

*Filters the checkout URL sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$checkout_url` | `string` | The checkout page URL.

Source: [./classes/requests/helpers/class-kco-merchant-urls.php](../classes/requests/helpers/class-kco-merchant-urls.php), [line 68](../classes/requests/helpers/class-kco-merchant-urls.php#L68-L73)


---
### `kco_wc_confirmation_url`

*Filters the confirmation URL sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$confirmation_url` | `string` | The URL the customer is sent to after completing the purchase.

Source: [./classes/requests/helpers/class-kco-merchant-urls.php](../classes/requests/helpers/class-kco-merchant-urls.php), [line 105](../classes/requests/helpers/class-kco-merchant-urls.php#L105-L110)


---
### `kco_wc_push_url`

*Filters the push notification URL sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$push_url` | `string` | The URL Kustom calls to notify the store that an order has been placed.

Source: [./classes/requests/helpers/class-kco-merchant-urls.php](../classes/requests/helpers/class-kco-merchant-urls.php), [line 130](../classes/requests/helpers/class-kco-merchant-urls.php#L130-L135)


---
### `kco_wc_address_update_url`

*Filters the address update callback URL sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$address_update_url` | `string` | The URL Kustom calls when the customer changes their address.

Source: [./classes/requests/helpers/class-kco-merchant-urls.php](../classes/requests/helpers/class-kco-merchant-urls.php), [line 151](../classes/requests/helpers/class-kco-merchant-urls.php#L151-L156)


---
### `kco_wc_country_change_url`

*Filters the country change callback URL sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$country_change_url` | `string` | The URL Kustom calls when the customer changes their purchase country.

Source: [./classes/requests/helpers/class-kco-merchant-urls.php](../classes/requests/helpers/class-kco-merchant-urls.php), [line 171](../classes/requests/helpers/class-kco-merchant-urls.php#L171-L176)


---
### `kco_wc_shipping_options`

*Filters the shipping options sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shipping_options` | `array` | The shipping options, formatted for Kustom.

Source: [./classes/requests/helpers/class-kco-request-shipping-options.php](../classes/requests/helpers/class-kco-request-shipping-options.php), [line 87](../classes/requests/helpers/class-kco-request-shipping-options.php#L87-L92)


---
### `kco_additional_checkboxes`

*Filters the additional checkboxes displayed in Kustom Checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$additional_checkboxes` | `array` | The additional checkboxes, each an array with id, text, checked and required keys.

Examples: 
- [Add additional checkboxes](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#add-additional-checkboxes)

Source: [./classes/requests/helpers/class-kco-request-options.php](../classes/requests/helpers/class-kco-request-options.php), [line 259](../classes/requests/helpers/class-kco-request-options.php#L259-L265)


---
### `kco_wc_surcharge_name`

*Filters the name of the surcharge order line added when the order lines do not match the cart total.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$name` | `string` | The order line name. Default 'Surcharge'.

Source: [./classes/requests/helpers/class-kco-request-cart.php](../classes/requests/helpers/class-kco-request-cart.php), [line 166](../classes/requests/helpers/class-kco-request-cart.php#L166-L171)


---
### `kco_wc_cart_line_item`

*Filters a cart item order line before it is added to the Kustom order lines.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$klarna_item` | `array` | The order line formatted for Kustom. Return a falsy value to exclude it.
`$cart_item` | `array` | The WooCommerce cart item.

Source: [./classes/requests/helpers/class-kco-request-cart.php](../classes/requests/helpers/class-kco-request-cart.php), [line 262](../classes/requests/helpers/class-kco-request-cart.php#L262-L268)


---
### `kco_wc_api_callbacks_push_klarna_order`

*Filters the Kustom order data retrieved during the push notification callback.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$klarna_order` | `array\|false` | The Kustom order data from the order management API, or false on failure.

Source: [./classes/class-kco-api-callbacks.php](../classes/class-kco-api-callbacks.php), [line 85](../classes/class-kco-api-callbacks.php#L85-L93)


---
### `kco_enable_redirected_flow`

*Filters whether to enable the redirected checkout flow setting.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$enable_redirected_flow` | `bool` | Whether to enable the redirected checkout flow. Default false.

Source: [./classes/class-kco-fields.php](../classes/class-kco-fields.php), [line 317](../classes/class-kco-fields.php#L317-L322)


---
### `kco_wc_gateway_settings`

*Filters the Kustom Checkout gateway settings fields.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$settings` | `array` | The settings fields.

Source: [./classes/class-kco-fields.php](../classes/class-kco-fields.php), [line 374](../classes/class-kco-fields.php#L374-L379)


---
### `kco_wc_supports`

*Filters the features supported by the Kustom Checkout gateway.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$supports` | `string[]` | The supported features.

Source: [./classes/class-kco-gateway.php](../classes/class-kco-gateway.php), [line 54](../classes/class-kco-gateway.php#L54-L74)


---
### `wc_klarna_checkout_icon_html`

*Filters the HTML for the Kustom Checkout payment method icon.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$icon_html` | `string` | The icon HTML.

Examples: 
- [Modify payment method icon](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#modify-payment-method-icon)

Source: [./classes/class-kco-gateway.php](../classes/class-kco-gateway.php), [line 245](../classes/class-kco-gateway.php#L245-L251)


---
### `wc_klarna_checkout_process_refund`

*Filters the result of a refund processed through the Kustom Checkout gateway.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$result` | `bool` | Whether the refund was successful. Default false.
`$order_id` | `int` | The WooCommerce order ID.
`$amount` | `float\|null` | The refund amount.
`$reason` | `string` | The reason for the refund.

Source: [./classes/class-kco-gateway.php](../classes/class-kco-gateway.php), [line 275](../classes/class-kco-gateway.php#L275-L283)


---
### `kco_checkout_timeout_duration`

*Filters the timeout duration in seconds for the Kustom Checkout order submission.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout_time` | `int` | The timeout duration in seconds. Default 20.

Source: [./classes/class-kco-gateway.php](../classes/class-kco-gateway.php), [line 421](../classes/class-kco-gateway.php#L421-L426)


---
### `kco_ignored_checkout_fields`

*Filters the checkout field names that are ignored by Kustom Checkout, since Kustom collects the data in the iframe.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$fields` | `string[]` | The ignored checkout field names.

Source: [./classes/class-kco-gateway.php](../classes/class-kco-gateway.php), [line 428](../classes/class-kco-gateway.php#L428-L433)


---
### `kco_check_if_needs_payment`

*Filters whether Kustom Checkout should only be used when the cart or order needs payment.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$check_if_needs_payment` | `bool` | Whether to check if payment is needed. Default true.

Examples: 
- [Display Kustom Checkout even on free orders](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#display-kustom-checkout-even-on-free-orders)

Source: [./classes/class-kco-templates.php](../classes/class-kco-templates.php), [line 87](../classes/class-kco-templates.php#L87-L93)


---
### `kco_locate_checkout_template`

*Filters the path to the Kustom Checkout template.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$template` | `string` | The template path.
`$template_name` | `string` | The name of the WooCommerce template being overridden.

Source: [./classes/class-kco-templates.php](../classes/class-kco-templates.php), [line 112](../classes/class-kco-templates.php#L112-L118)


---
### `kco_check_if_needs_payment`

*Filters whether Kustom Checkout should only be used when the cart or order needs payment.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$check_if_needs_payment` | `bool` | Whether to check if payment is needed. Default true.

Examples: 
- [Display Kustom Checkout even on free orders](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#display-kustom-checkout-even-on-free-orders)

Source: [./classes/class-kco-checkout.php](../classes/class-kco-checkout.php), [line 129](../classes/class-kco-checkout.php#L129-L135)


---
### `kco_shipping_auto_correct`

*Filters whether to automatically correct the shipping method to the customer's chosen method instead of throwing a shipping error.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$auto_correct` | `bool` | Whether to auto-correct the shipping method. Default false.
`$default` | `string` | The shipping method ID that WooCommerce would set as the default.
`$rates` | `array` | The shipping rates calculated when getting the default method.
`$chosen_method` | `string` | The shipping method ID chosen by the customer.

Source: [./classes/class-kco-checkout.php](../classes/class-kco-checkout.php), [line 193](../classes/class-kco-checkout.php#L193-L201)


---
### `kco_check_if_needs_payment`

*Filters whether Kustom Checkout should only be used when the cart or order needs payment.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$check_if_needs_payment` | `bool` | Whether to check if payment is needed. Default true.

Examples: 
- [Display Kustom Checkout even on free orders](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#display-kustom-checkout-even-on-free-orders)

Source: [./classes/class-kco-checkout.php](../classes/class-kco-checkout.php), [line 255](../classes/class-kco-checkout.php#L255-L261)


---
### `kco_check_if_needs_payment`

*Filters whether Kustom Checkout should only be used when the cart or order needs payment.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$check_if_needs_payment` | `bool` | Whether to check if payment is needed. Default true.

Examples: 
- [Display Kustom Checkout even on free orders](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#display-kustom-checkout-even-on-free-orders)

Source: [./classes/class-kco-checkout.php](../classes/class-kco-checkout.php), [line 277](../classes/class-kco-checkout.php#L277-L283)


---
### `kco_wc_lock_confirmation`

*Filters whether to lock the order confirmation to prevent simultaneous confirmations of the same order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$lock` | `bool` | Whether to lock the confirmation. Default false.
`$klarna_order_id` | `string` | The Kustom order ID.
`$order_id` | `int` | The WooCommerce order ID.

Examples: 
- [Prevent duplicate order confirmations](https://docs.krokedil.com/kustom-checkout-for-woocommerce/customization/hooks-action-filter/#prevent-duplicate-order-confirmations)

Source: [./includes/kco-functions.php](../includes/kco-functions.php), [line 614](../includes/kco-functions.php#L614-L622)


---
### `kco_wc_chosen_shipping_method`

*Filters the chosen shipping methods set from the shipping option selected in Kustom Checkout.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$chosen_shipping_methods` | `string[]` | The chosen shipping method IDs.

Source: [./includes/kco-functions.php](../includes/kco-functions.php), [line 987](../includes/kco-functions.php#L987-L992)


---
### `kco_wc_get_order_by_klarna_id_args`

*Filters the query args used to look up a WooCommerce order by its Kustom order id.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$args` | `array` | The wc_get_orders() args.
`$klarna_order_id` | `string` | The Kustom order id being looked up.
`$date_after` | `string\|null` | Optional date lower bound, if provided.

Source: [./includes/kco-functions.php](../includes/kco-functions.php), [line 1063](../includes/kco-functions.php#L1063-L1070)


---
### `kco_elements_locale`

*Filters the locale used for Kustom Elements.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$locale` | `string` | The 5-character locale (language-COUNTRY), derived from the WordPress locale.

Source: [./src/Elements/Utility.php](../src/Elements/Utility.php), [line 35](../src/Elements/Utility.php#L35-L40)


---
### `kco_elements_show_everywhere`

*Filters whether the Kustom Elements should be considered active on every request.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$show_everywhere` | `bool` | Whether to load Kustom Elements on every request. Default false.

Source: [./src/Elements/Elements.php](../src/Elements/Elements.php), [line 132](../src/Elements/Elements.php#L132-L137)


---
### `kco_elements_script_src`

*Filters the source URL of the Kustom Elements SDK script.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$default_src` | `string` | The default script URL for the current environment.
`$testmode` | `bool` | Whether test mode is enabled.

Source: [./src/Elements/Elements.php](../src/Elements/Elements.php), [line 183](../src/Elements/Elements.php#L183-L189)


---
### `klarna_kss_shipping_method_add_rate`

*Filters the shipping rate from the Kustom Shipping Assistant before it is added to the shipping method.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$rate` | `array` | The shipping rate arguments (id, label, cost and optional meta_data). Empty if no shipping data is available.

Source: [./src/ShippingAssistant/ShippingMethod.php](../src/ShippingAssistant/ShippingMethod.php), [line 136](../src/ShippingAssistant/ShippingMethod.php#L136-L141)


---
### `kom_allowed_update_statuses`

*Filters the WooCommerce order statuses in which order updates are synced to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$allowed_statuses` | `string[]` | The allowed order statuses, without the "wc-" prefix. Default array( 'on-hold' ).

Source: [./src/OrderManagement/OrderManagement.php](../src/OrderManagement/OrderManagement.php), [line 345](../src/OrderManagement/OrderManagement.php#L345-L350)


---
### `klarna_applied_return_fees`

*Filters the return fees applied to the refund, used when writing the refund order note.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$applied_return_fees` | `array` | The applied return fee data, with 'amount' and 'tax_amount' keys. Default empty array.

Source: [./src/OrderManagement/OrderManagement.php](../src/OrderManagement/OrderManagement.php), [line 600](../src/OrderManagement/OrderManagement.php#L600-L605)


---
### `kom_meta_environment`

*Filters the Kustom environment shown in the order management metabox.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$environment` | `string` | The Kustom environment the order was placed in ('test' or 'live'), or an empty string.

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 144](../src/OrderManagement/MetaBox.php#L144-L149)


---
### `kom_meta_order_status`

*Filters the Kustom order status shown in the order management metabox.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$status` | `string` | The Kustom order status.

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 152](../src/OrderManagement/MetaBox.php#L152-L157)


---
### `kom_meta_payment_method`

*Filters the initial payment method shown in the order management metabox.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$payment_method` | `string` | The description of the initial payment method of the Kustom order.

Source: [./src/OrderManagement/MetaBox.php](../src/OrderManagement/MetaBox.php), [line 160](../src/OrderManagement/MetaBox.php#L160-L165)


---
### `kom_skip_scheduled_actions`

*Filters whether to skip displaying the scheduled actions for the order in the order management metabox.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$skip` | `bool` | Whether to skip displaying the scheduled actions. Default false.

Source: [./src/OrderManagement/ScheduledActions.php](../src/OrderManagement/ScheduledActions.php), [line 47](../src/OrderManagement/ScheduledActions.php#L47-L52)


---
### `kom_line_item_product_type`

*Filters the Kustom product type for a refunded item whose product no longer exists in WooCommerce.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$type` | `string` | The Kustom order line type. Default 'physical'.
`$item` | `\WC_Order_Item_Product` | The refunded order item.

Source: [./src/OrderManagement/Request/Post/RequestPostRefund.php](../src/OrderManagement/Request/Post/RequestPostRefund.php), [line 154](../src/OrderManagement/Request/Post/RequestPostRefund.php#L154-L160)


---
### `kom_refund_order_args`

*Filters the request body sent to Kustom when refunding an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | The refund request body.
`$order_id` | `int` | The WooCommerce order ID.

Source: [./src/OrderManagement/Request/Post/RequestPostRefund.php](../src/OrderManagement/Request/Post/RequestPostRefund.php), [line 282](../src/OrderManagement/Request/Post/RequestPostRefund.php#L282-L288)


---
### `kom_order_capture_args`

*Filters the request body sent to Kustom when capturing an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | The capture request body.
`$order_id` | `int` | The WooCommerce order ID.

Source: [./src/OrderManagement/Request/Post/RequestPostCapture.php](../src/OrderManagement/Request/Post/RequestPostCapture.php), [line 77](../src/OrderManagement/Request/Post/RequestPostCapture.php#L77-L83)


---
### `kom_request_timeout`

*Filters the timeout in seconds for order management requests to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$timeout` | `int` | The request timeout in seconds. Default 10.

Source: [./src/OrderManagement/Request/Request.php](../src/OrderManagement/Request/Request.php), [line 278](../src/OrderManagement/Request/Request.php#L278-L283)


---
### `kom_order_update_args`

*Filters the request body sent to Kustom when updating the order lines of an order.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$data` | `array` | The order lines request body.
`$order_id` | `int` | The WooCommerce order ID.

Source: [./src/OrderManagement/Request/Patch/RequestPatchUpdate.php](../src/OrderManagement/Request/Patch/RequestPatchUpdate.php), [line 44](../src/OrderManagement/Request/Patch/RequestPatchUpdate.php#L44-L50)


---
### `kom_wc_order_line_item`

*Filters the Kustom order line for a WooCommerce order item product. Return a falsy value to exclude the line.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$klarna_item` | `array` | The Kustom order line data.
`$order_item` | `\WC_Order_Item_Product` | The WooCommerce order item.

Source: [./src/OrderManagement/OrderLines.php](../src/OrderManagement/OrderLines.php), [line 130](../src/OrderManagement/OrderLines.php#L130-L136)


---
### `klarna_pw_gift_card_sku`

*Filters the reference (SKU) used for PW WooCommerce Gift Cards order lines sent to Kustom.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$sku` | `string` | The gift card reference. Default 'gift_card'.
`$code` | `string` | The gift card number.

Source: [./src/OrderManagement/OrderLines.php](../src/OrderManagement/OrderLines.php), [line 180](../src/OrderManagement/OrderLines.php#L180-L186)


---


