// جولة العرض الصامتة بعناوين عربية (demo-tour.config.mjs): الموقع العام، ثم رحلة المستفيد، ثم البوابات
// الخمس، ثم لوحة الإدارة. تقرأ ولا تكتب: لا تُرسل نموذجاً غير الدخول. وشريط العنوان يُحقن في الصفحة أثناء
// التسجيل وحده، ولا يدخل الموقع. وكلمة المرور تظهر نقاطاً، ورمز التحقق الثنائي مخفي قبل كتابته.
import { test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const DOMAIN = process.env.DEMO_DOMAIN ?? 'ejaz-demo.test';
const PASSWORD = process.env.DEMO_PASSWORD ?? '';
const TOTP_SECRET = process.env.DEMO_TOTP_SECRET ?? '';
const PAUSE = Number(process.env.TOUR_PAUSE_MS ?? 4000);

const TARGET = new URL(process.env.FULL_SITE_URL ?? 'http://localhost:8090');
if (!/^(localhost|127\.0\.0\.1|.+\.test)$/.test(TARGET.hostname)) {
    throw new Error(`جولة العرض على نسخة محلية وحدها، لا على ${TARGET.host}`);
}

// ── رمز التحقق الثنائي، بملف النافذة نفسه في التجربة الشاملة، فلا يُستعمل رمز مرتين بين التشغيلين
const WINDOW_FILE = 'storage/playwright/full-site/last-totp-window';
let lastWindow = existsSync(WINDOW_FILE) ? Number(readFileSync(WINDOW_FILE, 'utf8')) : -1;

function base32(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
    return Buffer.from(bytes);
}

function windowReady() {
    const window = Math.floor(Date.now() / 30_000);
    return window > lastWindow && Date.now() % 30_000 <= 25_000;
}

async function freshCode() {
    while (!windowReady()) await new Promise((resolve) => setTimeout(resolve, 500));
    const window = Math.floor(Date.now() / 30_000);
    lastWindow = window;
    mkdirSync('storage/playwright/full-site', { recursive: true });
    writeFileSync(WINDOW_FILE, String(window));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(window));
    const hmac = createHmac('sha1', base32(TOTP_SECRET)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 15;
    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

// ── العرض: شريط عنوان صغير أعلى الصفحة تحت رأسها (فلا يغطي القائمة)، وتمرير هادئ، ووقفة
async function caption(page, text) {
    await page.evaluate((label) => {
        document.getElementById('demo-tour-caption')?.remove();
        const bar = document.createElement('div');
        bar.id = 'demo-tour-caption';
        bar.textContent = label;
        bar.setAttribute('dir', 'rtl');
        Object.assign(bar.style, {
            position: 'fixed', top: '96px', insetInlineStart: '50%', transform: 'translateX(50%)', zIndex: '2147483647',
            background: 'rgba(46, 104, 152, 0.94)', color: '#fff', padding: '8px 22px', borderRadius: '999px',
            font: '600 18px Zain, system-ui, sans-serif', boxShadow: '0 4px 14px rgba(0,0,0,.18)', pointerEvents: 'none',
        });
        document.body.appendChild(bar);
    }, text);
}

async function scrollSlowly(page) {
    const height = await page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight);
    for (let y = 0; y < height; y += 220) {
        await page.evaluate((top) => window.scrollTo({ top, behavior: 'smooth' }), y);
        await page.waitForTimeout(450);
    }
    await page.waitForTimeout(800);
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
    await page.waitForTimeout(700);
}

async function show(page, path, title, { scroll = true } = {}) {
    await page.goto(path);
    await page.waitForLoadState('load');
    await caption(page, title);
    await page.waitForTimeout(PAUSE);
    if (scroll) await scrollSlowly(page);
}

// الدخول: البريد ظاهر (حساب تجريبي)، وكلمة المرور نقاط، ورمز التحقق مخفي. وانتظار نافذة رمز جديدة يحدث
// على الصفحة السابقة قبل الانتقال، فلا يظهر في الفيديو انتظار على صفحة الدخول
async function login(page, who, title) {
    if (TOTP_SECRET && who !== 'member') {
        while (!windowReady()) await page.waitForTimeout(500);
    }
    await page.context().clearCookies();
    await page.goto('/login');
    await caption(page, title);
    await page.waitForTimeout(1200);
    await page.locator('#field-login').pressSequentially(`demo-${who}@${DOMAIN}`, { delay: 35 });
    await page.fill('#field-password', PASSWORD);
    await page.waitForTimeout(600);
    await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 60_000 }), page.locator('main form button[type=submit]').first().click()]);

    if (page.url().includes('two-factor-challenge')) {
        await caption(page, title);
        const code = page.locator('input[name="code"]');
        await code.evaluate((input) => { input.style.webkitTextSecurity = 'disc'; input.style.color = 'transparent'; input.style.textShadow = '0 0 0 #6b7280'; });
        await code.fill(await freshCode());
        await page.waitForTimeout(500);
        await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge'), { timeout: 60_000 }), page.locator('main form button[type=submit]').first().click()]);
    }
    await page.waitForLoadState('load');
}

