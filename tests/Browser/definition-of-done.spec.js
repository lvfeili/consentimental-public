// @ts-check
const { test, expect } = require('./offline-test');


async function scriptRan(page, marker) {
	return page.evaluate((m) => {
		var el = document.querySelector('script[src*="' + m + '"]');

		return !!el && !el.hasAttribute('data-cmp-category');
	}, marker);
}

async function iframeLoaded(page, id) {
	return page.evaluate((elId) => {
		var el = document.getElementById(elId);

		return !!el && el.hasAttribute('src') && el.hasAttribute('data-cmp-released') && 'none' !== el.style.display;
	}, id);
}

test('before interaction: only Necessary is active, and no configured service has executed', async ({ page }) => {
	await page.goto('/');

	await expect(page.locator('#cmp-banner')).toBeVisible();

	expect(await scriptRan(page, 'gtag/js?id=G-')).toBe(false); // GA4
	expect(await scriptRan(page, 'gtm.js')).toBe(false); // GTM
	expect(await scriptRan(page, 'gtag/js?id=AW-')).toBe(false); // Google Ads
	expect(await scriptRan(page, 'fbevents.js')).toBe(false); // Meta Pixel
	expect(await iframeLoaded(page, 'dod-youtube')).toBe(false);
	expect(await iframeLoaded(page, 'dod-maps')).toBe(false);
	expect(await iframeLoaded(page, 'dod-vimeo')).toBe(false); // Vimeo (marketing)
	expect(await scriptRan(page, 'recaptcha/api.js')).toBe(false); // reCAPTCHA (functional)

	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('necessary'))).toBe(true);
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('analytics'))).toBe(false);
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('functional'))).toBe(false);
});

test('accepting Analytics: GA4 executes, Marketing-only services stay blocked', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="customize"]');
	await page.click('label[for="cmp-category-analytics"]');
	await page.click('#cmp-customize [data-cmp-action="save"]');
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('analytics'))).toBe(true);

	expect(await scriptRan(page, 'gtag/js?id=G-')).toBe(true);
	expect(await scriptRan(page, 'gtm.js')).toBe(false);
	expect(await scriptRan(page, 'fbevents.js')).toBe(false);
	expect(await iframeLoaded(page, 'dod-youtube')).toBe(false);
	expect(await iframeLoaded(page, 'dod-vimeo')).toBe(false); // Marketing not granted
	expect(await scriptRan(page, 'recaptcha/api.js')).toBe(false); // Functional not granted
});

test('Accept All: every configured service executes', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="accept-all"]');
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(true);

	for (const marker of ['gtag/js?id=G-', 'gtm.js', 'gtag/js?id=AW-', 'fbevents.js', 'recaptcha/api.js']) {
		await expect.poll(() => scriptRan(page, marker), marker).toBe(true);
	}
	for (const id of ['dod-youtube', 'dod-maps', 'dod-vimeo']) {
		await expect.poll(() => iframeLoaded(page, id), id).toBe(true);
	}
});

test('withdrawing Marketing: those services stop, and known removable storage is cleaned up', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="accept-all"]');
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(true);

	await page.evaluate(() => {
		document.cookie = '_fbp=fb.1.999; path=/';
		sessionStorage.removeItem('dodVendorCleanupRan');
		window.CMP.registerCleanup('meta-pixel', () => {
			sessionStorage.setItem('dodVendorCleanupRan', '1');
		});
	});

	await page.click('#cmp-floating-button');
	await page.click('label[for="cmp-category-marketing"]');
	await page.click('#cmp-customize [data-cmp-action="save"]');
	await expect.poll(() => page.evaluate(() => sessionStorage.getItem('dodVendorCleanupRan'))).toBe('1');
	await page.waitForLoadState('load');
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('marketing'))).toBe(false);

	const cookieNames = (await page.context().cookies()).map((c) => c.name);
	expect(cookieNames).not.toContain('_fbp');

	expect(await page.evaluate(() => window.CMP.hasConsent('analytics'))).toBe(true);
});

test('Reject All: the site remains usable, with only Necessary active', async ({ page }) => {
	await page.goto('/');
	await page.click('#cmp-banner [data-cmp-action="reject-all"]');
	await expect.poll(() => page.evaluate(() => window.CMP.hasConsent('analytics'))).toBe(false);

	expect(await page.evaluate(() => window.CMP.hasConsent('necessary'))).toBe(true);
	expect(await iframeLoaded(page, 'dod-vimeo')).toBe(false);
	expect(await scriptRan(page, 'recaptcha/api.js')).toBe(false);

	const response = await page.goto('/', { waitUntil: 'networkidle' });
	expect(response.status()).toBe(200);
	await expect(page.locator('#cmp-banner')).toBeHidden();
});
