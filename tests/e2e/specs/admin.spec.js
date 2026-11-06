// @ts-check
const { test, expect } = require('@playwright/test');
const { observe, fixture } = require('./helpers');

test.describe.configure({ mode: 'serial' });

let admin;

test.beforeAll(async ({ browser }) => {
	await fixture({ reset: true, bump: true });
	const context = await browser.newContext();
	await observe(context);
	admin = await context.newPage();
	await admin.goto('/wp-login.php');
	await admin.fill('#user_login', 'admin');
	await admin.fill('#user_pass', 'admin');
	await admin.click('#wp-submit');
	await admin.waitForURL('**/wp-admin/**');
});

test.afterAll(async () => {
	await admin.context().close();
	await fixture({ reset: true, bump: true });
});

const setup = (step) => `/wp-admin/admin.php?page=consentimental-setup&step=${step}`;

test('exports are refused without an administrator session or a valid nonce', async ({ playwright }) => {
	const anonymous = await playwright.request.newContext({ baseURL: process.env.CMP_E2E_URL || 'http://localhost:8891' });
	const response = await anonymous.get('/wp-admin/admin-post.php?action=cmp_export_consent_log&format=csv', { maxRedirects: 0 });
	expect(response.headers()['content-disposition'] || '').not.toContain('attachment');
	await anonymous.dispose();

	const forged = await admin.request.get('/wp-admin/admin-post.php?action=cmp_export_consent_log&format=csv&_wpnonce=invalid');
	expect(forged.status()).toBe(403);
	expect(forged.headers()['content-disposition'] || '').not.toContain('attachment');
});

test('discover: the scan reports what it found and what it cannot prove', async () => {
	await admin.goto(setup('discover'));
	await expect(admin.getByRole('navigation', { name: 'Setup steps' }).locator('[aria-current="step"]')).toHaveText('Discover');
	await expect(admin.getByRole('link', { name: 'Skip to all settings' })).toBeVisible();
	await expect(admin.locator('.cmp-setup')).toContainText('does not show that tracking is blocked at runtime');

	await admin.getByRole('button', { name: /Scan my site|Scan again/ }).click();
	await expect(admin.getByRole('status').filter({ hasText: 'Scan finished' })).toBeVisible();
	await expect(admin.locator('#cmp-step-heading')).toBeFocused();
	await expect(admin.locator('.cmp-panel')).toContainText(/\d+ known services detected/);
});

test('review: detected, confirmed and unverified are distinguished; confirming stages the change', async () => {
	await admin.goto(setup('review'));
	const widget = admin.locator('[data-cmp-service-row="e2e-widget"]');
	await expect(widget).toContainText('Detected by scan, not confirmed');
	await expect(admin.locator('[data-cmp-service-row="e2e-analytics"]')).toContainText('Detected by scan, confirmed by you');

	await widget.getByRole('checkbox', { name: 'In use — E2E Chat Widget' }).check();
	await expect(widget.getByRole('combobox', { name: 'Category for E2E Chat Widget' })).toHaveValue('functional');
	await admin.getByRole('button', { name: 'Save decisions' }).click();

	await expect(admin.getByRole('status').filter({ hasText: 'staged until you publish' })).toBeVisible();
	await expect(admin.locator('[data-cmp-service-row="e2e-widget"]')).toContainText('Detected by scan, confirmed by you');
});

test('problems: findings explain problem, impact and next step for the draft', async () => {
	await admin.goto(setup('problems'));
	const disclosure = admin.locator('[data-cmp-finding="service-disclosure"]');
	await expect(disclosure).toHaveAttribute('data-cmp-status', 'warning');
	await expect(disclosure).toContainText('E2E Chat Widget');
	for (const term of ['Problem', 'Impact', 'Next step', 'Checked:', 'Scope:']) {
		await expect(disclosure).toContainText(term);
	}
	await expect(admin.locator('[data-cmp-finding="scan-unconfirmed"]')).toHaveAttribute('data-cmp-status', 'passed');

	await disclosure.getByRole('link', { name: 'Edit E2E Chat Widget' }).click();
	await admin.getByLabel('Purpose').fill('Live chat with our support team');
	await admin.getByLabel('Privacy policy URL').fill('https://widget.cmp-e2e.test/privacy');
	await admin.getByRole('button', { name: 'Save', exact: true }).click();
	await expect(admin.locator('.notice-success')).toBeVisible();

	await admin.goto(setup('problems'));
	await expect(admin.locator('[data-cmp-finding="service-disclosure"]')).toHaveAttribute('data-cmp-status', 'passed');
});

test('banner: wording and privacy policy link', async () => {
	await admin.goto(setup('banner'));
	const preview = admin.locator('#cmp-preview-viewport');
	await expect(preview).toBeVisible();
	await expect(preview.locator('.cmp-btn')).toHaveCount(3);
	await expect(preview.locator('#cmp-preview-policy-links')).toContainText('Privacy policy');

	await admin.getByLabel('Banner text').fill('Live preview text');
	await expect(preview.locator('#cmp-preview-text')).toHaveText('Live preview text');
	await admin.getByLabel('Banner text').fill('');
	await expect(preview.locator('#cmp-preview-text')).toContainText('We use cookies');

	await admin.getByLabel('Banner title').fill('E2E Privacy Choices');
	await expect(preview.locator('#cmp-preview-title')).toHaveText('E2E Privacy Choices');
	await expect(admin.getByLabel('Privacy policy page linked from the banner')).toBeVisible();
	await admin.getByRole('button', { name: 'Save banner' }).click();
	await expect(admin.getByRole('status').filter({ hasText: 'Saved' })).toBeVisible();
	await expect(preview.locator('#cmp-preview-title')).toHaveText('E2E Privacy Choices');

	await admin.goto('/wp-admin/admin.php?page=consentimental-banner');
	await expect(admin.locator('#cmp-preview-title')).toHaveText('E2E Privacy Choices');
	await admin.locator('#cmp-banner-title').fill('Changed on the Banner screen');
	await expect(admin.locator('#cmp-preview-title')).toHaveText('Changed on the Banner screen');
});

