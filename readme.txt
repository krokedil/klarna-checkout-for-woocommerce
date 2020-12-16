=== Klarna Checkout for WooCommerce ===
Contributors: klarna, krokedil, automattic
Tags: woocommerce, klarna, ecommerce, e-commerce, checkout
Donate link: https://klarna.com
Requires at least: 4.0
Tested up to: 5.5.6
Requires PHP: 5.6
WC requires at least: 3.4.0
WC tested up to: 4.7.0
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== DESCRIPTION ==

*Checkout is an embedded checkout solution and includes all popular payment methods (Pay Now, Pay Later, Financing, Installments). With Checkout, Klarna is your single payment provider.*

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
= 2020.12.16    - version 2.4.1 =
* Enhancement   - Several improvements to our JavaScript, making the checkout experience faster and smoother.
* Enhancement   - Added links to our documentation for the admin notices that we can print to make it easier to find a solution.
* Enhancement   - Changed the way data is stored for the Klarna Shipping Assistance ( previously Klarna Shipping Service ) plugin. We now save it as a transient named kss_data_{klarna_order_id}.
* Fix           - Removed the account fields ( account name and password ) from our ignored fields list. This will allow for account creation with custom password and username on the checkout page when placing an order.
* Fix           - Fixed an issue were changing only the address line for the shipping address in Klarna Checkout would cause the postcode to be cleared from shipping calculations.

= 2020.11.18    - version 2.4.0 =
* Feature       - Add setting to allow the store to force the validation of a national identification number. Only applicable for SE, NO, FI and DK.
* Feature       - Add a filer for additional checkboxes. This allows for using the checkbox that we include in the settings allow with your own checkboxes as needed. The filter is "kco_additional_checkboxes". Please check Klarnas documentation for how to format the data.
* Enhancement   - Add support for PW Gift Cards.
* Enhancement   - Save the B2B reference field to the WooCommerce order and display if on the order page.
* Enhancement   - Replace the action "kco_wc_before_checkout_form" with the WooCommerce standard action "woocommerce_before_checkout_form". This allows for more compatibility with other plugins.
* Enhancement   - Add support for Mailchimp abandoned cart again.
* Enhancement   - Add error message if pretty permalinks is not enabled on the admin page.
* Tweak         - Update the go live link for the American market.
* Fix           - Prevent fields from inside the order review table from being moved. For example some shipping method fields could be moved and duplicated.
* Fix           - Limit the size of the Klarna logo on the admin page.

= 2020.10.21    - version 2.3.2 =
* Enhancement   - Added a filter for the name of the extra surcharge used for rounding.
* Enhancement   - Saved the added surcharge to the order so that it can be used by other plugins if needed.
* Enhancement   - Delayed the priority for our confirmation step on the init action. It fixes some timing issues with other plugins that also run on priority 10.
* Enhancement   - We now save the organisation number for B2B purchases and display them on the admin order page.
* Fix           - Fixed logic for the rounding surcharge to only be applied when it should.
* Fix           - Fixed logic when testing credentials, it should no longer give a error when not entering test credentials.

= 2020.09.30    - version 2.3.1 =
* Fix           - Fixed an issue with shipping not being added correctly.

= 2020.09.30    - version 2.3.0 =
* Feature       - Added support for getting Klarna subscription ID from _klarna_recurring_token (support for KCO v2 to v3 subscription transfer).
* Feature       - Check if testmode is active that test credentials are added before saving settings.
* Enhancement   - Updated the flow of the EPM process. Sets the payment method before processing the payment.
* Enhancement   - Added 10 second timeout to all requests made to Klarna.
* Fix           - Prevent issue by adding a default tax rate of zero if tax rate is missing.
* Fix           - Prevent adding a surcharge with zero value.
* Fix           - Fixed an issue with the setting for adding Klarna links in the email not being set generating error notices.
* Fix           - Fixed an issue with missing address data from Klarna generating a error notice.

= 2020.09.07    - version 2.2.0 =
* Enhancement   - Improvement to how we calculate taxes for the Klarna orders. This should improve compatibility with other plugins and adds better support for things like zero decimals.
* Enhancement   - Added compatibility with the plugin WooCommerce Gift Cards. https://woocommerce.com/products/gift-cards/
* Enhancement   - Added the action 'woocommerce_review_order_before_submit' to our template. It is added before the iFrame.
* Enhancement   - Added compatibility for the WP GDPR Compliance plugin.
* Fix           - Fixed an issue with zero decimals.
* Fix           - Fixed an issue with WooCommerce subscription when using a startup fee for the subscriptions parent order.

