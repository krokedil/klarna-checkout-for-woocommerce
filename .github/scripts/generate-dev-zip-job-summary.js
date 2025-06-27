// .github/scripts/generate-dev-zip-job-summary.js
// This script generates the job summary for the GitHub Actions workflow.

// Import the 'fs' (file system) module to write to files
const fs = require('fs');

// Get the path to the GitHub Actions step summary file
const summaryFile = process.env.GITHUB_STEP_SUMMARY;

// If the summary file path is not available, do nothing
if (!summaryFile) {
  console.log('GITHUB_STEP_SUMMARY environment variable not found.');
  process.exit(0); // Exit gracefully
}

// Define constants for the ZIP file name and S3 URL
const ZIP_FILE_NAME = process.env.ZIP_FILE_NAME;
const S3_URL = `https://krokedil-plugin-dev-zip.s3.eu-north-1.amazonaws.com/${ZIP_FILE_NAME}.zip`;
const BLUEPRINT_LANDING_PAGE = '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=kco&from=WCADMIN_PAYMENT_SETTINGS';

// Define playground minimal setup URL
const minimalBlueprintJson = `{
	"$schema": "https://playground.wordpress.net/blueprint-schema.json",
	"preferredVersions": {
		"php": "8.0",
		"wp": "latest"
	},
    "phpExtensionBundles": [
        "kitchen-sink"
    ],
    "features": {
        "networking": true
    },
    "constants": {
      "WP_DEBUG": true
    },
    "plugins": [
            "woocommerce",
            "${S3_URL}"
        ],
    "steps": [
        {
            "step": "setSiteOptions",
            "options": {
                "show_on_front": "page",
                "woocommerce_onboarding_profile": {
                    "skipped": true
                },
                "woocommerce_default_country": "SE",
                "woocommerce_currency": "SEK",
                "woocommerce_price_num_decimals": "2"
            }
        },
        {
            "step": "wp-cli",
            "command": "wp transient delete _wc_activation_redirect"
        },
        {
            "step": "wp-cli",
            "command": "wp wc product create --name='Simple product' --sku='simple-product' --regular_price='99.99' --virtual=false --downloadable=false --user='admin'"
        },
        {
            "step": "runPHP",
            "code": "<?php require_once 'wordpress/wp-load.php'; $page = get_page_by_path('sample-page'); if ($page) { update_option('woocommerce_terms_page_id', $page->ID); }"
        },
        {
            "step": "runPHP",
            "code": "<?php require_once 'wordpress/wp-load.php'; $shop_page_id = get_option('woocommerce_shop_page_id'); if ($shop_page_id) { update_option('page_on_front', $shop_page_id); update_option('show_on_front', 'page'); }"
        },
        {
            "step": "runPHP",
            "code": "<?php require_once 'wordpress/wp-load.php'; $checkout_page_id = get_option('woocommerce_checkout_page_id'); if ($checkout_page_id) { wp_update_post(['ID' => $checkout_page_id, 'post_content' => '[woocommerce_checkout]']); }"
        }
    ],
    "login": true,
    "landingPage": "${BLUEPRINT_LANDING_PAGE}"
}`;
const minifiedJson = JSON.stringify(JSON.parse(minimalBlueprintJson));
const encodedJson = encodeURIComponent(minifiedJson);
const PLAYGROUND_MINIMAL_URL = `https://playground.wordpress.net/#${encodedJson}`;

// Generate the Markdown content for the summary
const markdownContent = `
# Created dev zip
Download created dev zip through URL below, which is available for 30 days:
* [${ZIP_FILE_NAME}.zip](${S3_URL})

Documentation about how to install the dev zip can be found [here](https://docs.krokedil.com/krokedil-general-support-info/installing-a-development-version/).
## Test dev zip using WordPress Playground
You can test the created dev zip directly in [WordPress Playground](https://wordpress.org/playground/), which is a experimental project and functionality can be limited, through the links below:
* [Test dev zip using WordPress Playground](${PLAYGROUND_MINIMAL_URL}) (Latest WP, PHP 8.0, WooCommerce and created dev zip)
`;

// Append the Markdown content to the summary file
// Using appendFileSync ensures that we don't overwrite content from previous steps
try {
  fs.appendFileSync(summaryFile, markdownContent);
  console.log('Successfully appended content to the summary file.');
} catch (err) {
  console.error('Failed to write to summary file:', err);
  process.exit(1); // Exit with an error code
}