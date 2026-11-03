// «محرر الصفحات» (docs/decisions/2026-11-02-page-editor.md): زر «تعديل هذه الصفحة» للمسؤول التقني وحده في الموقع، ويفتح شاشة
// الصفحة في اللوحة، ثم حفظ مسودة ومعاينتها برابطها الموقّع. والزائر بلا زر. على 1440 و390، ولقطة لكل خطوة في
// docs/design/screenshots/page-editor/<الخطوة>-<العرض>.png.
//
// يحتاج مسؤولاً تقنياً تجريبياً بتحقق ثنائي (PAGE_EDITOR_ADMIN_CREDENTIALS من ejaz:demo-user --admin --json). والمسودة تبقى
// في القاعدة المحلية بلا نشر، فلا يراها زائر:
//   docker compose run --rm -e PAGE_EDITOR_ADMIN_CREDENTIALS="$creds" browser npx playwright test \
//     --config=tests/browser/playwright.config.mjs page-editor.spec.mjs --project=desktop --project=mobile
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const ADMIN = process.env.PAGE_EDITOR_ADMIN_CREDENTIALS ? JSON.parse(process.env.PAGE_EDITOR_ADMIN_CREDENTIALS) : null;
const SHOTS = 'docs/design/screenshots/page-editor';
// رمز TOTP لا يُقبل مرتين: آخر نافذة في ملف مشترك مع اختبارات اللوحة الأخرى
const WINDOW_FILE = 'storage/playwright/admin-spa-last-totp-window';

mkdirSync(SHOTS, { recursive: true });

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
    const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;
    return String(code).padStart(6, '0');
}

async function expectRtlNoScroll(page) {
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'لا تمرير أفقي').toBeLessThanOrEqual(0);
}

const width = (page) => page.viewportSize().width;

// الخطوط، ثم ظهور العنوان كلمة كلمة قبل اللقطة (الحلقات المستمرة لا تنتهي فتُستثنى)
async function settle(page) {
    await page.evaluate(() => document.fonts.ready);
    await page.waitForFunction(() =>
        document.getAnimations().every((animation) => animation.playState !== 'running' || animation.effect?.getTiming().iterations === Infinity),
    );
}

test('visitors see no edit button', async ({ page }) => {
    await page.goto('/programs');
    await expectRtlNoScroll(page);
    await expect(page.locator('[data-page-hero]')).toBeVisible();
    await expect(page.locator('[data-page-edit-button]')).toHaveCount(0);
});

test('the technical officer edits a page from its button and previews the draft', async ({ page, context }) => {
    test.skip(!ADMIN, 'يحتاج مسؤولاً تقنياً تجريبياً: PAGE_EDITOR_ADMIN_CREDENTIALS من ejaz:demo-user --admin --json');

    await page.goto('/login');
    await page.fill('#field-login', ADMIN.email);
    await page.fill('#field-password', ADMIN.password);
    await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
    await page.fill('input[name="code"]', await freshCode());
    await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);

    // الزر في الموقع، في جهة البداية فلا يغطي زر المساعد
    await page.goto('/programs');
    const button = page.locator('[data-page-edit-button]');
    await expect(button).toBeVisible();
    await expect(button).toHaveText(/تعديل هذه الصفحة/);
    await expectRtlNoScroll(page);
    await settle(page);
    await page.screenshot({ path: `${SHOTS}/button-${width(page)}.png` });

    // يفتح شاشة الصفحة في اللوحة
    await Promise.all([page.waitForURL(/\/admin\/site-pages\/\d+\/edit/), button.click()]);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    const title = page.getByRole('textbox', { name: /^العنوان/ }).first();
    await expect(title).toBeVisible();
    await page.screenshot({ path: `${SHOTS}/editor-${width(page)}.png`, fullPage: true });

    // مسودة بعنوان تجريبي، ثم المعاينة برابطها الموقّع في تبويب جديد
    await title.fill('الدورات والبرامج — معاينة تجريبية');
    await page.getByRole('button', { name: 'حفظ المسودة' }).click();
    await expect(page.getByText('حُفظت المسودة')).toBeVisible({ timeout: 15_000 });

    const previewLink = page.getByRole('link', { name: 'معاينة' }).first();
    await expect(previewLink).toBeVisible({ timeout: 15_000 });
    const href = await previewLink.getAttribute('href');
    expect(href).toContain('/page-preview/');
    expect(href).toContain('signature=');

    const preview = await context.newPage();
    const response = await preview.goto(href);
    expect(response.headers()['x-robots-tag']).toBe('noindex, nofollow');
    await expect(preview.locator('[data-page-preview-banner]')).toBeVisible();
    await expect(preview.locator('[data-page-hero] h1')).toHaveText('الدورات والبرامج — معاينة تجريبية');
    await expectRtlNoScroll(preview);
    await settle(preview);
    await preview.screenshot({ path: `${SHOTS}/preview-${width(preview)}.png`, fullPage: true });

    // والصفحة العامة لم تتغير قبل النشر
    await page.goto('/programs');
    await expect(page.locator('[data-page-hero] h1')).not.toHaveText('الدورات والبرامج — معاينة تجريبية');
});
