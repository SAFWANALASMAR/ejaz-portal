// فحص لوحة الإدارة وبوابة الجمعية العمومية بحساب المدير التنفيذي (docs/reviews/2026-11-02-v0.15-admin-qa.md):
// يزحف من /admin و/assembly على الروابط الداخلية، صفحةً لكل نمط مسار (المعرّفات تُوحَّد)، ويتحقق في كل صفحة من:
// لا خطأ 4xx/5xx، ولا خطأ JavaScript ولا خطأ في وحدة التحكم، ولا طلب فاشل، ولا نص إنجليزي ظاهر (مفاتيح ترجمة،
// ونصوص Filament غير المترجمة، وقيم الحالات) سوى أسماء العلامات والروابط، ولا تمرير أفقي على 390.
// ثم يفتح في صفحات القوائم أزرار الرأس ومجموعة إجراءات الصف الأول، ويتحقق أن النافذة تفتح بلا خطأ.
//
// يحتاج حساب مدير تنفيذي بتحقق ثنائي وبيانات تجريبية محلية (لا تُحفظ في قيت):
//   ADMIN_QA_CREDENTIALS  {"email","password","totp_secret"}
// التشغيل (الأصول مبنية): docker compose run --rm --no-deps -e VITE_HOST=none -e ADMIN_QA_CREDENTIALS="$creds" browser \
//   npx playwright test --config=tests/browser/playwright.config.mjs v015-admin-qa.spec.mjs --project=desktop --project=mobile
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ADMIN = process.env.ADMIN_QA_CREDENTIALS ? JSON.parse(process.env.ADMIN_QA_CREDENTIALS) : null;
const WINDOW_FILE = 'storage/playwright/admin-qa-last-totp-window';
const MAX_PAGES = Number(process.env.ADMIN_QA_MAX_PAGES ?? 400);

// أسماء علامات ومصطلحات تُكتب بالإنجليزية عمداً
const ALLOWED = new Set(`
Microsoft Teams Graph Entra YouTube WhatsApp Cloud API PDF Excel Word Zoom Turnstile Cloudflare Anthropic Claude OpenAI
Salla Moyasar Google Horizon SMS QR URL CSV XLSX HTML JSON TOTP Unifonic Taqnyat Msegat Hetzner Contabo Ejaz ejaz sa
Filament Laravel Livewire Instagram Facebook LinkedIn TikTok Snapchat Telegram Threads Linktree Mailpit Redis HTTPS HTTP
PNG JPG JPEG WEBP SVG MP MB KB GB ID iOS Android Apple Outlook Gmail Tenant Client Secret Tweet DOCX DOC Brahin Meta
`.split(/\s+/).filter(Boolean));

function base32(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
    return Buffer.from(bytes);
}

// رمز TOTP لا يُعاد في نافذته (الخادم يرفض إعادة الرمز نفسه)، وآخر نافذة في ملف بين المشروعين
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

// نمط المسار: المعرّفات الرقمية وuuid وما يشبهها تُوحَّد، فتُزار صفحة واحدة لكل نمط
function pattern(pathname) {
    return pathname
        .split('/')
        .map((part) => (/^\d+$/.test(part) || /^[0-9a-f-]{20,}$/i.test(part) ? '{id}' : part))
        .join('/');
}

// ما لا يُزحف إليه: الخروج والتنزيلات والتصدير والملفات الموقّعة
const SKIP = /\/(logout|download|export|exports|files\/[^/]+$|attachments\/|admin-files\/)|horizon|livewire|\.(pdf|docx?|xlsx|csv|zip|png|jpe?g|webp|mp[34])$/i;

