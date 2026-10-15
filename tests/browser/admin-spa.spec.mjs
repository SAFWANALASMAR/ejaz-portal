// لوحة الإدارة بوضع SPA (docs/decisions.md — 14 أكتوبر 2026) على العروض الثلاثة: التنقّل بين صفحاتها بلا
// إعادة تحميل كاملة، وحقل التوقيع بعد الوصول إليه بالتنقّل، وتنزيل ملف تصدير، وانتهاء مهلة الخمول.
//
// يحتاج مشرفاً تجريبياً بتحقق ثنائي (ejaz:demo-user --admin --json)، وخادماً بمهلة خمول قصيرة حتى لا ينتظر
// الاختبار نصف ساعة. التشغيل في tests/browser/README.md («لوحة الإدارة بوضع SPA»).
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ADMIN = process.env.ADMIN_SPA_CREDENTIALS ? JSON.parse(process.env.ADMIN_SPA_CREDENTIALS) : null;
// مهلة الخمول في الخادم المختبَر بالدقائق (ADMIN_IDLE_MINUTES)، وبدونها يُتخطى اختبارها
const IDLE_MINUTES = Number(process.env.ADMIN_IDLE_MINUTES ?? 0);
const DEMO_FORM_TITLE = 'نموذج تجريبي بحقل توقيع (للاختبار)';

// ── التحقق الثنائي: رمز TOTP لا يُستعمل مرتين (الخادم يرفض إعادة الرمز نفسه)، وآخر نافذة في ملف
// لأن Playwright يعيد تشغيل العامل بعد كل اختبار فاشل
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

// ── علامة في نافذة الصفحة: تبقى ما دام التنقّل بلا إعادة تحميل، وتزول عند أي تحميل كامل
async function markPage(page) {
    await page.evaluate(() => { window.__adminSpaMarker = 'kept'; });
}

async function expectNoFullReload(page) {
    expect(await page.evaluate(() => window.__adminSpaMarker)).toBe('kept');
}

// القائمة الجانبية على الجوال واللوحي خارج الشاشة حتى تُفتح، وروابطها «ظاهرة» عند Playwright مع ذلك
async function openSidebar(page, link) {
    const inView = () => link.evaluate((element) => {
        const box = element.getBoundingClientRect();
        return box.right > 0 && box.left < window.innerWidth;
    });
    if (await inView()) return;
    await page.locator('.fi-topbar-open-sidebar-btn').click();
    await expect.poll(inView).toBe(true);
}

