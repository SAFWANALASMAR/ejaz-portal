// رئيسيات لوحة الإدارة والبوابات (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ٢): رئيسية /admin لكل دور على 1440 و390.
// يتحقق من:
// - التحية باسم صاحب الحساب في رأس الصفحة، والرأس بأيقونة ووصف.
// - بطاقات الإحصاء: أيقونة في مربع متدرج، ورقم بارز (لا يقل عن 30 بكسل على الحاسوب).
// - البوابات: كل قدرة بطاقة بأيقونة، والحالة الفارغة مرسومة لمن لا بوابة له.
// - الاتجاه من اليمين، ولا تمرير أفقي، وتباين النص الأساسي في الرأس والبطاقات (4.5:1 فأعلى).
// ولقطة لكل دور في docs/design/screenshots/admin-dashboards/<SHOT_PREFIX>-<الدور>-<العرض>.png (SHOT_PREFIX: before أو after).
//
// التشغيل (البيئة المحلية وحدها، والأصول مبنية):
//   ADMIN_DASHBOARD_ROLES='{"admin":{"email":..,"password":..,"totp_secret":..},...}'  لحسابات بتحقق ثنائي
//   docker compose run --rm --no-deps -e VITE_HOST=none -e ADMIN_DASHBOARD_ROLES="$roles" -e SHOT_PREFIX=after browser \
//     npx playwright test --config=tests/browser/playwright.config.mjs admin-dashboards.spec.mjs --project=desktop
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ROLES = process.env.ADMIN_DASHBOARD_ROLES ? JSON.parse(process.env.ADMIN_DASHBOARD_ROLES) : null;
const PREFIX = process.env.SHOT_PREFIX ?? 'after';
const DIR = 'docs/design/screenshots/admin-dashboards';
const WINDOW_FILE = 'storage/playwright/admin-dashboards-last-totp-window';
const WIDTHS = [
    { width: 1440, height: 900 },
    { width: 390, height: 844, mobile: true },
];
function base32(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
    return Buffer.from(bytes);
}

// رمز جديد في كل دخول: نافذة لم تُستعمل، وليست في آخر خمس ثوانٍ منها
async function freshCode(secret) {
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
    const hmac = createHmac('sha1', base32(secret)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 15;
    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

async function stateFor(browser, baseURL, role) {
    const file = `storage/playwright/admin-dashboards-${role}.json`;
    if (existsSync(file)) return file;
    const creds = ROLES[role];
    const context = await browser.newContext({ baseURL, locale: 'ar-SA' });
    const page = await context.newPage();
    await page.goto('/login');
    await page.fill('#field-login', creds.email);
    await page.fill('#field-password', creds.password);
    await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
    await page.fill('input[name="code"]', await freshCode(creds.totp_secret));
    await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);
    await context.storageState({ path: file });
    await context.close();
    return file;
}

// صفحة النموذج فيها اتصال لا يهدأ (Livewire)، فالانتظار محدود ثم يُكمل
async function settle(page) {
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
    // العناصر المؤجلة تُحمَّل عند ظهورها: تمرير إلى آخر الصفحة ثم انتظارها كلها، فلا تُلتقط مربعات فارغة
    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 600) {
            window.scrollTo(0, y);
            await new Promise((resolve) => setTimeout(resolve, 150));
        }
        window.scrollTo(0, 0);
    });
    await page.waitForFunction(() => !document.querySelector('[wire\\:snapshot*="\\"lazyLoaded\\":false"]'), null, { timeout: 30_000 }).catch(() => {});
    await page.evaluate(() => document.fonts?.ready);
    await page.waitForTimeout(400);
}

