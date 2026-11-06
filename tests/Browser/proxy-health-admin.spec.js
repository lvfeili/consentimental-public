const {test,expect}=require('./offline-test');

test('proxy diagnostics require an administrator session', async ({page})=>{
 await page.goto('/wp-admin/admin.php?page=consentimental-proxy-health');
 await expect(page.locator('#loginform')).toBeVisible();
});

test.describe('proxy infrastructure admin UI (read-only)',()=>{
 test.beforeEach(async ({page})=>{
  await page.goto('/wp-login.php');
  await page.fill('#user_login',process.env.CMP_TEST_ADMIN || 'admin');
  await page.fill('#user_pass',process.env.CMP_TEST_PASSWORD || 'admin');
  await page.click('#wp-submit');
  await page.waitForURL('**/wp-admin/**');
 });
 test('diagnostics expose separate health states, opt-in mail, and guides',async ({page})=>{
  await page.goto('/wp-admin/admin.php?page=consentimental-proxy-health');
  await expect(page.locator('h1')).toHaveText('Proxy diagnostics');
  await expect(page.locator('input[name="proxy_email"]')).toBeVisible();
  await expect(page.locator('button[name="check_proxy_health"]')).toBeVisible();
  await expect(page.locator('.wrap')).toContainText('not browser execution');
  await expect(page.locator('h2')).toContainText(['Regression email alerts','Proxy setup guides']);
  await expect(page.locator('a[href="https://docs.umami.is/docs/tracker-configuration"]')).toBeVisible();
 });
 test('new scripts have an explicit empty runtime allowlist',async ({page})=>{
  await page.goto('/wp-admin/admin.php?page=consentimental-scripts&action=add');
  await expect(page.locator('#cmp-proxy-paths')).toBeVisible();
  await expect(page.locator('#cmp-proxy-paths')).toHaveValue('');
  await expect(page.getByRole('link',{name:'Proxy diagnostics and setup guides',exact:true})).toBeVisible();
 });
});
