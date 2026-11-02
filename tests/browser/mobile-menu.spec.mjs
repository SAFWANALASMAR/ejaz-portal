// قائمة الجوال وقوائم الرأس المنسدلة، والانتقال من الموقع إلى لوحة الإدارة (docs/decisions/2026-11-03-mobile-menu.md).
// - على 390: بقائمة منسدلة مفتوحة، ضغطة واحدة على التالية تفتحها وتطوي الأولى (كان التركيز يطوي الاثنتين).
// - على 1440: لوحة المفاتيح كما هي: Tab يخرج من القائمة فيطويها، وEscape يطويها ويعيد التركيز إلى زرها.
// - زر «تعديل هذه الصفحة» إلى اللوحة بلا خطأ غير ملتقط من انتقال المستندات (@view-transition في visual.css).
//
// الأخير يحتاج مسؤولاً تقنياً تجريبياً بتحقق ثنائي، كاختبار محرر الصفحات:
//   creds=$(docker compose exec -T app php artisan ejaz:demo-user --admin --json)
//   docker compose run --rm --no-deps -e VITE_HOST=none -e PAGE_EDITOR_ADMIN_CREDENTIALS="$creds" browser npx playwright test \
//     --config=tests/browser/playwright.config.mjs mobile-menu.spec.mjs --project=desktop --project=mobile
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ADMIN = process.env.PAGE_EDITOR_ADMIN_CREDENTIALS ? JSON.parse(process.env.PAGE_EDITOR_ADMIN_CREDENTIALS) : null;
// رمز TOTP لا يُقبل مرتين: آخر نافذة في ملف مشترك مع اختبارات اللوحة الأخرى
const WINDOW_FILE = 'storage/playwright/admin-spa-last-totp-window';

function base32(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
    return Buffer.from(bytes);
}

async function freshCode() {
    const lastWindow = existsSync(WINDOW_FILE) ? Number(readFileSync(WINDOW_FILE, 'utf8')) : -1;
    let window = Math.floor(Date.now() / 30_000);
    while (window <= lastWindow || Date.now() % 30_000 > 25_000) {
        await new Promise((resolve) => setTimeout(resolve, 1000));
        window = Math.floor(Date.now() / 30_000);
    }
    mkdirSync('storage/playwright', { recursive: true });
    writeFileSync(WINDOW_FILE, String(window));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(window));
    const hmac = createHmac('sha1', base32(ADMIN.totp_secret)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 15;
    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

async function expectRtlNoScroll(page) {
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'لا تمرير أفقي').toBeLessThanOrEqual(0);
}

test('one tap on the next mobile dropdown opens it and closes the previous one', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'قائمة الجوال');

    await page.goto('/');
    await expectRtlNoScroll(page);
    await page.locator('[data-toggle="mobile-menu"]').tap();
    const buttons = page.locator('#mobile-menu [data-toggle="dropdown"]');
    expect(await buttons.count(), 'قائمتان منسدلتان على الأقل في قائمة الجوال').toBeGreaterThanOrEqual(2);

    await buttons.nth(0).tap();
    await expect(buttons.nth(0)).toHaveAttribute('aria-expanded', 'true');

    await buttons.nth(1).tap();
    await expect(buttons.nth(1)).toHaveAttribute('aria-expanded', 'true', { timeout: 3_000 });
    await expect(buttons.nth(0)).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator(`#${await buttons.nth(1).getAttribute('aria-controls')}`)).toBeVisible();
    await expect(page.locator(`#${await buttons.nth(0).getAttribute('aria-controls')}`)).toBeHidden();
    await expectRtlNoScroll(page);
});

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

test('the edit button opens the admin panel without an uncaught view-transition error', async ({ page }) => {
    test.skip(!ADMIN, 'يحتاج مسؤولاً تقنياً تجريبياً: PAGE_EDITOR_ADMIN_CREDENTIALS من ejaz:demo-user --admin --json');

    const errors = [];
    page.on('pageerror', (error) => errors.push(`${page.url()} ${error.name}: ${error.message}`));

    // والدخول نفسه ينتهي في اللوحة من صفحة عامة (التحقق الثنائي)، فهو داخل الفحص أيضاً
    await page.goto('/login');
    await page.fill('#field-login', ADMIN.email);
    await page.fill('#field-password', ADMIN.password);
    await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
    await page.fill('input[name="code"]', await freshCode());
    await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);

    await page.goto('/programs');
    await expectRtlNoScroll(page);
    const button = page.locator('[data-page-edit-button]');
    await expect(button).toBeVisible();
    await Promise.all([page.waitForURL(/\/admin\//), button.click()]);
    await page.waitForLoadState('load');
    // الخطأ يُرمى عند كشف الصفحة الجديدة (pagereveal)، قبل أول إطار يُرسم
    await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    expect(errors).toEqual([]);
});
