// @ts-check
const { test, expect } = require('@playwright/test');
const { observe, fixture, consentCookie, trackerStorage, runs, openCustomize, toggle, expectReload } = require('./helpers');

const ALL_ALLOWED = ['ads.cmp-e2e.test', 'analytics.cmp-e2e.test', 'video.cmp-e2e.test'];

test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ context }) => {
	await context.clearCookies();
});

test('first visit: banner shown, nothing optional requested or stored', async ({ page, context }) => {
	const net = await observe(context);
	await page.goto('/');

	await expect(page.locator('#cmp-banner')).toBeVisible();
	await page.waitForLoadState('networkidle');
	expect(net.hosts()).toEqual([]);
	expect(await trackerStorage(page)).toEqual({ cookies: [], storage: [] });
	expect(await consentCookie(page)).toBeNull();
	await expect(page.locator('#cmp-e2e-video')).not.toHaveAttribute('src', /./);
	expect(net.blocked).toEqual([]);

	await page.locator('#cmp-banner [data-cmp-action="customize"]').click();
	const necessary = page.locator('.cmp-customize__category').filter({ has: page.locator('#cmp-category-necessary') });
	await necessary.locator('summary').click();
	await expect(necessary).toContainText('Consent settings');
	await expect(necessary).toContainText('cmp_consent');
	await expect(necessary).toContainText('WordPress');
	await expect(necessary).toContainText('wordpress_logged_in_*');
	await expect(necessary.getByRole('checkbox', { name: 'Consent settings', exact: true })).toBeDisabled();
});

test('reject all: nothing optional before or after reload, decision recorded with its disclosure', async ({ page, context }) => {
	const net = await observe(context);
	await page.goto('/');

	const disclosure = await page.evaluate(() => window.cmpConfig.disclosure.id);
	const post = page.waitForResponse((r) => r.url().includes('/consentimental/v1/consent') && 'POST' === r.request().method());
	await page.locator('#cmp-banner [data-cmp-action="reject-all"]').click();
	const response = await post;
	const body = JSON.parse(response.request().postData() || '{}');

	expect(body.previous_state).toBeNull();
	expect(response.status()).toBe(200);

	expect(body.disclosure_id).toBe(disclosure);
	expect(body.categories).toMatchObject({ analytics: false, marketing: false, functional: false });
	await expect(page.locator('#cmp-banner')).toBeHidden();
	await expect(page.locator('#cmp-floating-button')).toBeVisible();

	await page.reload();
	await page.waitForLoadState('networkidle');
	await expect(page.locator('#cmp-banner')).toBeHidden();
	expect(net.hosts()).toEqual([]);
	expect(await trackerStorage(page)).toEqual({ cookies: [], storage: [] });
	expect(await consentCookie(page)).not.toBeNull();
});

test('accept all: every confirmed service loads, the unconfirmed one never does', async ({ page, context }) => {
	const net = await observe(context);
	await page.goto('/');
	await page.locator('#cmp-banner [data-cmp-action="accept-all"]').click();

	await expect.poll(() => runs(page)).toMatchObject({ analytics: 1, ads: 1 });
	await expect(page.locator('#cmp-e2e-video')).toHaveAttribute('src', 'https://video.cmp-e2e.test/embed/1');
	await expect.poll(() => net.hosts()).toEqual(ALL_ALLOWED);
	expect(await trackerStorage(page)).toEqual({ cookies: ['_e2e_a', '_e2e_m'], storage: ['e2e_a', 'e2e_m'] });

	await net.idle();
	net.clear();
	await page.reload();
	await expect(page.locator('#cmp-banner')).toBeHidden();
	await expect.poll(() => net.hosts()).toEqual(ALL_ALLOWED);
	expect(net.hosts()).not.toContain('widget.cmp-e2e.test');
});

test('partial consent: only the allowed category loads, also after reload', async ({ page, context }) => {
	const net = await observe(context);
	await page.goto('/');
	await openCustomize(page);
	await toggle(page, 'cmp-category-analytics');
	await page.locator('#cmp-customize [data-cmp-action="save"]').click();

	await expect.poll(() => runs(page)).toEqual({ analytics: 1 });
	await page.reload();
	await page.waitForLoadState('networkidle');
	expect(net.hosts()).toEqual(['analytics.cmp-e2e.test']);
	expect(await trackerStorage(page)).toEqual({ cookies: ['_e2e_a'], storage: ['e2e_a'] });
	expect((await consentCookie(page)).categories).toMatchObject({ analytics: true, marketing: false, functional: false });
});

