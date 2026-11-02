// جولة فحص v0.15 (docs/reviews/2026-11-02-v0.15-qa.md) بصيغة اختبار يبقى أخضر: كل صفحة عامة، وصفحة تفصيل من كل قائمة
// بأول رابط فيها (فيعمل على أي بيانات)، وصفحات الحساب للمستخدم التجريبي، على العروض الثلاثة: الاتجاه من اليمين، ولا تمرير أفقي،
// ولا خطأ في وحدة التحكم، ولا طلب فاشل (4xx/5xx) غير الصفحة المقصودة، ولا مفتاح ترجمة مسرّب في النص الظاهر وسماته، ولا رابط
// داخلي مكسور.
import { existsSync, readdirSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { AUTH_STATE } from './global-setup.mjs';

const PUBLIC = ['/', '/about', '/initiatives', '/governance', '/library', '/library/books', '/library/research', '/library/videos', '/library/audio',
    '/media', '/media/news', '/media/albums', '/media/announcements', '/media/partners', '/programs', '/services', '/services/assembly-membership',
    '/services/jobs', '/services/volunteering', '/live', '/contact', '/competitions', '/assembly', '/login', '/register', '/forgot-password'];

// الصفحات التي لا تظهر إلا بمحتواها: 404 بلا بث جارٍ أو قادم، فيُتخطى فحصها
const WHEN_PUBLISHED = new Set(['/live']);

// القائمة ← بادئة روابط تفاصيلها
const DETAILS = { '/initiatives': '/initiatives/', '/governance': '/governance/', '/library/books': '/library/books/', '/library/videos': '/library/videos/',
    '/media/news': '/media/news/', '/media/albums': '/media/albums/', '/programs': '/programs/', '/services/jobs': '/services/openings/', '/competitions': '/competitions/' };

const ACCOUNT = ['/account', '/account/profile', '/account/programs', '/account/requests', '/account/competitions', '/account/forms', '/account/my-reading', '/account/data'];

// مفتاح ترجمة مسرّب (home.sections.title): يبدأ باسم ملف من lang/ar، فلا يُخلط برقم إصدار أو نطاق أو اسم ملف
const LANG_FILES = readdirSync('lang/ar').filter((file) => file.endsWith('.php')).map((file) => file.slice(0, -4));
const LEAKED_KEY = new RegExp(String.raw`\b(?:${LANG_FILES.join('|')})(?:\.[a-z0-9_]+)+\b`, 'g');

// روابط لا تُطلب: الخروج، والملفات والتنزيل والشهادات والتصدير (توليد ملف بحد طلبات)، والروابط الموقّعة
const SKIP_LINK = /^\/(logout|livewire|horizon|storage|user)\b|\/(download|file|pdf|print|certificate|export|attachments|minutes)$|\/files\/|\/attachments\//;

// كل رابط داخلي يُطلب مرة واحدة في التشغيل، لا مرة لكل صفحة
const checkedLinks = new Map();
const LINKS_PER_TEST = Number(process.env.QA_LINKS_PER_TEST ?? 24);

// الصفحة مع روابطها الداخلية كلها أطول من مهلة الاختبار الافتراضية على خادم التطوير
test.describe.configure({ timeout: 300_000 });

function watch(page) {
    const problems = [];
    // «Failed to load resource» في وحدة التحكم يكرر ما يلتقطه مستمع response، وصفحة 404 المقصودة لها الرسالة نفسها
    page.on('console', (message) => message.type() === 'error' && !/status of 40[34]/.test(message.text()) && problems.push(`console: ${message.text().slice(0, 160)}`));
    page.on('pageerror', (error) => problems.push(`pageerror: ${String(error).slice(0, 160)}`));
    page.on('response', (response) => {
        if (response.status() >= 400 && response.request().resourceType() !== 'document') problems.push(`${response.status()} ${response.url()}`);
    });
    page.on('requestfailed', (request) => !/ERR_ABORTED/.test(request.failure()?.errorText ?? '') && problems.push(`failed ${request.url()}`));
    return problems;
}

async function audit(page, path) {
    const problems = watch(page);
    const response = await page.goto(path, { waitUntil: 'load' });
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

    test.skip(WHEN_PUBLISHED.has(path) && response?.status() === 404, `${path}: لا محتوى منشور`);
    expect(response?.status(), path).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    const facts = await page.evaluate(() => {
        const texts = [...document.querySelectorAll('[placeholder], [aria-label], [title], img[alt]')]
            .flatMap((el) => ['placeholder', 'aria-label', 'title', 'alt'].map((name) => el.getAttribute(name)).filter(Boolean));
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const parent = walker.currentNode.parentElement;
            if (!parent || ['SCRIPT', 'STYLE', 'NOSCRIPT', 'CODE', 'TEMPLATE'].includes(parent.tagName) || parent.closest('[hidden]')) continue;
            const text = walker.currentNode.textContent.trim();
            if (text && parent.getClientRects().length) texts.push(text);
        }
        return {
            overflow: document.documentElement.scrollWidth - window.innerWidth,
            texts,
            links: [...document.querySelectorAll('a[href]')].map((a) => a.href),
        };
    });

    expect(facts.overflow, `${path}: تمرير أفقي`).toBeLessThanOrEqual(1);
    const keys = facts.texts.flatMap((text) => [...text.replace(/\S+@\S+|https?:\/\/\S+/g, '').matchAll(LEAKED_KEY)].map((match) => match[0]));
    expect(keys, `${path}: مفتاح ترجمة ظاهر`).toEqual([]);
    expect(problems, `${path}: أخطاء الصفحة وطلباتها`).toEqual([]);

    return facts.links;
}

