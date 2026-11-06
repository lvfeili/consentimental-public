// @ts-check
const { test, expect } = require('./offline-test');


test.describe('fresh visitor, basic mode (the default)', () => {
	test('a correct consent default exists before anything else, and GTM does not load', async ({ page }) => {
		await page.goto('/');

		const dataLayer = await page.evaluate(() => window.dataLayer.map((entry) => Array.from(entry)));
		expect(Array.isArray(dataLayer)).toBe(true);
		expect(dataLayer.length).toBeGreaterThan(0);

		const [command, action, defaults] = dataLayer[0];
		expect(command).toBe('consent');
		expect(action).toBe('default');
		expect(defaults.security_storage).toBe('granted');
		expect(defaults.analytics_storage).toBe('denied');
		expect(defaults.ad_storage).toBe('denied');
		expect(defaults.ad_user_data).toBe('denied');
		expect(defaults.ad_personalization).toBe('denied');
		expect(defaults.functionality_storage).toBe('denied');
		expect(defaults.personalization_storage).toBe('denied');

		expect(defaults.wait_for_update).toBeUndefined();

		await expect(page.locator('script[src*="GTM-PWTEST"]')).toHaveAttribute('type', 'text/plain');
		await expect.poll(() => page.evaluate(() => window.google_tag_manager)).toBeUndefined();
	});

	test('accepting Marketing pushes a consent update and releases GTM', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="customize"]');
		await page.click('label[for="cmp-category-marketing"]');
		await page.click('#cmp-customize [data-cmp-action="save"]');

		await expect.poll(() => page.evaluate(() => window.dataLayer.some((e) => 'consent' === e[0] && 'update' === e[1]))).toBe(true);

		const update = await page.evaluate(() => window.dataLayer.find((e) => 'consent' === e[0] && 'update' === e[1])[2]);
		expect(update.ad_storage).toBe('granted');
		expect(update.ad_user_data).toBe('granted');
		expect(update.ad_personalization).toBe('granted');
		expect(update.analytics_storage).toBe('denied');
	});
});

test.describe('admin: Consent Mode setting', () => {
	test('switching to Advanced unblocks GTM and sets wait_for_update, and is reflected back in the form', async ({ page, context }) => {
		await page.goto('/wp-login.php');
		await page.fill('#user_login', 'admin');
		await page.fill('#user_pass', 'admin');
		await page.click('#wp-submit');
		await page.waitForURL('**/wp-admin/**');

		await page.goto('/wp-admin/admin.php?page=consentimental');
		await expect(page.locator('input[name="consent_mode_type"][value="basic"]')).toBeChecked();

		await page.check('input[name="consent_mode_type"][value="advanced"]');
		await page.click('button[name="cmp_save_consent_mode"]');
		await expect(page.locator('.notice-success')).toBeVisible();
		await expect(page.locator('input[name="consent_mode_type"][value="advanced"]')).toBeChecked();

		const frontPage = await context.newPage();
		await frontPage.goto('/');
		const html = await frontPage.content();
		expect(html).toContain('"waitForUpdate":500');

		await page.goto('/wp-admin/admin.php?page=consentimental');
		await page.check('input[name="consent_mode_type"][value="basic"]');
		await page.click('button[name="cmp_save_consent_mode"]');
		await expect(page.locator('.notice-success')).toBeVisible();
	});
});
