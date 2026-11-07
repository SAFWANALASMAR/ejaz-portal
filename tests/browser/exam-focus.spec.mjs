// وضع التركيز في صفحات اختبار الطالب (docs/decisions/2026-11-06-exam-focus-mode.md) على العروض الثلاثة: بلا رأس الموقع وقائمته
// ولا شريط «حسابي» ولا التذييل ولا شريط العودة ولا المساعد، وشريط علوي بالوقت الباقي، وأرقام الصفحات عمود ثابت (الحاسوب واللوحي)
// أو شريط أفقي ثابت (الجوال) يتمركز على الحالي. وسؤال عادي بأربعة خيارات بزريه في الشاشة بلا تمرير على 1366×768 و390×844،
// وأن ضغطة «التالي» وصلت يظهر قبل رد الخادم، وأن ملء الشاشة يبقى بين الأسئلة والأرقام كلها في العمود بلا تمرير على 1366×768. وفي كل صفحة: الاتجاه من اليمين، وبلا تمرير أفقي ولا أخطاء في وحدة التحكم ولا مخالفة axe.
// واللقطات في docs/design/screenshots/exam-focus/<الشاشة>-<العرض>.png
//
// يحتاج: STUDENT_COMPETITION من ejaz:demo-student-competition --json (يسجّل الطالب ويبدأ الاختبار إن لم يفعل)، والأصول المبنية:
// docker compose run --rm --no-deps -e VITE_HOST=none -e STUDENT_COMPETITION="$demo" browser npx playwright test --config=tests/browser/playwright.config.mjs exam-focus.spec.mjs
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const DEMO = process.env.STUDENT_COMPETITION ? JSON.parse(process.env.STUDENT_COMPETITION) : null;
const SHOTS = 'docs/design/screenshots/exam-focus';
const SERIALS = { desktop: 21, tablet: 22, mobile: 23 };

// رقم هوية صحيح الصيغة ورقم التحقق (كـ RegisterLoadStudents::nationalId)
function nationalId(serial) {
    const body = '19' + String(serial).padStart(7, '0');
    let sum = 0;
    [...body].forEach((digit, position) => {
        const number = Number(digit) * (position % 2 === 0 ? 2 : 1);
        sum += number > 9 ? number - 9 : number;
    });
    return body + ((10 - (sum % 10)) % 10);
}

function collectErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));
    return errors;
}

async function check(page, step, width, errors) {
    await page.evaluate(() => document.fonts.ready);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    // لا شيء من قالب الموقع وقت الاختبار
    for (const selector of ['[data-site-header]', '[data-site-footer]', '.account-nav', '[data-back-nav]', '[data-assistant]', '[data-page-transition]']) {
        await expect(page.locator(selector), `${step}: ${selector}`).toHaveCount(0);
    }
    await expect(page.locator('[data-exam-bar]')).toBeVisible();
    // دقائق من رقمين، أو ثلاثة لمرحلة أطول من 99 دقيقة
    await expect(page.locator('[data-exam-timer-value]')).toHaveText(/^\d{2,3}:\d\d$/);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, `${step}: no horizontal scroll`).toBeLessThanOrEqual(0);
    // وعلى الجوال يتسع عرض الصفحة نفسه لمحتوى أعرض منها، فلا يظهر في الفحص السابق: العرض كما طُلب
    expect(await page.evaluate(() => window.innerWidth), `${step}: layout width`).toBe(page.viewportSize().width);

    const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')}`), `${step}: axe`).toEqual([]);

    mkdirSync(SHOTS, { recursive: true });
    await page.screenshot({ path: `${SHOTS}/${step}-${width}.png` });
    expect(errors, `${step}: console`).toEqual([]);
}

// السؤال والخيارات و«التالي» و«السابق» داخل الشاشة، والصفحة لا تمرَّر
async function fits(page, label) {
    const m = await page.evaluate(() => ({
        bottom: Math.max(...[...document.querySelectorAll('button[name="go"][value="next"], button[name="go"][value="prev"]')].map((b) => b.getBoundingClientRect().bottom)),
        height: document.documentElement.scrollHeight,
        viewport: window.innerHeight,
    }));
    expect(m.bottom, `${label}: buttons inside the screen`).toBeLessThanOrEqual(m.viewport);
    expect(m.height, `${label}: no page scroll`).toBeLessThanOrEqual(m.viewport);
}

