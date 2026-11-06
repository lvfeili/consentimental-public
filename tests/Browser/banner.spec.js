// @ts-check
const { test, expect } = require('./offline-test');


test.describe('fresh visitor', () => {
	test('sees the banner, and only Necessary is active', async ({ page }) => {
		await page.goto('/');

		await expect(page.locator('#cmp-banner')).toBeVisible();
		await expect(page.locator('#cmp-customize')).toBeHidden();
		await expect(page.locator('#cmp-floating-button')).toBeHidden();

		await expect.poll(() => page.evaluate(() => window.CMP.needsConsent())).toBe(true);
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('necessary'))).toBe(true);
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('analytics'))).toBe(false);
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);
	});

	test('Customize does not pre-enable any optional category', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="customize"]');
		await expect(page.locator('#cmp-customize')).toBeVisible();

		await expect(page.locator('#cmp-category-necessary')).toBeChecked();
		await expect(page.locator('#cmp-category-necessary')).toBeDisabled();
		await expect(page.locator('#cmp-category-functional')).not.toBeChecked();
		await expect(page.locator('#cmp-category-analytics')).not.toBeChecked();
		await expect(page.locator('#cmp-category-marketing')).not.toBeChecked();
	});
});

test.describe('Accept all', () => {
	test('grants every category, hides the banner, and persists', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="accept-all"]');

		await expect(page.locator('#cmp-banner')).toBeHidden();
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('analytics'))).toBe(true);
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(true);

		const cookie = (await page.context().cookies()).find((c) => c.name === 'cmp_consent');
		expect(cookie).toBeTruthy();

		await page.reload();
		await expect(page.locator('#cmp-banner')).toBeHidden();
		await expect.poll(() => page.evaluate(() => window.CMP.needsConsent())).toBe(false);
	});
});

test.describe('Reject all', () => {
	test('denies every optional category while Necessary stays active', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="reject-all"]');

		await expect(page.locator('#cmp-banner')).toBeHidden();
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('necessary'))).toBe(true);
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('analytics'))).toBe(false);
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);
	});
});

test.describe('Customize: analytics only', () => {
	test('grants analytics without granting marketing', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="customize"]');
		await page.click('label[for="cmp-category-analytics"]');
		await page.click('#cmp-customize [data-cmp-action="save"]');

		await expect(page.locator('#cmp-customize')).toBeHidden();
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('analytics'))).toBe(true);
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);
	});
});

test.describe('withdrawal', () => {
	test('reopening settings reflects prior choices, and withdrawing takes effect immediately', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="accept-all"]');
		await expect(page.locator('#cmp-banner')).toBeHidden();
		await expect(page.locator('#cmp-floating-button')).toBeVisible();

		await page.click('#cmp-floating-button');
		await expect(page.locator('#cmp-customize')).toBeVisible();
		await expect(page.locator('#cmp-category-marketing')).toBeChecked();

		await page.click('label[for="cmp-category-marketing"]');
		await page.click('#cmp-customize [data-cmp-action="save"]');

		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('analytics'))).toBe(true);
	});
});

test.describe('accessibility', () => {
	test('opening Customize traps focus inside the dialog', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="customize"]');

		const focusInDialog = await page.evaluate(
			() => !!document.activeElement && document.getElementById('cmp-customize').contains(document.activeElement),
		);
		expect(focusInDialog).toBe(true);
	});

	test('Escape closes the dialog without applying unsaved changes', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="customize"]');
		await page.click('label[for="cmp-category-marketing"]');
		await page.keyboard.press('Escape');

		await expect(page.locator('#cmp-customize')).toBeHidden();
		await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);
	});
});
