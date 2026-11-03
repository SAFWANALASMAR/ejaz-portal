// قوائم الرأس المنسدلة بلوحة المفاتيح بعد إصلاح قائمة الجوال (docs/decisions/2026-11-03-mobile-menu.md).
// - على 1440: Tab يخرج من القائمة فيطويها، وEscape يطويها ويعيد التركيز إلى زرها.
// - وفتح التالية بضغطة واحدة على 390، والانتقال إلى اللوحة بلا خطأ من انتقال المستندات، في v015-e2e.spec.mjs.
//
//   docker compose run --rm --no-deps -e VITE_HOST=none browser npx playwright test \
//     --config=tests/browser/playwright.config.mjs mobile-menu.spec.mjs --project=desktop --project=mobile
import { expect, test } from '@playwright/test';

async function expectRtlNoScroll(page) {
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'لا تمرير أفقي').toBeLessThanOrEqual(0);
}

test('keyboard: Tab out of a header dropdown closes it, Escape closes and returns focus', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'قوائم الرأس على الحاسوب');

    await page.goto('/');
    await expectRtlNoScroll(page);
    const toggles = page.locator('[data-nav-dropdown] [data-toggle="dropdown"]');
    expect(await toggles.count(), 'قائمتان منسدلتان على الأقل في الرأس').toBeGreaterThanOrEqual(2);
    const first = toggles.nth(0);

    // Enter يفتحها ويركّز أول عناصرها، وEscape يطويها ويعيد التركيز إلى زرها
    await first.focus();
    await page.keyboard.press('Enter');
    await expect(first).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator(`#${await first.getAttribute('aria-controls')} a`).first()).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(first).toHaveAttribute('aria-expanded', 'false');
    await expect(first).toBeFocused();

    // Tab حتى يخرج التركيز من القائمة: تُطوى
    await page.keyboard.press('Enter');
    await expect(first).toHaveAttribute('aria-expanded', 'true');
    const item = first.locator('xpath=ancestor::*[@data-nav-dropdown][1]');
    for (let i = 0; i < 30 && (await item.evaluate((el) => el.contains(document.activeElement))); i++) {
        await page.keyboard.press('Tab');
    }
    await expect(first).toHaveAttribute('aria-expanded', 'false');

    // والسهم السفلي على زر الثانية يفتحها، ثم التركيز البرمجي بعد لوحة المفاتيح إلى زر آخر (:focus-visible) يطويها
    const second = toggles.nth(1);
    await second.focus();
    await page.keyboard.press('ArrowDown');
    await expect(second).toHaveAttribute('aria-expanded', 'true');
    await first.focus();
    await expect(second).toHaveAttribute('aria-expanded', 'false');
});

test('a click on a control that handles its own click (show password) still closes an open header dropdown', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'قوائم الرأس على الحاسوب');

    await page.goto('/login');
    await expectRtlNoScroll(page);
    const first = page.locator('[data-nav-dropdown] [data-toggle="dropdown"]').first();
    // تُفتح بلوحة المفاتيح، فلا تطويها مهلة خروج الفأرة منها
    await first.focus();
    await page.keyboard.press('Enter');
    await expect(first).toHaveAttribute('aria-expanded', 'true');

    // زر إظهار كلمة المرور يعود قبل آخر مستمع الضغط، وتركيز الضغط بالمؤشر لا يطوي القائمة: الطي في أول المستمع
    const reveal = page.locator('[data-password-toggle]').first();
    await reveal.click();
    await expect(reveal).toHaveAttribute('aria-pressed', 'true');
    await expect(first).toHaveAttribute('aria-expanded', 'false');
});