test.describe('student exam: focus mode', () => {
    test.skip(!DEMO, 'يحتاج مسابقة تجريبية: STUDENT_COMPETITION من ejaz:demo-student-competition --json');

    test('shows the exam without the site chrome, with the page numbers and the time left, fitting the screen', async ({ page }, testInfo) => {
        test.setTimeout(240_000);
        const errors = collectErrors(page);
        const width = { desktop: 1440, tablet: 820, mobile: 390 }[testInfo.project.name];

        await page.goto('/login');
        await page.fill('#field-login', DEMO.students[testInfo.project.name]);
        await page.fill('#field-password', DEMO.password);
        await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login')), page.locator('main form button[type=submit]').first().click()]);

        // التسجيل في المسابقة إن لم يسجل، ثم البدء إن لم يبدأ
        await page.goto(`/competitions/${DEMO.slug}/register`);
        if (await page.locator('#field-full_name').count()) {
            await page.fill('#field-full_name', 'طالب تجريبي بن مثال للتركيز');
            await page.fill('#field-national_id', nationalId(SERIALS[testInfo.project.name]));
            await page.selectOption('#field-category_id', { label: 'المرحلة الثانوية' });
            await page.selectOption('#field-grade', { label: 'الأول الثانوي' });
            await page.selectOption('#field-gender', 'male');
            await page.fill('#field-guardian_phone', '0551112233');
            await page.check('input[name="pledge"]');
            await Promise.all([page.waitForURL(`**/competitions/${DEMO.slug}`), page.getByRole('button', { name: 'تسجيل', exact: true }).click()]);
        }
        await page.goto(`/competitions/${DEMO.slug}/rounds/${DEMO.round}`);
        if (await page.getByRole('button', { name: 'ابدأ الاختبار' }).count()) {
            await Promise.all([page.waitForURL(/\/units\/\d+$/), page.getByRole('button', { name: 'ابدأ الاختبار' }).click()]);
        }
        await expect(page).toHaveURL(/\/units\/\d+$/);
        const base = page.url().replace(/\/units\/\d+$/, '');

        // صفحة سؤال اختيار عادي بأربعة خيارات بلا نص ولا مقطع
        const total = await page.locator('[data-exam-units] button[name="go"]:not([value="review"])').count();
        let normal = null;
        for (let number = 1; number <= total && normal === null; number++) {
            await page.goto(`${base}/units/${number}`);
            const single = (await page.locator('main fieldset').count()) === 1 && (await page.locator('main fieldset input[type=radio]').count()) === 4;
            if (single && (await page.locator('main .bg-surface').count()) === 0) {
                normal = number;
            }
        }
        expect(normal, 'a regular four-option question').not.toBeNull();

        // أرقام الصفحات: الحالي ظاهر في الشريط أو العمود، والعمود في جهة البداية (اليمين) على الحاسوب واللوحي
        const units = page.locator('[data-exam-units]');
        await expect(units).toBeVisible();
        await expect(units.locator('[aria-current="step"]')).toBeInViewport();
        const placement = await page.evaluate(() => {
            const nav = document.querySelector('[data-exam-units]').getBoundingClientRect();
            const panel = document.querySelector('.exam-panel').getBoundingClientRect();
            return { navRight: nav.right, panelRight: panel.right, navTop: nav.top, panelTop: panel.top, position: getComputedStyle(document.querySelector('[data-exam-units]')).position };
        });
        expect(placement.position).toBe('sticky');
        if (testInfo.project.name === 'mobile') {
            expect(placement.navTop, 'the strip above the question').toBeLessThan(placement.panelTop);
        } else {
            expect(placement.navRight, 'the column at the start (right)').toBeGreaterThan(placement.panelRight);
        }

        await fits(page, `${width}`);
        await check(page, 'question', width, errors);

        if (testInfo.project.name === 'desktop') {
            await page.setViewportSize({ width: 1366, height: 768 });
            await fits(page, '1366x768');
            // الأرقام كلها بلا تمرير في العمود، بأعمدة بقدر ما يلزم (decisions/2026-11-07-exam-fullscreen.md)
            const column = await page.evaluate(() => {
                const list = document.querySelector('[data-exam-units-list]');
                return { overflow: list.scrollHeight - list.clientHeight, cols: getComputedStyle(list).gridTemplateColumns.split(' ').length };
            });
            expect(column.overflow, '1366x768: every page number without scrolling').toBeLessThanOrEqual(1);
            await page.screenshot({ path: `${SHOTS}/question-1366x768.png` });

            // الدقائق الخمس الأخيرة: الكبسولة بالأزرق الداكن
            await page.evaluate(() => {
                document.querySelector('[data-exam-timer]').dataset.warning = 'true';
            });
            await page.locator('[data-exam-bar]').screenshot({ path: `${SHOTS}/timer-warning-1366.png`, animations: 'disabled' });
            await page.setViewportSize({ width: 1440, height: 900 });
        }

        // «التالي»: الضغطة وصلت قبل رد الخادم (الطلب معلّق ثانية ونصفاً)
        await page.route('**/units/*', async (route) => {
            if (route.request().method() === 'POST') {
                await new Promise((resolve) => setTimeout(resolve, 1500));
            }
            await route.continue();
        });
        await page.locator('main fieldset input[type=radio]').first().check();
        const cdp = await page.context().newCDPSession(page);
        await page.locator('button[name="go"][value="next"]').click({ noWaitAfter: true });
        const pressed = await cdp.send('Runtime.evaluate', {
            expression: "document.querySelector('button[name=go][value=next]').hasAttribute('data-exam-pressed') && document.querySelector('[data-exam-form]').hasAttribute('data-exam-pending')",
            returnByValue: true,
        });
        expect(pressed.result.value, '«التالي» shows the press at once').toBe(true);
        await page.waitForURL((url) => /\/(units\/\d+|review)$/.test(url.pathname) && !url.pathname.endsWith(`/units/${normal}`));
        await page.unroute('**/units/*');

        // صفحة يتركها الطالب بلا إجابة: رقمها كهرماني بعد الانتقال عنها، ومفتاح الألوان ظاهر، وشكل اليد على الأرقام والأزرار والخيارات
        if (/\/units\/\d+$/.test(page.url())) {
            const left = Number(page.url().split('/').pop());
            await Promise.all([page.waitForURL((url) => !url.pathname.endsWith(`/units/${left}`)), page.locator('button[name="go"][value="next"]').click()]);
            await page.waitForLoadState('load');
            if (/\/units\/\d+$/.test(page.url())) {
                const skipped = page.locator(`[data-exam-units-list] button[value="${left}"]`);
                await expect(skipped).toHaveAttribute('data-unit-state', 'skipped');
                await expect(skipped).toHaveCSS('background-color', 'rgb(246, 196, 83)');
                await expect(skipped).toHaveCSS('color', 'rgb(20, 46, 69)');
                await expect(page.locator('[data-exam-units-legend]')).toBeVisible();
                for (const selector of ['[data-exam-units-list] button', 'button[name="go"][value="next"]', 'main label:has(input[type=radio])']) {
                    await expect(page.locator(selector).first(), `${selector}: pointer`).toHaveCSS('cursor', 'pointer');
                }
                await skipped.scrollIntoViewIfNeeded();
                await page.evaluate(() => document.fonts.ready);
                await page.screenshot({ path: `${SHOTS}/question-skipped-${width}.png` });
            }
        }

        // ملء الشاشة (الحاسوب): الزر يظهر ويتبدل «تصغير الشاشة»، وتبقى الشاشة ممتلئة بعد «التالي» لأن التنقّل داخل الصفحة بلا تحميل
        if (testInfo.project.name === 'desktop' && /\/units\/\d+$/.test(page.url())) {
            const toggle = page.locator('[data-exam-fullscreen]');
            await expect(toggle).toBeVisible();
            await expect(toggle).toHaveText('ملء الشاشة');
            await toggle.click();
            await expect(toggle).toHaveAttribute('data-state', 'on');
            await expect(toggle).toHaveText('تصغير الشاشة');
            await page.evaluate(() => {
                window.examStayed = true;
            });
            const from = page.url();
            await page.locator('button[name="go"][value="next"]').click();
            await page.waitForURL((url) => url.href !== from);
            await expect(page.locator('[data-exam-fullscreen]')).toHaveAttribute('data-state', 'on');
            expect(await page.evaluate(() => window.examStayed === true && document.fullscreenElement !== null), 'no page load, still full screen').toBe(true);
            await page.locator('[data-exam-bar]').screenshot({ path: `${SHOTS}/fullscreen-on-1440.png`, animations: 'disabled' });
            await page.locator('[data-exam-fullscreen]').click();
            await expect(page.locator('[data-exam-fullscreen]')).toHaveText('ملء الشاشة');
        }

        // المراجعة بوضع التركيز أيضاً
        await page.goto(`${base}/review`);
        await expect(page.getByRole('button', { name: 'تسليم الإجابات' })).toBeVisible();
        await check(page, 'review', width, errors);
    });
});
