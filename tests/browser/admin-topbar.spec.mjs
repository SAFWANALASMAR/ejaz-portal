// الشريط العلوي والجانبي في لوحة الإدارة (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ١): على 1440 و1280 و390، موسَّعاً ومصغَّراً،
// لدورين (مدير النظام وعضو المجلس). يتحقق من:
// - الشريط العلوي: رمز الشعار والمخطوطة ظاهران، والبحث كبسولي، وتدرج الهوية بحد سفلي #8EC5F0.
// - الشريط المصغَّر: كل أيقونة في مربعها وسط الشريط بلا قص، ولا نص ظاهر، والشعار الرمز وحده في الوسط، والتلميح عند المرور،
//   والنشط مميز، وعناوين المجموعات لا تظهر.
// - الاتجاه من اليمين، ولا تمرير أفقي.
// ولقطة لكل حالة في docs/design/screenshots/admin-topbar/<SHOT_PREFIX>-<الدور>-<الحالة>-<العرض>.png (SHOT_PREFIX: before أو after).
//
// التشغيل (البيئة المحلية وحدها، والأصول مبنية):
//   ADMIN_TOPBAR_ROLES='{"admin":{...},"board":{...}}'  من ejaz:demo-user --admin --json ولعضو مجلس بتحقق ثنائي
//   docker compose run --rm --no-deps -e VITE_HOST=none -e ADMIN_TOPBAR_ROLES="$roles" -e SHOT_PREFIX=after browser \
//     npx playwright test --config=tests/browser/playwright.config.mjs admin-topbar.spec.mjs --project=desktop
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ROLES = process.env.ADMIN_TOPBAR_ROLES ? JSON.parse(process.env.ADMIN_TOPBAR_ROLES) : null;
const PREFIX = process.env.SHOT_PREFIX ?? 'after';
const DIR = 'docs/design/screenshots/admin-topbar';
const WINDOW_FILE = 'storage/playwright/admin-topbar-last-totp-window';
const WIDTHS = [
    { width: 1440, height: 900 },
    { width: 1280, height: 800 },
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
    const file = `storage/playwright/admin-topbar-${role}.json`;
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
    await page.evaluate(() => document.fonts?.ready);
    await page.waitForTimeout(400);
}

async function shot(page, role, state, width) {
    mkdirSync(DIR, { recursive: true });
    await page.screenshot({ path: `${DIR}/${PREFIX}-${role}-${state}-${width}.png` });
}

