// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const destructive = ['banner-appearance.spec.js', 'scripts-admin.spec.js', 'services-admin.spec.js', 'services.spec.js'];

module.exports = defineConfig({
	testDir: '.',
	testIgnore: ['isolated/**', ...('1' === process.env.CMP_ALLOW_SITE_RESET ? [] : destructive)],
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: 'list',
	use: {
		baseURL: process.env.CMP_TEST_URL || 'http://localhost:8080',
		trace: 'retain-on-failure',
		launchOptions: process.env.CMP_CHROMIUM ? { executablePath: process.env.CMP_CHROMIUM } : {},
	},
	projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
