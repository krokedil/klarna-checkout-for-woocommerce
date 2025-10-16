// .github/scripts/deploy-instawp.js
// Orchestrates InstaWP site detection/creation and dev-zip deployment.

// import required modules
const https = require('https');
const fs = require('fs');

// Shared metadata parsing (minimal helper)
const { loadMeta, safeGet } = require('./lib/plugin-meta');
let META;
try { META = loadMeta({ requireEnv: true }); } catch (e) { console.error(e.message); process.exit(1); }
// Inline domain-specific derivations using safeGet
const PLUGIN_WC_BLUEPRINT_URL = safeGet(META, 'instawp.plugin_wc_blueprint_url', '');
const PAYMENT_GATEWAY_ID = safeGet(META, 'instawp.payment_gateway_id', undefined);
const USE_CHECKOUT_BLOCK = safeGet(META, 'instawp.use_checkout_block', undefined);
const PLUGIN_CREDENTIALS_OPTION_PATCHES = (safeGet(META, 'instawp.plugin_credentials_option_patches', []) || []).map(p => ({
  option_name: p.option_name,
  key: p.key,
  value: process.env[p.env_var_value] || ''
}));

// Environment variables from GitHub Actions
const INSTA_WP_URL = process.env.INSTA_WP_URL;
const INSTAWP_API_TOKEN = process.env.INSTAWP_API_TOKEN;
const GITHUB_ENV = process.env.GITHUB_ENV;
const GITHUB_OUTPUT = process.env.GITHUB_OUTPUT;
const AWS_S3_PUBLIC_URL = process.env.AWS_S3_PUBLIC_URL;

// Validate required environment variables at the top
const REQUIRED_ENVS = [
  'INSTAWP_API_TOKEN',
  'ZIP_FILE_NAME',
  'AWS_S3_PUBLIC_URL',
];
const missingEnvs = REQUIRED_ENVS.filter((env) => !process.env[env]);
if (missingEnvs.length > 0) {
  console.error(`Missing required environment variables: ${missingEnvs.join(', ')}`);
  process.exit(1);
}

// Helper to normalize URLs (strip protocol and trailing slash)
function normalizeUrl(url) {
  return url ? url.replace(/^https?:\/\//, '').replace(/\/$/, '') : '';
}

// Centralized request options
const INSTA_WP_API_HOST = 'app.instawp.io';
const INSTA_WP_API_HEADERS = {
  'Authorization': `Bearer ${INSTAWP_API_TOKEN}`,
  'Accept': 'application/json',
  'Content-Type': 'application/json',
};

// API call wrapper with logging
async function apiCall({ method, path, body, logLabel }) {
  logGroupStart(logLabel || `API Request: ${method} ${path}`);
  if (body) {
    logInfo('Payload:');
    try { console.log(typeof body === 'string' ? body : JSON.stringify(body, null, 2)); } catch {}
  }
  let response;
  try {
    response = await instawpApiRequest({ method, path, body });
  } catch (err) {
    logError(`API call failed: ${err && err.message ? err.message : err}`);
    logGroupEnd();
    throw err;
  }
  logGroupStart('API Response');
  console.log(JSON.stringify(response, null, 2));
  logGroupEnd();
  logGroupEnd();
  return response;
}

// Generic request helper for InstaWP API
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
          logError(`InstaWP API error: ${JSON.stringify({ method, path, statusCode: res.statusCode, response: data })}`);
          reject(new Error(`InstaWP API error: ${res.statusCode} - ${data}`));
        }
      });
    });
    req.on('error', (err) => {
      logError('Network error: ' + (err && err.stack ? err.stack : err));
      reject(err);
    });
    if (body) req.write(body);
    req.end();
  });
}

// Fetch all existing InstaWP sites for the user
async function getExistingSites() {
  const json = await apiCall({ method: 'GET', path: '/api/v2/sites?per_page=300', logLabel: 'Get sites' });
  return json.data || [];
}

