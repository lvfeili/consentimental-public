// @ts-check
const { test, expect } = require('./offline-test');


test('withdrawing a category removes that service\'s known cookies and runs vendor callbacks', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="accept-all"]');
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(true);

	await page.evaluate(() => {
		document.cookie = '_fbp=fb.1.123; path=/';
		document.cookie = '_fbc=fb.1.456; path=/';
		document.cookie = 'unrelated_cookie=keep-me; path=/';
		sessionStorage.removeItem('cleanupCallbackFired');
		window.CMP.registerCleanup('meta-pixel', () => {
			sessionStorage.setItem('cleanupCallbackFired', '1');
		});
	});

	const before = (await page.context().cookies()).map((c) => c.name);
	expect(before).toContain('_fbp');
	expect(before).toContain('_fbc');

	await page.click('#cmp-floating-button');
	await page.click('label[for="cmp-category-marketing"]');
	await page.click('#cmp-customize [data-cmp-action="save"]');

	await expect.poll(() => page.evaluate(() => sessionStorage.getItem('cleanupCallbackFired'))).toBe('1');
	await page.waitForLoadState('load');

	const after = (await page.context().cookies()).map((c) => c.name);
	expect(after).not.toContain('_fbp');
	expect(after).not.toContain('_fbc');
	expect(after).toContain('unrelated_cookie');
});

test('a cmp:cleanup event reports which cookies (if any) could not be removed', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="accept-all"]');
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(true);

	const events = [];
	await page.exposeFunction('recordCleanup', (detail) => events.push(detail));
	await page.evaluate(() => {
		document.addEventListener('cmp:cleanup', (e) => window.recordCleanup(e.detail));
		document.cookie = '_fbp=fb.1.123; path=/';
	});
	await page.click('#cmp-floating-button');
	await page.click('label[for="cmp-category-marketing"]');
	await page.click('#cmp-customize [data-cmp-action="save"]');
	await expect.poll(() => events.some((e) => 'meta-pixel' === e.serviceId)).toBe(true);

	const detail = events.find((e) => 'meta-pixel' === e.serviceId);
	expect(detail).toBeTruthy();
	expect(Array.isArray(detail.remainingCookies)).toBe(true);
	expect(detail.remainingCookies).not.toContain('_fbp');
});
