// .github/scripts/deploy-instawp.js
// This script manages InstaWP site detection, creation, and triggers dev zip upload for a given URL.

const https = require('https');
const fs = require('fs');

// Environment variables from GitHub Actions
const INSTA_WP_URL = process.env.INSTA_WP_URL;
const INSTAWP_API_TOKEN = process.env.INSTAWP_API_TOKEN;
const GITHUB_ENV = process.env.GITHUB_ENV;
const GITHUB_OUTPUT = process.env.GITHUB_OUTPUT;
const ZIP_FILE_NAME = process.env.ZIP_FILE_NAME;

// Set if WooCommerce checkout should use checkout block or shortcode
const USE_CHECKOUT_BLOCK = false; // true = use checkout block, false = use shortcode

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
          // Enhanced error logging for debugging
          console.error('InstaWP API error:', {
            method,
            path,
            body,
            statusCode: res.statusCode,
            response: data
          });
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

// Create a new InstaWP site and wait for it to be ready if needed
async function createNewSite(normalizedUrl) {
  const payload = {
    configuration_id: 5141,
    team_id: 4875,
    server_group_id: 4,
    is_reserved: false,
    expiry_hours: 1,
  };
  // If a normalized URL is provided, set that as the site_name
  if (normalizedUrl) payload.site_name = normalizedUrl;

  // Make the API request to create the site
  const json = await instawpApiRequest({
    method: 'POST',
    path: '/api/v2/sites',
    body: JSON.stringify(payload),
  });

  // Save the response to a variable
  const siteData = json.data || json;
  
  // If the API returns a task_id, wait for the site to be ready
  if (siteData.task_id) {
    // Wait for site to be ready
    for (let i = 1; i <= 30; i++) {
      console.log(`Checking site status (attempt ${i})...`);
      try {
        const statusRes = await instawpApiRequest({
          method: 'GET',
          path: `/api/v2/tasks/${siteData.task_id}/status`,
        });
        // Log the response for debugging
        console.log('--- Response from InstaWP (task status) ---');
        console.log(JSON.stringify(statusRes, null, 2));
        console.log('------------------------------------------');
        const taskStatus = statusRes?.data?.status;
        const siteId = statusRes?.data?.resource_id;
        console.log(`Task status: ${taskStatus}, site_id: ${siteId}`);
        if (taskStatus === 'completed' && siteId && siteId !== 'null') {
          console.log('Site is ready!');
          // Fetch the site details after it's ready
          const sites = await getExistingSites();
          const found = sites.find(site => String(site.id) === String(siteId));
          if (found) {
            return found;
          } else {
            throw new Error('Site was created but could not be found after readiness check.');
          }
        }
      } catch (err) {
        console.error('Error checking site status:', err);
      }
      await new Promise(res => setTimeout(res, 10000));
    }
    throw new Error('Timed out waiting for site to be ready.');
  }
  // Fallback: use the returned site info directly
  return siteData;
}

// Generic helper for InstaWP command execution
async function triggerInstaWpCommand(siteid, command_id, commandArguments = undefined) {
  let payload = { command_id };
  if (Array.isArray(commandArguments) && commandArguments.length > 0) {
    payload.commandArguments = commandArguments;
  }
  const body = JSON.stringify(payload);
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

  // Normalize the target site URL (strip protocol and trailing slash), or empty string if not set
  const normalizedUrl = INSTA_WP_URL ? INSTA_WP_URL.replace(/^https?:\/\//, '').replace(/\/$/, '') : '';

  try {
    let siteid, siteurl, siteCreated = false;
    
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
        // No matching site found, create a new one with the normalized URL and wait for it to be ready
        const newSite = await createNewSite(normalizedUrl);
        siteid = newSite.id;
        siteurl = newSite.wp_url || newSite.url || '';
        siteCreated = true;
      }
    } else {
      // No URL provided, always create a new site with empty site_name and wait for it to be ready
      const newSite = await createNewSite('');
      siteid = newSite.id;
      siteurl = newSite.wp_url || newSite.url || '';
      siteCreated = true;
    }

    // Only run setup commands if a new site was created
    if (siteCreated) {
      // Command 2344: setup-default-site
      await triggerInstaWpCommand(siteid, 2344);
      // Command 2334: apply WooCommerce blueprint
      await triggerInstaWpCommand(siteid, 2334, [{ wc_blueprint_json_public_url: PLUGIN_WC_BLUEPRINT_URL }]);
      // Command 2417: apply credentials blueprint (API keys from secrets)
      await triggerInstaWpCommand(siteid, 2417, [{ wc_blueprint_json_string: PLUGIN_CREDENTIALS_WC_BLUEPRINT_JSON }]);

      // Check if WooCommerce checkout block should be used, if not switch to using the shortcode
      if (!USE_CHECKOUT_BLOCK) {
        // Command 2549: apply WooCommerce checkout shortcode
        await triggerInstaWpCommand(siteid, 2549);
      }
    }

    // Always upload the dev zip to the site (Command 2301)
    await triggerInstaWpCommand(siteid, 2301, [{ dev_zip_public_url: `https://krokedil-plugin-dev-zip.s3.eu-north-1.amazonaws.com/${ZIP_FILE_NAME}.zip` }]);

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
