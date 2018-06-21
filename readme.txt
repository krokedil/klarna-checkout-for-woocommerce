=== Klarna Checkout for WooCommerce ===
Contributors: klarna, krokedil, automattic
Tags: woocommerce, klarna, ecommerce, e-commerce, checkout
Donate link: https://klarna.com
Requires at least: 4.0
Tested up to: 4.9.6
Requires PHP: 5.6
WC requires at least: 3.0.0
WC tested up to: 3.4.0
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