= 2020.09.01    - version 2.1.4 =
* Tweak         - Added action kco_wc_confirm_klarna_order so other plugins can hook into the confirm order process.
* Fix           - Redirect customer to thankyou page if response to an update request returns a READ_ONLY message from Klarna.
* Fix           - Updated subscription payment method change to work properly with KCO v2.x.

= 2020.07.30    - version 2.1.3 =
* Fix           - Remove kcoResume in shipping_option_change to avoid issues with order total not being displayed correct when changing shipping options with KSS (Klarna Shipping Service).

= 2020.07.29    - version 2.1.2 =
* Fix           - Fixed an issue with regions for Ireland.

= 2020.07.03    - version 2.1.1 =
* Fix           - Fixed an issue that made the iFrame to not be suspended on update_checkout event. Caused the iFrame to not update to display the proper information.

= 2020.07.01    - version 2.1.0 =
* Feature       - Added post-purchase information to emails being sent to the customer with links to Klarna support and the Klarna app. Can be enabled in the settings for the plugin.
* Enhancement   - Improved the confirmation page. We now redirect directly to the thankyou page to improve speed and reduce the risk of any error. Also removes the need to do any database queries in this step.
* Enhancement   - Limited the "flickering" of the iFrame on the checkout page.
* Enhancement   - Improvements to the Klarna banner on the admin pages.
* Fix           - Fixed an issue with the selected shipping method not being displayed properly when showing shipping in the iFrame.
* Fix           - Removed an unused old definition used by our old logging. Should prevent issues when using the old plugin at the same time as the current plugin

= 2020.06.23    - version 2.0.16 =
* Enhancement   - Changed so we always force an update of the Klarna order on the pageload for the checkout.
* Enhancement   - Added a filter ( kco_wc_cart_line_item ) to Klarna line items.
* Enhancement   - We now catch 403 errors for the credentials verification when saving the plugin settings. Also added more text to further help in this issue.
* Fix           - Changed redirect for EPM to use wp_redirect as wp_safe_redirect caused an error.
* Fix           - Fixed the checkout page product amount input fields no longer working.
* Fix           - Fixed the naming of a parameter for the push and notification callbacks from Klarna.

= 2020.06.11    - version 2.0.15 =
* Enhancement   - Added a limit to only get orders for 2 days back to confirmation page database queries.
* Enhancement   - Added order id to the confirmation page parameters to prevent the need to get an order from the database. Should improve speed of the checkout process.
* Enhancement   - Added function to validate credentials when saving settings. Will display an error if your credentials are not valid.
* Enhancement   - Changed the API settings name and description.
* Enhancement   - Updated the Klarna banners shown on the admin pages.
* Fix           - Limit product names to max 255 characters.
* Fix           - Made region field not required when using KCO. This prevents issues for countries that have region fields in WooCommerce, but none is provided by Klarna.
* Fix           - Fixed an issue where we attempted to access our settings before they were saved.

= 2020.05.25    - version 2.0.14 =
* Enhancement   - Make sure that order amount is zero for subscription payment method change.
* Enhancement   - Improved JavaScript to support missing address data from Klarna. Supports a coming update to Klarnas system.
* Fix           - Prevent trying to access empty array in some cases. ( Thank you Sarang Shahane )

= 2020.04.29    - version 2.0.13 =
* Enhancement	- Added custom attribute to stop password and text fields in settings to be autofilled with incorrect data.
* Enhancement	- Added clearing of KCO sessions after finalizing the purchase on the confirmation page. Better support for things like custom thank you pages.
* Fix			- Added jQuery Block UI as a prerequisite for our checkout Javascript. Prevents JavaScript errors in case the checkout JavaScripts are loaded in a different order than normal.

= 2020.04.16    - version 2.0.12 =
* Fix			- Reverted change from 2.0.11 "Better calculations for product unit price..." due to an issue with some tax settings.

= 2020.04.16    - version 2.0.11 =
* Fix			- Better calculations for product unit price and total amount. Fixes issues regarding subscriptions with a starting fee.
* Fix			- Better handling of region special characters. Fixes issues were some regions could not complete and order.
* Enhancement	- Better support for zero decimals for subscription renewals.

= 2020.04.09    - version 2.0.10 =
* Fix			- Added security checks to the Klarna Addons page to prevent unauthorized changes to plugins.

= 2020.04.07    - version 2.0.9 =
* Fix			- Fixed an issue with states for US orders.

