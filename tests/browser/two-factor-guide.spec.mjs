// شرح التحقق الثنائي (طلب صفوان، نوفمبر 2026): «كيف أفعّله؟» و«إن تعطّل التطبيق أو ضاع الجوال» في صفحة «الأمان» للمستفيد
// وللمشرف (صفحة التفعيل التي تحوّل إليها لوحة الإدارة)، وسطر الوقت في شاشة رمز التحقق عند الدخول. الاتجاه من اليمين، ولا
// تمرير أفقي، واللقطات في docs/design/screenshots/two-factor/ بلا رمز مربع ولا مفتاح حقيقيين: الحسابان تجريبيان محليان
// (ejaz:demo-user)، والرمز والمفتاح مضبّبان قبل كل لقطة.
//
// التشغيل (tests/browser/README.md):
//   member=$(docker compose exec -T app php artisan ejaz:demo-user --json)
//   admin=$(docker compose exec -T app php artisan ejaz:demo-user --admin --json)
//   docker compose run --rm -e TWO_FACTOR_MEMBER="$member" -e ADMIN_SPA_CREDENTIALS="$admin" browser \
//     npx playwright test --config=tests/browser/playwright.config.mjs two-factor-guide.spec.mjs --project=desktop --project=mobile
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';

const MEMBER = process.env.TWO_FACTOR_MEMBER ? JSON.parse(process.env.TWO_FACTOR_MEMBER) : null;
const ADMIN = process.env.ADMIN_SPA_CREDENTIALS ? JSON.parse(process.env.ADMIN_SPA_CREDENTIALS) : null;
const DIR = 'docs/design/screenshots/two-factor';
// والرأس الثابت (sticky) يظهر وسط اللقطة الكاملة لصفحة الرمز المربع، فيُلتقط في مكانه أعلى الصفحة
const BLUR = '[data-two-factor-qr], [data-two-factor-key] { filter: blur(10px) !important; } [data-site-header] { position: relative !important; }';

function base32(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
    return Buffer.from(bytes);
}

// رمز من نافذة لم تُستعمل: الخادم يرفض الرمز نفسه مرتين، والمشروعان يدخلان بالحساب نفسه
async function freshCode(secret) {
    let window = Math.floor(Date.now() / 30_000);
    const start = window;
    while (window === start || Date.now() % 30_000 > 25_000) {
        await new Promise((resolve) => setTimeout(resolve, 1000));
        window = Math.floor(Date.now() / 30_000);
    }
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(window));
    const hmac = createHmac('sha1', base32(secret)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 15;
    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

async function login(page, user) {
    await page.goto('/login');
    await page.fill('#field-login', user.email);
    await page.fill('#field-password', user.password);
    await page.locator('main form button[type=submit]').first().click();
}

// صفحة «الأمان» خلف تأكيد كلمة المرور
async function openSecurity(page, user) {
    // بعد الدخول قد يقطع تحويل الصفحة السابقة أول انتقال (ERR_ABORTED)، فيعاد مرة
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.goto('/account/security').catch(() => page.goto('/account/security'));
    if (page.url().includes('confirm-password')) {
        await page.fill('#field-password', user.password);
        await Promise.all([page.waitForURL('**/account/security'), page.locator('main form button[type=submit]').first().click()]);
    }
}

async function expectPage(page) {
    await page.evaluate(() => document.fonts.ready);
    expect(await page.locator('html').getAttribute('dir')).toBe('rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
}

async function expectGuide(page) {
    const how = page.locator('[data-two-factor-how]');
    await expect(how).toBeVisible();
    await expect(how.locator('ol > li')).toHaveCount(5);
    await expect(page.locator('[data-two-factor-lost]')).toBeVisible();
    await expect(page.locator('[data-two-factor-lost] li')).toHaveCount(4);
    await expectPage(page);
}

async function shot(page, name) {
    await page.addStyleTag({ content: BLUR });
    await page.evaluate(() => {
        document.activeElement?.blur();
        window.scrollTo(0, 0);
    });
    await page.screenshot({ path: `${DIR}/${name}-${page.viewportSize().width}.png`, fullPage: true });
}

test('the account security page explains two-factor, before and while enabling it', async ({ page }) => {
    test.skip(!MEMBER, 'يحتاج مستخدماً تجريبياً: TWO_FACTOR_MEMBER من ejaz:demo-user --json');
    test.setTimeout(300_000);

    await login(page, MEMBER);
    await page.waitForURL((url) => !url.pathname.includes('login'));
    // خانة الرمز autofocus تمرّر الصفحة فيثبت الرأس وسط اللقطة الكاملة: تُنزع السمة في اللقطات وحدها
    await page.addInitScript(() => {
        new MutationObserver(() => document.querySelectorAll('[autofocus]').forEach((el) => el.removeAttribute('autofocus')))
            .observe(document, { childList: true, subtree: true });
    });
    await openSecurity(page, MEMBER);
    await expectGuide(page);
    await shot(page, 'account');

    // بعد «تفعيل التحقق الثنائي»: الرمز المربع والمفتاح، مضبّبين في اللقطة
    const enable = page.locator('form[action$="/user/two-factor-authentication"] button');
    if (await enable.count()) {
        await Promise.all([page.waitForURL('**/account/security'), enable.click()]);
    }
    // تحميل جديد بلا رسالة الجلسة: الصفحة بعد التحويل تبقى ممرَّرة فيثبت الرأس وسط اللقطة الكاملة
    await page.goto('/account/security');
    await expect(page.locator('[data-two-factor-qr]')).toBeVisible();
    await expectGuide(page);
    await shot(page, 'account-scan');
});

test('the admin activation page and the login code screen explain two-factor', async ({ page }) => {
    test.skip(!ADMIN, 'يحتاج مشرفاً تجريبياً: ADMIN_SPA_CREDENTIALS من ejaz:demo-user --admin --json');
    // انتظار نافذة رمز جديدة قد يبلغ نصف دقيقة، وأول تحميل للوحة بطيء على قرص ويندوز المشترك
    test.setTimeout(300_000);

    await login(page, ADMIN);
    await page.waitForURL('**/two-factor-challenge');
    await expect(page.locator('#field-code-hint')).toBeVisible();
    await expectPage(page);
    await shot(page, 'challenge');

    await page.fill('input[name="code"]', await freshCode(ADMIN.totp_secret));
    await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);

    await openSecurity(page, ADMIN);
    await expectGuide(page);
    await shot(page, 'admin');
});
