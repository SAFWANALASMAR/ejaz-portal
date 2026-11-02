// «إحصائيات البرامج لكل نوع» (docs/decisions/2026-11-02-program-stats-by-type.md) على العروض الثلاثة: رسم المقارنة في رئيسية
// اللوحة مرسوماً بـ Chart.js، وقسم بوابة المجلس في /assembly ببطاقة لكل نوع، والاتجاه من اليمين، وبلا تمرير أفقي ولا أخطاء في
// وحدة التحكم ولا مخالفة لفحص الوصول. ولقطة لكل عرض في docs/design/screenshots/program-stats-{admin,board}-<العرض>.png
//
// يحتاج مشرفاً تجريبياً بتحقق ثنائي (ejaz:demo-user --admin --json في ADMIN_STATS_CREDENTIALS) وحساباً للإحصائيات قبل التشغيل
// (ejaz:refresh-stats). والأرقام في اللقطات من بيانات محلية تجريبية.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ADMIN = process.env.ADMIN_STATS_CREDENTIALS ? JSON.parse(process.env.ADMIN_STATS_CREDENTIALS) : null;
const HEADING = 'إحصائيات البرامج لكل نوع';

// رمز TOTP لا يُقبل مرتين: آخر نافذة في الملف المشترك مع admin-spa.spec.mjs وadmin-stats.spec.mjs
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

test.describe('program stats by type', () => {
    test.skip(!ADMIN, 'يحتاج مشرفاً تجريبياً: ADMIN_STATS_CREDENTIALS من ejaz:demo-user --admin --json');

    test('shows the comparison chart in the admin and the section in the board portal', async ({ page }, testInfo) => {
        const width = testInfo.project.use.viewport.width;
        const errors = [];
        const ignored = (text) => text.startsWith('Transition was aborted');
        page.on('pageerror', (error) => !ignored(error.message) && errors.push(error.message));
        // انتقال الصفحة المقطوع بالانتقال المباشر (page.goto) رسالة من المتصفح لا خطأ في الصفحة
        page.on('console', (message) => message.type() === 'error' && !ignored(message.text()) && errors.push(message.text()));

        await page.goto('/login');
        await page.fill('#field-login', ADMIN.email);
        await page.fill('#field-password', ADMIN.password);
        await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
        await page.fill('input[name="code"]', await freshCode());
        await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);

        // اللوحة: الرسم باسمه ونصه البديل ولوحة مرسومة فعلاً، والنسبة لكل نوع في وصفه
        await page.goto('/admin');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        await page.waitForLoadState('networkidle');

        const chart = page.locator('.fi-wi-chart', { has: page.getByRole('heading', { name: HEADING }) });
        await expect(chart).toBeVisible();
        await expect(chart.locator('[data-program-stats-row="competitions"]')).toContainText('نسبة الإكمال');
        const canvas = chart.locator('canvas');
        await expect(canvas).toBeVisible();
        await expect.poll(() => canvas.evaluate((element) => {
            const pixels = element.getContext('2d').getImageData(0, 0, element.width, element.height).data;
            return pixels.some((value, index) => index % 4 === 3 && value > 0);
        })).toBe(true);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);

        const admin = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).include('.fi-wi-chart').analyze();
        expect(admin.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([]);

        await chart.scrollIntoViewIfNeeded();
        await page.evaluate(() => document.fonts.ready);
        // الشريط العلوي الثابت يغطي رأس الرسم في لقطة العنصر
        await chart.screenshot({ path: `docs/design/screenshots/program-stats-admin-${width}.png`, style: '.fi-topbar-ctn, .fi-topbar { visibility: hidden !important; }' });

        // بوابة المجلس: القسم ببطاقة لكل نوع
        await page.goto('/assembly');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        const section = page.locator('[data-board-program-stats]');
        await expect(section.getByRole('heading', { name: HEADING })).toBeVisible();
        await expect(section.locator('[data-program-stats-row]')).not.toHaveCount(0);
        await expect(section.locator('[data-program-stats-row="competitions"]')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);

        const board = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).include('[data-board-program-stats]').analyze();
        expect(board.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([]);

        await section.scrollIntoViewIfNeeded();
        await page.evaluate(() => document.fonts.ready);
        await section.screenshot({ path: `docs/design/screenshots/program-stats-board-${width}.png` });

        expect(errors).toEqual([]);
    });
});
