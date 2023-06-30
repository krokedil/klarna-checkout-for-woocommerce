import { GetWcApiClient, Teardown } from '@krokedil/wc-test-helper';
import { FullConfig } from '@playwright/test';

const {
	BASE_URL,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

const globalTeardown = async (config: FullConfig) => {
	const wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');

	// Destroy the test data using the WC API.
	await Teardown(wcApiClient);
}

export default globalTeardown;
