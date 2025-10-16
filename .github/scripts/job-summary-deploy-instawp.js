// .github/scripts/job-summary-deploy-instawp.js
// This script generates a summary for the InstaWP deploy job in GitHub Actions.

// Import the 'fs' (file system) module to write to files
const fs = require('fs');

// Get the path to the GitHub Actions step summary file
const summaryFile = process.env.GITHUB_STEP_SUMMARY;

// If the summary file path is not available, do nothing
if (!summaryFile) {
  console.log('GITHUB_STEP_SUMMARY environment variable not found.');
  process.exit(0); // Exit gracefully
}

// Define constants
const siteUrl = process.env.INSTAWP_SITE_URL;
const siteId = process.env.INSTAWP_SITE_ID;
const siteCreated = process.env.INSTAWP_SITE_CREATED;
const siteNewOrExisting = siteCreated === 'true' ? 'new' : 'existing';

// Generate the Markdown content for the summary
const markdownContent = `
# Deploy to InstaWP
Dev zip has been deployed to ${siteNewOrExisting} InstaWP site [${siteUrl}](${siteUrl}) ([InstaWP dashboard link](https://app.instawp.io/sites/${siteId}/dashboard?tab=all)).
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
