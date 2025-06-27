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

// Define dev zip and other URLs used in the summary

// Other URLs used in the summary

const ZIP_FILE_NAME = process.env.ZIP_FILE_NAME;
const S3_URL = `https://krokedil-plugin-dev-zip.s3.eu-north-1.amazonaws.com/${ZIP_FILE_NAME}.zip`;

// Define playground minimal setup URL
const minimalBlueprintJson = `{
	"$schema": "https://playground.wordpress.net/blueprint-schema.json",
	"preferredVersions": {
		"php": "8.0",
		"wp": "latest"
	},
    "plugins": [
            "woocommerce",
            "${S3_URL}"
        ]
    "steps": [
        {
            "step": "setSiteOptions",
            "options": {
                "woocommerce_onboarding_profile": {
                    "skipped": true
                }
            }
        },
    ],
    "login": true,
    "landingPage": "/wp-admin/admin.php?page=wc-settings&tab=checkout&section=kco&from=WCADMIN_PAYMENT_SETTINGS",
}`;
const PLAYGROUND_MINIMAL_URL = `https://playground.wordpress.net/#${JSON.stringify(JSON.parse(minimalBlueprintJson))}`;

// Generate the Markdown content for the summary
const markdownContent = `
# Created dev zip
Download created dev zip through URL below, which is available for 30 days:
* [${ZIP_FILE_NAME}](${S3_URL})

Documentation about how to install the dev zip can be found [here](https://docs.krokedil.com/krokedil-general-support-info/installing-a-development-version/).
## Test dev zip using WordPress Playground
You can test the created dev zip directly in [WordPress Playground](https://wordpress.org/playground/), through the links below:
* [Minimal setup](${PLAYGROUND_MINIMAL_URL}) (Latest WP, PHP 8.0, WooCommerce and created dev zip)
* [Advanced setup](#)
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