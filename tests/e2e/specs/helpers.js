// @ts-check
const { expect, request: apiRequest } = require('@playwright/test');

const BASE = process.env.CMP_E2E_URL || 'http://localhost:8891';
const VENDOR = /\.cmp-e2e\.test$/;

const tracker = (key, cookie, host) => `
window.e2eRuns = window.e2eRuns || {};
window.e2eRuns[${JSON.stringify(key)}] = (window.e2eRuns[${JSON.stringify(key)}] || 0) + 1;
document.cookie = ${JSON.stringify(cookie + '=1; path=/')};
try { localStorage.setItem(${JSON.stringify(cookie.replace(/^_/, ''))}, '1'); } catch (e) {}
fetch('https://${host}/collect?e=pageview', { mode: 'no-cors' }).catch(function () {});
`;

const STUBS = {
	'analytics.cmp-e2e.test/a.js': tracker('analytics', '_e2e_a', 'analytics.cmp-e2e.test'),
	'ads.cmp-e2e.test/m.js': tracker('ads', '_e2e_m', 'ads.cmp-e2e.test'),
	'widget.cmp-e2e.test/w.js': 'window.e2eRuns = window.e2eRuns || {}; window.e2eRuns.widget = 1;',
	'late.cmp-e2e.test/l.js': tracker('late', '_e2e_l', 'late.cmp-e2e.test'),
};

/**
 * @param {import('@playwright/test').BrowserContext} context
 */
async function observe(context) {
	const base = new URL(BASE);
	const vendor = [];
	const blocked = [];

	await context.route('**/*', async (route) => {
		const request = route.request();
		const url = new URL(request.url());

		if (url.host === base.host || 'data:' === url.protocol || 'blob:' === url.protocol) {
			return route.continue();
		}

		let page = null;
		try { page = request.frame().page(); } catch (e) { /* worker request */ }

		if (VENDOR.test(url.hostname)) {
			vendor.push({ host: url.hostname, path: url.pathname, method: request.method(), page });
			const stub = STUBS[url.hostname + url.pathname];

			if (stub) {
				return route.fulfill({ contentType: 'application/javascript', body: stub });
			}

			if ('document' === request.resourceType()) {
				return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>embed</title><p>embedded</p>' });
			}

			return route.fulfill({ status: 204, body: '' });
		}

		blocked.push(url.href);
		return route.abort();
	});

	return {
		vendor,
		blocked,
		hosts(page) {
			return [...new Set(vendor.filter((r) => !page || r.page === page).map((r) => r.host))].sort();
		},
		clear() {
			vendor.length = 0;
		},
		async idle(quiet = 500) {
			let seen = -1;
			while (seen !== vendor.length) {
				seen = vendor.length;
				await new Promise((resolve) => setTimeout(resolve, quiet));
			}
		},
	};
}

/**
 * @param {{set?: Record<string, string>, delete?: string[], bump?: boolean, banner_text?: string}} change
 * @returns {Promise<number>} the configuration version afterwards
 */
async function fixture(change) {
	const api = await apiRequest.newContext({ baseURL: BASE });
	const response = await api.post('/?rest_route=/cmp-e2e/v1/state', { headers: { 'x-cmp-e2e': 'disposable' }, data: change });
	expect(response.ok()).toBe(true);
	const { config_version: version } = await response.json();
	await api.dispose();
	return version;
}

async function environment() {
	const api = await apiRequest.newContext({ baseURL: BASE });
	const response = await api.get('/?rest_route=/cmp-e2e/v1/env', { headers: { 'x-cmp-e2e': 'disposable' } });
	const env = await response.json();
	await api.dispose();
	return env;
}

async function consentCookie(page) {
	const cookie = (await page.context().cookies()).find((c) => 'cmp_consent' === c.name);
	return cookie ? JSON.parse(decodeURIComponent(cookie.value)) : null;
}

async function trackerStorage(page) {
	const cookies = (await page.context().cookies()).map((c) => c.name).filter((n) => n.startsWith('_e2e_')).sort();
	const storage = await page.evaluate(() => Object.keys(localStorage).filter((k) => k.startsWith('e2e_')).sort());
	return { cookies, storage };
}

async function runs(page) {
	return page.evaluate(() => Object.assign({}, window.e2eRuns || {}));
}

async function openCustomize(page) {
	const banner = page.locator('#cmp-banner');
	if (await banner.isVisible()) {
		await banner.locator('[data-cmp-action="customize"]').click();
	} else {
		await page.locator('#cmp-floating-button').click();
	}
	await expect(page.locator('#cmp-customize')).toBeVisible();
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} id
 */
async function toggle(page, id) {
	const input = page.locator('#' + id);
	const details = page.locator('details.cmp-customize__services-details').filter({ has: input });
	if ((await details.count()) && !(await details.evaluate((d) => d.open))) {
		await details.locator('summary').click();
	}
	await page.locator(`label[for="${id}"]`).click();
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {() => Promise<unknown>} action
 */
async function expectReload(page, action) {
	const marker = await page.evaluate(() => (window.__cmpE2eMarker = Math.random()));
	await action();
	await expect.poll(() => page.evaluate(() => window.__cmpE2eMarker).catch(() => undefined), { timeout: 15_000 }).not.toBe(marker);
	await page.waitForLoadState('load');
}

module.exports = { observe, fixture, environment, consentCookie, trackerStorage, runs, openCustomize, toggle, expectReload };