async function brokenLinks(page, links) {
    const origin = new URL(page.url()).origin;
    const internal = [...new Set(links.map((href) => { const url = new URL(href); url.hash = ''; return url; })
        .filter((url) => url.origin === origin && !url.searchParams.has('signature') && !SKIP_LINK.test(url.pathname))
        .map((url) => url.href))];
    // الجديد منها وحده، أربعة معاً وحتى LINKS_PER_TEST في كل اختبار: الباقي يُفحص في الاختبارات التالية التي ترى الروابط نفسها
    // (الرأس والتذييل في كل صفحة)، فيبقى كل اختبار داخل مهلته على خادم التطوير
    const fresh = internal.filter((href) => !checkedLinks.has(href)).slice(0, LINKS_PER_TEST);
    for (let i = 0; i < fresh.length; i += 4) {
        await Promise.all(fresh.slice(i, i + 4).map(async (href) => {
            checkedLinks.set(href, (await page.request.get(href, { maxRedirects: 5, timeout: 60_000 })).status());
        }));
    }
    return internal.filter((href) => checkedLinks.has(href) && checkedLinks.get(href) !== 200).map((href) => `${checkedLinks.get(href)} ${href}`);
}

test.describe('v0.15 QA sweep — public pages', () => {
    for (const path of PUBLIC) {
        test(`clean: ${path}`, async ({ page }) => {
            const links = await audit(page, path);
            expect(await brokenLinks(page, links), `${path}: روابط داخلية مكسورة`).toEqual([]);

            // صفحة التفصيل نفسها: مقطع واحد بعد البادئة، لا مسار فرعي مثل /programs/{slug}/register
            const prefix = DETAILS[path];
            const detail = prefix && links.map((href) => new URL(href)).find((url) => url.pathname.startsWith(prefix) && /^[^/]+$/.test(url.pathname.slice(prefix.length)));
            if (detail) await audit(page, detail.pathname);
        });
    }

    test('an unknown address answers the Arabic 404 page without other errors', async ({ page }) => {
        const problems = watch(page);
        const response = await page.goto('/v015-qa-no-such-page');
        expect(response?.status()).toBe(404);
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(1);
        expect(problems).toEqual([]);
    });
});

test.describe('v0.15 QA sweep — account pages', () => {
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS من ejaz:demo-user --json');
    test.use({ storageState: AUTH_STATE });

    for (const path of ACCOUNT) {
        test(`clean: ${path}`, async ({ page }) => {
            const links = await audit(page, path);
            expect(await brokenLinks(page, links), `${path}: روابط داخلية مكسورة`).toEqual([]);
        });
    }
});
