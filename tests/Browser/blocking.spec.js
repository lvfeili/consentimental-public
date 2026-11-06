// @ts-check
const { test, expect } = require('./offline-test');


test.describe('fresh visitor', () => {
	test('nothing consent-dependent has executed yet, and iframes show a placeholder', async ({ page }) => {
		await page.goto('/');

		await expect.poll(() => page.evaluate(() => window.cmpTestExternalRan)).toBeUndefined();
		await expect.poll(() => page.evaluate(() => window.cmpTestInlineRan)).toBeUndefined();
		await expect(page.locator('#cmp-test-raw-iframe')).toBeHidden();

		const placeholder = page.locator('#cmp-test-raw-iframe + .cmp-embed-placeholder p');
		await expect(placeholder).toBeVisible();
		await expect(placeholder).toContainText('Test Provider');
		await expect(placeholder).toContainText('Marketing');
	});
});

test.describe('granting only Analytics', () => {
	test('releases the analytics-gated script but leaves marketing-gated content blocked', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="customize"]');
		await page.click('label[for="cmp-category-analytics"]');
		await page.click('#cmp-customize [data-cmp-action="save"]');

		await expect.poll(() => page.evaluate(() => window.cmpTestExternalRan)).toBe(true);
		await expect.poll(() => page.evaluate(() => window.cmpTestInlineRan)).toBeUndefined();
		await expect(page.locator('#cmp-test-raw-iframe')).toBeHidden();
	});
});

test.describe('one-click enable from a placeholder', () => {
	test('releases every element tagged with that service, but not the whole category', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="reject-all"]');
		await expect(page.locator('#cmp-banner')).toBeHidden();

		await page.click('#cmp-test-raw-iframe + .cmp-embed-placeholder button:has-text("Enable Test Embed")');

		await expect(page.locator('#cmp-test-raw-iframe')).toBeVisible();
		await expect(page.locator('#cmp-test-raw-iframe + .cmp-embed-placeholder')).toHaveCount(0);
		await expect(page.locator('#cmp-test-raw-iframe')).toHaveAttribute('src', 'https://test-embed-domain.test/embed');

		const rawScriptReleased = await page.evaluate(() => {
			var el = document.querySelector('script[src="https://test-embed-domain.test/raw.js"]');

			return !!el && !el.hasAttribute('data-cmp-category');
		});
		expect(rawScriptReleased).toBe(true);

		await expect.poll(() => page.evaluate(() => window.cmpTestInlineRan)).toBeUndefined();
	});
});

test.describe('Reject all', () => {
	test('everything stays blocked, including after a reload', async ({ page }) => {
		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="reject-all"]');
		await page.reload();

		await expect.poll(() => page.evaluate(() => window.cmpTestExternalRan)).toBeUndefined();
		await expect.poll(() => page.evaluate(() => window.cmpTestInlineRan)).toBeUndefined();
		await expect(page.locator('#cmp-test-raw-iframe')).toBeHidden();
	});

	test('no browser console errors occur across the full accept/release flow', async ({ page }) => {
		const errors = [];
		page.on('console', (msg) => {
			if ('error' === msg.type() && !msg.text().includes('Failed to load resource')) {
				errors.push(msg.text());
			}
		});
		page.on('pageerror', (err) => errors.push(err.message));

		await page.goto('/');
		await page.click('#cmp-banner [data-cmp-action="reject-all"]');
		await page.click('#cmp-floating-button');
		await page.click('#cmp-customize [data-cmp-action="accept-all"]');
		await expect.poll(() => page.evaluate(() => window.cmpTestExternalRan && window.cmpTestInlineRan)).toBeTruthy();

		expect(errors).toEqual([]);
	});
});
