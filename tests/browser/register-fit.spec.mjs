// بطاقة التسجيل تحت الرأس بلا تمرير (المدير عبر المشرف، 5 نوفمبر 2026): كانت تنتهي عند 947 بكسل، فيمرّر الزائر ليصل «إنشاء
// الحساب». بلا حذف حقل: الشروط مضغوطة، والموافقة والتحدي والزر في صف، ومسافات أقل على الحاسوب. والجوال كما هو بلا تمرير أفقي.
// ومراجعة المشرف على طلب الدمج 189: التحدي (Turnstile) ظاهر في الإنتاج دائماً، فيُدرج بديل بمقاسه (300×65 وسطر تنبيهه) قبل
// القياس. والهدف: زر الإنشاء ظاهر كاملاً في نافذة متصفح لابتوب 1366 (1366×657)، والبطاقة كاملة في 1366×768.
// واللقطات في docs/design/screenshots/register-fit/.
//   docker compose run --rm --no-deps vite npm run build
//   docker compose run --rm --no-deps -e VITE_HOST=none browser npx playwright test --config=tests/browser/playwright.config.mjs register-fit.spec.mjs
import { expect, test } from '@playwright/test';

const DIR = 'docs/design/screenshots/register-fit';

// بديل Turnstile بمقاس أداته وسطر تنبيهه، في موضعه من الصف كما يرسمه bot-challenge متى ضُبط المفتاح
async function withChallengeStandIn(page) {
    await page.evaluate(() => {
        const slot = document.querySelector('[data-bot-challenge-slot]');

        if (slot && !slot.querySelector('.cf-turnstile')) {
            // نص التنبيه من الصفحة (data-notice من lang/ar/account.php): طوله يحدد عرض الخانة وما يبقى للموافقة
            const notice = document.createElement('p');
            notice.className = 'text-xs text-muted';
            notice.textContent = slot.dataset.notice;
            slot.insertAdjacentHTML('afterbegin', '<div class="cf-turnstile" data-stand-in style="width:300px;height:65px;background:#d1d5db"></div>');
            slot.append(notice);
        }
    });
}

async function measure(page) {
    return page.evaluate(() => {
        const box = (selector) => document.querySelector(selector).getBoundingClientRect();
        const submit = box('form[action$="/register"] button[type="submit"]');
        const privacy = box('input[name="privacy"]');
        const challenge = box('.cf-turnstile');

        return {
            submit: Math.round(submit.bottom),
            card: Math.round(box('[data-auth-card]').bottom),
            scrolled: window.scrollY,
            // في صف واحد: منتصف الموافقة والتحدي داخل ارتفاع الصف نفسه
            oneRow: [privacy, challenge].every((item) => item.top + item.height / 2 >= submit.top - 30 && item.top + item.height / 2 <= submit.bottom + 30),
        };
    });
}

async function openRegister(page, width, height) {
    await page.setViewportSize({ width, height });
    await page.goto('/register');
    await withChallengeStandIn(page);
}

test('the create button is fully visible without scrolling in a 1366 laptop browser window (1366×657), with the challenge', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'الحاسوب');
    await openRegister(page, 1366, 657);

    const measured = await measure(page);
    expect(measured.scrolled).toBe(0);
    expect(measured.submit, `زر «إنشاء الحساب» في النافذة (${JSON.stringify(measured)})`).toBeLessThanOrEqual(657);
    expect(measured.oneRow, 'الموافقة والتحدي والزر في صف واحد').toBe(true);
    await page.screenshot({ path: `${DIR}/register-1366x657.png` });
});

// بلا مفتاح الموقع تخلو خانة التحدي فتُخفى، فلا تأخذ مسافة الصف بين الموافقة والزر (المشرف على طلب الدمج 189، منخفضة)
test('the empty challenge slot takes no room in the actions row without a site key', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'الحاسوب');
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto('/register');

    await expect(page.locator('[data-bot-challenge-slot]')).toBeHidden();
    await withChallengeStandIn(page);
    await expect(page.locator('[data-bot-challenge-slot]')).toBeVisible();
    await expect(page.locator('[data-bot-challenge-slot] p')).toHaveText('يتحقق مزود خارجي (Cloudflare) من أن مرسل النموذج إنسان.');
});