async function publicSite(page, short) {
    await show(page, '/', 'الموقع العام — الرئيسية');
    await show(page, '/about', 'عن الجمعية');
    await show(page, '/initiatives', 'المبادرات', { scroll: !short });
    await show(page, '/media/news', 'المركز الإعلامي — الأخبار', { scroll: !short });
    await show(page, '/library/audio', 'المكتبة — الصوتيات', { scroll: !short });
    await show(page, '/library/videos', 'المكتبة — المرئيات', { scroll: !short });
    await show(page, '/governance', 'الحوكمة — الوثائق', { scroll: !short });
    await show(page, '/contact', 'التواصل والاستفسارات', { scroll: !short });

    // المتجر رابط خارجي إلى منصة سلة: يُبرز في القائمة ولا يُفتح، فلا يخرج الفيديو من البوابة
    await page.goto('/');
    await caption(page, 'المتجر — رابط إلى متجر الجمعية على سلة');
    const store = page.locator('header a[href*="ejaz.org.sa"], header a[href$="/store"]').first();
    if (await store.count()) {
        await store.scrollIntoViewIfNeeded();
        await store.evaluate((a) => { a.style.outline = '3px solid #5D2A92'; a.style.outlineOffset = '4px'; a.style.borderRadius = '8px'; });
        await store.hover();
    }
    await page.waitForTimeout(PAUSE);
}

async function beneficiary(page, short) {
    await show(page, '/register', 'رحلة المستفيد — إنشاء حساب', { scroll: false });
    await login(page, 'member', 'رحلة المستفيد — الدخول');
    await caption(page, 'رحلة المستفيد — لوحتي');
    await page.waitForTimeout(PAUSE);
    await scrollSlowly(page);
    await show(page, '/services', 'الخدمات الإلكترونية', { scroll: !short });
    await show(page, '/competitions', 'المسابقات', { scroll: false });
    await show(page, '/competitions/demo-exam', 'المسابقة — الاختبار الإلكتروني', { scroll: !short });
    if (!short) await show(page, '/competitions/demo-works', 'المسابقة — رفع الأعمال');
}

async function portals(page, short) {
    const all = [
        ['board', 'بوابة مجلس الإدارة'],
        ['director', 'بوابة المدير التنفيذي'],
        ['secretary', 'بوابة الموظفين — السكرتير'],
        ['member', 'بوابة المشتركين'],
        ['reviewer', 'بوابة المحكّمين'],
    ];
    for (const [who, title] of short ? all.filter(([who]) => ['board', 'director', 'member'].includes(who)) : all) {
        await login(page, who, title);
        await caption(page, title);
        await page.waitForTimeout(PAUSE);
        await scrollSlowly(page);
    }
}

async function adminPanel(page, short) {
    await login(page, 'admin', 'لوحة الإدارة — المشرف العام');
    const screens = [
        ['/admin', 'لوحة الإدارة — الرئيسية'],
        ['/admin/news-articles', 'لوحة الإدارة — الأخبار'],
        ['/admin/library-items', 'لوحة الإدارة — المكتبة'],
        ['/admin/competitions', 'لوحة الإدارة — المسابقات'],
        ['/admin/service-requests', 'لوحة الإدارة — الطلبات الإلكترونية'],
        ['/admin/meetings', 'لوحة الإدارة — الاجتماعات'],
        ['/admin/register-entries', 'لوحة الإدارة — السجلات'],
        ['/admin/users', 'لوحة الإدارة — المستخدمون'],
    ];
    for (const [path, title] of short ? screens.filter(([path]) => ['/admin', '/admin/news-articles', '/admin/competitions', '/admin/register-entries', '/admin/users'].includes(path)) : screens) {
        await show(page, path, title, { scroll: !short });
    }
}

test('العرض الكامل', async ({ page }) => {
    test.skip(process.env.TOUR === 'short', 'المختصر وحده');
    await publicSite(page, false);
    await beneficiary(page, false);
    await portals(page, false);
    await adminPanel(page, false);
});

test('العرض المختصر', async ({ page }) => {
    test.skip(process.env.TOUR === 'full', 'الكامل وحده');
    await publicSite(page, true);
    await beneficiary(page, true);
    await portals(page, true);
    await adminPanel(page, true);
});
