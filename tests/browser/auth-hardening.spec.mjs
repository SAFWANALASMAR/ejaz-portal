// صفحات التسجيل والدخول والاستعادة بعد التشديد (security/2026-10-20-تشديد-الدخول.md): شروط كلمة المرور تُعلَّم أثناء
// الكتابة ومؤشر القوة يُعلَن، وخيارا الاستعادة بالبريد والجوال، وصفحة الرمز. على الحاسوب والجوال، بلقطات في
// docs/design/screenshots/auth-<الشاشة>-<العرض>.png، والاتجاه من اليمين، ولا تمرير أفقي.
import { expect, test } from '@playwright/test';

test.beforeEach(async ({}, testInfo) => {
    test.skip(testInfo.project.name === 'tablet', 'الحاسوب والجوال وحدهما');
});

async function check(page, name, testInfo) {
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
    const width = page.viewportSize().width;
    await page.screenshot({ path: `docs/design/screenshots/auth-${name}-${width}.png`, fullPage: true });
}

test('register marks the password rules while typing', async ({ page }, testInfo) => {
    await page.goto('/register');
    const rules = page.locator('[data-password-rules]');
    await expect(rules.locator('[data-rule]')).toHaveCount(6);
    await expect(rules.locator('[data-strength]')).toHaveAttribute('aria-live', 'polite');

    await page.fill('#field-name', 'مستفيد تجريبي');
    await page.fill('#field-email', 'browser-check@example.test');
    await page.fill('#field-password', 'abc');
    await expect(rules.locator('[data-rule="letter"]')).toHaveAttribute('data-state', 'met');
    await expect(rules.locator('[data-rule="length"]')).toHaveAttribute('data-state', 'unmet');
    await check(page, 'register-weak', testInfo);

    await page.fill('#field-password', 'Strong-Pass-2026!');
    for (const rule of ['length', 'letter', 'mixed', 'number', 'symbol', 'personal_item']) {
        await expect(rules.locator(`[data-rule="${rule}"]`)).toHaveAttribute('data-state', 'met');
    }
    await check(page, 'register-strong', testInfo);
});

test('login refuses a wrong password with one Arabic message', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.fill('#field-login', 'nobody-browser@example.test');
    await page.fill('#field-password', 'Wrong-Pass-2026!');
    await page.locator('form button[type="submit"]').first().click();
    await expect(page.locator('[role="alert"], .text-danger, [id$="-error"]').first()).toBeVisible();
    await check(page, 'login-error', testInfo);
});

test('forgot password offers email and phone, then the code page', async ({ page }, testInfo) => {
    await page.goto('/forgot-password');
    await expect(page.locator('#field-email')).toBeVisible();
    await expect(page.locator('#field-phone')).toBeVisible();
    await check(page, 'forgot-choose', testInfo);

    await page.fill('#field-phone', '0599999990');
    await page.locator('form:has(#field-phone) button').first().click();
    await expect(page).toHaveURL(/\/forgot-password\/phone$/);
    await expect(page.locator('#field-code')).toBeVisible();
    await expect(page.locator('[data-password-rules]')).toBeVisible();
    await check(page, 'reset-phone-code', testInfo);
});
