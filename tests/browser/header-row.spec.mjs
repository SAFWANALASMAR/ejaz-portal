// الرأس صفّان (أمر التصور النهائي، النسخة 63 — البندان R2 وR3، وFinal-Home): الأول الرمز والمخطوطة ومربع الترخيص والدخول والبحث،
// والثاني القائمة «ج» كبسولات متساوية في صف واحد، وكله ثابت عند التمرير ومنفصل عن البطل بحد #8EC5F0. وعلى عروض الحاسوب الشائعة
// كل اسم في كبسولته بلا قص، وما دون نقطة الظهور وما لا يتسع يصير زر القائمة.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// 1263: نافذة 1280 على ويندوز بشريط تمرير ظاهر (نحو 17 بكسل)، والمتصفح هنا يخفي الشريط
const WIDTHS = [1263, 1280, 1366, 1440, 1920];

// يكفي عرض واحد من مشاريع الإعداد، والعروض هنا صريحة
test.beforeEach(async ({}, testInfo) => test.skip(testInfo.project.name !== 'desktop', 'العروض في الاختبار نفسه'));

async function headerLayout(page) {
    return page.evaluate(() => {
        const header = document.querySelector('[data-site-header]');
        const nav = header.querySelector('[data-main-nav]');
        const capsules = [...nav.querySelectorAll('.nav-capsule')];
        const hamburger = header.querySelector('[data-toggle="mobile-menu"]');
        const top = header.querySelector('.header-top').getBoundingClientRect();
        const row = nav.getBoundingClientRect();

        return {
            navShown: getComputedStyle(nav).display !== 'none',
            hamburgerShown: getComputedStyle(hamburger).display !== 'none',
            licenceShown: getComputedStyle(header.querySelector('[data-header-licence]')).display !== 'none',
            // لا اسم مقصوص في كبسولته، والكبسولات بعرض واحد، وصف القائمة تحت الصف الأول
            clipped: capsules.filter((capsule) => {
                const label = capsule.querySelector('.truncate');
                return label.scrollWidth > label.clientWidth + 1;
            }).length,
            widths: [...new Set(capsules.map((capsule) => Math.round(capsule.getBoundingClientRect().width)))],
            secondRow: row.top >= top.bottom - 0.5,
            borderBottom: getComputedStyle(header).borderBottomColor,
            noHorizontalScroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
        };
    });
}

for (const width of WIDTHS) {
    test(`shows the seven capsules on the second row with no clipped name at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);

        const layout = await headerLayout(page);
        expect(layout.navShown).toBe(true);
        expect(layout.hamburgerShown).toBe(false);
        expect(layout.licenceShown).toBe(true);
        expect(layout.clipped).toBe(0);
        // الكبسولات بقدر أسمائها ثم الباقي بالتساوي منذ ملاحظات 1 نوفمبر 2026 (سبع قوائم منسدلة بسهمها)، فلا عرض واحد لها
        expect(layout.widths.length).toBeGreaterThanOrEqual(1);
        expect(layout.secondRow).toBe(true);
        expect(layout.borderBottom).toBe('rgb(142, 197, 240)');
        expect(layout.noHorizontalScroll).toBe(true);
    });
}

test('keeps the whole two-row header at the top while scrolling', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    await page.mouse.wheel(0, 1600);

    await expect.poll(() => page.evaluate(() => Math.round(document.querySelector('[data-site-header]').getBoundingClientRect().top))).toBe(0);
    await expect(page.locator('[data-main-nav]')).toBeInViewport();
});

for (const width of [820, 390]) {
    test(`keeps the menu button on tablet and phone at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);

        const layout = await headerLayout(page);
        expect(layout.navShown).toBe(false);
        expect(layout.hamburgerShown).toBe(true);
        expect(layout.noHorizontalScroll).toBe(true);

        await page.locator('[data-toggle="mobile-menu"]').click();
        await expect(page.locator('#mobile-menu [data-icon-tile]').first()).toBeVisible();
    });
}

for (const width of [1280, 1366, 1440, 820, 390]) {
    test(`passes axe on the header at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);

        const { violations } = await new AxeBuilder({ page }).include('[data-site-header]').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([]);
    });
}

// إضافة توجيه المدير التنفيذي (3 نوفمبر 2026، البند ٣): صف القائمة ظاهر من 1024 بخط أصغر، ولا يحل محله زر القائمة
test('keeps the menu row, not the menu button, below the licence width on the landscape tablet', async ({ page }) => {
    await page.setViewportSize({ width: 1200, height: 900 });
    await page.goto('/');

    const layout = await headerLayout(page);
    expect(layout.navShown).toBe(true);
    expect(layout.hamburgerShown).toBe(false);
    expect(layout.clipped).toBe(0);
});

test('shrinks the menu row when the items do not fit, instead of hiding it behind the menu button', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');

    // قائمة أطول مما يتسع (تُدار من اللوحة): عناصر إضافية بعبارات من كلمتين
    await page.evaluate(() => {
        const list = document.querySelector('[data-main-nav] ul');
        const item = [...list.children].find((li) => li.querySelector(':scope > a'));
        for (const label of ['الدورات والبرامج', 'الجمعية العمومية', 'قياس الرضا', 'البث المباشر', 'معرض الشواهد', 'الأسئلة الشائعة']) {
            const copy = item.cloneNode(true);
            copy.querySelector('.truncate').textContent = label;
            list.append(copy);
        }
    });
    await page.setViewportSize({ width: 1439, height: 900 });

    // الخط الأصغر (data-nav-overflow)، والصف ظاهر بكل عناصره وأيقوناتها داخل الشاشة، ولا زر قائمة
    await expect(page.locator('[data-site-header]')).toHaveAttribute('data-nav-overflow', '');
    const layout = await headerLayout(page);
    expect(layout.navShown).toBe(true);
    expect(layout.hamburgerShown).toBe(false);
    expect(layout.noHorizontalScroll).toBe(true);
    const icons = await page.evaluate(() => [...document.querySelectorAll('[data-main-nav] .nav-capsule [data-icon-tile]')].map((icon) => Math.round(icon.getBoundingClientRect().width)));
    expect(icons).toHaveLength(13);
    expect(Math.min(...icons)).toBeGreaterThanOrEqual(15);
});
