// @ts-check
const base = require('@playwright/test');

const test = base.test.extend({
	context: async ({ context, baseURL }, use) => {
		const site = new URL(baseURL || 'http://localhost:8080').host;
		await context.route((url) => url.host !== site && /^https?:$/.test(url.protocol), (route) => {
			const type = route.request().resourceType();
			if ('script' === type) return route.fulfill({ contentType: 'application/javascript', body: '' });
			if ('document' === type) return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>offline stub</title>' });
			return route.fulfill({ status: 204, body: '' });
		});
		await use(context);
	},
});

/**
 * @param {import('@playwright/test').Page} page
 */
async function publishDraft(page) {
	await page.goto('/wp-admin/admin.php?page=consentimental-publish');
	const button = page.locator('button[name="cmp_publish"]');
	if (await button.count()) {
		await button.click();
		await base.expect(page.locator('.notice-success')).toBeVisible();
	}
}

module.exports = { ...base, test, publishDraft };