= 2020.03.30    - version 2.0.8 =
* Fix			- Fixed an issue with regions not being processed correctly for some countries.
* Fix			- Fixed compatibility with some external payment methods. ( Thank you Christopher Hedqvist)

= 2020.03.20    - version 2.0.7 =
* Fix           - Fixed the merchant reference on order updates as well.
* Fix           - Fixed separate shipping address compatibility for WooCommerce 4.x.
* Enhancement   - Increased the default timeout time from 10 seconds to 20.

= 2020.03.20    - version 2.0.6 =
* Fix           - Changed so the merchant references are now the same as they were before.
* Enhancement   - Added logging from the frontend JavaScript. Will make debugging easier.
* Enhancement   - Orders that can not be found in order management during the confirmation page will now end up as on-hold instead of pending.

= 2020.03.10    - version 2.0.5 =
* Fix			- Fixed an issue with card payments, where resuming the iFrame caused the payment to not go through in some cases. ( Updates in both the plugin and in Klarnas system )
* Fix			- Removed a get request to Klarnas checkout endpoint during the confirmation stages. Could cause an error to be shown to the customer.

= 2020.03.10    - version 2.0.4 =
* Fix			- Readded kco_wc_before_extra_fields and kco_wc_before_extra_fields actions. Should renable support for the germanized plugin using the snippet we have: https://gist.github.com/krokedilgists/7fab7cf5d6a7b3c52fdd6bbc641592d0
* Fix           - Better error handling for subscription renewals.
* Fix			- We now save the company name to the WooCommerce order.
* Enhancement   - Added better support for extra checkboxes added to the terms Template.
* Enhancement	- Changed how we read the change in URL for the hashtag change. Should be able to handle JavaScript errors and be able to respond to the callback.

= 2020.03.06    - version 2.0.3 =
* Fix			- Fixed an issue that caused the recurring token to not be saved to the subscription order.

= 2020.03.04    - version 2.0.2 =
* Fix			- Fixed an issue that caused the login button to be moved on the checkout page if you allowed for users to login during the checkout.
* Enhancement   - Removed some old code relating to the validation callback url being passed to Klarna.

= 2020.03.03    - version 2.0.1 =
* Fix			- Fixed an issue where we did not return the default from the filter woocommerce_checkout_cart_item_quantity.
* Fix           - Removed some old code related to our old logging function.
* Enhancement   - Removed old code that showed the amount orders created on fallback from the old flow. Could cause long pageload times on sites with a lot of orders.

= 2020.03.03    - version 2.0.0 =
* Feature       - Implemented Klarnas frontend validation event.
* Feature       - Subscription EMD is automatically added to the Klarna order.
* Tweak	        - Complete rewrite of plugin.
* Tweak         - Major update to the flow of the checkout. Follows the WooCommerce flow more closely now. Orders are now created on the checkout page and validated by WooCommerce.
* Enhancement   - Removed validation callbacks. We now rely on the WooCommerce validation for extra checkout fields, and other validation steps.
* Enhancement   - Removed the old debug logging that was saved to the database.
* Enhancement   - Improved the logging. Requests and responses are now logged together. Added a stacktrace to the logs for easier debugging.
* Enhancement   - API errors between WooCommerce and Klarna are now always logged, independent on what your debug setting is. With debug on every request is logged.
* Enhancement   - Reduced the amount of requests to Klarna needed per order.
* Enhancement   - Better handling of external payment methods.
* Enhancement   - Better support for extra checkout fields.

= 2020.01.28    - version 1.11.7 =
* Fix           - Force update_checkout on checkout page load (if WC version 3.9+) to keep KCO iframe in sync with WooCommerce cart.
* Fix           - Don't try to run process_payment_handler function if KCO order status is checkout_incomplete.
* Fix           - Don't try to change WC order status to On hold in process_payment_handler function if order status is Pending.

= 2019.12.03    - version 1.11.6 =
* Fix           - Prevent function for changing to Klarna Checkout payment method from running on the confirmation page. Caused an issue with Google Tag Manager for WordPress by Thomas Geiger
* Fix           - Changed where we set the recurring token for a Subscription order.

= 2019.11.11    - version 1.11.5 =
* Fix           - Fixed issue for order_tax_amount not being set as correct amount when bulk renewal subscription is triggered.

= 2019.10.02    - version 1.11.4 =
* Fix           - Made recurring payments more compatible with new WooCommerce subscription flow.
* Fix           - Added merchant references to renewal orders.
* Fix           - Fixed an issue where some orders failed with zero decimals for subscriptions.
* Fix           - B2B purchases are now saved correctly on backup order creation.
* Enhancement   - Added filters to merchant URLs. ( Thank you Hampus Alstermo )
* Enhancement   - Changed from get_home_url to home_url function. ( Thank you Hampus Alstermo )

