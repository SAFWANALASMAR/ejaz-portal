// جرد شاشات لوحة الإدارة بالسمة (docs/decisions/2026-10-30-admin-theme.md): كل مسار GET تحت /admin بلا معامل،
// وكل مسار بمعامل وُجد له رابط في قائمته (أول سجل)، ثم صفحتا الدخول والتحقق الثنائي. لكل شاشة لقطة بعرض
// الشاشة (لا الصفحة كاملة، لحجم المستودع) في docs/design/screenshots/admin-theme/<المسار>-<العرض>.jpg، والحالة
// والتمرير الأفقي في storage/playwright/admin-theme-inventory-<العرض>.json، ومنه يُكتب inventory.md.
//
// يحتاج: storage/playwright/admin-routes.json من route:list --path=admin --method=GET --json، ومديراً تجريبياً
// (ADMIN_THEME_CREDENTIALS) يرى كل الشاشات. التشغيل في tests/browser/README.md («سمة لوحة الإدارة»).
import { test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ADMIN = process.env.ADMIN_THEME_CREDENTIALS ? JSON.parse(process.env.ADMIN_THEME_CREDENTIALS) : null;
const ROUTES_FILE = 'storage/playwright/admin-routes.json';
const STATE_FILE = 'storage/playwright/admin-theme-state.json';
const WINDOW_FILE = 'storage/playwright/admin-spa-last-totp-window';
// والجرد النهائي للنسخة 0.15 (المرحلة د): INVENTORY_DIR=docs/design/screenshots/v0.15 وINVENTORY_FORMAT=png، بأسماء admin-*
const OUT = process.env.INVENTORY_DIR ?? 'docs/design/screenshots/admin-theme';
const PNG = process.env.INVENTORY_FORMAT === 'png';

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
    writeFileSync(WINDOW_FILE, String(window));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(window));
    const hmac = createHmac('sha1', base32(ADMIN.totp_secret)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 15;
    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

const slug = (path) => path.replace(/^\//, '').replace(/[^a-z0-9]+/gi, '-').replace(/-+$/, '') || 'admin';

test.describe('admin theme inventory', () => {
    test.skip(!ADMIN || !existsSync(ROUTES_FILE), 'يحتاج ADMIN_THEME_CREDENTIALS وstorage/playwright/admin-routes.json');
    test.setTimeout(1_800_000);

    test('screenshots every admin screen', async ({ browser, baseURL }, testInfo) => {
        const { viewport, isMobile, hasTouch } = testInfo.project.use;
        mkdirSync(OUT, { recursive: true });
        const results = [];
        const shoot = async (page, path, kind) => {
            const response = await page.goto(path, { waitUntil: 'networkidle' }).catch(() => null);
            const status = response?.status() ?? 0;
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth).catch(() => null);
            const name = PNG && !slug(path).startsWith('admin') ? `admin-${slug(path)}` : slug(path);
            const file = `${name}-${viewport.width}.${PNG ? 'png' : 'jpg'}`;
            await page.screenshot(PNG ? { path: `${OUT}/${file}`, type: 'png' } : { path: `${OUT}/${file}`, type: 'jpeg', quality: 60 });
            results.push({ path, kind, status, overflow, themed: await page.locator('.ej-sidebar-brand').count().catch(() => 0) > 0, file });
        };

        // الدخول والتحقق الثنائي: الشاشتان قبل اللوحة، بلا إرسال رمز
        const guest = await browser.newContext({ baseURL, viewport, isMobile, hasTouch, locale: 'ar-SA' });
        const guestPage = await guest.newPage();
        await shoot(guestPage, '/login', 'auth');
        await guestPage.fill('#field-login', ADMIN.email);
        await guestPage.fill('#field-password', ADMIN.password);
        await Promise.all([guestPage.waitForURL('**/two-factor-challenge'), guestPage.locator('main form button[type=submit]').first().click()]);
        await shoot(guestPage, '/two-factor-challenge', 'auth');

        if (!existsSync(STATE_FILE)) {
            await guestPage.fill('input[name="code"]', await freshCode());
            await Promise.all([guestPage.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), guestPage.locator('main form button[type=submit]').first().click()]);
            await guest.storageState({ path: STATE_FILE });
        }
        await guest.close();

        const context = await browser.newContext({ baseURL, viewport, isMobile, hasTouch, storageState: STATE_FILE, locale: 'ar-SA' });
        const page = await context.newPage();
        const routes = JSON.parse(readFileSync(ROUTES_FILE, 'utf8')).map((route) => route.uri).filter((uri) => /^admin(\/|$)/.test(uri));
        const plain = routes.filter((uri) => !uri.includes('{'));
        const patterns = routes.filter((uri) => uri.includes('{')).map((uri) => ({ uri, regex: new RegExp(`^/${uri.replace(/\{[^}]+\}/g, '[^/?#]+')}$`) }));
        const found = new Map();

        for (const uri of plain) {
            await shoot(page, `/${uri}`, 'route');
            // روابط المسارات ذات المعامل في هذه الصفحة: أول رابط لكل مسار
            const hrefs = await page.locator('a[href]').evaluateAll((links) => links.map((link) => new URL(link.href).pathname));
            for (const href of hrefs) {
                const match = patterns.find((pattern) => !found.has(pattern.uri) && pattern.regex.test(href));
                if (match) found.set(match.uri, href);
            }
        }

        for (const { uri } of patterns) {
            if (found.has(uri)) await shoot(page, found.get(uri), `/${uri}`);
            else results.push({ path: `/${uri}`, kind: 'unseeded', status: null, overflow: null, themed: null, file: null });
        }

        await context.close();
        writeFileSync(`storage/playwright/admin-theme-inventory-${viewport.width}.json`, JSON.stringify(results, null, 1));
    });
});