// الفحوص المشتركة لكل حالة: الاتجاه، ولا تمرير أفقي، والشريط العلوي بالهوية
async function checkCommon(page, label) {
    await expect.soft(page.locator('html'), label).toHaveAttribute('dir', 'rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect.soft(overflow, `${label}: تمرير أفقي`).toBeLessThanOrEqual(0);

    const topbar = page.locator('.fi-topbar').first();
    const style = await topbar.evaluate((el) => ({ bg: getComputedStyle(el).backgroundImage, border: getComputedStyle(el).borderBottomColor }));
    expect.soft(style.bg, `${label}: تدرج الشريط العلوي`).toContain('linear-gradient');
    expect.soft(style.border, `${label}: الحد السفلي #8EC5F0`).toBe('rgb(142, 197, 240)');

    // رمز الشعار والمخطوطة ظاهران في الشريط العلوي، داخل حدوده
    const brand = page.locator('.fi-topbar [data-ej-topbar-brand]');
    await expect.soft(brand, `${label}: هوية الشريط العلوي`).toBeVisible();
    // المخطوطة 22 بكسل على الأقل لتُقرأ، وتحت 432 بكسل مخفية والرمز وحده (ملاحظة المقيّم على #166)
    const narrow = page.viewportSize().width < 432;
    const calligraphy = page.locator('.fi-topbar [data-ej-topbar-brand] [data-ej-brand-calligraphy]');
    if (narrow) {
        await expect.soft(calligraphy, `${label}: المخطوطة مخفية تحت 432`).toBeHidden();
    } else if (await calligraphy.count()) {
        expect.soft((await calligraphy.boundingBox())?.height ?? 0, `${label}: المخطوطة مقروءة`).toBeGreaterThanOrEqual(22);
    }
    for (const part of narrow ? ['mark'] : ['mark', 'calligraphy']) {
        const img = page.locator(`.fi-topbar [data-ej-topbar-brand] [data-ej-brand-${part}]`);
        await expect.soft(img, `${label}: ${part}`).toBeVisible();
        if (await img.count()) {
            const box = await img.boundingBox();
            const bar = await topbar.boundingBox();
            expect.soft(box && bar && box.y >= bar.y - 1 && box.y + box.height <= bar.y + bar.height + 1, `${label}: ${part} داخل الشريط`).toBeTruthy();
            expect.soft(await img.evaluate((el) => el.complete && el.naturalWidth > 0), `${label}: ${part} محمّل`).toBeTruthy();
        }
    }

    // لا تداخل ولا قص (ملاحظات صفوان على #166): أزرار نهاية الشريط كلها على يسار الهوية وداخل الشاشة، وزر المساعد ظاهر إن شُغّل
    const layout = await page.evaluate(() => {
        const brand = document.querySelector('.fi-topbar [data-ej-topbar-brand]')?.getBoundingClientRect();
        const items = [...document.querySelectorAll('.fi-topbar-end > *, .fi-topbar .fi-user-menu-trigger')]
            .map((el) => el.getBoundingClientRect())
            .filter((r) => r.width > 0);
        return { brandStart: brand?.x ?? 0, endRight: Math.max(...items.map((r) => r.x + r.width)), endLeft: Math.min(...items.map((r) => r.x)) };
    });
    expect.soft(layout.endRight, `${label}: أزرار الشريط لا تتداخل مع الهوية`).toBeLessThanOrEqual(layout.brandStart + 1);
    // هامش عن حافة الشاشة، لا ملاصقة لها (كانت صورة الحساب على بكسلين من الحافة)
    expect.soft(layout.endLeft, `${label}: أزرار الشريط داخل الشاشة بهامش`).toBeGreaterThanOrEqual(8);
    const assistant = page.locator('.fi-topbar [data-ej-topbar-assistant]');
    if (await assistant.count()) {
        await expect.soft(assistant, `${label}: زر البحث بالذكاء الاصطناعي`).toBeVisible();
    }

    // البحث كبسولي إن ظهر (للأدوار التي لها موارد قابلة للبحث)
    const search = page.locator('.fi-topbar .fi-global-search-field .fi-input-wrp').first();
    if (await search.count() && await search.isVisible()) {
        const radius = await search.evaluate((el) => parseFloat(getComputedStyle(el).borderTopLeftRadius));
        const height = (await search.boundingBox()).height;
        expect.soft(radius, `${label}: البحث كبسولي`).toBeGreaterThanOrEqual(height / 2 - 1);
    }
}

// الشريط المصغَّر: كل أيقونة وسط الشريط وفي مربعها كاملاً، ولا نص ولا عنوان مجموعة ظاهر، والشعار الرمز وحده في الوسط
async function checkCollapsed(page, label) {
    const sidebar = page.locator('.fi-sidebar').first();
    const bar = await sidebar.boundingBox();
    const centre = bar.x + bar.width / 2;

    const icons = await page.locator('.fi-sidebar-nav .fi-sidebar-item-btn > .fi-sidebar-item-icon').evaluateAll((items) => items
        .filter((icon) => icon.getClientRects().length)
        .map((icon) => {
            const r = icon.getBoundingClientRect();
            const btn = icon.parentElement.getBoundingClientRect();
            return { x: r.x, w: r.width, h: r.height, btnX: btn.x, btnW: btn.width, btnH: btn.height, y: r.y, btnY: btn.y };
        }));
    expect.soft(icons.length, `${label}: أيقونات ظاهرة`).toBeGreaterThan(3);
    for (const [index, icon] of icons.entries()) {
        expect.soft(Math.abs(icon.x + icon.w / 2 - centre), `${label}: الأيقونة ${index + 1} في الوسط`).toBeLessThanOrEqual(2);
        expect.soft(icon.x >= icon.btnX - 0.5 && icon.x + icon.w <= icon.btnX + icon.btnW + 0.5, `${label}: الأيقونة ${index + 1} داخل زرها أفقياً`).toBeTruthy();
        expect.soft(icon.y >= icon.btnY - 0.5 && icon.y + icon.h <= icon.btnY + icon.btnH + 0.5, `${label}: الأيقونة ${index + 1} داخل زرها عمودياً`).toBeTruthy();
        expect.soft(Math.abs(icon.w - icon.h), `${label}: مربع الأيقونة ${index + 1}`).toBeLessThanOrEqual(1);
        expect.soft(Math.abs(icon.btnW - icon.btnH), `${label}: زر الأيقونة ${index + 1} مربع`).toBeLessThanOrEqual(2);
    }

    const visibleText = await page.locator('.fi-sidebar-nav .fi-sidebar-item-label, .fi-sidebar-nav .fi-sidebar-group-label').evaluateAll((items) => items.filter((el) => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden' && el.getBoundingClientRect().width > 0).length);
    expect.soft(visibleText, `${label}: لا نص ظاهر في المصغَّر`).toBe(0);

    // لا قص: لا عنصر يخرج عن عرض الشريط
    const outside = await page.locator('.fi-sidebar-nav *').evaluateAll((items, b) => items.filter((el) => {
        if (!el.getClientRects().length) return false;
        const r = el.getBoundingClientRect();
        return r.width > 0 && (r.x < b.x - 1 || r.x + r.width > b.x + b.width + 1);
    }).map((el) => el.className.toString().slice(0, 50)).slice(0, 5), bar);
    expect.soft(outside, `${label}: عناصر خارج الشريط`).toEqual([]);

    // النشط مميز بحد #8EC5F0
    const active = page.locator('.fi-sidebar-nav .fi-sidebar-item.fi-active > .fi-sidebar-item-btn').first();
    if (await active.count()) {
        expect.soft(await active.evaluate((btn) => getComputedStyle(btn).borderTopColor), `${label}: النشط`).toBe('rgb(142, 197, 240)');
    }

    // التلميح باسم العنصر عند المرور
    const first = page.locator('.fi-sidebar-nav .fi-sidebar-item-btn').first();
    await first.hover();
    await page.waitForTimeout(500);
    await expect.soft(page.locator('[data-tippy-root], .tippy-box, [role="tooltip"]').first(), `${label}: التلميح`).toBeVisible();
}

async function setCollapsed(page, collapsed) {
    const button = page.locator(collapsed ? '.fi-topbar-close-collapse-sidebar-btn' : '.fi-topbar-open-collapse-sidebar-btn').first();
    if (await button.isVisible()) {
        await button.click();
        await page.waitForTimeout(500);
    }
}

test.describe('admin topbar and sidebar', () => {
    test.skip(!ROLES, 'يحتاج حسابين بتحقق ثنائي: ADMIN_TOPBAR_ROLES');

    for (const role of ['admin', 'board']) {
        test(`${role}: topbar and sidebar, expanded and collapsed`, async ({ browser, baseURL }) => {
            test.setTimeout(900_000);
            const storageState = await stateFor(browser, baseURL, role);

            for (const vp of WIDTHS) {
                const context = await browser.newContext({ baseURL, storageState, locale: 'ar-SA', viewport: { width: vp.width, height: vp.height }, isMobile: !!vp.mobile, hasTouch: !!vp.mobile });
                const page = await context.newPage();
                await page.goto('/admin');
                await settle(page);

                if (vp.mobile) {
                    await checkCommon(page, `${role} ${vp.width}`);
                    await shot(page, role, 'dashboard', vp.width);
                    await page.locator('.fi-topbar-open-sidebar-btn').first().click();
                    await page.waitForTimeout(600);
                    await expect.soft(page.locator('.fi-sidebar.fi-sidebar-open'), `${role} 390: القائمة مفتوحة`).toBeVisible();
                    await shot(page, role, 'drawer', vp.width);
                    // وزر الإغلاق يغلقها، ويعتمد مخزن Filament الداخلي ($store.sidebar) الذي غيّره 5.11 (مراجعة #246)
                    await page.locator('[data-ej-sidebar-close]').click();
                    await expect(page.locator('.fi-sidebar.fi-sidebar-open'), `${role} 390: القائمة تُغلق بالزر`).toHaveCount(0);
                } else {
                    await setCollapsed(page, false);
                    await checkCommon(page, `${role} ${vp.width} موسّع`);
                    await shot(page, role, 'expanded', vp.width);

                    await setCollapsed(page, true);
                    await checkCommon(page, `${role} ${vp.width} مصغّر`);
                    await shot(page, role, 'collapsed', vp.width);
                    await checkCollapsed(page, `${role} ${vp.width} مصغّر`);
                    await shot(page, role, 'collapsed-tooltip', vp.width);
                    await setCollapsed(page, false);
                }

                // الصفحات الداخلية (البند ٣): قائمة ونموذج، موسّعاً على الحاسوب وعلى الجوال
                if (vp.width !== 1280) {
                    for (const [name, path] of role === 'admin' ? [['list', '/admin/menu-items'], ['form', '/admin/home-content']] : [['list', '/admin/meetings']]) {
                        const response = await page.goto(path);
                        if (!response || response.status() >= 400) continue;
                        await settle(page);
                        await checkCommon(page, `${role} ${vp.width} ${name}`);
                        // لا حقل رفع أصلي ظاهر بنص المتصفح الإنجليزي («No file chosen»)، قبل تحميل مكوّن الرفع وبعده (ملاحظة المقيّم)
                        const rawInputs = await page.evaluate(() => [...document.querySelectorAll('.fi-fo-file-upload input[type=file]')]
                            .filter((input) => input.getClientRects().length && getComputedStyle(input).opacity !== '0' && !input.closest('.filepond--root')).length);
                        expect.soft(rawInputs, `${role} ${vp.width} ${name}: حقل رفع أصلي ظاهر`).toBe(0);
                        await shot(page, role, name, vp.width);
                    }
                }
                await context.close();
            }
        });
    }
});