// رابط من القائمة الجانبية كما ينقره المشرف، وعلى الجوال واللوحي تُفتح القائمة أولاً
async function viaSidebar(page, path) {
    const link = page.locator(`.fi-sidebar-nav a[href$="${path}"]`).first();
    await openSidebar(page, link);
    await expect(link).toHaveAttribute('wire:navigate', '');
    await link.click();
    await page.waitForURL((url) => url.pathname === path);
    await expect(page.locator('h1').first()).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test.describe('admin panel in SPA mode', () => {
    test.skip(!ADMIN, 'يحتاج مشرفاً تجريبياً: ADMIN_SPA_CREDENTIALS من ejaz:demo-user --admin --json');

    let page;
    const errors = [];

    test.beforeAll(async ({ browser }, testInfo) => {
        const { baseURL, viewport, isMobile, hasTouch, locale, timezoneId } = testInfo.project.use;
        page = await (await browser.newContext({ baseURL, viewport, isMobile, hasTouch, locale, timezoneId })).newPage();
        page.on('pageerror', (error) => errors.push(error.message));

        await page.goto('/login');
        await page.fill('#field-login', ADMIN.email);
        await page.fill('#field-password', ADMIN.password);
        await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
        await page.fill('input[name="code"]', await freshCode());
        await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);

        await page.goto('/admin');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await markPage(page);
    });

    test.afterAll(async () => page?.context().close());

    test('moves between three panel pages without a full reload', async () => {
        for (const path of ['/admin/users', '/admin/service-requests', '/admin/form-entries']) {
            await viaSidebar(page, path);
            await expectNoFullReload(page);
        }

        // رابط «عرض الموقع» يفتح تبويباً جديداً ولا يدخل في التنقّل
        const viewSite = page.locator('.fi-sidebar-nav a[target="_blank"]').first();
        await expect(viewSite).not.toHaveAttribute('wire:navigate', /.*/);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
        expect(errors).toEqual([]);
    });

    test('draws a signature on a form reached by navigation, not by a direct load', async ({}, testInfo) => {
        const create = page.locator('a[href$="/admin/form-entries/create"]').first();
        await expect(create).toHaveAttribute('wire:navigate', '');
        await create.click();
        await page.waitForURL('**/admin/form-entries/create');
        await expectNoFullReload(page);

        const template = page.locator('select').filter({ has: page.locator('option', { hasText: DEMO_FORM_TITLE }) }).first();
        await template.selectOption({ label: DEMO_FORM_TITLE });

        const canvas = page.locator('canvas[role="img"]');
        await expect(canvas).toBeVisible();
        await canvas.scrollIntoViewIfNeeded();
        const box = await canvas.boundingBox();
        await page.mouse.move(box.x + box.width * 0.2, box.y + box.height * 0.5);
        await page.mouse.down();
        await page.mouse.move(box.x + box.width * 0.5, box.y + box.height * 0.3, { steps: 8 });
        await page.mouse.move(box.x + box.width * 0.8, box.y + box.height * 0.6, { steps: 8 });
        await page.mouse.up();

        // الرسم ظاهر على اللوحة، والحالة صورة PNG مربوطة بالنموذج
        const drawn = await canvas.evaluate((element) => {
            const pixels = element.getContext('2d').getImageData(0, 0, element.width, element.height).data;
            return pixels.some((value, index) => index % 4 === 3 && value > 0);
        });
        expect(drawn).toBe(true);
        const state = () => canvas.evaluate((element) => window.Alpine.$data(element.closest('[x-data]')).state);
        expect(await state()).toMatch(/^data:image\/png;base64,/);

        await page.screenshot({ path: `docs/design/screenshots/admin-spa-signature-${testInfo.project.use.viewport.width}.png`, fullPage: true });

        // المسح يفرغ الحالة، ثم توقيع جديد يُحفظ مع النموذج
        await canvas.locator('xpath=following-sibling::button').click();
        expect(await state()).toBeNull();
        await page.mouse.move(box.x + box.width * 0.3, box.y + box.height * 0.4);
        await page.mouse.down();
        await page.mouse.move(box.x + box.width * 0.7, box.y + box.height * 0.5, { steps: 8 });
        await page.mouse.up();
        expect(await state()).toMatch(/^data:image\/png;base64,/);

        await page.getByRole('button', { name: 'إضافة', exact: true }).click();
        await page.waitForURL((url) => /\/admin\/form-entries\/\d+/.test(url.pathname));
        await expectNoFullReload(page);
        expect(errors).toEqual([]);
    });

    test('downloads an export file after navigating to the list', async () => {
        await viaSidebar(page, '/admin/form-templates');
        await expectNoFullReload(page);

        // معبأ النموذج CSV (ExportFormEntries): رد ملف من إجراء Livewire، والصفحة تبقى كما هي
        const row = page.locator('tr', { hasText: DEMO_FORM_TITLE }).first();
        const [file] = await Promise.all([page.waitForEvent('download'), row.getByText('تصدير المعبأ').click()]);
        expect(file.suggestedFilename()).toMatch(/\.csv$/);
        const content = readFileSync(await file.path(), 'utf8');
        expect(content).toContain('الاسم');

        await expectNoFullReload(page);
        expect(errors).toEqual([]);
    });

    test('logs the administrator out with a full page load after the idle limit', async () => {
        test.skip(!IDLE_MINUTES || IDLE_MINUTES > 2, 'يحتاج خادماً بمهلة خمول قصيرة: ADMIN_IDLE_MINUTES=1');
        test.setTimeout(IDLE_MINUTES * 60_000 + 120_000);

        // الخمول: لا طلب يصل الخادم أطول من المهلة. وتحديث إشعارات اللوحة الدوري يُوقف، لأنه ليس نشاطاً للمشرف
        await page.route('**/livewire*/update', (route) => route.abort());
        await page.waitForTimeout(IDLE_MINUTES * 60_000 + 5_000);
        await page.unroute('**/livewire*/update');

        const link = page.locator('.fi-sidebar-nav a[href$="/admin/users"]').first();
        await openSidebar(page, link);
        await link.click();

        // صفحة الدخول بتحميل كامل (لا داخل قالب اللوحة)، ومعها رسالة الخروج
        await page.waitForURL('**/login');
        expect(await page.evaluate(() => window.__adminSpaMarker)).toBeUndefined();
        await expect(page.locator('#field-login')).toBeVisible();
        await expect(page.locator('.fi-sidebar')).toHaveCount(0);
        await expect(page.getByText('بسبب عدم النشاط', { exact: false }).first()).toBeVisible();

        await page.goto('/admin');
        await page.waitForURL('**/login');
    });
});
