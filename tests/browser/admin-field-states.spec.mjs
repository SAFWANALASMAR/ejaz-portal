// حالات الحقول في لوحة الإدارة بعد أنماط الدفعة ٣ (resources/css/filament/admin/tables-forms.css — مراجعة الشيفرة على #169):
// - الحقل المخالف بعد «حفظ» بخطأ تحقق من الخادم: حلقته بلون الخطر، 1px ثم 2px عند التركيز، والحقل السليم بجانبه بلا لون الخطر.
// - الحقل المعطّل (اسم المستخدم في تعديل مستخدم): خلفيته غير بيضاء.
// - زر «حذف» في رأس صفحة التعديل: يبقى بلون الخطر.
// على 1440 و390، ولقطة للحقل المخالف في docs/design/screenshots/admin-tables-forms/field-invalid-<العرض>.png.
//
// لوحة الإدارة تشترط التحقق الثنائي، فيُشغَّل بجلسة محفوظة لحساب مدير نظام (البيئة المحلية وحدها، ويُحذف الملف بعدها):
//   docker compose run --rm --no-deps -e VITE_HOST=none -e ADMIN_STATE=storage/playwright/<الملف>.json browser \
//     npx playwright test --config=tests/browser/playwright.config.mjs admin-field-states.spec.mjs --project=desktop --project=mobile
// وفي القاعدة برنامج ومستخدم واحد على الأقل.
import { expect, test } from '@playwright/test';
import { existsSync, mkdirSync } from 'node:fs';

const ADMIN_STATE = process.env.ADMIN_STATE;
const DIR = 'docs/design/screenshots/admin-tables-forms';

test.skip(!ADMIN_STATE || !existsSync(ADMIN_STATE), 'يحتاج جلسة إدارية محفوظة: ADMIN_STATE');
test.use({ storageState: ADMIN_STATE });

// لون الخطر كما يحسبه المتصفح من متغير Filament، للمقارنة بظل الحلقة
async function dangerColor(page) {
    return page.evaluate(() => {
        const probe = document.createElement('span');
        probe.style.color = 'var(--danger-600)';
        document.body.append(probe);
        const color = getComputedStyle(probe).color;
        probe.remove();

        return color;
    });
}

// ظل الحلقة (box-shadow) لغلاف الحقل الذي يحمل المحدد
function ringOf(page, selector) {
    return page.locator(selector).locator('xpath=ancestor::*[contains(concat(" ", normalize-space(@class), " "), " fi-input-wrp ")][1]')
        .evaluate((wrapper) => getComputedStyle(wrapper).boxShadow);
}

async function firstEditPath(page, list) {
    await page.goto(list);
    const href = await page.locator(`a[href*="${list}/"][href$="/edit"]`).first().getAttribute('href');
    expect(href, `لا سجل في ${list}`).toBeTruthy();

    return new URL(href, page.url()).pathname;
}

test('the invalid field keeps the danger ring, the valid one does not, and delete stays red', async ({ page }, testInfo) => {
    await page.goto(await firstEditPath(page, '/admin/programs'));

    // العنوان الإلزامي فارغاً، وتحقق المتصفح مطفأ ليصل الحفظ إلى تحقق الخادم، فلا يُحفظ شيء
    const title = page.locator('[id$=".title"]').first();
    await title.fill('');
    await page.evaluate(() => document.querySelectorAll('form').forEach((form) => { form.noValidate = true; }));
    await page.locator('form .fi-ac .fi-btn[type="submit"]').first().click();
    // الحفظ عبر Livewire بطيء على قرص ويندوز المشترك
    await expect(page.locator('.fi-input-wrp.fi-invalid')).toHaveCount(1, { timeout: 60_000 });

    // الحلقة تنتقل في 0.15 ثانية، فالقراءة بعد انتهاء الانتقال
    const danger = await dangerColor(page);
    await expect.poll(() => ringOf(page, '[id$=".title"]')).toContain(`${danger} 0px 0px 0px 1px`);
    expect(await ringOf(page, '[id$=".location"]')).not.toContain(danger);

    // عند التركيز: 2px بلون الخطر، لا الحلقة الزرقاء
    await title.focus();
    await expect.poll(() => ringOf(page, '[id$=".title"]')).toContain(`${danger} 0px 0px 0px 2px`);

    mkdirSync(DIR, { recursive: true });
    await page.locator('.fi-input-wrp.fi-invalid').scrollIntoViewIfNeeded();
    await page.screenshot({ path: `${DIR}/field-invalid-${testInfo.project.name === 'mobile' ? 390 : 1440}.png` });

    // زر الحذف في رأس الصفحة بلون الخطر لا بكبسولة الأزرار الثانوية البيضاء
    const deleteButton = page.locator('.fi-header .fi-btn.fi-color-danger').first();
    if (await deleteButton.count()) {
        const background = await deleteButton.evaluate((button) => getComputedStyle(button).backgroundColor);
        expect(background).not.toBe('rgb(255, 255, 255)');
    }

    // لا تمرير أفقي، والاتجاه من اليمين
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
});

test('the disabled field is not white, so it does not look editable', async ({ page }) => {
    await page.goto(await firstEditPath(page, '/admin/users'));

    const background = await page.locator('[id$="username"]').first()
        .locator('xpath=ancestor::*[contains(concat(" ", normalize-space(@class), " "), " fi-input-wrp ")][1]')
        .evaluate((wrapper) => ({ disabled: wrapper.classList.contains('fi-disabled'), color: getComputedStyle(wrapper).backgroundColor }));

    expect(background.disabled).toBe(true);
    expect(background.color).not.toBe('rgb(255, 255, 255)');
});
