// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const consentSpecs = /consent-matrix|accessibility|compat/;

module.exports = defineConfig({
	testDir: './specs',
	workers: 1,
	fullyParallel: false,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
	timeout: 60_000,
	use: {
		baseURL: process.env.CMP_E2E_URL || 'http://localhost:8891',
		trace: 'retain-on-failure',
	},
	projects: [
		{ name: 'chromium', use: { ...devices['Desktop Chrome'] } },
		{ name: 'firefox', use: { ...devices['Desktop Firefox'] }, testMatch: consentSpecs },
		{ name: 'webkit', use: { ...devices['Desktop Safari'] }, testMatch: consentSpecs },
		{ name: 'mobile-chrome', use: { ...devices['Pixel 7'] }, testMatch: consentSpecs },
		{ name: 'mobile-safari', use: { ...devices['iPhone 13'] }, testMatch: consentSpecs },
	],
});
