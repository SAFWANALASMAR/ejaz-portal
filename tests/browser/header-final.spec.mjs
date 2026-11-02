// الرأس النهائي (ترتيب صفوان، 2 نوفمبر 2026 — docs/decisions/2026-11-02-header-final.md، ويلغي البند ٢٥ من ملاحظات 1 نوفمبر):
// صفّان ثابتان معاً عند التمرير بلا أي صغر. الصف الأول بعرض الشاشة بهامش صغير: الرمز في أقصى اليمين، والمخطوطة في منتصف الشاشة،
// ومربع الترخيص بجانبها من اليسار بفاصل صغير، والدخول والبحث في أقصى اليسار. والصف الثاني القائمة بكبسولاتها.
// اللقطات: docs/design/screenshots/header-final/<العرض>-top.png و<العرض>-scrolled.png
import { expect, test } from '@playwright/test';

const WIDTHS = [1920, 1440, 1280, 1263, 820, 390];
const SHOTS = 'docs/design/screenshots/header-final';

// العروض صريحة في الاختبار، فيكفي مشروع واحد
test.beforeEach(async ({}, testInfo) => test.skip(testInfo.project.name !== 'desktop', 'العروض في الاختبار نفسه'));

function layout(page) {
    return page.evaluate(() => {
        const header = document.querySelector('[data-site-header]');
        const box = (selector) => {
            const element = selector === 'header' ? header : header.querySelector(selector);
            if (!element || getComputedStyle(element).display === 'none' || element.offsetParent === null) {
                return null;
            }
            const { left, right, top, bottom, width, height } = element.getBoundingClientRect();

            return { left, right, top, bottom, width, height };
        };

        return {
            viewport: document.documentElement.clientWidth,
            dir: document.documentElement.dir,
            noHorizontalScroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
            header: box('header'),
            top: box('.header-top'),
            nav: box('[data-main-nav]'),
            mark: box('[data-brand-mark]'),
            calligraphy: box('[data-logo-calligraphy]'),
            licence: box('[data-header-licence]'),
            login: box('.header-top .login-pill'),
            search: box('[data-search-open]'),
            burger: box('[data-toggle="mobile-menu"]'),
            capsule: box('.nav-capsule'),
            capsules: header.querySelectorAll('.nav-capsule').length,
        };
    });
}

// كل مقاس بعد التمرير كما كان في أعلى الصفحة (لا صغر)
function expectSameSizes(before, after) {
    for (const key of ['header', 'top', 'nav', 'mark', 'calligraphy', 'licence', 'login', 'search', 'burger', 'capsule']) {
        expect(after[key] === null, `${key}: الظهور نفسه`).toBe(before[key] === null);
        if (before[key]) {
            expect(Math.abs(after[key].width - before[key].width), `${key}: العرض`).toBeLessThan(0.5);
            expect(Math.abs(after[key].height - before[key].height), `${key}: الارتفاع`).toBeLessThan(0.5);
        }
    }
}

for (const width of WIDTHS) {
    test(`header at ${width}px: positions, both rows pinned, no shrinking on scroll`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);

        const top = await layout(page);
        const vw = top.viewport;
        expect(top.dir).toBe('rtl');
        expect(top.noHorizontalScroll).toBe(true);

        // الرمز في أقصى اليمين بهامش صغير
        expect(vw - top.mark.right).toBeLessThanOrEqual(24);
        expect(vw - top.mark.right).toBeGreaterThanOrEqual(8);

        if (width >= 800) {
            // المخطوطة في منتصف الشاشة
            expect(Math.abs((top.calligraphy.left + top.calligraphy.right) / 2 - vw / 2)).toBeLessThanOrEqual(3);
        } else {
            // الجوال كما كان: المخطوطة بجانب الرمز، وزر القائمة في أقصى اليسار بلا تراكب
            expect(top.mark.left - top.calligraphy.right).toBeGreaterThanOrEqual(4);
            expect(top.calligraphy.left).toBeGreaterThan(top.search.right);
        }

        if (width >= 1240) {
            // الصف الثاني تحت الأول، بكبسولاته
            expect(top.nav).not.toBeNull();
            expect(top.nav.top).toBeGreaterThanOrEqual(top.top.bottom - 0.5);
            expect(top.capsules).toBeGreaterThan(0);
            expect(top.burger).toBeNull();

            // مربع الترخيص بجانب المخطوطة من اليسار بفاصل صغير واضح
            expect(top.licence).not.toBeNull();
            const gap = top.calligraphy.left - top.licence.right;
            expect(gap).toBeGreaterThanOrEqual(4);
            expect(gap).toBeLessThanOrEqual(24);

            // الدخول في أقصى اليسار بهامش صغير، والبحث بجانبه، وكلاهما يسار مربع الترخيص
            expect(top.login.left).toBeLessThanOrEqual(24);
            expect(top.login.left).toBeGreaterThanOrEqual(8);
            expect(top.search.left - top.login.right).toBeGreaterThanOrEqual(0);
            expect(top.search.left - top.login.right).toBeLessThanOrEqual(16);
            expect(top.licence.left).toBeGreaterThanOrEqual(top.search.right);
        } else {
            // اللوحي والجوال: زر القائمة في أقصى اليسار، والبحث (والدخول على اللوحي) بجانبه
            expect(top.burger.left).toBeLessThanOrEqual(24);
            const next = top.login ?? top.search;
            expect(next.left - top.burger.right).toBeLessThanOrEqual(16);
            expect(top.calligraphy.left).toBeGreaterThan((top.login ?? top.search).right);
        }

        await page.screenshot({ path: `${SHOTS}/${width}-top.png`, clip: { x: 0, y: 0, width, height: Math.ceil(top.header.bottom) + 120 } });

        await page.evaluate(() => window.scrollTo({ top: 1400, behavior: 'instant' }));
        await page.waitForTimeout(500);
        const scrolled = await layout(page);

        // الصفّان معاً ثابتان في أعلى الشاشة، بالمقاسات نفسها
        expect(Math.round(scrolled.header.top)).toBe(0);
        expect(Math.round(scrolled.top.top)).toBe(0);
        if (scrolled.nav) {
            expect(Math.abs(scrolled.nav.top - top.nav.top)).toBeLessThan(0.5);
        }
        expectSameSizes(top, scrolled);
        expect(Math.abs(scrolled.calligraphy.left - top.calligraphy.left)).toBeLessThan(0.5);
        expect(scrolled.noHorizontalScroll).toBe(true);

        await page.screenshot({ path: `${SHOTS}/${width}-scrolled.png`, clip: { x: 0, y: 0, width, height: Math.ceil(scrolled.header.bottom) + 120 } });
    });
}

test('header: search comes before the login button, so login sits at the far left', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const login = page.locator('.header-top .login-pill');
    await expect(login).toHaveAttribute('href', /\/login$/);

    // الترتيب في الشيفرة: البحث قبل الدخول، فالدخول في أقصى اليسار (RTL)
    const order = await page.evaluate(() => {
        const actions = document.querySelector('.header-actions');
        const search = actions.querySelector('[data-search-open]');
        const pill = actions.querySelector('.login-pill');

        return search.compareDocumentPosition(pill) & Node.DOCUMENT_POSITION_FOLLOWING;
    });
    expect(order).toBeTruthy();
});
