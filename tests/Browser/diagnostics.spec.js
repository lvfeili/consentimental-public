// @ts-check
const { test, expect } = require('./offline-test');


test('is not present for an anonymous visitor', async ({ page }) => {
	await page.goto('/');
	await expect(page.locator('#cmp-debug-toggle')).toHaveCount(0);
});

test.describe('as a logged-in administrator', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('/wp-login.php');
		await page.fill('#user_login', 'admin');
		await page.fill('#user_pass', 'admin');
		await page.click('#wp-submit');
		await page.waitForURL('**/wp-admin/**');
	});

	test('the panel is usable even while the mandatory banner is still showing', async ({ page }) => {
		await page.goto('/');
		await expect(page.locator('#cmp-banner')).toBeVisible();

		await page.click('#cmp-debug-toggle');
		await expect(page.locator('#cmp-debug-panel')).toBeVisible();
	});

	test('shows fresh-visitor consent state, Consent Mode signals, and the blocked GTM script', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-debug-toggle');

		const consentRows = await page.locator('#cmp-debug-consent tr').allTextContents();
		expect(consentRows.some((r) => r.includes('Necessary') && r.includes('ACTIVE'))).toBe(true);
		expect(consentRows.some((r) => r.includes('Analytics') && r.includes('DENIED'))).toBe(true);

		await expect(page.locator('#cmp-debug-config')).toContainText('yes'); // needs consent

		await expect(page.locator('#cmp-debug-consent-mode-section')).toBeVisible();
		const consentModeRows = await page.locator('#cmp-debug-consent-mode tr').allTextContents();
		expect(consentModeRows.some((r) => r.includes('analytics_storage') && r.includes('denied'))).toBe(true);

		const blockedItems = await page.locator('#cmp-debug-blocked li').allTextContents();
		expect(blockedItems.some((t) => t.includes('SCRIPT') && t.includes('google-tag-manager'))).toBe(true);

		const releasedItems = await page.locator('#cmp-debug-released li').allTextContents();
		expect(releasedItems).toEqual(['(none)']);
	});

	test('self-test: simulating Marketing consent releases GTM and updates the panel live', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-debug-toggle');

		await page.click('[data-cmp-debug-action="marketing"]');

		await expect
			.poll(() => page.locator('#cmp-debug-released li').allTextContents())
			.toEqual(expect.arrayContaining([expect.stringContaining('google-tag-manager')]));

		const blocked = await page.locator('#cmp-debug-blocked li').allTextContents();
		expect(blocked.some((t) => t.includes('google-tag-manager'))).toBe(false);

		const consentRows = await page.locator('#cmp-debug-consent tr').allTextContents();
		expect(consentRows.some((r) => r.includes('Marketing') && r.includes('ACTIVE'))).toBe(true);
	});

	test('self-test: Reset consent reopens the banner for real', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="accept-all"]');
		await expect(page.locator('#cmp-banner')).toBeHidden();

		await page.click('#cmp-debug-toggle');
		await page.click('[data-cmp-debug-action="reset"]');

		await expect(page.locator('#cmp-banner')).toBeVisible();
		await expect.poll(() => page.evaluate(() => window.CMP.needsConsent())).toBe(true);
	});

	test('self-test: Reopen banner previews the banner without discarding real consent', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="accept-all"]');
		await expect(page.locator('#cmp-banner')).toBeHidden();

		await page.click('#cmp-debug-toggle');
		await page.click('[data-cmp-debug-action="reopen-banner"]');

		await expect(page.locator('#cmp-banner')).toBeVisible();
		await expect.poll(() => page.evaluate(() => window.CMP.needsConsent())).toBe(false);
	});
});
