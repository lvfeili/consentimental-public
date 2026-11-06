// @ts-check
const { test, expect, publishDraft } = require('./offline-test');
const { execSync } = require('child_process');

const WP_CLI = process.env.CMP_WP_CLI || 'docker compose exec -T wpcli wp';
const path = require('path');


const REPO_ROOT = path.resolve(__dirname, '..', '..');

test.beforeEach(async ({ page }) => {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**');
});

test.afterEach(() => {
	for (const option of ['cmp_custom_scripts', 'cmp_custom_scripts_draft', 'cmp_service_overrides', 'cmp_draft_meaningful']) {
		execSync(`${WP_CLI} option delete ${option} --path=/var/www/html || true`, { cwd: REPO_ROOT });
	}
});

test('an inline, whole-site script is output, gated, and released on consent', async ({ page, context }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-scripts&action=add');
	await page.fill('#cmp-s-name', 'My Inline Tracker');
	await page.check('input[name="type"][value="inline"]');
	await page.fill('#cmp-s-code', 'window.myInlineTrackerRan = true;');
	await page.selectOption('#cmp-s-placement', 'footer');
	await page.selectOption('#cmp-s-category', 'analytics');
	await page.selectOption('#cmp-s-condition-type', 'whole_site');
	await page.click('button[name="cmp_save_script"]');
	await expect(page.locator('.notice-success')).toBeVisible();
	await expect(page.locator('table.widefat')).toContainText('My Inline Tracker');

	await publishDraft(page);
	const front = await context.newPage();
	await front.goto('/', { waitUntil: 'networkidle' });

	await expect.poll(() => front.evaluate(() => window.myInlineTrackerRan)).toBeUndefined();

	await front.click('#cmp-banner [data-cmp-action="customize"]');
	await front.click('label[for="cmp-category-analytics"]');
	await front.click('#cmp-customize [data-cmp-action="save"]');
	await expect.poll(() => front.evaluate(() => window.myInlineTrackerRan)).toBe(true);
});

test('a head-placed inline script is released on consent (blocking.js must scan <head>, not just <body>)', async ({ page, context }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-scripts&action=add');
	await page.fill('#cmp-s-name', 'My Head Tracker');
	await page.check('input[name="type"][value="inline"]');
	await page.fill('#cmp-s-code', 'window.myHeadTrackerRan = true;');
	await page.selectOption('#cmp-s-placement', 'head');
	await page.selectOption('#cmp-s-category', 'analytics');
	await page.selectOption('#cmp-s-condition-type', 'whole_site');
	await page.click('button[name="cmp_save_script"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await publishDraft(page);
	const front = await context.newPage();
	await front.goto('/', { waitUntil: 'networkidle' });

	await expect.poll(() => front.evaluate(() => window.myHeadTrackerRan)).toBeUndefined();

	await front.click('#cmp-banner [data-cmp-action="customize"]');
	await front.click('label[for="cmp-category-analytics"]');
	await front.click('#cmp-customize [data-cmp-action="save"]');
	await expect.poll(() => front.evaluate(() => window.myHeadTrackerRan)).toBe(true);
});

test('two head-placed scripts sharing a service (one external URL, one inline) both release on consent', async ({ page, context }) => {
	execSync(`${WP_CLI} option update cmp_service_overrides '{"google-analytics":{"active":true}}' --format=json --path=/var/www/html`, { cwd: REPO_ROOT });
	await page.goto('/wp-admin/admin.php?page=consentimental-scripts&action=add');
	await page.fill('#cmp-s-name', 'GA4 loader (repro)');
	await page.fill('#cmp-s-url', 'https://example.test/gtag/js?id=G-TEST');
	await page.selectOption('#cmp-s-placement', 'head');
	await page.selectOption('#cmp-s-category', 'analytics');
	await page.selectOption('#cmp-s-service', 'google-analytics');
	await page.click('button[name="cmp_save_script"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await page.goto('/wp-admin/admin.php?page=consentimental-scripts&action=add');
	await page.fill('#cmp-s-name', 'GA4 config (repro)');
	await page.check('input[name="type"][value="inline"]');
	await page.fill('#cmp-s-code', 'window.myGa4ConfigRan = true;');
	await page.selectOption('#cmp-s-placement', 'head');
	await page.selectOption('#cmp-s-category', 'analytics');
	await page.selectOption('#cmp-s-service', 'google-analytics');
	await page.click('button[name="cmp_save_script"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await publishDraft(page);
	const front = await context.newPage();
	await front.goto('/', { waitUntil: 'networkidle' });

	await expect.poll(() => front.evaluate(() => window.myGa4ConfigRan)).toBeUndefined();

	await front.click('#cmp-banner [data-cmp-action="customize"]');
	await front.click('label[for="cmp-category-analytics"]');
	await front.click('#cmp-customize [data-cmp-action="save"]');

	await expect.poll(() => front.evaluate(() => window.myGa4ConfigRan)).toBe(true);
	await expect.poll(() => front.evaluate(() =>
		document.querySelectorAll('[data-cmp-service="google-analytics"]').length
	)).toBe(0);
});

test('a URL-pattern-scoped script only loads on matching pages', async ({ page, context }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-scripts&action=add');
	await page.fill('#cmp-s-name', 'Contact Page Pixel');
	await page.fill('#cmp-s-url', 'https://tracker.example.test/pixel.js');
	await page.selectOption('#cmp-s-placement', 'head');
	await page.selectOption('#cmp-s-category', 'marketing');
	await page.selectOption('#cmp-s-condition-type', 'url_pattern');
	await page.fill('input[name="condition_value"]', '*contact*');
	await page.click('button[name="cmp_save_script"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await publishDraft(page);
	const front = await context.newPage();

	await front.goto('/', { waitUntil: 'networkidle' });
	expect(await front.content()).not.toContain('tracker.example.test');

	await front.goto('/?pagename=my-contact-page', { waitUntil: 'networkidle' });
	const html = await front.content();
	expect(html).toContain('tracker.example.test');
	expect(html).toContain('data-cmp-category="marketing"');
	expect(html).toContain('type="text/plain"');
});

test('deleting a script removes it', async ({ page }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-scripts&action=add');
	await page.fill('#cmp-s-name', 'Temp Script');
	await page.check('input[name="type"][value="inline"]');
	await page.fill('#cmp-s-code', 'console.log(1);');
	await page.click('button[name="cmp_save_script"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	page.once('dialog', (dialog) => dialog.accept());
	await page.locator('tr', { hasText: 'Temp Script' }).locator('button:has-text("Delete")').click();
	await page.waitForLoadState('networkidle');

	await page.goto('/wp-admin/admin.php?page=consentimental-scripts');
	await expect(page.locator('body')).toContainText('No custom scripts yet.');
});
