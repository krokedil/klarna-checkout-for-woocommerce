=== Klarna Checkout for WooCommerce ===
Contributors: klarna, krokedil, automattic
Tags: woocommerce, klarna, ecommerce, e-commerce, checkout
Donate link: https://klarna.com
Requires at least: 4.0
Tested up to: 4.9.1
Requires PHP: 5.6
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== DESCRIPTION ==

*A full checkout experience embedded on your site with Pay Now, Pay Later and Slice It. No credit card numbers, no passwords, no worries.*

Our complete checkout is a seamless and mobile optimized solution that delivers a best-in-class user experience that comes with all our payment methods. It also identifies the customer and enables one-click repeat purchases across Klarna’s merchant network, resulting in increased average order value, conversions, and loyalty.

This official Klarna extension also makes it easy for you to handle orders in WooCommerce after a purchase is complete. With a single click of a button, you can activate, update, refund and cancel orders directly from WooCommerce without logging into the Klarna administration.

=== Pay Now ===
Customers who want to pay in full at checkout can do it quickly and securely with a credit/debit card. Friction-free direct purchases while maximising the value for your business thanks to guaranteed payments. If they have a Klarna account they can save their details and enjoy one-click purchases from then on.

=== Pay later ===
Try it first, pay it later. Delayed payments for customers who like low friction purchases and to pay after delivery.

=== Slice it ===
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
4. Read more about the configuration process in the [plugin documentation](http://docs.krokedil.com/documentation/klarna-checkout-for-woocommerce/).

== Changelog ==
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
