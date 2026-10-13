// الزحف النهائي على نسخة تعمل (docs/reviews/2026-10-13-final-check.md): كل رابط داخلي يصل إليه الزائر
// والمستفيد، وكل رابط قديم في redirects.csv، وكل ملف مرتبط (PDF والصوت).
//
// FULL_SITE_URL=http://localhost:8090 DEMO_PASSWORD=... DEMO_DOMAIN=ejaz-demo.test node tests/browser/final-crawl.mjs
//
// يقرأ فقط (GET)، ولا يدخل لوحة الإدارة ولا يتبع روابط الخروج. ويرفض غير المضيف المحلي إلا بـ FULL_SITE_ALLOW_REMOTE=1.
import { chromium } from '@playwright/test';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const BASE = new URL(process.env.FULL_SITE_URL ?? 'http://localhost:8090');
if (!/^(localhost|127\.0\.0\.1|.+\.test)$/.test(BASE.hostname) && process.env.FULL_SITE_ALLOW_REMOTE !== '1') {
    throw new Error(`Refusing to crawl ${BASE.host}: not a local host.`);
}
const OUT = 'storage/playwright/final-check';
const MAX_PAGES = Number(process.env.CRAWL_MAX_PAGES ?? 600);
// والرجوع من الدفع يتصل ببوابة الدفع (ليس قراءة)
const SKIP = [/^\/admin/, /^\/logout/, /^\/payments\//, /^\/livewire/, /^\/horizon/, /^\/two-factor/, /^\/user\//, /\/download/, /^\/storage\//];
const FILE = /\.(pdf|mp3|mp4|webm|jpe?g|png|webp|svg|zip)$/i;

function internal(href) {
    try {
        const url = new URL(href, BASE);
        if (url.origin !== BASE.origin) return null;
        url.hash = '';
        return url;
    } catch {
        return null;
    }
}

async function crawl(context, label, start) {
    const queue = [...start];
    const seen = new Set(queue);
    const pages = [];
    const files = new Map();
    const page = await context.newPage();
    let errors = [];
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text().slice(0, 200)));
    page.on('pageerror', (e) => errors.push(String(e).slice(0, 200)));

    while (queue.length && pages.length < MAX_PAGES) {
        const path = queue.shift();
        errors = [];
        let status = 0;
        try {
            const response = await page.goto(new URL(path, BASE).href, { waitUntil: 'load', timeout: 60_000 });
            status = response?.status() ?? 0;
        } catch (e) {
            pages.push({ path, status: 'timeout', note: String(e).slice(0, 120) });
            continue;
        }
        const info = await page.evaluate(() => ({
            lang: document.documentElement.lang,
            dir: document.documentElement.dir,
            html: (document.contentType ?? '').includes('html'),
            links: [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')),
            media: [...document.querySelectorAll('audio[src], audio source[src], video[src], video source[src], iframe[src], object[data]')]
                .map((el) => el.getAttribute('src') ?? el.getAttribute('data')),
        }));
        pages.push({ path, status, lang: info.lang, dir: info.dir, errors: [...new Set(errors)] });

        for (const href of [...info.links, ...info.media]) {
            const url = href && internal(href);
            if (!url || SKIP.some((re) => re.test(url.pathname))) continue;
            const key = url.pathname + url.search;
            // الملفات: بامتدادها، أو بمسار ملف بلا امتداد (ملف الوثيقة، وأعمال المسابقات المرفوعة)
            if (FILE.test(url.pathname) || url.pathname.includes('/file') || url.pathname.startsWith('/competition-works/')) {
                files.set(key, path);
            } else if (!seen.has(key) && !/[?&]page=\d{2,}/.test(key)) {
                seen.add(key);
                queue.push(key);
            }
        }
    }
    await page.close();

    const fileResults = [];
    for (const [file, from] of files) {
        const response = await context.request.get(new URL(file, BASE).href, { maxRedirects: 5, timeout: 60_000 }).catch(() => null);
        // المسار بلا توقيعه المؤقت، فلا يُحفظ رابط موقّع على القرص
        fileResults.push({ file: new URL(file, BASE).pathname, from, status: response?.status() ?? 'error', type: response?.headers()['content-type'] ?? '' });
    }

    return { label, pages, files: fileResults };
}

async function redirects(request) {
    const rows = readFileSync('docs/legacy-import/redirects.csv', 'utf8').trim().split(/\r?\n/).slice(1);
    const results = [];
    for (const row of rows) {
        const [source, target, kind] = row.split(',');
        const first = await request.get(new URL(source, BASE).href, { maxRedirects: 0 });
        const location = first.headers().location ? new URL(first.headers().location, BASE) : null;
        const final = await request.get(new URL(source, BASE).href, { maxRedirects: 5 });
        const expected = new URL(target, BASE);
        results.push({
            source, target, kind,
            status: first.status(),
            location: location ? decodeURIComponent(location.pathname + location.search) : null,
            matches: location !== null && location.pathname + location.search === expected.pathname + expected.search,
            final: final.status(),
        });
    }
    return results;
}

async function login(context) {
    const page = await context.newPage();
    await page.goto(new URL('/login', BASE).href);
    await page.fill('#field-login', `demo-member@${process.env.DEMO_DOMAIN ?? 'ejaz-demo.test'}`);
    await page.fill('#field-password', process.env.DEMO_PASSWORD ?? '');
    await page.locator('main form button[type=submit]').first().click();
    const ok = await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 60_000 }).then(() => true, () => false);
    await page.close();
    return ok;
}

async function horizontalScroll(context, paths) {
    const page = await context.newPage();
    const wide = [];
    for (const path of paths) {
        await page.goto(new URL(path, BASE).href, { waitUntil: 'load', timeout: 60_000 }).catch(() => null);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth).catch(() => 0);
        if (overflow > 1) wide.push({ path, overflow });
    }
    await page.close();
    return wide;
}

const browser = await chromium.launch({ channel: process.env.FULL_SITE_CHANNEL ?? 'chrome' });
const guest = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'ar-SA' });
const visitor = await crawl(guest, 'visitor', ['/']);
const legacy = await redirects(guest.request);

const memberContext = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'ar-SA' });
const loggedIn = process.env.DEMO_PASSWORD ? await login(memberContext) : false;
const member = loggedIn ? await crawl(memberContext, 'member', ['/account']) : { label: 'member', pages: [], files: [], skipped: true };

const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'ar-SA' });
const wideMobile = await horizontalScroll(mobile, visitor.pages.filter((p) => p.status === 200).map((p) => p.path));
await browser.close();

const bad = (r) => r.pages.filter((p) => p.status !== 200 || p.lang !== 'ar' || p.dir !== 'rtl' || (p.errors ?? []).length);
const summary = {
    base: BASE.origin,
    visitor: { pages: visitor.pages.length, files: visitor.files.length, problems: bad(visitor), badFiles: visitor.files.filter((f) => f.status !== 200) },
    member: { loggedIn, pages: member.pages.length, files: member.files.length, problems: bad(member), badFiles: member.files.filter((f) => f.status !== 200) },
    redirects: { total: legacy.length, permanent: legacy.filter((r) => r.status === 301).length, matching: legacy.filter((r) => r.matches).length, problems: legacy.filter((r) => r.status !== 301 || !r.matches || r.final !== 200) },
    mobileHorizontalScroll: wideMobile,
};
mkdirSync(OUT, { recursive: true });
writeFileSync(`${OUT}/crawl.json`, JSON.stringify({ summary, visitor, member, legacy }, null, 2));
console.log(JSON.stringify(summary, null, 2));
