const {defineConfig}=require('@playwright/test');
module.exports=defineConfig({
 testDir:'.',testMatch:'proxy-health-admin.spec.js',workers:1,reporter:'list',
 outputDir:'/tmp/cmp-admin-readonly-results',
 use:{baseURL:process.env.CMP_TEST_URL || 'http://localhost:8080',launchOptions:process.env.CMP_CHROMIUM ? {executablePath:process.env.CMP_CHROMIUM}:{}},
});