= 2019.10.02    - version 1.11.3 =
* Fix           - IE support in logic for checking if kco-external-payment exist in url during order confirmation process.
* Fix           - Request order from checkout API (instead of Order Management API) for address field population during confirmation process in Woo to avoid 404-responses from Klarna. 

= 2019.09.26    - version 1.11.2 =
* Tweak         - Increased minimum required WooCommerce version to 3.2.0.
* Tweak         - Store _wc_klarna_order_id and _transaction_id during process_payment_handler() function if not yet stored in order.
* Tweak         - Truncate Shipping reference field to 64 characters sent to Klarna.
* Tweak         - Set $coupon_key as reference instead of description for US dicsounts sent to Klarna.
* Fix           - Improved support for notifying customer to login if customer tries to create an account but already have one (and is not logged in). Could trigger orders created on checkout_error event.

= 2019.09.11    - version 1.11.1 =
* Fix           - Small fix in handle_push_cb_for_payment_method_change function that caused API Callback creation to not being created on the Push from Klarna.

= 2019.09.04	- version 1.11.0 =
* Feature       - Added support for changing subscription payment method. Useful if customers card has expired.
* Tweak         - Changed YITH giftcard reference sent to Klarna to "giftcard".
* Tweak         - Check if wcs_cart_contains_renewal() to maybe add subscription data to Klarna. So subscription token is created if manual subscription renewal payment is done via KCO.
* Tweak         - Added action wc_klarna_push_cb.
* Fix           - Adding subscription recurring token to fallback order creation.

= 2019.08.07	- version 1.10.4 =
* Enhancement	- Added a filter to keep displaying Klarna on free orders "kco_check_if_needs_payment".
* Enhancement	- We now send billing_countries field to Klarna.
* Enhancement	- Added check to prevent people from placing orders without logging in to existing account first if that is required.
* Fix			- Added a fix for handling cart errors in a better way.
* Fix			- Fixed an issue with multiple tax classes for subscription renewal orders.

= 2019.07.05  	- version 1.10.3 =
* Fix			- Reverted change to the validation callback URL from site to home URL. We are now using the home_url again.

= 2019.07.03  	- version 1.10.2 =
* Enhancement	- Added require_validate_callback_success to the API calls. This means that all orders have to get a valid response from your store on the validation callback to be able to be completed. For more info about this read here: https://docs.krokedil.com/article/287-klarna-checkout-faq
* Enhancement	- Changed purchase_country to be based on customer billing address instead of store base address.
* Enhancement	- Improved logging for subscription errors.
* Enhancement	- Added saving of shipping phone and email to the order as _shipping_phone and _shipping_email meta fields for other plugins to use if needed.
* Enhancement	- Added functionality to dismiss notices.
* Enhancement	- Now save the klarna order id as the transaction id every time to prevent possible issues if the update_post_meta function failed for any reasson.
* Fix			- Correctly add query params to the confirmation URL to prevent issues with other plugins trying to do the same.
* Fix			- Improved subscription controlls to prevent issues with other plugins.
* Fix			- Changed from using home_url to site_url for the validation callback to better support other plugins.

= 2019.06.13  	- version 1.10.1 =
* Fix           - Don't set orderSubmitted sessionStorage for external payment orders. Sets correct payment method in Woo order if EPM purchase is cancelled and KCO is selected payment method again.

= 2019.06.11  	- version 1.10.0 =
* Feature       - Added check for WooCommerce checkout phone field setting to determine if phone should be mandatory or not in Klarna Checkout.
* Enhancement   - Added order line price adjustment in backup order creation if order totals don't match.
* Enhancement   - Added tabs to add-ons page. Prepare for add-ons settings page.
* Tweak         - Set chosen payment method to KCO on confirmation page.
* Tweak         - Added filter kco_wc_credentials_from_session so plugins can modify credentials used when communicating with Klarna.
* Tweak         - Added hook kco_wc_process_payment so plugins can execute action during process_payment.
* Tweak         - Added kco_shipping_address_changed JS event so other plugins can act on the change.
* Tweak         - Delete sessions in woocommerce_thankyou instead of when Klarna order staus is checkout_complete.
* Tweak         - Display Klarna thankyou iframe even if order received page is reloaded in Woo. 
* Tweak         - Use get_label() instead of label when fetching shipping method name sent to Klarna.
* Tweak         - Improved logging.
* Tweak         - Added de_DE_formal to kco_wc_prefill_consent.
* Fix           - Added checks to prevent JS error when looping through extra checkout fields if no fields exist.

