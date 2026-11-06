// @ts-check
const { test, expect } = require('./offline-test');
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
	execSync(`${WP_CLI} option delete cmp_settings --path=/var/www/html || true`, { cwd: REPO_ROOT });
});

test('the appearance section saves and is reflected on the live banner', async ({ page, context }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-banner');

	await page.evaluate(() => window.jQuery('#cmp-color-bg').wpColorPicker('color', '#123456'));
	await page.fill('#cmp-banner-radius', '20');
	await page.selectOption('#cmp-banner-position', 'top_bar');
	await page.selectOption('#cmp-button-shape', 'pill');
	await page.fill('#cmp-banner-font-size', '18');
	await page.click('button[name="cmp_save_banner"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await expect(page.locator('#cmp-color-bg')).toHaveValue('#123456');
	await expect(page.locator('#cmp-banner-radius')).toHaveValue('20');
	await expect(page.locator('#cmp-banner-position')).toHaveValue('top_bar');

	const front = await context.newPage();
	await front.goto('/', { waitUntil: 'networkidle' });

	await expect(front.locator('#cmp-banner-style')).toHaveCount(1);
	const css = await front.locator('#cmp-banner-style').textContent();
	expect(css).toContain('--cmp-color-bg: #123456;');
	expect(css).toContain('--cmp-radius: 20px;');
	expect(css).toContain('--cmp-btn-radius: 999px;');
	expect(css).toContain('--cmp-font-size: 18px;');

	await expect(front.locator('#cmp-banner')).toHaveAttribute('data-cmp-position', 'top_bar');
	await expect
		.poll(() => front.evaluate(() => getComputedStyle(document.getElementById('cmp-banner').querySelector('.cmp-banner__panel')).backgroundColor))
		.toBe('rgb(18, 52, 86)');
});

test('the preview panel shows the logo once one is set', async ({ page }) => {
	const logoUrl = (process.env.CMP_TEST_URL || 'http://localhost:8080') + '/wp-includes/images/w-logo-blue.png';

	execSync(
		`${WP_CLI} option update cmp_settings '${JSON.stringify({ banner_logo_url: logoUrl })}' --format=json --path=/var/www/html`,
		{ cwd: REPO_ROOT },
	);

	await page.goto('/wp-admin/admin.php?page=consentimental-banner');

	await expect(page.locator('#cmp-preview-logo')).toBeVisible();
	await expect(page.locator('#cmp-preview-logo')).toHaveAttribute('src', logoUrl);
});

test('logo width and custom CSS are saved and applied on the live banner', async ({ page, context }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-banner');

	await page.fill('#cmp-banner-logo-width', '220');
	await page.fill('#cmp-custom-css', '.cmp-banner__panel { border: 3px solid rgb(255, 0, 0); }');
	await page.click('button[name="cmp_save_banner"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await expect(page.locator('#cmp-banner-logo-width')).toHaveValue('220');
	await expect(page.locator('#cmp-custom-css')).toHaveValue('.cmp-banner__panel { border: 3px solid rgb(255, 0, 0); }');

	const front = await context.newPage();
	await front.goto('/', { waitUntil: 'networkidle' });

	const css = await front.locator('#cmp-banner-style').textContent();
	expect(css).toContain('--cmp-logo-width: 220px;');
	expect(css).toContain('.cmp-banner__panel { border: 3px solid rgb(255, 0, 0); }');

	await expect
		.poll(() => front.evaluate(() => getComputedStyle(document.getElementById('cmp-banner').querySelector('.cmp-banner__panel')).borderColor))
		.toBe('rgb(255, 0, 0)');
});

test('leaving appearance fields blank falls back to the built-in defaults', async ({ page, context }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-banner');
	await page.click('button[name="cmp_save_banner"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	const front = await context.newPage();
	await front.goto('/', { waitUntil: 'networkidle' });

	await expect(front.locator('#cmp-banner-style')).toHaveCount(0);
	await expect(front.locator('#cmp-banner')).toHaveAttribute('data-cmp-position', 'bottom_bar');
});
