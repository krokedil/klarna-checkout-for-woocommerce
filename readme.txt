=== Klarna Checkout for WooCommerce ===
Contributors: klarna, krokedil, automattic
Tags: woocommerce, klarna, ecommerce, e-commerce, checkout
Donate link: https://klarna.com
Requires at least: 4.0
Tested up to: 5.0.3
Requires PHP: 5.6
WC requires at least: 3.0.0
WC tested up to: 3.5.4
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== DESCRIPTION ==

*A full checkout experience embedded on your site with Pay Now, Pay Later and Slice It. No credit card numbers, no passwords, no worries.*

https://www.youtube.com/watch?v=XayUzOUkyDQ

Our complete checkout is a seamless and mobile optimized solution that delivers a best-in-class user experience that comes with all our payment methods. It also identifies the customer and enables one-click repeat purchases across Klarna’s merchant network, resulting in increased average order value, conversions, and loyalty.

This official Klarna extension also makes it easy for you to handle orders in WooCommerce after a purchase is complete. With a single click of a button, you can activate, update, refund and cancel orders directly from WooCommerce without logging into the Klarna administration.

=== Pay Now (direct payments) ===
Customers who want to pay in full at checkout can do it quickly and securely with a credit/debit card. Friction-free direct purchases while maximising the value for your business thanks to guaranteed payments. If they have a Klarna account they can save their details and enjoy one-click purchases from then on.

=== Pay later (invoice) ===
Try it first, pay it later. Delayed payments for customers who like low friction purchases and to pay after delivery.

=== Slice it (installments) ===
Installment, revolving and other flexible financing plans let customers pay when they can and when they want.