= 2019.05.08  	- version 1.9.6 =
* Fix           - Bug fix in totals comparison between Klarna & Woo in validation callback.

= 2019.05.03  	- version 1.9.5 =
* Tweak         - Limit locale sent to Klarna to max 5 characters (to avoid issues when DE formal is used).
* Fix           - Fixed iframe not updating properly after switching shipping method.
* Fix           - Fix for saving customer address correctly in Woo order when user is logged in.

= 2019.05.02  	- version 1.9.4 =
* Fix           - Improved handling of multi-currency plugins during validation process of order totals.

= 2019.04.30  	- version 1.9.3 =
* Fix           - Changed filter to wc_get_template for overriding checkout template (props @forsvunnet).
* Fix           - Improved logic in locale sent to Klarna. Fixes WC 3.6 bug where English where displayed as default lang in some stores. 

= 2019.04.18  	- version 1.9.2 =
* Fix           - Only set autoResume = false when suspending KCO iframe during required extra checkout fields check. Otherwise keep it to 10 seconds.
* Fix           - Don't redirect customer in check_that_kco_template_has_loaded function if user is not logged and registration is disabled.
* Fix           - Organization name and address line 2 no longer dependenat on billing address for the shipping address to be added.

= 2019.04.05  	- version 1.9.1 =
* Tweak         - Added check to see if validate-required form row contains input field (in extra checkout field handling).
* Tweak         - Retain error notice regarding required checkout fields needs to be entered after updated_checkout event has triggered.

= 2019.04.04  	- version 1.9.0 =
* Feature       - Added support for displaying shipping methods in KCO iframe. Activate/deactivate feature via settings.
* Feature       - Added new Klarna Add-ons page.
* Feature       - Added Klarna On-site Messaging & Klarna order management as available add-ons.
* Tweak         - Save extra checkout form field data in sessionStorage instead of WC session.
* Tweak         - Validate if required extra checkout fields have been entered in front-end (during shipping_address_change event) instead of during validate callback.
* Tweak         - Use cart data in backup_order_creation if it exist.
* Tweak         - Use data from Klarna order in backup_order_creation if cart is missing (instead of regular product price).
* Tweak         - Send smart coupon to Klarna as gift_card (instead of discount).
* Tweak         - Calculate cart_total and use that instead of cart session data in totals match validation.
* Tweak         - Added class instead of inline CSS to select different payment method button wrapper.
* Tweak         - Adds payment_complete() to fallback order creation to send mail to customer.
* Tweak         - Re-arranged plugin settings fields.
* Tweak         - Changed customer type info in settings.
* Tweak         - Added 10sec timeout to all requests.
* Tweak         - Added cleaning to string added to JS (klarna_process_text).
* Tweak         - Added WooCommerce version to user agent string.
* Tweak         - Changed priority to 999 for woocommerce_locate_template hook to avoid conflicts with other plugins.
* Tweak         - Redirect customer to cart page if KCO template file hasn't been loaded.
* Tweak         - Improved handling of gift cards.
* Tweak         - Use request_pre_get_order instead of get_order in set_recurring_token_for_order.
* Fix           - Added support for Aelia multi currency plugin in validate and push (backup order creation) callbacks.
* Fix           - Always acknowledge the order in push callback if order exist in Woo.
* Fix           - Improved error handling to avoid situations with displaying "Missing Klarnas order ID".
* Fix           - Re-added check on recurring shipping.
* Fix           - Bug fix in shipping_valid in validation callback.

= 2019.02.13  	- version 1.8.4 =
* Tweak         - Added WC hooks woocommerce_checkout_create_order & woocommerce_checkout_update_order_meta in backup order creation. Better support for Sequential order numbers plugin (props @jonathan-dejong). 
* Tweak         - Added billing_state, billing_country, shipping_state & shipping_country to standard checkout fields to exclude from extra checkout fields control.
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
* Fix           - Updated shipping control in validate callback. Fixes issue with only digital/virtual orders.
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
* Tweak			- Moved from replacing Woo order review HTML to calling update_checkout event after ajax kco_wc_iframe_shipping_address_change has run.
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
* Feature       - Added fallback order creation if checkout form submission fails.
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
* Enhancement   - Adds WC required and tested up to date to main plugin file.
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
