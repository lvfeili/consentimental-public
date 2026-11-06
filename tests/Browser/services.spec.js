// @ts-check
const { test, expect } = require('./offline-test');
const { execSync } = require('child_process');

const WP_CLI = process.env.CMP_WP_CLI || 'docker compose exec -T wpcli wp';
const path = require('path');


const REPO_ROOT = path.resolve(__dirname, '..', '..');
let shortcodePageId;

test.beforeAll(() => {
	const output = execSync(
		`${WP_CLI} post create --post_type=page --post_title="Services Shortcode Test" ` +
			'--post_content="[cmp_services]" --post_status=publish --porcelain --path=/var/www/html',
		{ cwd: REPO_ROOT },
	);
	shortcodePageId = output.toString().trim();
});

test.afterAll(() => {
	if (shortcodePageId) {
		execSync(`${WP_CLI} post delete ${shortcodePageId} --force --path=/var/www/html`, { cwd: REPO_ROOT });
	}
});

test('the [cmp_services] shortcode renders the live service/cookie table', async ({ page }) => {
	await page.goto(`/?page_id=${shortcodePageId}`);

	const table = page.locator('.cmp-services-table');
	await expect(table).toBeVisible();

	const rowCount = await table.locator('tbody tr').count();
	expect(rowCount).toBeGreaterThanOrEqual(14); // 3 Google + 11 other built-ins

	await expect(table).toContainText('Google Analytics 4');
	await expect(table).toContainText('Meta Pixel');
	await expect(table).toContainText('_ga');
});

test('a page WITHOUT the shortcode does not load the table stylesheet', async ({ page }) => {
	await page.goto('/');

	const hasStylesheet = await page.evaluate(
		() => !!document.querySelector('link[href*="services-table.css"]'),
	);
	expect(hasStylesheet).toBe(false);
});

test('the Consent Services block inserts, previews, and publishes the live table', async ({ page, context }) => {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**');

	await page.goto('/wp-admin/post-new.php?post_type=page');
	await page.waitForTimeout(1500);

	const closeModal = page.getByRole('button', { name: 'Close' });
	if (await closeModal.first().isVisible({ timeout: 5000 }).catch(() => false)) {
		await closeModal.first().click();
	}
	await page.waitForSelector('.components-modal__screen-overlay', { state: 'detached', timeout: 5000 }).catch(() => {});

	const editorFrame = page.frameLocator('iframe[name="editor-canvas"]');
	await editorFrame.getByRole('textbox', { name: /Add title/i }).click();
	await page.keyboard.type('Consent Services Block Test');

	await page.getByRole('button', { name: 'Block Inserter' }).click();
	await page.getByRole('searchbox').fill('Consent Services');
	await page.waitForSelector('button[role="option"]:has-text("Consent Services")');
	await page.click('button[role="option"]:has-text("Consent Services")');

	await editorFrame.locator('text=The service/cookie table is rendered live on the published page.').waitFor();

	await page.click('button:has-text("Publish")');
	const confirmPublish = page.locator('.editor-post-publish-panel').getByRole('button', { name: 'Publish', exact: true });
	if (await confirmPublish.isVisible({ timeout: 3000 }).catch(() => false)) {
		await confirmPublish.click();
	}
	await page.waitForSelector('text=is now live.');

	const viewLink = page.locator('.post-publish-panel__postpublish').getByRole('link', { name: /View Page/i });
	const href = await viewLink.getAttribute('href');
	expect(href).toBeTruthy();

	const frontPage = await context.newPage();
	await frontPage.goto(href);
	await expect(frontPage.locator('.cmp-services-table')).toBeVisible();
	const rowCount = await frontPage.locator('.cmp-services-table tbody tr').count();
	expect(rowCount).toBeGreaterThanOrEqual(14);

	const url = new URL(href);
	const postId = url.searchParams.get('page_id') || url.searchParams.get('p');
	if (postId) {
		execSync(`${WP_CLI} post delete ${postId} --force --path=/var/www/html`, { cwd: REPO_ROOT });
	}
});
