/**
 * WordPress Playground dev-environment config for this plugin.
 * Consumed by @krokedil/wp-playground-tools — see its README for the full schema.
 */
import { envSecret } from '@krokedil/wp-playground-tools';

export default {
	slug: 'klarna-checkout-for-woocommerce',
	siteName: 'Kustom Checkout for WooCommerce',

	// Claimed in the org port registry (wp-playground-tools README):
	// start 8910, development 8911, demo 8912 (--https proxies on +400).
	basePort: 8910,

	// The Kustom Checkout settings screen — the same URL the dev-zip smoke test
	// asserts on (.github/plugin-meta.json).
	landingPage: '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=kco',

	// wpify-scoper writes the scoped krokedil/* packages to dependencies/ (see
	// composer.json "extra"), and the main plugin file `use`s that namespace at
	// file scope — an unscoped mount fatals outright. vendor/ can exist while
	// dependencies/ is still empty, so both markers are needed to trigger an
	// install. Installing pulls the private krokedil/* repos over SSH.
	composer: {
		markers: [ 'vendor/autoload.php', 'dependencies/scoper-autoload.php' ],
	},

	// No build config: the blocks bundle (blocks/build/) and the minified assets
	// are committed. Rebuild manually with `npm run build` when working on
	// blocks/src/ or assets/ — that chain also runs lint:js:fix, which rewrites
	// source, so it is deliberately not wired into provisioning.

	options: {
		development: {
			// The tool seeds a BACS gateway in development mode so that any
			// plugin can place test orders. For a checkout gateway that works
			// against us: BACS sorts ahead of Kustom, so a fresh site opens the
			// *classic* checkout with "Direct bank transfer" preselected and the
			// Kustom iframe only appears once you pick it by hand. This plugin
			// is the payment method — turn the placeholder off.
			woocommerce_bacs_settings: { enabled: 'no' },
		},

		all: {
			woocommerce_kco_settings: {
				enabled: 'yes',
				testmode: 'yes',
				logging: 'yes',
				// Kustom test credentials (EU) from the central playground .env —
				// missing values warn by name and leave the gateway unconfigured
				// (the site still boots).
				test_merchant_id_eu: envSecret( 'KCO_TEST_MERCHANT_ID_EU' ),
				test_shared_secret_eu: envSecret( 'KCO_TEST_SHARED_SECRET_EU' ),
			},
		},
	},

	// Kustom's checkout callbacks need a public URL: run with --tunnel, reserve a
	// domain under the company ngrok account, claim it in the tunnel domain
	// registry (wp-playground-tools README) and set it here.
	// tunnel: { provider: 'ngrok', domain: 'kco-woo.eu.ngrok.io' },
};
