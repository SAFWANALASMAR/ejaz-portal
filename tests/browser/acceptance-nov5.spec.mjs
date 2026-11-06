// قبول ملاحظات المدير التنفيذي ليلة 4 نوفمبر (5 نوفمبر 2026 — docs/decisions/2026-11-05-acceptance-nov5.md):
// - بطاقة «مسابقة البراهين» أعلى «حسابي»، فوق شريط الترحيب وشريط «جديد مكتبة إعجاز»، وتُرى كاملة على جوال 390 بلا تمرير.
// - ولا خطأ «Transition was aborted… Page already revealed» في وحدة التحكم بعد الدخول ولا بعد التنقّل الكامل بين الصفحات: يلتقطه
//   partials/view-transition-guard.blade.php، ولا يلتقط غيره من الأخطاء.
// يحتاج: ejaz:demo-student-competition --json (STUDENT_COMPETITION): الطالب السابق مسجل فيها ومرحلتها الحالية جارية.
// التشغيل في tests/browser/README.md («قبول 5 نوفمبر»). واللقطات في docs/design/screenshots/acceptance-nov5/<الشاشة>-<العرض>.png
import { expect, test } from '@playwright/test';

const DEMO = process.env.STUDENT_COMPETITION ? JSON.parse(process.env.STUDENT_COMPETITION) : null;
const shot = (name, testInfo) => `docs/design/screenshots/acceptance-nov5/${name}-${testInfo.project.use.viewport.width}.png`;
const TRANSITION_ERROR = /Transition was aborted|Page already revealed/;

function collectErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });

    return errors;
}

async function login(page, email, password) {
    await page.goto('/login');
    await page.fill('#field-login', email);
    await page.fill('#field-password', password);
    await Promise.all([page.waitForURL((url) => url.pathname === '/account'), page.locator('main').getByRole('button', { name: 'تسجيل الدخول', exact: true }).click()]);
}

async function expectNoHorizontalScroll(page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
}

test.describe('acceptance of the 4 November notes', () => {
    test.skip(!DEMO, 'يحتاج مسابقة تجريبية: STUDENT_COMPETITION من ejaz:demo-student-competition --json');

    test('the competition card sits above the welcome and library strips, whole in the first screen', async ({ page }, testInfo) => {
        const errors = collectErrors(page);
        await login(page, DEMO.students.veteran, DEMO.password);
        await page.waitForLoadState('networkidle');

        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        const card = page.locator('[data-account-competition]').first();
        await expect(card).toBeVisible();
        await expect(card.locator('[data-account-competition-action]')).toBeVisible();

        // بلا تمرير: أعلى البطاقة وأسفلها وزرها داخل الشاشة الأولى
        expect(await page.evaluate(() => window.scrollY)).toBe(0);
        const viewport = page.viewportSize();
        const box = await card.boundingBox();
        expect(box.y, 'أعلى البطاقة في الشاشة الأولى').toBeLessThan(viewport.height);
        expect(box.y + box.height, 'البطاقة كاملة بزرها في الشاشة الأولى').toBeLessThanOrEqual(viewport.height);

        // قبل الترحيب وشريط المكتبة في ترتيب المستند وفي الموضع الظاهر
        const welcome = page.locator('[data-account-welcome]');
        const order = await page.evaluate(() => {
            const cardElement = document.querySelector('[data-account-competition]');
            const before = (selector) => {
                const other = document.querySelector(selector);

                return other === null ? null : Boolean(cardElement.compareDocumentPosition(other) & Node.DOCUMENT_POSITION_FOLLOWING);
            };

            return { welcome: before('[data-account-welcome]'), library: before('[data-account-library-strip]') };
        });
        expect(order.welcome).toBe(true);
        expect(order.library).not.toBe(false);
        expect(box.y + box.height).toBeLessThanOrEqual((await welcome.boundingBox()).y);

        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('account-card', testInfo) });
        expect(errors.filter((message) => TRANSITION_ERROR.test(message)), 'خطأ الانتقال بعد الدخول').toEqual([]);
    });

    // انتقال المستندات (@view-transition) لا يكون إلا لتنقّل تطلقه الصفحة: إرسال نموذج الدخول، ورابط بتحميل كامل. وعلى اتصال بطيء
    // كرابط العرض (نحو ثانيتين لكل صفحة)
    test('full page loads on a slow connection raise no aborted view transition error', async ({ page }) => {
        test.setTimeout(240_000);
        const errors = collectErrors(page);
        await page.route((url) => !/\.(css|js|mjs|woff2?|svg|png|jpe?g|webp|ico)$/.test(url.pathname) && !url.pathname.startsWith('/@') && !url.pathname.startsWith('/node_modules') && !url.pathname.startsWith('/resources'), async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 1700));
            await route.continue();
        });

        await login(page, DEMO.students.veteran, DEMO.password);
        await page.waitForTimeout(1500);

        for (const path of ['/', '/account', '/about', '/account/competitions', '/account']) {
            await Promise.all([page.waitForURL((url) => url.pathname === path), page.evaluate((href) => {
                const link = document.createElement('a');
                link.href = href;
                document.body.append(link);
                link.click();
            }, path)]);
            await page.waitForTimeout(1500);
        }

        expect(errors.filter((message) => TRANSITION_ERROR.test(message))).toEqual([]);
    });

    // رفض الانتقال الملغى نفسه لم يظهر محلياً (يظهر على رابط العرض عبر النفق)، فيُصنع بمثله: يُلتقط هو وحده، ويبقى غيره خطأً ظاهراً
    test('the guard silences only an aborted view transition rejection', async ({ page }) => {
        const errors = collectErrors(page);
        await page.goto('/');
        await expect(page.locator('script[data-view-transition-guard]')).toHaveCount(1);

        await page.evaluate(() => {
            Promise.reject(new DOMException('Transition was aborted because of invalid state. Page already revealed', 'InvalidStateError'));
            Promise.reject(new Error('unrelated failure'));
        });
        await page.waitForTimeout(500);

        expect(errors.filter((message) => TRANSITION_ERROR.test(message))).toEqual([]);
        expect(errors.some((message) => message.includes('unrelated failure')), 'غير الانتقال يبقى ظاهراً').toBe(true);
    });
});
