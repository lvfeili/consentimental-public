const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
 testDir: './isolated', workers: 1, reporter: 'list',
 outputDir: process.env.CMP_TEST_OUTPUT || '/tmp/cmp-isolated-results',
 use: { launchOptions: process.env.CMP_CHROMIUM ? { executablePath: process.env.CMP_CHROMIUM } : {} },
});
