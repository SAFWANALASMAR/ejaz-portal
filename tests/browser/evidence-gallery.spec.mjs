// معرض الشواهد برابط سري (docs/decisions.md — 30 أكتوبر 2026) على العروض الثلاثة: الصفحة العامة بلا حساب (الاتجاه من
// اليمين، والرأس، والصور بالعارض المكبَّر، والملفات، ووسما noindex وno-referrer، وبلا تمرير أفقي ولا أخطاء ولا مخالفة
// لفحص الوصول)، وشاشة الإنشاء في اللوحة. ولقطة لكل عرض في docs/design/screenshots/evidence-gallery/.
//
// يحتاج رابط معرض تجريبي محلي (EVIDENCE_URL)، ولشاشة اللوحة مشرفاً تجريبياً بتحقق ثنائي
// (EVIDENCE_ADMIN_CREDENTIALS من ejaz:demo-user --admin --json). والبيانات عينة محلية لا تُحفظ في قيت.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const URL_ = process.env.EVIDENCE_URL ?? null;
const ADMIN = process.env.EVIDENCE_ADMIN_CREDENTIALS ? JSON.parse(process.env.EVIDENCE_ADMIN_CREDENTIALS) : null;
const SHOTS = 'docs/design/screenshots/evidence-gallery';

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

test.describe('evidence gallery', () => {
    test('opens the private gallery without an account', async ({ page }, testInfo) => {
        test.skip(!URL_, 'يحتاج رابط معرض تجريبي: EVIDENCE_URL');
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));

        const path = new URL(URL_).pathname;
        const response = await page.goto(path);
        expect(response.status()).toBe(200);
        expect(response.headers()['x-robots-tag']).toBe('noindex, nofollow, noarchive');
        expect(response.headers()['referrer-policy']).toBe('no-referrer');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow');
        await expect(page.locator('meta[name="referrer"]')).toHaveAttribute('content', 'no-referrer');
        await expect(page.locator('h1')).toBeVisible();

        // الصور كلها محمّلة، ومنها صورة الأرشيف برابطها الموقّع، والعارض يفتح ويغلق
        const images = page.locator('[data-gallery] [data-gallery-open] img');
        expect(await images.count()).toBeGreaterThan(1);
        for (const image of await images.all()) {
            await image.scrollIntoViewIfNeeded();
            await expect.poll(() => image.evaluate((element) => element.complete && element.naturalWidth > 0)).toBe(true);
        }
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.locator('[data-gallery-open]').first().click();
        await expect(page.locator('[data-gallery-dialog]')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('[data-gallery-dialog]')).toBeHidden();

        await expect(page.locator('a[href*="/evidence/files/"]').first()).toBeVisible();

        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);

        const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).include('main').analyze();
        expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([]);

        await page.evaluate(() => document.fonts.ready);
        mkdirSync(SHOTS, { recursive: true });
        await page.screenshot({ path: `${SHOTS}/public-${testInfo.project.use.viewport.width}.png`, fullPage: true });
        expect(errors).toEqual([]);
    });

    test('answers a wrong link with 404', async ({ page }) => {
        const response = await page.goto('/evidence/' + 'x'.repeat(48));
        expect(response.status()).toBe(404);
        expect(response.headers()['x-robots-tag']).toBe('noindex, nofollow, noarchive');
    });

    test('shows the create screen in the panel', async ({ page }, testInfo) => {
        test.skip(!ADMIN, 'يحتاج مشرفاً تجريبياً: EVIDENCE_ADMIN_CREDENTIALS من ejaz:demo-user --admin --json');

        await page.goto('/login');
        await page.fill('#field-login', ADMIN.email);
        await page.fill('#field-password', ADMIN.password);
        await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
        await page.fill('input[name="code"]', await freshCode());
        await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);

        await page.goto('/admin/evidence-galleries/create');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByLabel('العنوان')).toBeVisible();

        // بند جديد: يبدأ باختيار المصدر (ألبوم أو عنصر أرشيف)
        await page.getByLabel('العنوان').fill('معرض شواهد تجريبي');
        await page.getByRole('button', { name: 'إضافة بند', exact: true }).click();
        await expect(page.getByText('المصدر').first()).toBeVisible({ timeout: 15_000 });

        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
        await page.evaluate(() => document.fonts.ready);
        mkdirSync(SHOTS, { recursive: true });
        await page.screenshot({ path: `${SHOTS}/admin-create-${testInfo.project.use.viewport.width}.png`, fullPage: true });
    });
});
