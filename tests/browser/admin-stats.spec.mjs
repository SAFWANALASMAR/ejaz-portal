// لوحة إحصائيات الإدارة والمجلس (docs/plans/admin-stats.md) على العروض الثلاثة: المجموعات الأربع بعناوينها
// وأرقامها، ووقت آخر حساب، ورسم الزيارات في آخر 30 يوماً مرسوماً بـ Chart.js، والاتجاه من اليمين، وبلا تمرير
// أفقي ولا أخطاء في وحدة التحكم ولا مخالفة لفحص الوصول. ولقطة لكل عرض في docs/design/screenshots/admin-stats-<العرض>.png
//
// يحتاج مشرفاً تجريبياً بتحقق ثنائي (ejaz:demo-user --admin --json)، وزيارة معدودة واحدة على الأقل ثم حساباً
// للإحصائيات قبل التشغيل (ejaz:refresh-stats)، وإلا ظهر الرسم فارغاً. التشغيل في tests/browser/README.md («لوحة الإحصائيات»).
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ADMIN = process.env.ADMIN_STATS_CREDENTIALS ? JSON.parse(process.env.ADMIN_STATS_CREDENTIALS) : null;

const GROUPS = ['المستخدمون والزيارات', 'الطلبات الإلكترونية', 'البرامج والمسابقات وقياس الرضا', 'الجمعية العمومية والأرشيف'];
const CHART_HEADING = 'الزيارات في آخر 30 يوماً';

// ── التحقق الثنائي: رمز TOTP لا يُقبل مرتين، وآخر نافذة في ملف admin-spa.spec.mjs نفسه، لأن الملفين يدخلان
// بالمشرف التجريبي نفسه في التشغيل نفسه
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

test.describe('admin statistics dashboard', () => {
    test.skip(!ADMIN, 'يحتاج مشرفاً تجريبياً: ADMIN_STATS_CREDENTIALS من ejaz:demo-user --admin --json');

    test('shows the statistics groups and the visits chart', async ({ page }, testInfo) => {
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));

        await page.goto('/login');
        await page.fill('#field-login', ADMIN.email);
        await page.fill('#field-password', ADMIN.password);
        await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
        await page.fill('input[name="code"]', await freshCode());
        await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);

        await page.goto('/admin');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

        // عناصر اللوحة الأخرى تُحمَّل بطلب مؤجل حين تظهر: تُعرض كلها أولاً، فتُفحص الصفحة التي تُلتقط
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => window.scrollTo(0, 0));

        // المجموعات الأربع بأرقامها، وتحت الأولى وقت آخر حساب
        for (const group of GROUPS) {
            const widget = page.locator('.fi-wi-stats-overview', { has: page.getByRole('heading', { name: group, exact: true }) });
            await expect(widget).toBeVisible();
            await expect(widget.locator('.fi-wi-stats-overview-stat-value').first()).toHaveText(/^\s*[\d,]+\s*$/);
        }
        await expect(page.getByText('آخر تحديث:', { exact: false }).first()).toBeVisible();

        // الرسم: اسم يقرؤه قارئ الشاشة، ونص بديل بالأرقام، ولوحة مرسومة فعلاً
        const chart = page.locator('.fi-wi-chart');
        await expect(chart.getByRole('heading', { name: CHART_HEADING })).toBeVisible();
        await expect(chart.getByRole('img', { name: new RegExp(CHART_HEADING) })).toBeVisible();
        await expect(chart.locator('.fi-wi-chart-assistive-content')).toContainText('المشاهدات في آخر 30 يوماً');
        const canvas = chart.locator('canvas');
        await expect(canvas).toBeVisible();
        await expect.poll(() => canvas.evaluate((element) => {
            const pixels = element.getContext('2d').getImageData(0, 0, element.width, element.height).data;
            return pixels.some((value, index) => index % 4 === 3 && value > 0);
        })).toBe(true);
        const box = await canvas.boundingBox();
        expect(box.height).toBeGreaterThan(120);

        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);

        const { violations } = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .include('.fi-wi-stats-overview')
            .include('.fi-wi-chart')
            .analyze();
        expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([]);

        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({ path: `docs/design/screenshots/admin-stats-${testInfo.project.use.viewport.width}.png`, fullPage: true });

        expect(errors).toEqual([]);
    });
});
