// بطاقة التسجيل كاملة تحت الرأس بلا تمرير على 1366×768 وما فوقها (المدير عبر المشرف، 5 نوفمبر 2026): كانت تنتهي عند 947 بكسل،
// فيمرّر الزائر ليصل «إنشاء الحساب». بلا حذف حقل: الشروط مضغوطة، والموافقة والزر في صف، ومسافات أقل على الحاسوب. والجوال كما هو
// بلا تمرير أفقي. واللقطات في docs/design/screenshots/register-fit/register-<العرض>.png.
//   docker compose run --rm --no-deps vite npm run build
//   docker compose run --rm --no-deps -e VITE_HOST=none browser npx playwright test --config=tests/browser/playwright.config.mjs register-fit.spec.mjs
import { expect, test } from '@playwright/test';

const DIR = 'docs/design/screenshots/register-fit';

async function bottoms(page) {
    return page.evaluate(() => {
        const bottom = (selector) => Math.round(document.querySelector(selector).getBoundingClientRect().bottom);

        return {
            submit: bottom('form[action$="/register"] button[type="submit"]'),
            card: bottom('[data-auth-card]'),
            scrolled: window.scrollY,
        };
    });
}

for (const [width, height] of [[1366, 768], [1440, 900], [1920, 1080]]) {
    test(`the whole register card fits under the header at ${width}×${height}, with every field`, async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', 'الحاسوب');
        await page.setViewportSize({ width, height });
        await page.goto('/register');

        // الحقول كلها باقية، والشروط بنصوصها الست
        for (const name of ['name', 'phone', 'email', 'password', 'password_confirmation', 'privacy']) {
            await expect(page.locator(`form [name="${name}"]`)).toHaveCount(1);
        }
        await expect(page.locator('[data-password-rules] [data-rule]')).toHaveCount(6);

        const measured = await bottoms(page);
        expect(measured.scrolled).toBe(0);
        expect(measured.submit, `زر «إنشاء الحساب» في الشاشة (${JSON.stringify(measured)})`).toBeLessThanOrEqual(height);
        expect(measured.card, `البطاقة كاملة في الشاشة (${JSON.stringify(measured)})`).toBeLessThanOrEqual(height);

        // والموافقة والزر في صف واحد
        const row = await page.evaluate(() => {
            const box = (element) => element.getBoundingClientRect();
            const privacy = box(document.querySelector('input[name="privacy"]'));
            const submit = box(document.querySelector('form[action$="/register"] button[type="submit"]'));

            return { privacy: Math.round(privacy.top + privacy.height / 2), submitTop: Math.round(submit.top), submitBottom: Math.round(submit.bottom) };
        });
        expect(row.privacy).toBeGreaterThanOrEqual(row.submitTop);
        expect(row.privacy).toBeLessThanOrEqual(row.submitBottom);

        if (width !== 1920) {
            await page.screenshot({ path: `${DIR}/register-${width}.png` });
        }

        // والتركيز في كلمة المرور يُظهر تنبيه التسريب، والزر باقٍ في الشاشة
        await page.locator('form [name="password"]').focus();
        await expect(page.locator('[data-password-rules] [data-leaked]')).toBeVisible();
        expect((await bottoms(page)).submit).toBeLessThanOrEqual(height);
    });
}

test('the register page on the phone keeps its single column with no horizontal scroll', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'الجوال');
    await page.goto('/register');

    expect(await page.evaluate(() => document.documentElement.getAttribute('dir'))).toBe('rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
    // على الجوال الشروط كاملة، وتنبيه التسريب ظاهر
    await expect(page.locator('[data-password-rules] [data-leaked]')).toBeVisible();
    await page.screenshot({ path: `${DIR}/register-390.png`, fullPage: true });
});