// Create a new InstaWP site and wait for it to be ready if needed
async function createNewSite(normalizedUrl) {
  logGroupStart('Create InstaWP site');
  const payload = {
    configuration_id: 5141,
    team_id: 4875,
    server_group_id: 4,
    is_reserved: false,
    expiry_hours: 1,
  };
  if (normalizedUrl) payload.site_name = normalizedUrl;
  logInfo(`Create site payload: ${JSON.stringify(payload)}`);
  const json = await apiCall({
    method: 'POST',
    path: '/api/v2/sites',
    body: JSON.stringify(payload),
    logLabel: 'Site creation',
  });
  const siteData = json.data || json;
  
  // If the API returns a task_id, wait for the site to be ready
  if (siteData.task_id) {
    logInfo(`Creation is asynchronous (task_id=${siteData.task_id}). Waiting for readiness...`);
    for (let i = 1; i <= 30; i++) {
      logInfo(`Checking site status (attempt ${i})...`);
      try {
        const statusRes = await instawpApiRequest({
          method: 'GET',
          path: `/api/v2/tasks/${siteData.task_id}/status`,
        });
        logGroupStart('Task status response');
        console.log(JSON.stringify(statusRes, null, 2));
        logGroupEnd();
        const taskStatus = statusRes?.data?.status;
        const siteId = statusRes?.data?.resource_id;
        logInfo(`Task status: ${taskStatus}, site_id: ${siteId}`);
        if (taskStatus === 'completed' && siteId && siteId !== 'null') {
          logInfo('Site is ready!');
          logGroupEnd();
          // Return the siteData from the original creation response, updated with the final siteId if needed
          return { ...siteData, id: siteId };
        }
      } catch (err) {
        logError('Error checking site status: ' + (err && err.stack ? err.stack : err));
      }
      await new Promise(res => setTimeout(res, 10000));
    }
    logError('Timed out waiting for site to be ready.');
    logGroupEnd();
    throw new Error('Timed out waiting for site to be ready.');
  }
  logGroupEnd();
  return siteData;
}

// Generic helper for InstaWP command execution
async function triggerInstaWpCommand(siteid, command_id, commandArguments = undefined) {
  let payload = { command_id };
  if (Array.isArray(commandArguments) && commandArguments.length > 0) {
    payload.commandArguments = commandArguments;
  }
  logInfo(`Triggering InstaWP command ${command_id} for site ${siteid}`);
  await apiCall({
    method: 'POST',
    path: `/api/v2/sites/${siteid}/execute-command`,
    body: JSON.stringify(payload),
    logLabel: `Command ${command_id}`,
  });
}

// Conditional wrapper: executes command only if condition true, otherwise logs skipMessage
async function maybeTriggerCommand({ siteid, command_id, condition, args = [], skipMessage }) {
  if (!condition) {
    if (skipMessage) logInfo(skipMessage);
    return { skipped: true };
  }
  await triggerInstaWpCommand(siteid, command_id, args);
  return { skipped: false };
}

// Logging helpers for GitHub Actions
function logInfo(msg) { console.log(`[INFO] ${msg}`); }
function logWarn(msg) { console.warn(`[WARN] ${msg}`); }
function logError(msg) { console.error(`::error::${msg}`); }
function logGroupStart(name) { console.log(`::group::${name}`); }
function logGroupEnd() { console.log('::endgroup::'); }

// Main logic
(async () => {
  // Normalize the target site URL (strip protocol and trailing slash), or empty string if not set
  const normalizedUrl = normalizeUrl(INSTA_WP_URL);

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

    // Always upload the dev zip to the site (Command 2301)
    await triggerInstaWpCommand(siteid, 2301, [{ dev_zip_public_url: AWS_S3_PUBLIC_URL }]);

    // Only run setup commands if a new site was created
    if (siteCreated) {
      logGroupStart('InstaWP setup commands for new site');
      // Basic setup commands
      await triggerInstaWpCommand(siteid, 2344);

      // WooCommerce blueprint
      await maybeTriggerCommand({
        siteid,
        command_id: 2334,
        condition: !!PLUGIN_WC_BLUEPRINT_URL,
        args: [{ wc_blueprint_json_public_url: PLUGIN_WC_BLUEPRINT_URL }],
        skipMessage: 'Skipping WooCommerce blueprint (no PLUGIN_WC_BLUEPRINT_URL configured)'
      });

      // Credential patches
      if (PLUGIN_CREDENTIALS_OPTION_PATCHES.length === 0) {
        logInfo('Skipping credential option patches (none configured)');
      } else {
        for (const patch of PLUGIN_CREDENTIALS_OPTION_PATCHES) {
          await maybeTriggerCommand({
            siteid,
            command_id: 2679,
            condition: !!patch.value,
            args: [patch],
            skipMessage: `Skipping credential patch for ${patch.option_name}.${patch.key} (empty value)`
          });
        }
      }

      // Payment gateway setup
      await maybeTriggerCommand({
        siteid,
        command_id: 2681,
        condition: !!PAYMENT_GATEWAY_ID,
        args: [{ payment_gateway_id: PAYMENT_GATEWAY_ID, order: 1 }],
        skipMessage: 'Skipping payment gateway order (no PAYMENT_GATEWAY_ID in metadata)'
      });

      // Checkout block setup
      await maybeTriggerCommand({
        siteid,
        command_id: 2549,
        condition: USE_CHECKOUT_BLOCK === false,
        args: [],
        skipMessage: USE_CHECKOUT_BLOCK === true
          ? 'Skipping checkout shortcode (USE_CHECKOUT_BLOCK=true)'
          : 'Skipping checkout shortcode (USE_CHECKOUT_BLOCK not specified)'
      });
      logGroupEnd();
    }

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
    logError('Unhandled error: ' + (e && e.stack ? e.stack : e));
    process.exit(1);
  }
})();