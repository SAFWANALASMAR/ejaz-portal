// مراسي «عن الجمعية» (رابط العرض، 5 نوفمبر 2026): «الأهداف» تفتح مقطع «أهداف الجمعية» لا رأس الصفحة، والعنوان بعد النزول تحت
// الرأس الثابت مباشرة (كانت الإزاحة مضاعفة: scroll-mt-48 مع scroll-padding-top، فيقع 412 بكسل تحت الأعلى على الحاسوب و272 على
// الجوال)، وعلى الجوال من الصفحة نفسها تُغلق ☰ وينزل فوراً. على 1440 و390. البيانات: صفحة «عن الجمعية» منشورة بمقطع عنوانه
// «الأهداف» أو «أهداف الجمعية» (tests/browser/README.md)، وبدونه يُتخطى.
import { expect, test } from '@playwright/test';

// المسافة بين أسفل الرأس الثابت وأعلى المقطع بعد أن يستقر التمرير الناعم
async function gapUnderHeader(page, id) {
    return page.evaluate(async (target) => {
        let last = -1;
        for (let i = 0; i < 40; i++) {
            await new Promise((resolve) => setTimeout(resolve, 100));
            if (Math.abs(window.scrollY - last) < 1) {
                break;
            }
            last = window.scrollY;
        }
        await new Promise((resolve) => setTimeout(resolve, 400));

        const header = document.querySelector('[data-site-header]').getBoundingClientRect();
        const section = document.getElementById(target).getBoundingClientRect();

        return { gap: Math.round(section.top - header.bottom), top: Math.round(section.top), header: Math.round(header.bottom) };
    }, id);
}

test('the goals anchor lands its section right under the header, and from the same page the menu closes and scrolls at once', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'tablet', 'الحاسوب والجوال');

    await page.goto('/about');
    test.skip((await page.locator('#about-goals').count()) === 0, 'يلزم مقطع «أهداف الجمعية» منشور');

    const desktop = testInfo.project.name === 'desktop';
    const link = desktop
        ? page.locator('[data-main-nav] a[href$="/about#about-goals"]').first()
        : page.locator('#mobile-menu a[href$="/about#about-goals"]').first();

    if (desktop) {
        await page.locator('[data-main-nav] .nav-capsule', { hasText: 'عن الجمعية' }).first().hover();
    } else {
        await page.locator('[aria-controls="mobile-menu"]').first().click();
        await expect(page.locator('#mobile-menu')).toBeVisible();
        // القائمة المنسدلة «عن الجمعية» داخل ☰
        await page.locator('#mobile-menu [data-toggle="dropdown"]', { hasText: 'عن الجمعية' }).first().click();
    }

    const requests = [];
    page.on('request', (request) => request.url().includes('/about') && requests.push(request.url()));
    await link.click();

    // الصفحة نفسها: لا طلب إلى الخادم، وتُغلق ☰، والعنوان في الرابط
    if (!desktop) {
        await expect(page.locator('#mobile-menu')).toBeHidden({ timeout: 500 });
    }
    await expect(page).toHaveURL(/\/about#about-goals$/);

    const measured = await gapUnderHeader(page, 'about-goals');
    expect(requests, 'لا تنقّل إلى الخادم لمرساة في الصفحة نفسها').toEqual([]);
    expect(measured.gap, `المقطع تحت الرأس مباشرة (${JSON.stringify(measured)})`).toBeGreaterThanOrEqual(-2);
    expect(measured.gap, `المقطع تحت الرأس مباشرة (${JSON.stringify(measured)})`).toBeLessThanOrEqual(48);

    await page.screenshot({ path: `docs/design/screenshots/store-followups/about-goals-anchor-${page.viewportSize().width}.png` });

    // ومن صفحة أخرى بالرابط نفسه: الموضع نفسه بعد التنقّل
    await page.goto('/contact');
    await page.goto('/about#about-goals');
    const fromElsewhere = await gapUnderHeader(page, 'about-goals');
    expect(fromElsewhere.gap, `من رابط مباشر (${JSON.stringify(fromElsewhere)})`).toBeGreaterThanOrEqual(-2);
    expect(fromElsewhere.gap, `من رابط مباشر (${JSON.stringify(fromElsewhere)})`).toBeLessThanOrEqual(48);
});
