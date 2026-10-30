// قائمة الرأس صف واحد مريح (docs/reviews/ready-fix-header-single-row.md): على عروض الحاسب الشائعة كل عنصر
// بسطر واحد بلا تداخل مع الشعار وزر الدخول، وما دون نقطة الظهور وما لا يتسع يصير زر القائمة.
// وعلى 1280 بعناصرها التسعة والشعار الكامل منذ #66، بخط وحشو أصغر قليلاً دون 1440 (docs/reviews/2026-10-27-header-fit-1280.md).
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// 1263: نافذة 1280 على ويندوز بشريط تمرير ظاهر (نحو 17 بكسل)، والمتصفح هنا يخفي الشريط
const WIDTHS = [1263, 1280, 1366, 1440, 1920];

// يكفي عرض واحد من مشاريع الإعداد، والعروض هنا صريحة
test.beforeEach(async ({}, testInfo) => test.skip(testInfo.project.name !== 'desktop', 'العروض في الاختبار نفسه'));

async function headerLayout(page) {
    return page.evaluate(() => {
        const header = document.querySelector('[data-site-header]');
        const row = header.firstElementChild;
        const nav = header.querySelector('[data-main-nav]');
        const links = [...nav.querySelectorAll(':scope > ul > li > a, :scope > ul > li > button')];
        const box = (element) => element.getBoundingClientRect();
        const brand = box(row.firstElementChild);
        const actions = box(row.lastElementChild);
        const list = box(nav.querySelector('ul'));
        const lineHeight = parseFloat(getComputedStyle(links[0]).lineHeight);
        // اسم العنصر تحت أيقونته (الخيار «ب» — صفوان، 31 أكتوبر 01:38): سطره من نص الرابط وحده لا من المربع كله
        const textHeight = (link) => {
            const range = document.createRange();
            const text = [...link.childNodes].concat([...link.querySelectorAll(':scope > span:not([data-icon-tile])')].flatMap((span) => [...span.childNodes])).find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '');
            range.selectNodeContents(text);
            return range.getBoundingClientRect().height;
        };
        const hamburger = header.querySelector('[data-toggle="mobile-menu"]');

        return {
            navShown: getComputedStyle(nav).display !== 'none',
            hamburgerShown: getComputedStyle(hamburger).display !== 'none',
            // اسم كل عنصر سطر واحد
            lines: links.map((link) => Math.round(textHeight(link) / lineHeight)),
            clearOfBrand: list.right <= brand.left + 0.5,
            clearOfActions: list.left >= actions.right - 0.5,
            noHorizontalScroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
        };
    });
}

for (const width of WIDTHS) {
    test(`keeps every top menu item on one line at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);

        const layout = await headerLayout(page);
        expect(layout.navShown).toBe(true);
        expect(layout.hamburgerShown).toBe(false);
        expect(layout.lines.every((lines) => lines === 1)).toBe(true);
        expect(layout.clearOfBrand).toBe(true);
        expect(layout.clearOfActions).toBe(true);
        expect(layout.noHorizontalScroll).toBe(true);
    });
}

// اسم العنصر بخط 13 تحت أيقونته على كل عروض الحاسوب (الخيار «ب»). و1240 أول ظهور القائمة (--breakpoint-nav)
for (const [width, size] of [[1240, '13px'], [1280, '13px'], [1439, '13px'], [1440, '13px'], [1920, '13px']]) {
    test(`uses ${size} menu text at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');

        const sizes = await page.locator('[data-main-nav] > ul > li > :is(a, button)').evaluateAll((items) => [...new Set(items.map((item) => getComputedStyle(item).fontSize))]);
        expect(sizes).toEqual([size]);
    });
}

for (const width of [820, 390]) {
    test(`keeps the menu button on tablet and phone at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);

        const layout = await headerLayout(page);
        expect(layout.navShown).toBe(false);
        expect(layout.hamburgerShown).toBe(true);
        expect(layout.noHorizontalScroll).toBe(true);
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

test('shows the menu button below the measured width', async ({ page }) => {
    await page.setViewportSize({ width: 1200, height: 900 });
    await page.goto('/');

    const layout = await headerLayout(page);
    expect(layout.navShown).toBe(false);
    expect(layout.hamburgerShown).toBe(true);
});

test('switches to the menu button when the items do not fit, instead of wrapping or overlapping', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');

    // قائمة أطول مما يتسع (تُدار من اللوحة): عناصر إضافية بعبارات من كلمتين
    await page.evaluate(() => {
        const list = document.querySelector('[data-main-nav] ul');
        const item = [...list.children].find((li) => li.querySelector(':scope > a'));
        for (const label of ['خدماتنا الإلكترونية', 'الدورات والبرامج', 'المركز الإعلامي', 'الجمعية العمومية', 'قياس الرضا', 'البث المباشر', 'معرض الشواهد', 'الأسئلة الشائعة']) {
            const copy = item.cloneNode(true);
            copy.querySelector('a').textContent = label;
            list.append(copy);
        }
    });
    await page.setViewportSize({ width: 1439, height: 900 });

    await expect.poll(async () => (await headerLayout(page)).navShown).toBe(false);
    const layout = await headerLayout(page);
    expect(layout.hamburgerShown).toBe(true);
    expect(layout.noHorizontalScroll).toBe(true);

    await page.locator('[data-toggle="mobile-menu"]').click();
    await expect(page.locator('#mobile-menu')).toBeVisible();
});
