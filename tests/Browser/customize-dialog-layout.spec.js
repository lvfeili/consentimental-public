// @ts-check
const { test, expect } = require('./offline-test');


test('the actions row stays visible without scrolling, even with a long category/service list', async ({ page }) => {
	await page.setViewportSize({ width: 480, height: 500 });
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="customize"]');
	await expect(page.locator('#cmp-customize')).toBeVisible();

	const panelBox = await page.locator('.cmp-customize__panel').boundingBox();
	const actionsBox = await page.locator('.cmp-customize__actions').boundingBox();

	expect(panelBox).not.toBeNull();
	expect(actionsBox).not.toBeNull();

	expect(actionsBox.y + actionsBox.height).toBeLessThanOrEqual(panelBox.y + panelBox.height + 1);
	expect(actionsBox.y + actionsBox.height).toBeLessThanOrEqual(500);

	const categoriesOverflow = await page
		.locator('.cmp-customize__categories')
		.evaluate((el) => el.scrollHeight > el.clientHeight);
	expect(categoriesOverflow).toBe(true);

	await page.click('#cmp-customize [data-cmp-action="save"]');
	await expect(page.locator('#cmp-customize')).toBeHidden();
});
