// @ts-check
const { test, expect } = require('./offline-test');

async function expandServices(page, categorySlug) {
	const category = page.locator('.cmp-customize__category', {
		has: page.locator(`[data-cmp-category-toggle="${categorySlug}"]`),
	});
	await category.locator('summary').click();
}


test('fresh visitor: no service is pre-selected, and each category lists its own services', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="customize"]');
	await page.waitForSelector('#cmp-customize:not([hidden])');

	await expect(page.locator('#cmp-service-google-analytics')).not.toBeChecked();
	await expect(page.locator('#cmp-service-google-tag-manager')).not.toBeChecked();

	const marketingServiceCount = await page.locator('[data-cmp-service-category="marketing"]').count();
	expect(marketingServiceCount).toBeGreaterThanOrEqual(5);
});

test('toggling one service makes its category checkbox indeterminate, not checked or unchecked', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="customize"]');

	await expandServices(page, 'marketing');
	await page.click('label[for="cmp-service-google-tag-manager"]');
	await expect(page.locator('#cmp-service-google-tag-manager')).toBeChecked();

	const indeterminate = await page.locator('#cmp-category-marketing').evaluate((el) => el.indeterminate);
	expect(indeterminate).toBe(true);
});

test('the category checkbox cascades to all its services in both directions', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="customize"]');

	const marketingCheckedStates = () =>
		page.locator('[data-cmp-service-category="marketing"]').evaluateAll((els) => els.map((el) => el.checked));

	await page.click('label[for="cmp-category-marketing"]');
	const checkedStates = await marketingCheckedStates();
	expect(checkedStates.length).toBeGreaterThan(0);
	expect(checkedStates.every(Boolean)).toBe(true);

	await page.click('label[for="cmp-category-marketing"]');
	const uncheckedStates = await marketingCheckedStates();
	expect(uncheckedStates.every((checked) => !checked)).toBe(true);
});

test('saving a mixed selection grants exactly the checked services, not the whole category', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="customize"]');

	await expandServices(page, 'marketing');
	await page.click('label[for="cmp-service-google-tag-manager"]');
	await expandServices(page, 'analytics');
	await page.click('label[for="cmp-service-google-analytics"]'); // different category
	await page.click('#cmp-customize [data-cmp-action="save"]');
	await page.waitForFunction(() => document.getElementById('cmp-customize').hidden === true);

	await expect.poll(() => page.evaluate(() => window.CMP.hasServiceConsent('google-tag-manager'))).toBe(true);
	await expect.poll(() => page.evaluate(() => window.CMP.hasServiceConsent('meta-pixel'))).toBe(false);
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);
	await expect.poll(() => page.evaluate(() => window.CMP.hasServiceConsent('google-analytics'))).toBe(true);
});

test('reopening the dialog shows the exact granular selection, including indeterminate categories', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="customize"]');
	await expandServices(page, 'marketing');
	await page.click('label[for="cmp-service-google-tag-manager"]');
	await page.click('#cmp-customize [data-cmp-action="save"]');
	await page.waitForFunction(() => document.getElementById('cmp-customize').hidden === true);

	await page.click('#cmp-floating-button');
	await page.waitForSelector('#cmp-customize:not([hidden])');

	await expect(page.locator('#cmp-service-google-tag-manager')).toBeChecked();
	await expect(page.locator('#cmp-service-meta-pixel')).not.toBeChecked();
	const indeterminate = await page.locator('#cmp-category-marketing').evaluate((el) => el.indeterminate);
	expect(indeterminate).toBe(true);
});

test('withdrawing a whole category also clears any service-level override within it', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="customize"]');
	await expandServices(page, 'marketing');
	await page.click('label[for="cmp-service-google-tag-manager"]');
	await page.click('#cmp-customize [data-cmp-action="save"]');
	await page.waitForFunction(() => document.getElementById('cmp-customize').hidden === true);
	await expect.poll(() => page.evaluate(() => window.CMP.hasServiceConsent('google-tag-manager'))).toBe(true);

	await page.click('#cmp-floating-button');
	await page.click('label[for="cmp-category-marketing"]');
	await page.click('label[for="cmp-category-marketing"]');
	await page.click('#cmp-customize [data-cmp-action="save"]');

	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);
	await expect.poll(() => page.evaluate(() => window.CMP.hasServiceConsent('google-tag-manager'))).toBe(false);
});

test('Accept All / Reject All set every individual service explicitly, not just categories', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="accept-all"]');
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(true);

	const allOn = await page.evaluate(() => Object.keys(window.cmpConfig.services).every((id) => window.CMP.hasServiceConsent(id)));
	expect(allOn).toBe(true);

	await page.click('#cmp-floating-button');
	await page.click('#cmp-customize [data-cmp-action="reject-all"]');
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);

	const allOff = await page.evaluate(() => Object.keys(window.cmpConfig.services).every((id) => !window.CMP.hasServiceConsent(id)));
	expect(allOff).toBe(true);
});

test('a category\'s individual services are collapsed by default, and expand without affecting other categories', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="customize"]');

	const marketingDetails = page
		.locator('.cmp-customize__category', { has: page.locator('[data-cmp-category-toggle="marketing"]') })
		.locator('.cmp-customize__services-details');
	const analyticsDetails = page
		.locator('.cmp-customize__category', { has: page.locator('[data-cmp-category-toggle="analytics"]') })
		.locator('.cmp-customize__services-details');

	await expect(marketingDetails).not.toHaveJSProperty('open', true);
	await expect(page.locator('label[for="cmp-service-google-tag-manager"]')).toBeHidden();

	await expandServices(page, 'marketing');

	await expect(marketingDetails).toHaveJSProperty('open', true);
	await expect(page.locator('label[for="cmp-service-google-tag-manager"]')).toBeVisible();
	await expect(analyticsDetails).not.toHaveJSProperty('open', true);
});
