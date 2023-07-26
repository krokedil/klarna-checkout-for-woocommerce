=== Klarna Checkout for WooCommerce ===
Contributors: klarna, krokedil, automattic
Tags: woocommerce, klarna, ecommerce, e-commerce, checkout
Donate link: https://klarna.com
Requires at least: 4.0
Tested up to: 6.2.1
Requires PHP: 7.0
WC requires at least: 4.0.0
WC tested up to: 7.8.0
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== DESCRIPTION ==

*Checkout is an embedded checkout solution and includes all popular payment methods (Pay Now, Pay Later, Financing, Installments). With Checkout, Klarna is your single payment provider.*

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
4. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/klarna-checkout-for-woocommerce/).


== Frequently Asked Questions ==
= Which countries does this payment gateway support? =
Klarna Checkout works for merchants in Sweden, Finland, Norway, Germany, Austria, the Netherlands, UK and United States.

= Where can I find Klarna Checkout for WooCommerce documentation? =
For help setting up and configuring Klarna Checkout for WooCommerce please refer to our [documentation](https://docs.krokedil.com/klarna-checkout-for-woocommerce/).

== Changelog ==
= 2023.07.26    - version 2.11.4 =
* Fix           - Resolved an issue related to redirection when changing or updating the subscription payment method. Now, Klarna's hosted payment page has been added to the list of allowed external URLs for 'wp_safe_redirect' function.
* Fix           - Addressed an issue where the recurring token was not being saved appropriately. This was occurring because the orders containing subscriptions were not being correctly identified.
* Tweak         - Removed the settings tab from the “Klarna Add-ons” page because its functionalities have been transferred to the plugin.
* Tweak         - We will now validate the API credentials based on the active mode, whether it’s test or production. This enhancement should prevent the plugin from inaccurately attempting to verify production credentials when the test mode is in operation.

= 2023.07.18    - version 2.11.3 =
* Fix           - Fixed an issue where the recurring token was no longer being displayed in the billing fields.
* Fix           - Fixed an undefined index warning that ocurred when the shipping was shown outside of the iframe.
* Fix           - When processing a payment, and the order is not found, we'll now return the response in the expected format. 

= 2023.06.28    - version 2.11.2 =
* Fix           - Fixed an issue with how we made our meta queries when trying to find orders based on a Klarna order ID.
* Enhancement   - Added a validation to ensure that the order returned by our meta query actually is the correct order by verifying that the Klarna order ID stored matches the one we searched for.

= 2023.06.26    - version 2.11.1 =
* Fix           - Resolved a critical error that occurred in certain cases when an external payment method ("EPM") was used and the order ID couldn't be retrieved.

= 2023.06.20    - version 2.11.0 =
* Feature       - The plugin now supports WooCommerce's "High-Performance Order Storage" ("HPOS") feature.
* Fix           - Corrected a typo in transient name.
* Fix           - Addressed a typo in the name of the order status (thanks @bhrugesh96!).
* Fix           - Resolved an issue where the checkout would crash if invalid customer data was processed in the frontend events.
* Fix           - Fixed a problem where the order review and checkout form would get stuck in a "blocked" state when the customer closes the Klarna modal without completing the order.
* Tweak         - Due to API changes, the upsell feature would sometimes fail when receiving an empty body response. This has now been resolved.
* Enhancement   - Updated logging to include the browser's user agent.

= 2023.05.15    - version 2.10.2 =
* Fix           - Fixed an issue where the Klarna modal would prevent the page from scrolling to the WooCommerce error notice.
* Fix           - Fixed an issue with the $checkout_flow variable being referenced without being defined.
* Fix           - Various spelling mistakes.

= 2023.02.21    - version 2.10.1 =
* Fix           - Fixed an issue caused by passing an empty array to Klarnas billing or shipping address when creating a order.

= 2023.02.14    - version 2.10.0 =
* Feature       - Pay for order purchases are now redirected to Klarna's hosted payment page. This will now also happen when changing payment method.
* Fix           - Fixed an issue where a fatal error would occur when logging due to a third-party plugin "RankMath".
* Tweak         - Delay saving the Klarna order id until we're on the confirmation page.
* Tweak         - Removed 'wc-cart' JavaScript dependency as it is no longer needed.

= 2022.12.08    - version 2.9.0 =
* Feature       - Added subscription support for Denmark and the Netherlands.
* Tweak         - Explicitly set the size of the branding icon to prevent it from growing out of proportion on certain themes.
* Tweak         - If a business customer has saved their company details to WooCommerce, it will be used for prefilling the payment form.
* Tweak         - Related to Klarna Shipping Assistant, the purchase currency is saved as a transient to add compatibility with currency switchers.
* Fix           - Fixed pass by reference warning.
* Fix           - Fixed an issue where the shipping tax would sometimes not be accounted for.
* Fix           - Fixed an issue with recurring subscriptions and negative fee that occurs when renewing the subscription.

= 2022.10.26    - version 2.8.7 =
* Fix           - The shipping phone number should now be saved to the subscription.
* Fix           - Fixed an undefined index when trying to create or update the session.
* Enhancement   - You can now use the 'kco_locale' filter to change the Klarna locale.
* Enhancement   - Added support for PHP 8.

= 2022.09.21    - version 2.8.6 =
* Fix           - Fixed an issue where WooCommerce reported about shipping changes happening despite no changes which prevented the customer from finalizing the purchase.
* Fix           - Fixed undefined index and variable.
* Fix           - Fixed an issue where the WC form would sometimes not be updated when changing billing country.
* Tweak         - Added the "subscription" object.
* Tweak         - The plugin's JavaScript is now limited to run only on the checkout and order pay pages. This should fix the issue with the "Twenty Twenty-One/Two" themes where the buttons on the shop page would disappear when adding an item to the cart.

= 2022.08.15    - version 2.8.5 =
* Feature       - Add new layouts for the checkout form, including a new theme.
* Enhancement   - Compatibility with WordPress block themes.
* Tweak         - Improved handling of shipping method changes during the checkout process along with a new filter 'kco_shipping_auto_correct'.
* Tweak         - Updated links to documentation.

= 2022.07.04    - version 2.8.4 =
* Tweak         - Prevent the resume/suspend-cycle during validation.

= 2022.06.30    - version 2.8.3 =
* Fix           - Fixed the error handling for upselling orders using Post Purchase Upsell For WooCommerce.

= 2022.06.28    - version 2.8.2 =
* Fix           - The setting for changing border radius should now work as expected (thanks @adevade!)
* Fix           - External payment methods ("EPM") should now appear on the checkout page. EPM are not available for pay for order.
* Tweak         - Added additional documentation on the settings page.
* Tweak         - You can now use the 'kco_wc_cart_line_item' hook to filter out products that you don't want to send to Klarna.

= 2022.05.25    - version 2.8.1 =
* Fix           - Fix undefined index warning.

= 2022.05.23    - version 2.8.0 =
* Feature       - Add support for the Post Purchase Upsell For WooCommerce plugin.
* Enhancement   - Changed so the debuglog option is no longer autoloaded.
* Enhancement   - Shipping address fields will now be prefilled by billing address data from WooCommerce when creating a Klarna order if no shipping address exists for the customer in WooCommerce.
* Fix           - Product images and URLs will now be correctly sent to Klarna when using Pay for order.
* Fix           - Fixed a incorrect template name reference.
* Fix           - Fixed a issue that could cause a missmatch in order line references in the Klarna Portal.

= 2022.04.27    - version 2.7.4 =
* Enhancement   - Remove the Klarna payment information text from emails sent to the admin. ( Thank you Andréas Lundgren ).
* Fix           - External Payment methods will now be stripped from admin orders sent to customers. This is because we can not guarantee compatability with the payment gateways when this is the case.
* Fix           - Reload the checkout page if the order no longer needs to be paid for to show the default WooCommerce checkout page.
* Fix           - Fixed an issue that could happen if the Klarna order id is no longer set during the order validation.


= 2022.03.18    - version 2.7.3 =
* Fix           - Update the minified JavaScript file from the 2.7.2 update that was missed.

= 2022.03.17    - version 2.7.2 =
* Tweak         - Print any HTML part of the standard WooCommerce text for missing shipping methods if the customer address does not have a valid shipping method.

= 2022.03.09    - version 2.7.1 =
* Fix           - Fix showing standard WooCommerce text for missing shipping methods when there is only a single shipping method available.

= 2022.03.08    - version 2.7.0 =
* Enhancement   - Added a filter for the located checkout template when the Klarna checkout template is being used. The filter is kco_locate_checkout_template.
* Enhancement   - Added the field ship_to_different_address as a standard field, to prevent it from being moved.
* Enhancement   - Added shipping email and shipping phone to the default address that we send to Klarna if they are available.
* Fix           - We will now show the standard WooCommerce text for missing shipping methods if the customer address does not have a valid shipping method.
* Fix           - Fixed a bug that caused shipping options to not be updated properly on the normal checkout page if you had selected to show shipping options in Klarna Checkout.
* Fix           - Fixed an issue caused by us printing error notices during AJAX calls.
* Fix           - Fixed a incorrect log of GET order requests being logged as a POST request.
* Fix           - Removed the ability to use External Payment Methods for admin created orders, since this caused an issue when switching to the other payment method on the confirmation page.

= 2021.12.16    - version 2.6.4 =
* Enhancement   - Stored customer shipping address will now be sent to Klarna with the first request if you have enabled separate shipping address in the plugin.
* Enhancement   - The Request URLs are now saved to the log entries to make debugging issues easier.
* Enhancement   - Translations for the mail text addition have been added for several languages.
* Fix           - Removed old code related to the shipping option callback from Klarna that we no longer use.

= 2021.11.17    - version 2.6.3 =
* Fix           - Fixed an issue where we would sometimes print the same error notices twice.
* Fix           - Fixed a bug that could cause incorrect order totals when using a fee with admin created orders / pay for order links.
* Fix           - Fixed an issue where we did not update the renewal order with a new recurring token after the customer changes payment method for their subscription.

= 2021.10.26    - version 2.6.2 =
* Fix           - Fixed an issue where we sent the incorrect giftcard amount to Klarna when using WC Giftcards.
* Tweak         - Updated URLs to our documentation.
* Tweak         - Update description of the setting that adds post purchase information to emails.

= 2021.09.29    - version 2.6.1 =
* Fix           - Fixed so that billing and shipping addresses gets updated to the WooCommerce order after the customer changes the payment method for a subscription.
* Fix           - Fixed an issues where the customer object would sometimes be missing from the Klarna order causing a PHP notice.
* Tweak         - Updates the URLs to Klarnas docs for the post purchase email information.
* Tweak         - Bumped minimum supported versions for WooCommerce to version 4.0.0.

= 2021.08.25    - version 2.6.0 =
* Feature       - We now support Pay for order. You can now create an order in advance on the admin page and send a pay link to a customer, where they can finish the payment using Klarna Checkout.
* Feature       - We will now save the last 15 requests to Klarna that had an API error and display them on the WooCommerce status page. This should help with getting error messages when you need to debug issues without going through the logs. These will also be in the status report that you can send to us for support tickets.
* Fix           - Fixed some error notices related to PHP 8.0.

= 2021.08.03    - version 2.5.9 =
* Enhancement   - Added a filter to the fields we ignore when moving extra checkout fields on the checkout page. This filter is "kco_ignored_checkout_fields" and expects an array of strings that are the HTML element IDs of the fields you wish to not have moved.
* Fix           - Fixed a bug causing you to have to click some setting groups twice to have them properly expand.
* Fix           - Fixed an issue that could happen if a customer no longer had shipping options available to them, the old methods would still show. (Thank you Himanshu Seth!).
* Fix           - Removed the Klarna banner from the sidebar on the settings page.
* Fix           - Fixed an issue that could happen if a coupon a customer was using is no longer valid for the customer. The checkout would not be updated properly.

= 2021.07.12    - version 2.5.8 =
* Fix           - Trigger .change after adding country via shipping_address_change. So country change in checkout is update correctly in Woo.

= 2021.06.16    - version 2.5.7 =
* Fix           - Fixed an issue with PHP 8.0 that could cause a fatal error.
* Fix           - Fixed a typo in one of the log entries from the frontend JavaScript.

= 2021.06.03    - version 2.5.6 =
* Fix           - Update the minified JavaScript file from the 2.5.5 update that was missed.

= 2021.06.02    - version 2.5.5 =
* Fix           - Fixed an issue that could happen if you had shipping set to be calculated on billing address  in WooCommerce, but allowed for seperate shipping in Klarna.
* Fix           - Errors that happen on AJAX calls are now properly logged instead of [Object, Object] in the status log when placing an order.

= 2021.04.27    - version 2.5.4 =
* Fix           - Move our hidden shipping field to the billing address fields to prevent issues if you remove the order comments from the checkout using the filter woocommerce_enable_order_notes_field.
* Fix           - Prevent unlocking the iframe during the order submission process.
* Fix           - Fixed an issue with not sending shipping tax to Klarna if you had prices exclusive of tax and shipping in the Klarna iframe.

= 2021.04.07    - version 2.5.3 =
* Fix           - Removed old code that would set the Klarna checkout page to be considered to also be the cart page.

= 2021.04.01    - version 2.5.2 =
* Fix           - Fixed the total amount being calculated correctly if the shipping is in the iframe. This could cause an extra line item to be added to the Klarna order.

= 2021.03.31    - version 2.5.1 =
* Fix           - Prevent shipping from being added to the Klarna order if shipping in the iframe is selected.

= 2021.03.31    - version 2.5.0 =
* Feature       - Added a setting to select if you want to show the order details in Klarna, WooCommerce or in both during the checkout process. Default is to show it in WooCommerce as the order review.
* Enhancement   - Improved the calculation flow for the plugin so we are more inline with the WooCommerce standard.
* Enhancement   - Improved the speed of update calls to Klarna to enhance the checkout experience for the customer.
* Enhancement   - Removed the dependency of URL fragments, or hashtag urls. This should improve the comptability with some other plugins that use a similar feature to display content.
* Fix           - Fixed a issue with a fee reference beeing to long in some cases.
* Fix           - Fixed a issue with updating the Klarna order incorrectly during order submission if the cart had been cleared at this point.

= 2021.02.17    - version 2.4.3 =
* Enhancement   - We now save the recurring token from Klarna to the parent subscription order. If the token fails to be set for the subscription we will then get it from the Parent order instead. Should help some in cases where renewals fail when getting a HTTP error from Klarna.
* Enhancement   - When moving from Klarna V2 to V3, in some cases the recurring token was missing on the subscription. If this is the case we will now fetch it from the parent order.
* Fix           - Fixed an issue with the additional checkboxes feature.
* Fix           - Fixed an issue that could cause a malformed JSON object in the requests to Klarna. This would happen if an order line was removed causing the keys set to be out of order.
* Fix           - We now correctly save the shipping organization name.
* Fix           - Fixed an issue where we tried to get the quantity of fees.

= 2021.01.12    - version 2.4.2 =
* Enhancement   - Improved logging around checkout errors. The checkout error that stops the purchase is now being logged to make debugging easier.
* Enhancement   - Payment method changes to subscriptions are now confirmed instantly when changing to Klarna or updating a expired card. The push is no longer required, but is still used as a backup.
* Fix           - Fixed an issue with zero value orders not being completed properly when using Klarna.
* Fix           - Fixed an issue were the page was not properly reloaded when not using Klarna for zero value orders.
* Fix           - Fixed an issue were coupons and shipping prices did not register in the Klarna iFrame properly.

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
