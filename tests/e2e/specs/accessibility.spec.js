// @ts-check
const { test, expect } = require('@playwright/test');
const { observe, fixture } = require('./helpers');

test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ context }) => {
	await context.clearCookies();
	await observe(context);
});

const activeIn = (page, selector) => page.evaluate((s) => !!document.activeElement && !!document.activeElement.closest(s), selector);

test('a blocking banner takes focus once and keeps Tab inside it', async ({ page }) => {
	await page.goto('/');
	const banner = page.locator('#cmp-banner');
	await expect(banner).toBeVisible();
	await expect(banner).toHaveAttribute('aria-modal', 'true');
	await expect(banner).toBeFocused();
	await expect(banner).toHaveAccessibleName(/.+/);
	await expect(banner).toHaveAccessibleDescription(/.+/);

	const count = await banner.locator('a[href], button').count();
	for (let i = 0; i < count + 2; i++) {
		await page.keyboard.press('Tab');
		expect(await activeIn(page, '#cmp-banner')).toBe(true);
	}
	await page.keyboard.press('Shift+Tab');
	expect(await activeIn(page, '#cmp-banner')).toBe(true);
});

test('settings dialog: keyboard open, labelled controls, Escape returns focus', async ({ page }) => {
	await page.goto('/');
	const customizeButton = page.locator('#cmp-banner [data-cmp-action="customize"]');
	await customizeButton.focus();
	await page.keyboard.press('Enter');

	const dialog = page.locator('#cmp-customize');
	await expect(dialog).toBeVisible();
	expect(await activeIn(page, '#cmp-customize')).toBe(true);
	await expect(dialog).toHaveAccessibleName(/.+/);

	for (const name of ['Analytics', 'Marketing', 'Functional', 'E2E Analytics', 'E2E Ads', 'E2E Video']) {
		await expect(dialog.getByRole('checkbox', { name, exact: true, includeHidden: true })).toHaveCount(1);
	}
	await expect(dialog.getByRole('checkbox', { name: 'Necessary', exact: true })).toBeDisabled();

	await page.keyboard.press('Escape');
	await expect(dialog).toBeHidden();
	await expect(customizeButton).toBeFocused();
});

test('a choice made with the keyboard can be reopened with the keyboard', async ({ page }) => {
	await page.goto('/');
	await page.locator('#cmp-banner [data-cmp-action="reject-all"]').focus();
	await page.keyboard.press('Enter');
	await expect(page.locator('#cmp-banner')).toBeHidden();

	const reopen = page.locator('#cmp-floating-button');
	await expect(reopen).toHaveAccessibleName(/.+/);
	await reopen.focus();
	await page.keyboard.press('Enter');
	await expect(page.locator('#cmp-customize')).toBeVisible();
	expect(await activeIn(page, '#cmp-customize')).toBe(true);
	await page.keyboard.press('Escape');
	await expect(reopen).toBeFocused();
});

test('banner text and buttons meet WCAG AA contrast', async ({ page }) => {
	await page.goto('/');
	const ratios = await page.evaluate(() => {
		const parse = (c) => c.match(/[\d.]+/g).map(Number);
		const lum = ([r, g, b]) => {
			const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
			return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
		};
		const background = (el) => {
			for (let node = el; node; node = node.parentElement) {
				const c = parse(getComputedStyle(node).backgroundColor);
				if (4 > c.length || c[3] > 0) return c;
			}
			return [255, 255, 255];
		};
		return Array.from(document.querySelectorAll('#cmp-banner .cmp-banner__title, #cmp-banner .cmp-banner__text, #cmp-banner .cmp-policy-links a, #cmp-banner button')).map((el) => {
			const a = lum(parse(getComputedStyle(el).color));
			const b = lum(background(el));
			return { text: el.textContent.trim().slice(0, 30), ratio: (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) };
		});
	});

	expect(ratios.length).toBeGreaterThan(3);
	for (const { text, ratio } of ratios) {
		expect(ratio, text).toBeGreaterThanOrEqual(4.5);
	}
});

test.describe('non-blocking banner', () => {
	test.afterEach(async () => {
		await fixture({ require_decision: true });
	});

	test('is not announced as modal and does not steal focus', async ({ page }) => {
		await fixture({ require_decision: false });
		await page.goto('/');
		const banner = page.locator('#cmp-banner');
		await expect(banner).toBeVisible();
		await expect(banner).toHaveAttribute('aria-modal', 'false');
		await expect(banner).not.toBeFocused();
		await expect(page.locator('[data-cmp-backdrop]')).toBeHidden();
	});
});

test.describe('small screens, long translations, right-to-left', () => {
	const long = 'Datenschutzgrundverordnungskonformitätsbestätigungsverfahrenseinstellungen '.repeat(3)
		+ 'Wir verwenden Cookies und ähnliche Technologien für notwendige Funktionen und, mit Ihrer Einwilligung, für Statistik und Marketing. '.repeat(4);

	test.afterEach(async () => {
		await fixture({ banner_text: '' });
	});

	for (const rtl of [false, true]) {
		test(`320px wide, long text${rtl ? ', right-to-left' : ''}: no horizontal scrolling, every action reachable`, async ({ page }) => {
			await fixture({ banner_text: long });
			await page.setViewportSize({ width: 320, height: 640 });
			await page.goto(rtl ? '/?cmp_e2e_rtl=1' : '/');

			if (rtl) {
				await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
			}

			const noOverflow = () => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
			const banner = page.locator('#cmp-banner');
			await expect(banner).toContainText('Datenschutzgrundverordnungs');
			expect(await noOverflow()).toBe(true);

			for (const action of ['reject-all', 'customize', 'accept-all']) {
				await banner.locator(`[data-cmp-action="${action}"]`).scrollIntoViewIfNeeded();
				await expect(banner.locator(`[data-cmp-action="${action}"]`)).toBeInViewport();
			}

			await banner.locator('[data-cmp-action="customize"]').click();
			const save = page.locator('#cmp-customize [data-cmp-action="save"]');
			await expect(save).toBeInViewport();
			expect(await noOverflow()).toBe(true);
			await save.click();

			const box = await page.locator('#cmp-floating-button').boundingBox();
			expect(box).not.toBeNull();
			expect(box.x).toBeGreaterThan(160);
		});
	}
});