for (const [width, height] of [[1366, 768], [1440, 900], [1920, 1080]]) {
    test(`the whole register card fits under the header at ${width}×${height}, with the challenge and every field`, async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', 'الحاسوب');
        await openRegister(page, width, height);

        // الحقول كلها باقية، والشروط بنصوصها الست
        for (const name of ['name', 'phone', 'email', 'password', 'password_confirmation', 'privacy']) {
            await expect(page.locator(`form [name="${name}"]`)).toHaveCount(1);
        }
        await expect(page.locator('[data-password-rules] [data-rule]')).toHaveCount(6);

        const measured = await measure(page);
        expect(measured.scrolled).toBe(0);
        expect(measured.submit, `زر «إنشاء الحساب» في الشاشة (${JSON.stringify(measured)})`).toBeLessThanOrEqual(height);
        expect(measured.card, `البطاقة كاملة في الشاشة (${JSON.stringify(measured)})`).toBeLessThanOrEqual(height);
        expect(measured.oneRow, 'الموافقة والتحدي والزر في صف واحد').toBe(true);

        if (width !== 1920) {
            await page.screenshot({ path: `${DIR}/register-${width}${width === 1366 ? 'x768' : ''}.png` });
        }

        // والتركيز في كلمة المرور يُظهر تنبيه التسريب ويصفه للحقل، والزر باقٍ في الشاشة
        const password = page.locator('form [name="password"]');
        await password.focus();
        await expect(page.locator('[data-password-rules] [data-leaked]')).toBeVisible();
        expect(await password.getAttribute('aria-describedby')).toContain('field-password-rules-leaked');
        expect((await measure(page)).submit).toBeLessThanOrEqual(height);
    });
}

// خطأ التحقق في كلمة المرور يبقى موصولاً بالحقل بمعرّفه كاملاً، ولا تتكرر المعرّفات (المشرف على طلب الدمج 189، المهمة 1)
test('the password error stays described with exact, unique ids', async ({ page }) => {
    await page.goto('/register');
    await page.locator('form [name="password"]').fill('Short1!');
    await page.locator('form [name="password_confirmation"]').fill('Different1!');
    // submit() يتجاوز تحقق المتصفح فيصل الخطأ من الخادم
    await page.locator('form [name="password_confirmation"]').evaluate((el) => el.form.submit());
    await page.locator('#field-password-error').waitFor();

    const ids = ((await page.locator('form [name="password"]').getAttribute('aria-describedby')) ?? '').split(/\s+/).filter(Boolean);
    expect(ids).toContain('field-password-error');
    expect(ids).toContain('field-password-rules-heading');
    expect(ids).toContain('field-password-rules-leaked');
    expect(new Set(ids).size).toBe(ids.length);
});

// تخطيط صفحات الحساب كلها تغيّر على الحاسوب (المسافات)، فلقطة للدخول أيضاً (المشرف على طلب الدمج 189، المنخفضة 6)
test('the login card still fits at 1366×768 after the tighter auth layout', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'الحاسوب');
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto('/login');

    const bottom = await page.evaluate(() => Math.round(document.querySelector('[data-auth-card]').getBoundingClientRect().bottom));
    expect(bottom).toBeLessThanOrEqual(768);
    await page.screenshot({ path: `${DIR}/login-1366.png` });
});

test('the register page on the phone keeps its single column with no horizontal scroll', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'الجوال');
    await page.goto('/register');
    await withChallengeStandIn(page);

    expect(await page.evaluate(() => document.documentElement.getAttribute('dir'))).toBe('rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
    // على الجوال الشروط كاملة بعنوانها، وتنبيه التسريب ظاهر
    await expect(page.locator('[data-password-rules] [data-leaked]')).toBeVisible();
    await expect(page.locator('[data-password-rules] #field-password-rules-heading')).toBeVisible();
    await page.screenshot({ path: `${DIR}/register-390.png`, fullPage: true });
});