test('individual service consent and its withdrawal', async ({ page, context }) => {
	const net = await observe(context);
	await page.goto('/');
	await openCustomize(page);
	await toggle(page, 'cmp-service-e2e-ads');
	await expect(page.locator('#cmp-category-marketing')).toHaveJSProperty('indeterminate', false);
	await page.locator('#cmp-customize [data-cmp-action="save"]').click();

	await expect.poll(() => runs(page)).toEqual({ ads: 1 });
	expect(net.hosts()).toEqual(['ads.cmp-e2e.test']);
	expect(await page.evaluate(() => [CMP.hasServiceConsent('e2e-ads'), CMP.hasConsent('analytics')])).toEqual([true, false]);

	await openCustomize(page);
	await expectReload(page, async () => {
		await toggle(page, 'cmp-service-e2e-ads');
		await page.locator('#cmp-customize [data-cmp-action="save"]').click();
	});
	await net.idle();
	net.clear();
	await page.reload();
	await page.waitForLoadState('networkidle');
	expect(net.hosts()).toEqual([]);
	expect(await trackerStorage(page)).toEqual({ cookies: [], storage: [] });
	expect((await consentCookie(page)).services['e2e-ads']).toBe(false);
});

test('withdrawal after scripts and embeds were released', async ({ page, context }) => {
	const net = await observe(context);
	await page.goto('/');
	await page.locator('#cmp-banner [data-cmp-action="accept-all"]').click();
	await expect(page.locator('#cmp-e2e-video')).toHaveAttribute('src', /video\.cmp-e2e\.test/);
	await expect.poll(() => trackerStorage(page)).toEqual({ cookies: ['_e2e_a', '_e2e_m'], storage: ['e2e_a', 'e2e_m'] });

	await openCustomize(page);
	await expectReload(page, () => page.locator('#cmp-customize [data-cmp-action="reject-all"]').click());

	await net.idle();
	net.clear();
	await page.reload();
	await page.waitForLoadState('networkidle');
	expect(net.hosts()).toEqual([]);
	await expect(page.locator('#cmp-e2e-video')).not.toHaveAttribute('src', /./);
	expect(await trackerStorage(page)).toEqual({ cookies: [], storage: [] });
});

test('embed-only withdrawal re-blocks without a reload', async ({ page, context }) => {
	await observe(context);
	await page.goto('/');
	await openCustomize(page);
	await toggle(page, 'cmp-category-functional');
	await page.locator('#cmp-customize [data-cmp-action="save"]').click();
	await expect(page.locator('#cmp-e2e-video')).toHaveAttribute('src', /video\.cmp-e2e\.test/);

	const marker = await page.evaluate(() => (window.__noReload = 1));
	await openCustomize(page);
	await toggle(page, 'cmp-category-functional');
	await page.locator('#cmp-customize [data-cmp-action="save"]').click();

	await expect(page.locator('#cmp-e2e-video')).not.toHaveAttribute('src', /./);
	expect(await page.evaluate(() => window.__noReload)).toBe(marker);
});

/**
 * @param {import('@playwright/test').BrowserContext} context
 * @param {object} state
 */
async function setConsentCookie(context, state) {
	await context.addCookies([{ name: 'cmp_consent', value: encodeURIComponent(JSON.stringify(state)), url: process.env.CMP_E2E_URL || 'http://localhost:8891' }]);
}

test('expired decision: banner again, nothing released', async ({ page, context }) => {
	const net = await observe(context);
	await page.goto('/');
	const version = await page.evaluate(() => window.cmpConfig.configVersion);
	const now = Math.floor(Date.now() / 1000);

	await setConsentCookie(context, { consent_id: 'e2e-expired', timestamp: now - 400 * 86400, expiry: now - 60, config_version: version,
		categories: { necessary: true, functional: true, analytics: true, marketing: true }, services: {} });
	await page.reload();
	await page.waitForLoadState('networkidle');

	await expect(page.locator('#cmp-banner')).toBeVisible();
	expect(net.hosts()).toEqual([]);
	expect(await page.evaluate(() => CMP.needsConsent())).toBe(true);
});

