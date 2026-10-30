// سمة لوحة الإدارة (docs/decisions/2026-10-30-admin-theme.md، والتصور النهائي 13–15) على العروض الثلاثة:
// كل أيقونة في الشريط الجانبي داخل مربع متدرج بالترتيب أزرق ثم بنفسجي ثم أخضر، والعنصر النشط بحلقة فاتحة،
// والأيقونة البيضاء على أفتح نقطة في المربع لا تقل عن 3:1، وتقليل الحركة يوقف الميل والنمو، ورأس الصفحة
// بتدرج ونقش، وبلا تمرير أفقي. ولقطة لكل شاشة في docs/design/screenshots/admin-theme-<الشاشة>-<العرض>.png
//
// يحتاج مشرفاً تجريبياً بتحقق ثنائي (ejaz:demo-user --admin --json) والإحصائيات محسوبة (ejaz:refresh-stats).
// التشغيل في tests/browser/README.md («سمة لوحة الإدارة»).
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ADMIN = process.env.ADMIN_THEME_CREDENTIALS ? JSON.parse(process.env.ADMIN_THEME_CREDENTIALS) : null;

// الجلسة تُحفظ بعد أول دخول وتُستعمل في العروض الثلاثة، فلا يتكرر التحقق الثنائي (حده عشر محاولات في ربع ساعة)
const STATE_FILE = 'storage/playwright/admin-theme-state.json';
const WINDOW_FILE = 'storage/playwright/admin-spa-last-totp-window';

const SCREENS = [
    { name: 'dashboard', path: '/admin' },
    { name: 'list', path: '/admin/menu-items' },
    { name: 'form', path: '/admin/home-content' },
    { name: 'empty', path: '/admin/announcements' },
];

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

async function signedInPage(browser, baseURL, use, reducedMotion = 'no-preference') {
    if (!existsSync(STATE_FILE)) {
        const context = await browser.newContext({ baseURL });
        const page = await context.newPage();
        await page.goto('/login');
        await page.fill('#field-login', ADMIN.email);
        await page.fill('#field-password', ADMIN.password);
        await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
        await page.fill('input[name="code"]', await freshCode());
        await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);
        await context.storageState({ path: STATE_FILE });
        await context.close();
    }

    const { viewport, isMobile, hasTouch } = use;
    const context = await browser.newContext({ baseURL, viewport, isMobile, hasTouch, storageState: STATE_FILE, reducedMotion, locale: 'ar-SA' });

    return context.newPage();
}

// نسبة التباين (WCAG) بين الأبيض ولون rgb()
function contrastWithWhite(rgb) {
    const [r, g, b] = rgb.map((channel) => {
        const c = channel / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 1.05 / (0.2126 * r + 0.7152 * g + 0.0722 * b + 0.05);
}

test.describe('admin panel theme', () => {
    test.skip(!ADMIN, 'يحتاج مشرفاً تجريبياً: ADMIN_THEME_CREDENTIALS من ejaz:demo-user --admin --json');

    test('styles the sidebar, headers and stats, and takes the screenshots', async ({ browser, baseURL }, testInfo) => {
        const viewport = testInfo.project.use.viewport;
        const page = await signedInPage(browser, baseURL, testInfo.project.use);

        for (const screen of SCREENS) {
            await page.goto(screen.path);
            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            await page.waitForLoadState('networkidle');

            // رأس الصفحة بتدرج ونقش
            const header = await page.locator('.fi-header').first().evaluate((el) => getComputedStyle(el).backgroundImage);
            expect(header).toContain('linear-gradient');
            expect(header).toContain('url(');

            // بلا تمرير أفقي
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow, screen.path).toBeLessThanOrEqual(0);

            await page.screenshot({ path: `docs/design/screenshots/admin-theme-${screen.name}-${viewport.width}.png`, fullPage: true });
        }

        // الشريط الجانبي: الشعار الأبيض، وكل أيقونة في مربع متدرج، وأول ثلاثة مربعات بثلاثة ألوان مختلفة
        await page.goto('/admin/menu-items');
        await expect(page.locator('.ej-sidebar-brand img')).toHaveCount(2);
        const tiles = await page.locator('.fi-sidebar-item-icon').evaluateAll((icons) => icons.map((icon) => getComputedStyle(icon).backgroundImage));
        expect(tiles.length).toBeGreaterThan(3);
        for (const tile of tiles) expect(tile).toContain('linear-gradient');
        expect(new Set(tiles).size).toBeGreaterThanOrEqual(3);

        // العنصر النشط: حلقة فاتحة (#8EC5F0) حول مربعه
        const active = page.locator('.fi-sidebar-item.fi-active .fi-sidebar-item-icon').first();
        expect(await active.evaluate((icon) => getComputedStyle(icon).boxShadow)).toContain('rgba(142, 197, 240');

        // التباين: الأيقونة البيضاء على أفتح نقطة في كل لون من الألوان الثلاثة لا تقل عن 3:1 (WCAG 1.4.11)
        for (const tile of new Set(tiles)) {
            const stops = [...tile.matchAll(/rgb\((\d+), (\d+), (\d+)\)/g)].map((match) => match.slice(1, 4).map(Number));
            expect(stops.length).toBeGreaterThanOrEqual(2);
            for (const stop of stops) expect(contrastWithWhite(stop), tile).toBeGreaterThanOrEqual(3);
        }
        expect(await active.evaluate((icon) => getComputedStyle(icon).color)).toBe('rgb(255, 255, 255)');

        // الميل عند المرور (الحاسوب، فالشريط ظاهر)
        if (viewport.width >= 1024) {
            const item = page.locator('.fi-sidebar-item:not(.fi-active) .fi-sidebar-item-btn').first();
            await item.hover();
            await expect.poll(() => item.locator('.fi-sidebar-item-icon').evaluate((icon) => getComputedStyle(icon).transform)).not.toBe('none');
        }

        await page.context().close();
    });

    test('stops the tilt and the growing line under reduced motion', async ({ browser, baseURL }, testInfo) => {
        const viewport = testInfo.project.use.viewport;
        const page = await signedInPage(browser, baseURL, testInfo.project.use, 'reduce');
        await page.goto('/admin/menu-items');

        const icon = page.locator('.fi-sidebar-item:not(.fi-active) .fi-sidebar-item-icon').first();
        // 0s من السمة، أو 0.01ms من قاعدة تقليل الحركة العامة في Filament: لا حركة تُرى في الحالتين
        expect(parseFloat(await icon.evaluate((el) => getComputedStyle(el).transitionDuration))).toBeLessThanOrEqual(0.001);
        if (viewport.width >= 1024) {
            await page.locator('.fi-sidebar-item:not(.fi-active) .fi-sidebar-item-btn').first().hover();
            expect(await icon.evaluate((el) => getComputedStyle(el).transform)).toBe('none');
        }
        expect(await page.locator('.fi-header-heading').first().evaluate((el) => getComputedStyle(el, '::after').animationName)).toBe('none');

        await page.context().close();
    });
});