=== How to Get Started ===
* [Sign up for Klarna](https://www.klarna.com/international/business/woocommerce/).
* [Install the plugin](https://wordpress.org/plugins/klarna-checkout-for-woocommerce/) on your site. During this process you will be asked to download [Klarna Order Management](https://wordpress.org/plugins/klarna-order-management-for-woocommerce/) so you can handle orders in Klarna directly from WooCommerce.
* Get your store approved by Klarna, and start selling.

=== What's the difference between Klarna Checkout and Klarna Payments? ===
Klarna as your single payment provider keeps everything under one roof. You’ll have one agreement, one point of contact, one settlement file, one payout with __Klarna Checkout__. It only takes a single integration to deliver the full Klarna hosted checkout experience through a widget placed on your site.

__Klarna Payments__ removes the headaches of payments, for both consumers and merchants. Complement your checkout with a Klarna hosted widget located in your existing checkout which offers payment options for customers with a smooth user experience.


== Installation ==
1. Upload plugin folder to to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go WooCommerce Settings –> Payment Gateways and configure your Klarna Checkout settings.
4. Read more about the configuration process in the [plugin documentation](https://docs.woocommerce.com/document/klarna-checkout/).


== Frequently Asked Questions ==
= Which countries does this payment gateway support? =
Klarna Checkout works for merchants in Sweden, Finland, Norway, Germany, Austria, the Netherlands, UK and United States.

= Where can I find Klarna Checkout for WooCommerce documentation? =
For help setting up and configuring Klarna Payments for WooCommerce please refer to our [documentation](https://docs.woocommerce.com/document/klarna-checkout/).

= Are there any specific requirements? =
* WooCommerce 3.0 or newer is required.
* PHP 5.6 or higher is required.
* A SSL Certificate is required.
* This plugin integrates with Klarnas V3 platform. You need an agreement with Klarna specific to the V3 platform to use this plugin.

== Changelog ==

= 2019.02.13  	- version 1.8.4 =
* Tweak         - Added WC hooks woocommerce_checkout_create_order & woocommerce_checkout_update_order_meta in backup order creation. Better support for Sequential order numbers plugin (props @jonathan-dejong). 
* Tweak         - Added billing_state, billing_country, shipping_state & shipping_country to standard chekout fields to exclude from extra checkout fields control.
* Tweak         - Don't display shipping on checkout page until customer address has been entered if WC setting "Hide shipping costs until an address is entered" is active.
* Tweak         - Only unrequire checkout fields on KCO confirmation page.
* Fix           - Bug fix in get_order(). Could cause issues with purchases being placed in Klarna but checkout displayed error notice in Woo.
* Fix           - Fix for populating Billing email in setFieldValues.

= 2019.02.08  	- version 1.8.3 =
* Fix           - Fixed out of stock problem in validation callback.
* Fix           - Fixed issue where customer couldn't checkout if Klarna order ID session had expired.

= 2019.02.06  	- version 1.8.2 =
* Tweak         - Added action hooks kco_wc_before_extra_fields & kco_wc_after_extra_fields in kco_wc_show_extra_fields() function.
* Fix           - Fixed totals match issue in validation callback.
* Fix           - Fixed valid shipping check in validation callback. General error message was returned instead of specific shipping message.
* Fix           - Improved handling of communication errors in get_order() function (used to create and display KCO iframe).
* Fix           - Look for digital cart item type in process_cart() in API callback class. Digital item type was added in cart data sent to Klarna in v1.8.0.

= 2019.01.25  	- version 1.8.1 =
* Tweak         - Removed WooCommerce Germanized specific code from plugin. Use following gist instead: https://gist.github.com/krokedilgists/7fab7cf5d6a7b3c52fdd6bbc641592d0
* Fix           - Added customer order comment to extra checkout fields handling.
* Fix           - Fixes issue with customer country not being set & calculated correctly on initial checkout page rendering.
* Fix           - Exclude payment_method from extra checkout field handling. Could cause wrong payment method being set in order.

= 2019.01.24  	- version 1.8.0 =
* Feature       - Improved backup order creation (WC order created on API callback). Possibility to add coupons, item meta data etc to the order in a more correct way.
* Tweak         - Store extra checkout fields data as session. Removed use of transients.
* Tweak         - Improved extra checkout field validation. Better support for select and radio fields.
* Tweak         - Make it possible to retrieve WC()->cart/session data in push and validation notification callback.
* Tweak         - First introduction of 0 decimals support.
* Tweak         - Add billing & shipping country to hidden form fields in checkout. Adds support for switching currency based on country in Aelia currency switcher plugin.
* Tweak         - Merged request_pre_get_order & request_pre_retrieve_order into one function. Now uses only request_pre_get_order.
* Tweak         - Tweak in how we return response in request_pre_get_order.
* Tweak         - Use totals_match instead of cart_hash_validation in validate callback.
* Fix           - Check for is_wp_error in several KCO request. To avoid php errors if request fails.
* Fix           - Added default message and redirect url if validation callback check fail.
* Fix           - Updated shipping controll in validate callback. Fixes issue with only digital/virtual orders.
* Fix           - Fixed external payment methods error.
* Fix           - Only enqueue front end scripts if plugin is enabled in Woo settings.

= 2019.01.02  	- version 1.7.9 =
* Fix			- If checkout registration is disabled and not logged in, the user cannot checkout.
* Fix			- Fixed issue with krokedil_log_events where logs got saved in wrong order id.
* Fix			- Redirect customer to cart page if KCO id session is missing in shipping address change ajax function.
* Fix			- Check that we have KCO id session before trying to retreive Klarna order.
* Fix			- Improved handling of errors in request_post_get_order.
* Fix			- Check that we have a valid Klarna order before creating a Woo order in backup_order_creation.

= 2018.12.18  	- version 1.7.8 =
* Tweak			- Improved error messaging if KCO order request fails.
* Fix			- Check if Klarna order ID exist in Woo order before starting checkout_error order creation. To avoid double order creation.
* Fix			- Don't try to update Klarna order if kco payment method isn't selected.
* Fix			- Don't try to add shipping to virtual subscription renewal orders.
* Fix			- Improved locale check for better compatibility with Polylang & WPML.

= 2018.12.10  	- version 1.7.7 =
* Tweak			- Moved from replacing Woo order review HTML to calling update_checkout event after ajax kco_wc_iframe_shipping_address_change har run.
* Fix			- Reset order lines before collecting and sending them to Klarna. In rare cases order lines was added twice.
* Fix			- Added order review blocker to updateKlarnaOrder call (to avoid changes in WooCommerce cart during Klarna order update).
* Fix 			- Don’t try to update Klarna order if the $klarna_order_id is missing in update Klarna order function.
* Fix			- Don’t try to update Klarna order if we’re on confirmation page.
* Fix			- Changed update Klarna order function to handle status checkout_complete scenarios.
* Fix			- Get $klarna_order_id from url instead of session in confirmation page.
* Fix			- Don’t rely on session when saving kco_order_id to WC order.
* Fix			- Improved way of fetching Klarna order in process_payment.
* Fix			- Submit form with name ”checkout” instead of form with class name woocommerce-checkout (to avoid theme template compatibility issues).

= 2018.11.27  	- version 1.7.6 =
* Tweak			- Added check to see if customer have an account and needs to login before purchase with KCO can be completed.
* Tweak			- Move sending of is_user_logged_in from subscription class to regular api class.
* Tweak			- Plugin WordPress 5.0 compatible.
* Fix			- Shipping tax rate calculation bug fix.
* Fix			- Redirect customer to cart page if an error occurs during update of Klarna order in checkout page.

= 2018.11.21  	- version 1.7.5 =
* Tweak			- Change Please wait while we process your order text to be displayed as a modal popup.
* Fix			- Correct calculation of state based tax (for US) in shipping address change event.
* Fix			- Improved calculation of shipping tax rates with decimals sent to Klarna.
* Fix			- Added email and tel input fields as supported field types to extra checkout field handler.
* Fix			- Updated feature for avoiding double orders (sessionStorage function) to prevent infinite reloading of checkout page.
* Fix			- Only run checkout error fallback if it is triggered from the KCO confirmation page.

= 2018.11.12  	- version 1.7.4 =
* Tweak			- Improved handling of order in WooCommerce. Payment now finalized during process_payment. Plugin now allows custom thankyou page.
* Tweak			- Add prefix kco_wc_order_id_ to transient name used during purchase.
* Tweak			- Order creation caused by checkout_error now sets order status to On hold.
* Fix			- Add support for cart_hash control to avoid mismatch in order totals between Klarna & Woo.
* Fix			- Changed Klarna country stored in Woo order to prevent issue with Klarna Global.
* Fix			- Deletes kco_wc_order_id_ transient on order received page.
* Fix			- Added checks to prevent duplicate orders.
* Fix			- Added check to ensure hash sign is added to hexcode sent to Klarna (for color display of KCO).
* Fix			- Save Klarna order id in Woo order on woocommerce_checkout_order_processed (previously only saved on thankyou page).
* Fix			- Improved error handling/display when request to Klarna returns in wp_error.
* Fix			- PHP notice fixes.

= 2018.10.19  	- version 1.7.3 =
* Fix			- Fixed issue with no_shipping error on free trial subscriptions.
* Fix			- json_decode fix to avoid crash when using more than 1 coupon code (props @johanholm).
* Fix			- Fixed tax rate for zero tax shipping and fee in order lines sent to Klarna for recurring orders.

= 2018.10.12  	- version 1.7.2 =
* Fix			- Fixed error when using coupons.

= 2018.10.10  	- version 1.7.1 =
* Tweak			- Logging improvements.
* Fix			- Don't save KCO html_snippet in logs.
* Fix			- Don't let existing customers that isn't logged in finalize subscription purchase.

= 2018.10.09  	- version 1.7.0 =
* Feature		- Added support for recurring payments via WooCommerce Subscriptions (in SE, NO, FI, DE & AT).
* Feature		- Fetch and save customer email address in Woo on shipping address change event. Adds support for better compatibility with abandoned cart plugins.
* Tweak			- Fetch and save customer state in Woo on shipping address change event.
* Tweak			- Added admin notice if Autoptimize plugin is used and "Optimize shop cart/checkout" setting is on.
* Tweak			- Removed customer address setters used before WC 3.0.0.
* Tweak			- Added order note if order is created via API callback (Klarnas push notification).


= 2018.09.20  	- version 1.6.1 =
* Fix	        - Added no as possible Norwegian locale code.
* Fix			- Added bussiness name from B2B purchase to WooCommerce order.

= 2018.09.10  	- version 1.6.0 =
* Feature		- Added support for YITH WooCommerce Gift Cards.
* Enhancement	- Added validation of coupons to validation callback.
* Enhancement	- Don’t use KCO template file if cart doesn’t need payment.
* Tweak			- Logging improvements.
* Fix			- Add class "processing" to form after trigger submit to avoid checkout form from being triggered twice.

= 2018.08.08  	- version 1.5.5 =
* Tweak			- Send url to single product image to Klarna (instead of tumbnail size).
* Tweak			- Added Woo account settings check in Admin Notices class.
* Fix			- Only check required empty form fields that are non standard checkout fields in validation callback.
* Fix			- Improved logging. Removes html snippet in thank you page log message (to avoid "Oops"-message in edit order view).
* Fix			- Prevent duplicate orders if KCO confirmation page is reloaded manually by customer during create Woo order process.

= 2018.07.23  	- version 1.5.4 =
* Enhancement	- Added Klarna LEAP functionality (URL's for new customer signup & onboarding).
* Fix			- Change fees to be sent to Klarna as surcharge.
* Fix			- Maybe define constants WOOCOMMERCE_CHECKOUT & WOOCOMMERCE_CART in ajax functions. Fix compat issue with https://woocommerce.com/products/payment-gateway-based-fees/. 
* Fix			- Updated krokedil-logger with json parse error fix (that could be triggered on signel order page).

= 2018.07.16  	- version 1.5.3 =
* Fix			- Fixed issue where seperate shipping address was not saved to order.
* Fix           - Limited preventDefault function on the checkout page. Now only prevents default on quantity field.
* Fix           - Added check for a setting to prevent error.
* Enhancement   - Added support for WPML with ICL_LANGUAGE_CODE in switch case.

= 2018.05.29  	- version 1.5.2 =
* Fix			- Fixed error in get_purchase_locale() (caused checkout to be rendered in English even if local lang was used in store).

= 2018.05.25  	- version 1.5.1 =
* Fix			- Fixed a check on a definition.
* Fix			- Fixed minor spelling error in privacy policy text.
* Fix			- Prevent default on customer pressing enter on checkout page to prevent accidental order submit.

= 2018.05.24  	- version 1.5.0 =
* Feature		- Added support for validation of required WooCommerce checkout fields displayed in kco_wc_show_extra_fields().
* Feature		- Added support for wp_add_privacy_policy_content (for GDPR compliance). More info: https://core.trac.wordpress.org/attachment/ticket/43473/PRIVACY-POLICY-CONTENT-HOOK.md.
* Feature		- Added setting for displaying privacy policy checkout text (above or below KCO iframe).
* Feature		- Possibility to add terms checkbox inside KCO iframe via plugin settings (GDPR compliance for some companies).
* Tweak			- Changed what we base purchase locale on. Adds better support for WPML compatibility.
* Tweak			- Added support for handling cart with virtual products in validation callback (check if order needs shipping).
* Tweak			- Added Klarna icon next to payment method title in regular checkout page.
* Fix			- Fixed issue in validation callback logic (where purchase could be finalized without a valid shipping method).

= 2018.04.27  	- version 1.4.0 =
* Feature       - Added facllback order creation if checkout form submission fails.
* Tweak         - Acknowledge Klarna order and set WC order to Processing in thankyou page if possible.
* Tweak         - Improved UI in settings page.
* Tweak         - Improved logging.
* Tweak         - Added error handling in 405 response from Klarna.
* Tweak         - Updated Krokedil logger.
* Tweak         - Change standard log event type to INFO (previously ERROR).
* Tweak         - Function for hiding Klarna banner (displayed when in test mode).
* Tweak         - Added PHP version to user agent sent in orders to Klarna.

= 2018.03.29  	- version 1.3.0 =
* Update        - Adds Krokedil logger class.
* Update        - Adds status report on Woocommerce status page.
* Enhancement   - Adds verify_national_identification_number alongside with national_identification_number_mandatory setting in order data sent to Klarna.
* Enhancement   - Improved order note for orders created via API callback.
* Enhancement   - Improved messaging in order note when order totals doesn’t match.
* Enhancement   - Display admin notice if https isn’t enabled.
* Fix           - Spelling fix in banner.

= 2018.03.14  	- version 1.2.6 =
* Fix           - Fixes how product name is fetched for Klarna.
* Update        - Adds new mandatory PNO field.
* Update        - Adds dashboard banners and Klarna information.
* Update        - Adds exception error code to logger, in addition to error message.
* Update        - Changes CSS selector from table to generic class for cart widget.

= 2018.02.26  	- version 1.2.5 =
* Feature       - Allows Klarna Checkout to be overwritten from the theme.
* Fix           - Keeps extra checkout fields values on checkout page reload.
* Enhancement   - Cleans up template files.
* Enhancement   - Adds WC required and tested up to data to main plugin file.
* Enhancement   - Allows English locale for non-english countries.
* Dev           - Adds Gulp task for .pot file processing.

= 2018.01.31  	- version 1.2.4 =
* Fix			- Fixes backup order creation process to check for product SKU.
* Enhancement   - Adds admin notice if Terms URL is not set in WooCommerce settings.

= 2018.01.29  	- version 1.2.3 =
* Fix			- Cleans up translation strings.
* Enhancement   - Adds woocommerce_enable_order_notes_field to KCO checkout template.

= 2018.01.26  	- version 1.2.2 =
* Fix			- Removes email check on validation CB.
* Enhancement   - Cleans up template files.

= 2018.01.25  	- version 1.2.1 =
* Tweak         - Saves KCO as payment method for orders with total equals zero.
* Tweak         - Checks if email already exists when guest checkout is disabled and forces users to log in before checking out.
* Fix			- Fixes empty JSON AJAX response.
* Enhancement   - Improves order query to only retrieve IDs.

= 2018.01.22  	- version 1.2 =
* Tweak         - Switches to using store base country as purchase country in all cases.
* Tweak         - Switches from using 'change' to 'shipping_address_change' for storing customer data.
* Fix			- Prevents Klarna Checkout order update after iframe has been submitted.

= 2018.01.11  	- version 1.1.1 =
* Tweak			- Makes datepicker extra field work in checkout.
* Fix			- Acknowledge order & set merchant reference in Klarnas system during backup order creation (on push notification).
* Fix			- Fixes storing WC_Customer postal code.

= 2017.12.20  	- version 1.1 =
* Tweak			- Allows external payment method plugin to work.
* Tweak			- Adds border-box to floated elements in KCO page.
* Fix			- Adds 3-letter to 2-letter country code translation.

= 1.0 =
* Initial release.
