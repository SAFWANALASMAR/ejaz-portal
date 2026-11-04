// التجربة الشاملة على 9b9ce5f0 (4 نوفمبر 2026): الرئيسية على 390 كانت تنزاح أفقياً 47 بكسل حين تكثر الإعلانات، لأن نقاط شريطها
// في صف لا يلتف. وما زاد على ست شرائح عدّاد «03 / 10» بدل النقاط (x-carousel). البيانات: عشرة إعلانات منشورة فأكثر في القاعدة
// المحلية (tests/browser/README.md)، وبأقل منها يُتخطى الاختبار. واختبار الشيفرة: tests/Feature/Content/V0151QaTest.php.
import { expect, test } from '@playwright/test';

test('the home page does not scroll sideways at 390 with ten announcements, and their carousel shows a counter', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'العيب على عرض 390');

    await page.goto('/');
    const carousel = page.locator('.home-announcements');
    const slides = await carousel.locator('[data-carousel-item]').count();
    test.skip(slides < 10, 'يلزم عشرة إعلانات منشورة فأكثر');

    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 500) {
            window.scrollTo(0, y);
            await new Promise((resolve) => setTimeout(resolve, 50));
        }
        window.scrollTo(0, 0);
    });

    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBe(390);
    await expect(carousel.locator('[data-carousel-dots]')).toHaveCount(0);
    await expect(carousel.locator('[data-carousel-status]')).toHaveText(new RegExp(`^01 / ${slides}$`));

    // والعدّاد يتبع الأسهم
    await carousel.locator('[data-carousel-next]').click();
    await expect(carousel.locator('[data-carousel-status]')).toHaveText(new RegExp(`^02 / ${slides}$`));
    await carousel.scrollIntoViewIfNeeded();
    await page.screenshot({ path: 'docs/design/screenshots/v0151-qa/home-announcements-390.png' });
});
