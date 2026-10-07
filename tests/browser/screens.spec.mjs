// لكل شاشة: لقطة كاملة على العرض الحالي في docs/design/screenshots/<الشاشة>-<العرض>.png،
// والتحقق من الاتجاه من اليمين، والعناصر الأساسية، وعدم وجود تمرير أفقي.
import { expect, test } from '@playwright/test';
import { existsSync } from 'node:fs';
import { AUTH_STATE } from './global-setup.mjs';

const guestScreens = [
    { name: 'home', path: '/', heading: 'h1' },
    // صفحات المحتوى تُصوَّر بعد نشرها من اللوحة، وقبله ترد 404 فتُتخطى
    { name: 'about', path: '/about', heading: 'h1', whenPublished: true },
    { name: 'initiatives', path: '/initiatives', heading: 'h1' },
    { name: 'initiative', path: '/initiatives/qiyam', heading: 'h1', whenPublished: true },
    { name: 'governance', path: '/governance', heading: 'h1' },
    { name: 'document', path: '/governance/privacy-policy', heading: 'h1', whenPublished: true },
    { name: 'login', path: '/login', heading: 'h1' },
    { name: 'register', path: '/register', heading: 'h1' },
    { name: 'forgot-password', path: '/forgot-password', heading: 'h1' },
];

const accountScreens = [
    { name: 'dashboard', path: '/account', heading: 'main p.text-2xl' },
    { name: 'profile', path: '/account/profile', heading: 'h1' },
];

async function checkScreen(page, testInfo, screen) {
    const response = await page.goto(screen.path);
    test.skip(screen.whenPublished === true && response?.status() === 404, `${screen.path} لم تُنشر بعد`);

    expect(response?.status(), `${screen.path} status`).toBeLessThan(400);

    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('header [aria-label]').first()).toBeVisible();
    await expect(page.locator(screen.heading).first()).toBeVisible();
    await expect(page.locator('footer')).toBeVisible();

    // الخط العربي محمّل من خادم البوابة
    await page.evaluate(() => document.fonts.ready);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(0);

    const width = page.viewportSize().width;
    await page.screenshot({ path: `docs/design/screenshots/${screen.name}-${width}.png`, fullPage: true });
}

test.describe('guest screens', () => {
    for (const screen of guestScreens) {
        test(screen.name, async ({ page }, testInfo) => {
            await checkScreen(page, testInfo, screen);
        });
    }

    test('mobile menu opens below the header', async ({ page }) => {
        test.skip(page.viewportSize().width >= 1280, 'القائمة كاملة على الحاسوب');

        await page.goto('/');
        await page.click('[aria-controls="mobile-menu"]');
        await expect(page.locator('#mobile-menu')).toBeVisible();
    });
});

test.describe('account screens', () => {
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_EMAIL وDEMO_PASSWORD');
    test.use({ storageState: AUTH_STATE });

    for (const screen of accountScreens) {
        test(screen.name, async ({ page }, testInfo) => {
            await checkScreen(page, testInfo, screen);
        });
    }
});
