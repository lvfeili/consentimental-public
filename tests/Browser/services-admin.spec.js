// @ts-check
const { test, expect, publishDraft } = require('./offline-test');


const { execSync } = require('child_process');

const WP_CLI = process.env.CMP_WP_CLI || 'docker compose exec -T wpcli wp';
const path = require('path');

const REPO_ROOT = path.resolve(__dirname, '..', '..');

function cleanUpOptions() {
	for (const option of ['cmp_service_overrides', 'cmp_service_overrides_draft', 'cmp_services', 'cmp_services_draft', 'cmp_draft_meaningful']) {
		execSync(`${WP_CLI} option delete ${option} --path=/var/www/html || true`, { cwd: REPO_ROOT });
	}
}

test.beforeEach(async ({ page }) => {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**');
});

test.afterEach(() => {
	cleanUpOptions();
});

test('the Services submenu lists all 14 built-in services', async ({ page }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-services');

	const rowCount = await page.locator('table.widefat tbody tr').count();
	expect(rowCount).toBeGreaterThanOrEqual(14);
	await expect(page.locator('table.widefat')).toContainText('Matomo');
});

test('overriding a built-in service persists and is reflected in the registry (and the list)', async ({ page }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-services');

	const matomoRow = page.locator('tr', { hasText: 'Matomo' });
	await matomoRow.locator('a[href*="action=override"]').click();

	await page.fill('#cmp-o-domains', 'analytics.mysite.test');
	await page.click('button[name="cmp_save_override"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await page.goto('/wp-admin/admin.php?page=consentimental-services');
	const table = page.locator('table.widefat');
	await expect(table).toContainText('analytics.mysite.test');
	await expect(table).toContainText('overridden');
});

test('resetting an override removes it', async ({ page }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-services');
	const matomoRow = page.locator('tr', { hasText: 'Matomo' });
	await matomoRow.locator('a[href*="action=override"]').click();
	await page.fill('#cmp-o-domains', 'analytics.mysite.test');
	await page.click('button[name="cmp_save_override"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await page.goto('/wp-admin/admin.php?page=consentimental-services');
	await page.locator('tr', { hasText: 'Matomo' }).locator('a[href*="action=override"]').click();
	await page.click('button[name="cmp_reset_override"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await page.goto('/wp-admin/admin.php?page=consentimental-services');
	await expect(page.locator('table.widefat')).not.toContainText('analytics.mysite.test');
});

test('adding, then deleting, a custom service', async ({ page }) => {
	await page.goto('/wp-admin/admin.php?page=consentimental-services&action=add');
	await page.fill('#cmp-name', 'My Custom Tracker');
	await page.fill('#cmp-provider', 'Acme Inc');
	await page.selectOption('#cmp-category', 'analytics');
	await page.fill('#cmp-domains', 'tracker.acme.test');
	await page.fill('#cmp-cookies', '_acme_id|1 year|Distinguishes visitors');
	await page.click('button[name="cmp_save_custom_service"]');
	await expect(page.locator('.notice-success')).toBeVisible();

	await expect(page.locator('table.widefat')).toContainText('My Custom Tracker');

	await publishDraft(page);
	const front = await page.context().newPage();
	const pageId = execSync(
		`${WP_CLI} post create --post_type=page --post_title="Services Admin Test Docs" ` +
			'--post_content="[cmp_services]" --post_status=publish --porcelain --path=/var/www/html',
		{ cwd: REPO_ROOT },
	)
		.toString()
		.trim();
	await front.goto(`/?page_id=${pageId}`);
	await expect(front.locator('.cmp-services-table')).toContainText('My Custom Tracker');
	execSync(`${WP_CLI} post delete ${pageId} --force --path=/var/www/html`, { cwd: REPO_ROOT });

	await page.goto('/wp-admin/admin.php?page=consentimental-services');
	const row = page.locator('tr', { hasText: 'My Custom Tracker' });
	page.on('dialog', (dialog) => dialog.accept());
	await row.locator('button:has-text("Delete")').click();
	await expect(page.locator('.notice-success')).toBeVisible();
	await expect(page.locator('table.widefat')).not.toContainText('My Custom Tracker');
});