test('preview shows staged services and saves nothing; publish applies them', async () => {
	await admin.goto(setup('publish'));
	const previewLink = admin.getByRole('link', { name: /Preview the banner/ });
	const href = await previewLink.getAttribute('href');
	expect(href).toContain('cmp_preview=1');

	const preview = await admin.context().newPage();
	await preview.goto(href);
	await expect(preview.locator('.cmp-preview-note')).toBeVisible();
	await expect(preview.locator('#cmp-banner')).toContainText('E2E Privacy Choices');
	await preview.locator('#cmp-banner [data-cmp-action="customize"]').click();
	await expect(preview.locator('#cmp-customize')).toContainText('E2E Chat Widget');
	await preview.locator('#cmp-customize [data-cmp-action="accept-all"]').click();
	expect((await admin.context().cookies()).some((c) => 'cmp_consent' === c.name)).toBe(false);
	await preview.close();

	await expect(admin.locator('.cmp-panel')).toContainText('will see the banner again');
	await admin.getByRole('button', { name: 'Publish configuration' }).click();
	await expect(admin.getByRole('status').filter({ hasText: 'Published' })).toBeVisible();
});

test('a visitor decision is linked to the snapshot and language it was shown', async ({ browser }) => {
	const visitorContext = await browser.newContext();
	await observe(visitorContext);
	const visitor = await visitorContext.newPage();
	await visitor.goto('/');
	await expect(visitor.locator('#cmp-banner')).toContainText('E2E Privacy Choices');
	await expect(visitor.locator('#cmp-customize')).toContainText('Live chat with our support team');
	const disclosure = await visitor.evaluate(() => window.cmpConfig.disclosure.id);
	const recorded = visitor.waitForResponse((r) => r.url().includes('/consentimental/v1/consent') && 'POST' === r.request().method());
	await visitor.locator('#cmp-banner [data-cmp-action="accept-all"]').click();
	expect((await recorded).status()).toBe(200);
	await visitorContext.close();

	await admin.goto('/wp-admin/admin.php?page=consentimental-consent-log');
	const link = admin.locator('table.widefat').first().locator(`a[href*="snapshot=${disclosure}"]`).first();
	await expect(link).toBeVisible();
	await link.click();
	await expect(admin.locator('h1')).toHaveText('Disclosure snapshot');
	await expect(admin.locator('.cmp-wrap')).toContainText(disclosure);
	await expect(admin.locator('.cmp-snapshot')).toContainText('E2E Privacy Choices');
	await expect(admin.locator('.cmp-wrap')).toContainText('E2E Chat Widget');
	await expect(admin.locator('.cmp-wrap')).not.toContainText('no longer matches');

	await admin.goto('/wp-admin/admin.php?page=consentimental-consent-log');
	const csvHref = await admin.getByRole('link', { name: 'Export all records (CSV)' }).getAttribute('href');
	const csv = await admin.request.get(csvHref);
	expect(csv.headers()['content-disposition']).toContain('attachment');
	const csvText = await csv.text();
	expect(csvText.split('\n')[0]).toContain('disclosure_hash');
	expect(csvText).toContain(disclosure);

	const jsonHref = await admin.getByRole('link', { name: 'Export with disclosure snapshots (JSON)' }).getAttribute('href');
	const json = await (await admin.request.get(jsonHref)).json();
	expect(json.meta.notes.join(' ')).toContain('client assertions');
	expect(json.snapshots.map((s) => s.snapshot_hash)).toContain(disclosure);
});

test('verify: an administrator report is recorded as such, and vendor receipt stays unchecked', async () => {
	await admin.goto(setup('verify'));
	const form = admin.locator('#cmp-verify-browser');
	for (const box of await form.getByRole('checkbox').all()) {
		await box.check();
	}
	await form.getByRole('button', { name: 'Record result' }).click();
	await expect(admin.getByRole('status').filter({ hasText: 'Verification result recorded' })).toBeVisible();

	await admin.goto('/wp-admin/admin.php?page=consentimental-status');
	const browserFinding = admin.locator('[data-cmp-finding="browser-enforcement"]');
	await expect(browserFinding).toHaveAttribute('data-cmp-status', 'passed');
	await expect(browserFinding).toContainText('Reported by an administrator');
	await expect(admin.locator('[data-cmp-finding="vendor-receipt"]')).toHaveAttribute('data-cmp-status', 'not_checked');
	await expect(admin.locator('.cmp-summary')).not.toHaveClass(/cmp-summary--passed/);
	for (const heading of ['1. Configuration validation', '2. Discovery', '3. Proxy and upstream availability', '4. Consent enforcement in the browser', '5. Vendor-side receipt']) {
		await expect(admin.getByRole('heading', { name: heading })).toBeVisible();
	}
});

test('admin screens fit a 375px screen, also right-to-left', async () => {
	await admin.setViewportSize({ width: 375, height: 800 });
	for (const url of [setup('review'), setup('problems'), setup('banner'), '/wp-admin/admin.php?page=consentimental-banner', '/wp-admin/admin.php?page=consentimental-status']) {
		for (const rtl of ['', '&cmp_e2e_rtl=1']) {
			await admin.goto(url + rtl);
			if (rtl) {
				await expect(admin.locator('html')).toHaveAttribute('dir', 'rtl');
			}
			const overflow = await admin.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
			expect(overflow, url + rtl).toBeLessThanOrEqual(1);
		}
	}
});