test('decision for an outdated configuration: banner again, nothing released', async ({ page, context }) => {
	const net = await observe(context);
	await page.goto('/');
	const version = await page.evaluate(() => window.cmpConfig.configVersion);
	const now = Math.floor(Date.now() / 1000);

	await setConsentCookie(context, { consent_id: 'e2e-outdated', timestamp: now, expiry: now + 86400, config_version: version - 1,
		categories: { necessary: true, functional: true, analytics: true, marketing: true }, services: {} });
	await page.reload();
	await page.waitForLoadState('networkidle');

	await expect(page.locator('#cmp-banner')).toBeVisible();
	expect(net.hosts()).toEqual([]);
});

test.describe('cross-tab changes', () => {
	test('a decision in one tab applies to an already open tab without a reload', async ({ context }) => {
		const net = await observe(context);
		const first = await context.newPage();
		const second = await context.newPage();
		await first.goto('/');
		await second.goto('/');
		await expect(second.locator('#cmp-banner')).toBeVisible();

		await first.bringToFront();
		await first.locator('#cmp-banner [data-cmp-action="accept-all"]').click();
		await second.bringToFront();

		await expect(second.locator('#cmp-banner')).toBeHidden();
		await expect.poll(() => runs(second)).toMatchObject({ analytics: 1, ads: 1 });
		await expect.poll(() => net.hosts(second)).toEqual(ALL_ALLOWED);
	});

	test('a withdrawal in one tab stops trackers already running in another', async ({ context }) => {
		const net = await observe(context);
		const first = await context.newPage();
		await first.goto('/');
		await first.locator('#cmp-banner [data-cmp-action="accept-all"]').click();
		const second = await context.newPage();
		await second.goto('/');
		await expect.poll(() => runs(second)).toMatchObject({ analytics: 1, ads: 1 });

		await first.bringToFront();
		await openCustomize(first);
		await expectReload(second, async () => {
			await first.locator('#cmp-customize [data-cmp-action="reject-all"]').click();
			await second.bringToFront();
		});

		await net.idle();
		net.clear();
		await second.reload();
		await second.waitForLoadState('networkidle');
		expect(net.hosts(second)).toEqual([]);
		await expect(second.locator('#cmp-e2e-video')).not.toHaveAttribute('src', /./);
		expect(await trackerStorage(second)).toEqual({ cookies: [], storage: [] });
	});
});

test.describe('configuration changes after a decision', () => {
	test.afterEach(async () => {
		await fixture({ delete: ['cmp_e2e_extra_service', 'cmp_e2e_analytics_category'], bump: true });
	});

	test('a newly published service asks everyone again and releases nothing meanwhile', async ({ page, context }) => {
		const net = await observe(context);
		await page.goto('/');
		await page.locator('#cmp-banner [data-cmp-action="accept-all"]').click();
		await expect.poll(() => runs(page)).toMatchObject({ analytics: 1 });

		await fixture({ set: { cmp_e2e_extra_service: '1' }, bump: true });
		await net.idle();
		net.clear();
		await page.reload();
		await page.waitForLoadState('networkidle');

		await expect(page.locator('#cmp-banner')).toBeVisible();
		expect(net.hosts()).toEqual([]);

		await openCustomize(page);
		await expect(page.locator('#cmp-customize')).toContainText('E2E Late Addition');
	});

	test('a service moved to another category follows its new category', async ({ page, context }) => {
		const net = await observe(context);
		await page.goto('/');
		await openCustomize(page);
		await toggle(page, 'cmp-category-analytics');
		await page.locator('#cmp-customize [data-cmp-action="save"]').click();
		await expect.poll(() => runs(page)).toEqual({ analytics: 1 });

		await fixture({ set: { cmp_e2e_analytics_category: 'marketing' }, bump: true });
		await net.idle();
		net.clear();
		await page.reload();
		await expect(page.locator('#cmp-banner')).toBeVisible();

		await openCustomize(page);
		await toggle(page, 'cmp-category-analytics');
		await page.locator('#cmp-customize [data-cmp-action="save"]').click();
		await page.reload();
		await page.waitForLoadState('networkidle');
		expect(net.hosts()).toEqual([]);
	});
});
