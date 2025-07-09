// .github/scripts/deploy-instawp.js
// This script manages InstaWP site detection, creation, and triggers dev zip upload for a given URL.

const https = require('https');
const fs = require('fs');

// Environment variables from GitHub Actions
const INSTA_WP_URL = process.env.INSTA_WP_URL;
const INSTAWP_API_TOKEN = process.env.INSTAWP_API_TOKEN;
const GITHUB_ENV = process.env.GITHUB_ENV;
const GITHUB_OUTPUT = process.env.GITHUB_OUTPUT;

// Blueprint URL for WooCommerce KCO (Klarna Checkout) default settings
const PLUGIN_WC_BLUEPRINT_URL = 'https://raw.githubusercontent.com/krokedil/instawp-commands/refs/heads/main/assets/wc-blueprints/wc-blueprint-kco-default.json';

// Set KCO Test Klarna API Username and Test Klarna API Password
const PLUGIN_CREDENTIALS_WC_BLUEPRINT_JSON = JSON.stringify({
  steps: [
    {
      step: "setSiteOptions",
      options: {
        woocommerce_kco_settings: {
          test_merchant_id_eu: process.env.E2E_API_KEY || '',
          test_shared_secret_eu: process.env.E2E_API_SECRET || '',
        }
      }
    }
  ]
});


// Centralized request options
const INSTA_WP_API_HOST = 'app.instawp.io';
const INSTA_WP_API_HEADERS = {
  'Authorization': `Bearer ${INSTAWP_API_TOKEN}`,
  'Accept': 'application/json',
  'Content-Type': 'application/json',
};

// Generic request helper
function instawpApiRequest({ method, path, body }) {
  const options = {
    hostname: INSTA_WP_API_HOST,
    path,
    method,
    headers: { ...INSTA_WP_API_HEADERS },
  };
  if (body) {
    options.headers['Content-Length'] = Buffer.byteLength(body);
  }
  return new Promise((resolve, reject) => {
    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        if (res.statusCode >= 200 && res.statusCode < 300) {
          try {
            resolve(data ? JSON.parse(data) : {});
          } catch (e) {
            resolve(data);
          }
        } else {
          reject(new Error(`InstaWP API error: ${res.statusCode} - ${data}`));
        }
      });
    });
    req.on('error', reject);
    if (body) req.write(body);
    req.end();
  });
}

// Fetch all existing InstaWP sites for the user
async function getExistingSites() {
  const json = await instawpApiRequest({ method: 'GET', path: '/api/v2/sites?per_page=300' });
  return json.data || [];
}

// Create a new InstaWP site
async function createNewSite(normalizedUrl) {
  const payload = {
    configuration_id: 5141,
    team_id: 4875,
    server_group_id: 4,
    is_reserved: false,
    expiry_hours: 1,
  };
  if (normalizedUrl) payload.site_name = normalizedUrl;
  const json = await instawpApiRequest({
    method: 'POST',
    path: '/api/v2/sites',
    body: JSON.stringify(payload),
  });
  return json.data || json;
}

// Generic helper for InstaWP command execution
async function triggerInstaWpCommand(siteid, command_id, commandArguments = []) {
  const body = JSON.stringify({ command_id, commandArguments });
  await instawpApiRequest({
    method: 'POST',
    path: `/api/v2/sites/${siteid}/execute-command`,
    body,
  });
}

// Main logic
(async () => {
  // Validate required environment variables (only INSTAWP_API_TOKEN is required)
  if (!INSTAWP_API_TOKEN) {
    console.error('INSTAWP_API_TOKEN must be set.');
    process.exit(1);
  }

  // Get zip file name
  const ZIP_FILE_NAME = process.env.ZIP_FILE_NAME;
  // Normalize the target site URL (strip protocol and trailing slash), or empty string if not set
  const normalizedUrl = INSTA_WP_URL ? INSTA_WP_URL.replace(/^https?:\/\//, '').replace(/\/$/, '') : '';

  try {
    let siteid, siteurl, siteCreated = false;


    // Helper to create a new site (with or without normalizedUrl)
    async function createAndAssignSite(urlArg) {
      const newSite = await createNewSite(urlArg);
      siteid = newSite.id;
      siteurl = newSite.wp_url || '';
      siteCreated = true;
    }

    if (normalizedUrl) {
      // If a URL is provided, try to find an existing site
      const sites = await getExistingSites();
      const matches = sites.filter(site => {
        if (!site.url) return false;
        const url = site.url.replace(/^https?:\/\//, '').replace(/\/$/, '');
        return url.toLowerCase() === normalizedUrl.toLowerCase();
      });

      if (matches.length > 0) {
        // Site already exists, use its ID and URL
        siteid = matches[0].id;
        siteurl = matches[0].url;
        siteCreated = false;
      } else {
        // No matching site found, create a new one with the normalized URL
        await createAndAssignSite(normalizedUrl);
      }
    } else {
      // No URL provided, always create a new site with empty site_name
      await createAndAssignSite('');
    }

    // Only run setup commands if a new site was created
    if (siteCreated) {
      await triggerInstaWpCommand(siteid, 2344);
      await triggerInstaWpCommand(siteid, 2334, [{ wc_blueprint_json_public_url: PLUGIN_WC_BLUEPRINT_URL }]);
      await triggerInstaWpCommand(siteid, 2417, [{ wc_blueprint_json_string: PLUGIN_CREDENTIALS_WC_BLUEPRINT_JSON }]);
    }

    // Always upload the dev zip to the site (Command 2301)
    await triggerInstaWpCommand(siteid, 2301, [{ dev_zip_public_url: `https://krokedil-plugin-dev-zip.s3.eu-north-1.amazonaws.com/${ZIP_FILE_NAME}` }]);

    // Set GitHub Actions outputs and environment variables for downstream steps
    if (GITHUB_ENV) fs.appendFileSync(GITHUB_ENV, `INSTA_WP_SITE_ID=${siteid}\n`);
    if (GITHUB_OUTPUT) {
      fs.appendFileSync(GITHUB_OUTPUT, `instawp_site_id=${siteid}\n`);
      fs.appendFileSync(GITHUB_OUTPUT, `instawp_site_url=${siteurl}\n`);
      fs.appendFileSync(GITHUB_OUTPUT, `instawp_site_created=${siteCreated}\n`);
    }

    // Log summary to console
    console.log(`${siteCreated ? 'Created new' : 'Found'} site with siteid: ${siteid}, siteurl: ${siteurl}`);
  } catch (e) {
    // Log errors and exit with failure
    console.error('Error:', e);
    process.exit(1);
  }
})();