// تباين لونين بصيغة rgb(...)
function contrast(fg, bg) {
    const lum = (c) => {
        const [r, g, b] = c.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number).map((v) => {
            const s = v / 255;
            return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const [a, b] = [lum(fg), lum(bg)].sort((x, y) => y - x);
    return (a + 0.05) / (b + 0.05);
}

async function checkDashboard(page, role, label, mobile) {
    await expect.soft(page.locator('html'), label).toHaveAttribute('dir', 'rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect.soft(overflow, `${label}: تمرير أفقي`).toBeLessThanOrEqual(0);

    // لا مربع فارغ ينتظر تحميلاً مؤجلاً
    expect.soft(await page.evaluate(() => document.querySelectorAll('[wire\\:snapshot*="\\"lazyLoaded\\":false"]').length), `${label}: عناصر لم تُحمَّل`).toBe(0);

    // التحية باسم صاحب الحساب، والرأس بأيقونة ووصف
    const header = page.locator('[data-ej-dashboard-hero]');
    await expect.soft(header, `${label}: رأس الرئيسية`).toBeVisible();
    await expect.soft(header.locator('[data-ej-greeting]'), `${label}: التحية`).toContainText(await page.evaluate(() => document.querySelector('[data-ej-user-name]')?.dataset.ejUserName ?? '§'));
    await expect.soft(header.locator('[data-ej-hero-icon]'), `${label}: أيقونة الرأس`).toBeVisible();
    await expect.soft(header.locator('[data-ej-hero-description]'), `${label}: وصف الرأس`).toBeVisible();

    if (await header.count()) {
        const ratio = await header.locator('[data-ej-greeting]').evaluate((el) => {
            let node = el;
            let bg = 'rgba(0, 0, 0, 0)';
            while (node && /rgba\(0, 0, 0, 0\)|transparent/.test(bg)) {
                bg = getComputedStyle(node).backgroundColor;
                node = node.parentElement;
            }
            return { fg: getComputedStyle(el).color, bg };
        });
        expect.soft(contrast(ratio.fg, ratio.bg), `${label}: تباين التحية`).toBeGreaterThanOrEqual(4.5);
    }

    // بطاقات الإحصاء: الرقم بارز والأيقونة في مربع متدرج
    const stats = await page.locator('.fi-wi-stats-overview-stat').evaluateAll((cards) => cards.filter((c) => c.getClientRects().length).map((c) => {
        const value = c.querySelector('.fi-wi-stats-overview-stat-value');
        const icon = c.querySelector('.fi-wi-stats-overview-stat-label-ctn > .fi-icon');
        return { size: value ? parseFloat(getComputedStyle(value).fontSize) : 0, tile: icon ? getComputedStyle(icon).backgroundImage : 'none' };
    }));
    for (const [index, stat] of stats.entries()) {
        expect.soft(stat.size, `${label}: رقم البطاقة ${index + 1} بارز`).toBeGreaterThanOrEqual(mobile ? 26 : 30);
        expect.soft(stat.tile, `${label}: مربع أيقونة البطاقة ${index + 1} متدرج`).toContain('gradient');
    }

    // البوابة: كل قدرة بطاقة بأيقونة، أو حالة فارغة مرسومة
    const portal = page.locator('[data-ej-portal]');
    if (await portal.count()) {
        const cards = await page.locator('[data-ej-portal-item]').count();
        const withIcon = await page.locator('[data-ej-portal-item] [data-ej-portal-icon]').count();
        expect.soft(cards, `${label}: قدرات البوابة بطاقات`).toBeGreaterThan(0);
        expect.soft(withIcon, `${label}: لكل قدرة أيقونة`).toBe(cards);
    } else if (!(await page.locator('.fi-wi-widget').count())) {
        // لا عنصر في الرئيسية كلها (محرر المحتوى مثلاً)
        await expect.soft(page.locator('[data-ej-dashboard-empty]'), `${label}: حالة فارغة مرسومة`).toBeVisible();
    }
}

test.describe('admin dashboards', () => {
    test.skip(!ROLES, 'ADMIN_DASHBOARD_ROLES غير مضبوط');
    test.setTimeout(900_000);

    for (const role of Object.keys(ROLES ?? {})) {
        test(`${role}: dashboard at 1440 and 390`, async ({ browser, baseURL }) => {
            const storageState = await stateFor(browser, baseURL, role);
            for (const size of WIDTHS) {
                const context = await browser.newContext({ baseURL, locale: 'ar-SA', storageState, viewport: { width: size.width, height: size.height }, isMobile: !!size.mobile, hasTouch: !!size.mobile });
                const page = await context.newPage();
                await page.goto('/admin');
                await settle(page);
                await checkDashboard(page, role, `${role} ${size.width}`, !!size.mobile);
                mkdirSync(DIR, { recursive: true });
                await page.screenshot({ path: `${DIR}/${PREFIX}-${role}-${size.width}.png`, fullPage: true });
                await context.close();
            }
        });
    }
});
