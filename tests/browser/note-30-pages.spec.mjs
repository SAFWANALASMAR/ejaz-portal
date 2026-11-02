// ملاحظة صفوان ٣٠ — طباعة النصوص في كل الصفحات (docs/decisions/2026-11-03-note-30-typography.md): لقطة كاملة لكل صفحة على
// 1440 و820 و390 في docs/design/screenshots/note-30/<SHOT_PREFIX>-<الصفحة>-<العرض>.png، وفحص الاتجاه وعدم التمرير الأفقي وطول سطر
// الفقرات (نحو 75 حرفاً على الأكثر). والمعرّفات (qa-news-1 وdemo-book…) من بيانات التطوير المحلية، وما لم يوجد يُتخطى.
// docker compose run --rm --no-deps -e VITE_HOST=none -e SHOT_PREFIX=after browser npx playwright test \
//   --config=tests/browser/playwright.config.mjs note-30-pages.spec.mjs
import fs from 'node:fs';
import { createHmac } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { AUTH_STATE } from './global-setup.mjs';

const prefix = process.env.SHOT_PREFIX ?? 'after';
const dir = 'docs/design/screenshots/note-30';

const guestPages = [
    ['about', '/about'],
    ['initiatives', '/initiatives'],
    ['initiative', '/initiatives/qiyam'],
    ['library', '/library'],
    ['library-section', '/library/books'],
    ['library-item', '/library/books/demo-book'],
    ['media', '/media'],
    ['news', '/media/news'],
    ['news-article', '/media/news/qa-news-1'],
    ['governance', '/governance'],
    ['governance-document', '/governance/qa-doc-0'],
    ['services', '/services'],
    ['programs', '/programs'],
    ['program', '/programs/qa-program-1'],
    ['competitions', '/competitions'],
    ['brahin', '/competitions/demo-students'],
    ['achievements', '/achievements'],
    ['contact', '/contact'],
    ['login', '/login'],
    ['register', '/register'],
    ['error-404', '/note-30-missing-page'],
];

async function check(page, name, info) {
    expect(await page.evaluate(() => document.documentElement.getAttribute('dir'))).toBe('rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `${name}: لا تمرير أفقي`).toBeLessThanOrEqual(0);

    if (prefix !== 'before') {
        // عرض الفقرة بوحدة ch (عرض الرقم 0 بخطها، كـ max-width: 68ch)، لكل فقرة نصها أطول من 60 حرفاً. والحد 75ch (ملاحظة ٣٠)
        const [widest, where] = await page.evaluate(() => {
            const context = document.createElement('canvas').getContext('2d');
            let best = [0, ''];
            for (const p of document.querySelectorAll('main p, main .measure')) {
                const text = p.textContent.replace(/\s+/g, ' ').trim();
                if (text.length < 60 || p.getClientRects().length === 0 || p.closest('[aria-hidden="true"], [data-marquee-copy]')) {
                    continue;
                }
                const style = getComputedStyle(p);
                context.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
                const perChar = context.measureText('0').width;
                const capacity = Math.round(p.getBoundingClientRect().width / perChar);
                if (capacity > best[0]) {
                    best = [capacity, `${p.className || p.parentElement.className}`.slice(0, 80)];
                }
            }

            return best;
        });
        console.log(`MEASURE ${name} ${info.project.name} ${widest} ${where}`);
        expect.soft(widest, `${name}: سعة السطر في الفقرات (${where})`).toBeLessThanOrEqual(75);
    }

    await page.screenshot({ path: `${dir}/${prefix}-${name}-${info.project.name}.png`, fullPage: true });
}

for (const [name, path] of guestPages) {
    test(`note 30 pages: ${name}`, async ({ page }, info) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        const response = await page.goto(path);
        test.skip(name !== 'error-404' && response.status() === 404, `${path} غير موجود في البيانات المحلية`);
        await page.waitForTimeout(400);
        await check(page, name, info);
    });
}

test.describe('account', () => {
    test.skip(! fs.existsSync(AUTH_STATE), 'بلا مستخدم تجريبي');
    test.use({ storageState: fs.existsSync(AUTH_STATE) ? AUTH_STATE : undefined });

    test('note 30 pages: account dashboard', async ({ page }, info) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto('/account');

        // بطاقات لوحة المستفيد: عمود واحد على الجوال، وعمودان ثابتان على اللوحي والحاسوب (قرار صفوان على #142)
        const columns = await page.locator('[data-account-cards]').evaluate((list) => new Set([...list.children]
            .map((item) => Math.round(item.getBoundingClientRect().left))).size);
        expect(columns, 'أعمدة بطاقات المستفيد').toBe(info.project.name === 'mobile' ? 1 : 2);

        await check(page, 'account', info);
    });
});

// بوابة مجلس الإدارة في لوحة الإدارة: عضو مجلس تجريبي محلي بتحقق ثنائي (BOARD_CREDENTIALS بصيغة ejaz:demo-user --admin --json)
const BOARD = process.env.BOARD_CREDENTIALS ? JSON.parse(process.env.BOARD_CREDENTIALS) : null;

function base32(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));

    return Buffer.from(bytes);
}

async function totp(secret) {
    while (Date.now() % 30_000 > 25_000) {
        await new Promise((resolve) => setTimeout(resolve, 1000));
    }
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30_000)));
    const hmac = createHmac('sha1', base32(secret)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 15;

    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

test('note 30 pages: board portal', async ({ page }, info) => {
    test.skip(! BOARD || info.project.name === 'tablet', 'بلا عضو مجلس تجريبي، أو اللوحي');
    await page.goto('/login');
    await page.fill('#field-login', BOARD.email);
    await page.fill('#field-password', BOARD.password);
    await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
    await page.fill('input[name="code"]', await totp(BOARD.totp_secret));
    await Promise.all([page.waitForURL((url) => ! url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'لا تمرير أفقي').toBeLessThanOrEqual(0);
    await page.screenshot({ path: `${dir}/${prefix}-board-portal-${info.project.name}.png`, fullPage: true });
});

// الحد 68ch على أبناء النص المنسق لا على حاويته (مراجعة المشرف على #142): جدول عريض في المقطع يبقى بعرض الحاوية، والفقرة بجانبه
// مقيدة. والجدول يُضاف في الصفحة نفسها، لأن تنقية النص (RichText) لا تحفظ الجداول اليوم، والقاعدة تحمي ما يأتي بعدها.
test('note 30 pages: a wide table in a text section is not capped at 68ch', async ({ page }) => {
    const response = await page.goto('/media/news/qa-news-1');
    test.skip(response.status() === 404, 'لا خبر محلي');
    const flow = page.locator('.measure-flow').first();
    await expect(flow).toBeAttached();

    const widths = await flow.evaluate((element) => {
        const paragraph = document.createElement('p');
        paragraph.textContent = 'نص '.repeat(200);
        const table = document.createElement('table');
        table.style.width = '100%';
        table.innerHTML = '<tr><td>خانة</td><td>خانة</td></tr>';
        element.append(paragraph, table);
        const zero = document.createElement('span');
        zero.textContent = '0';
        paragraph.append(zero);
        const ch = zero.getBoundingClientRect().width;

        return { container: element.getBoundingClientRect().width, table: table.getBoundingClientRect().width, paragraph: paragraph.getBoundingClientRect().width, ch };
    });

    expect(Math.round(widths.table), 'الجدول بعرض الحاوية').toBe(Math.round(widths.container));
    expect(widths.paragraph, 'الفقرة لا تتجاوز 68ch').toBeLessThanOrEqual(widths.ch * 68 + 1);
});
