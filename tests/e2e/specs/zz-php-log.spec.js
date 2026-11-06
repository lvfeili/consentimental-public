// @ts-check
const { test, expect } = require('@playwright/test');

test('the plugin raised no PHP errors, warnings, notices or deprecations', async ({ request }) => {
	const response = await request.get('/?rest_route=/cmp-e2e/v1/php-log', { headers: { 'x-cmp-e2e': 'disposable' } });
	expect(response.ok()).toBe(true);
	expect(await response.json()).toEqual([]);
});
