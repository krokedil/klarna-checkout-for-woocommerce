import { defineConfig } from 'allure';

export default defineConfig({
	name: 'Kustom Checkout tests',
	plugins: {
		awesome: {
			options: {
				reportName: 'Kustom Checkout tests',
				singleFile: true,
			},
		},
	},
});
