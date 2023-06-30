import { AdminLogin, GetSystemReportData, GetWcApiClient, Setup } from '@krokedil/wc-test-helper';
import { BrowserContext, chromium, FullConfig, Page } from '@playwright/test';
import { SetKcSettings } from './utils/Utils';

const {
	BASE_URL,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

let adminContext: BrowserContext;
let guestContext: BrowserContext;

let adminPage: Page;
let guestPage: Page;


const globalSetup = async (config: FullConfig) => {
	const wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');

	const { storageState, baseURL } = config.projects[0].use;

	process.env.ADMINSTATE = `${storageState}/admin/state.json`;
	process.env.GUESTSTATE = `${storageState}/guest/state.json`;

	await setupContexts(baseURL, storageState.toString());

	// Login to the admin page.
	await AdminLogin(adminPage);

	// Set Klarna settings.
	await SetKcSettings(wcApiClient);

	// Get system report data, and save them to env variables.
	await GetSystemReportData(wcApiClient);

	// Save contexts as states.
	await adminContext.storageState({ path: process.env.ADMINSTATE });
	await guestContext.storageState({ path: process.env.GUESTSTATE });

	await adminContext.close();
	await guestContext.close();

	// Setup the test data using the WC API.
	await Setup(wcApiClient);
}

async function setupContexts(baseUrl: string, statesDir: string) {
	adminContext = await chromium.launchPersistentContext(`${statesDir}/admin`, { headless: true, baseURL: baseUrl });
	adminPage = await adminContext.newPage();
	guestContext = await chromium.launchPersistentContext(`${statesDir}/guest`, { headless: true, baseURL: baseUrl });
	guestPage = await guestContext.newPage();
}

export default globalSetup;
