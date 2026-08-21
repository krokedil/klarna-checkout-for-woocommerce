/**
 * Shows the store setup status under the Kustom Checkout gateway on the WooCommerce
 * payments settings page, where WooCommerce suppresses classic admin notices.
 *
 * The page is React rendered, so the notice is injected into the gateway row once it
 * exists, with a MutationObserver as backup while the page renders. Same pattern as
 * the official Stripe gateway uses for its row notice on this page.
 *
 * @package Klarna_Checkout/Assets
 */

( function () {
	'use strict';

	var params = window.kcoStoreSetupNotice;
	if ( ! params ) {
		return;
	}

	/**
	 * Injects the notice into the gateway row, once.
	 *
	 * @return {boolean} Whether the notice exists in the row.
	 */
	function injectNotice() {
		var row = document.getElementById( params.gatewayId );
		if ( ! row ) {
			return false;
		}

		if ( row.querySelector( '.kco-store-setup-row-notice' ) ) {
			return true;
		}

		var target = row.querySelector( '.woocommerce-list__item-text' );
		if ( ! target ) {
			return false;
		}

		var notice           = document.createElement( 'div' );
		notice.className     = 'kco-store-setup-row-notice';
		notice.style.cssText = 'background:#fcf0f1;border-left:4px solid #d63638;padding:8px 12px;margin-top:8px;';

		var summary         = document.createElement( 'strong' );
		summary.textContent = params.summary;
		notice.appendChild( summary );

		( params.checks || [] ).forEach(
			function ( check ) {
				var row           = document.createElement( 'p' );
				row.style.cssText = 'margin:4px 0 0;';

				// The message is escaped server side and may contain links; the label is plain text.
				row.textContent = '» ' + check.label + ' - ';
				row.insertAdjacentHTML( 'beforeend', check.message );
				notice.appendChild( row );
			}
		);

		var link           = document.createElement( 'a' );
		link.href          = params.reportUrl;
		link.textContent   = params.linkText;
		link.style.cssText = 'display:block;margin-top:4px;';
		notice.appendChild( link );

		target.appendChild( notice );
		return true;
	}

	function run() {
		if ( injectNotice() ) {
			return;
		}

		var observer = new MutationObserver(
			function () {
				if ( injectNotice() ) {
						observer.disconnect();
				}
			}
		);

		var observerTarget = document.getElementById( 'experimental_wc_settings_payments_main' ) || document.body;
		observer.observe( observerTarget, { childList: true, subtree: true } );

		window.setTimeout(
			function () {
				observer.disconnect();
			},
			15000
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