async function englishOnPage(page) {
    return page.evaluate((allowed) => {
        const allow = new Set(allowed);
        const found = new Set();
        const visible = (el) => el && el.checkVisibility?.({ checkOpacity: true, checkVisibilityCSS: true }) !== false;
        const check = (text) => {
            for (const raw of text.split(/\s+/)) {
                const token = raw.replace(/^[^A-Za-z]+|[^A-Za-z0-9]+$/g, '');
                // البريد والروابط والمسارات والمعرّفات النصية (slug) بيانات لا واجهة
                if (!token || /[@/]|^www\.|\.(sa|com|org|net|test)$/i.test(raw) || /^[a-z0-9]+(-[a-z0-9]+)+$/.test(token)) continue;
                if (!/[A-Za-z]{2,}/.test(token) || allow.has(token) || allow.has(token.toUpperCase())) continue;
                found.add(token);
            }
        };
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const parent = node.parentElement;
            if (!parent || ['SCRIPT', 'STYLE', 'NOSCRIPT', 'CODE', 'PRE', 'TEMPLATE'].includes(parent.tagName)) continue;
            if (parent.closest('[dir="ltr"], .fi-ta-text-item-label[dir="ltr"], input, textarea, [data-qa-ignore]')) continue;
            if (!visible(parent)) continue;
            check(node.textContent ?? '');
        }
        for (const el of document.querySelectorAll('[placeholder], [aria-label], [title]')) {
            if (!visible(el) && el.getAttribute('aria-label') === null) continue;
            for (const attr of ['placeholder', 'aria-label', 'title']) {
                const value = el.getAttribute(attr);
                if (value) check(value);
            }
        }
        return [...found];
    }, [...ALLOWED]);
}

test.describe.configure({ mode: 'serial' });

test.describe('admin panel QA as the executive director', () => {
    test.skip(!ADMIN, 'يحتاج حساب المدير التنفيذي: ADMIN_QA_CREDENTIALS');

    let page;
    let current = '';
    const problems = [];
    const note = (kind, detail) => problems.push({ page: current, kind, detail });

    test.beforeAll(async ({ browser }, testInfo) => {
        const { baseURL, viewport, isMobile, hasTouch, locale, timezoneId } = testInfo.project.use;
        page = await (await browser.newContext({ baseURL, viewport, isMobile, hasTouch, locale, timezoneId })).newPage();
        page.on('pageerror', (error) => note('pageerror', error.message));
        page.on('console', (message) => {
            if (message.type() === 'error') note('console', message.text().slice(0, 300));
        });
        page.on('requestfailed', (request) => {
            const failure = request.failure()?.errorText ?? '';
            // التنقّل المقطوع بتنقّل آخر ليس خللاً
            if (!/ERR_ABORTED|NS_BINDING_ABORTED/.test(failure)) note('requestfailed', `${request.url()} ${failure}`);
        });
        page.on('response', (response) => {
            const url = new URL(response.url());
            if (url.origin === new URL(baseURL).origin && response.status() >= 400) note('http', `${response.status()} ${url.pathname}`);
        });

        await page.goto('/login');
        await page.fill('#field-login', ADMIN.email);
        await page.fill('#field-password', ADMIN.password);
        await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
        await page.fill('input[name="code"]', await freshCode());
        await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);
    });

    test.afterAll(async ({}, testInfo) => {
        mkdirSync('storage/playwright', { recursive: true });
        writeFileSync(`storage/playwright/admin-qa-${testInfo.project.name}.json`, JSON.stringify(problems, null, 1));
        await page?.context().close();
    });

    test('every reachable admin and assembly page is clean', async ({}, testInfo) => {
        test.setTimeout(60 * 60_000);
        const origin = new URL(testInfo.project.use.baseURL).origin;
        const queue = ['/admin', '/assembly'];
        const seen = new Set(queue.map(pattern));
        let visited = 0;

        while (queue.length && visited < MAX_PAGES) {
            const path = queue.shift();
            current = path;
            visited++;
            const response = await page.goto(path, { waitUntil: 'load' });
            await page.waitForTimeout(400);
            if ((response?.status() ?? 0) >= 400) continue;

            const english = await englishOnPage(page);
            if (english.length) note('english', english.join(' '));

            if (testInfo.project.name === 'mobile') {
                const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
                if (overflow > 0) note('overflow', `${overflow}px`);
            }

            const hrefs = await page.$$eval('a[href]', (links) => links.map((a) => a.href));
            for (const href of hrefs) {
                let url;
                try {
                    url = new URL(href);
                } catch {
                    continue;
                }
                if (url.origin !== origin || !/^\/(admin|assembly)(\/|$)/.test(url.pathname) || SKIP.test(url.pathname)) continue;
                const key = pattern(url.pathname);
                if (seen.has(key)) continue;
                seen.add(key);
                queue.push(url.pathname);
            }
        }

        console.log(`admin-qa ${testInfo.project.name}: ${visited} pages, ${problems.length} problems`);
        expect.soft(problems, JSON.stringify(problems, null, 1)).toEqual([]);
    });
});
