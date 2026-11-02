// ملاحظة صفوان ٣٠ (3 نوفمبر 2026 — docs/decisions/2026-11-03-note-30-announcements-news.md): «إعلانات» دوّاراً و«أخبار الجمعية»
// قائمة جنباً إلى جنب على الحاسوب ومتراصّتين على الجوال، وشريحة الإعلان بصورتها وشارتها وعنوانها ووصفها وتاريخها وزرها بلا فراغ
// كبير، وكل خبر بصورته وعنوانه وتاريخه. على العروض الثلاثة (playwright.config.mjs).
// اللقطات في docs/design/screenshots/note-30/<SHOT_PREFIX>-home-feed-<العرض>.png (after افتراضياً، وbefore على main قبل التعديل).
import { expect, test } from '@playwright/test';

const prefix = process.env.SHOT_PREFIX ?? 'after';
const dir = 'docs/design/screenshots/note-30';

test('note 30: screenshot of the announcements and news band', async ({ page }, info) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    const band = page.locator('section:has(#home-announcements)');
    await expect(band).toBeVisible();
    await band.scrollIntoViewIfNeeded();
    await page.waitForTimeout(600);
    await band.screenshot({ path: `${dir}/${prefix}-home-feed-${info.project.name}.png` });
});

test('note 30: side by side on desktop, stacked on mobile, no gap, no horizontal scroll', async ({ page }, info) => {
    test.skip(prefix === 'before', 'لقطات ما قبل التعديل وحدها');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');

    expect(await page.evaluate(() => document.documentElement.getAttribute('dir'))).toBe('rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'لا تمرير أفقي').toBeLessThanOrEqual(0);

    const columns = await page.evaluate(() => ['#home-announcements', '#home-news'].map((id) => {
        const box = document.querySelector(id).closest('[data-feed-column]').getBoundingClientRect();

        return { top: Math.round(box.top), left: Math.round(box.left), bottom: Math.round(box.bottom) };
    }));

    if (info.project.name === 'desktop') {
        expect(Math.abs(columns[0].top - columns[1].top), 'جنباً إلى جنب').toBeLessThanOrEqual(3);
        expect(columns[0].left, 'الإعلانات يميناً').toBeGreaterThan(columns[1].left);
    } else if (info.project.name === 'mobile') {
        expect(columns[1].top, 'الأخبار تحت الإعلانات').toBeGreaterThanOrEqual(columns[0].bottom);
    }

    // الشريحة الظاهرة: عناصرها، وأكبر فراغ عمودي بين عنصرين متتاليين فيها
    const slide = page.locator('[data-announcement-slide]').first();
    await expect(slide).toBeVisible();
    await expect(slide.locator('[data-slide-media]')).toBeVisible();
    await expect(slide.locator('[data-slide-badge]')).toBeVisible();
    await expect(slide.locator('[data-slide-title]')).toBeVisible();
    await expect(slide.locator('[data-slide-date]')).toBeVisible();

    const gap = await slide.evaluate((element) => {
        const body = element.querySelector('[data-slide-body]');
        const parts = [...body.children].filter((child) => child.getBoundingClientRect().height > 0)
            .map((child) => child.getBoundingClientRect());
        let largest = 0;
        for (let index = 1; index < parts.length; index++) {
            largest = Math.max(largest, parts[index].top - parts[index - 1].bottom);
        }
        const last = parts.at(-1);

        return Math.max(largest, body.getBoundingClientRect().bottom - last.bottom);
    });
    expect(gap, 'لا فراغ أكبر من 120 بكسل داخل الشريحة').toBeLessThanOrEqual(120);

    // الأخبار: صورة وعنوان بسطرين على الأكثر وتاريخ
    const item = page.locator('[data-news-card]:not([aria-hidden="true"])').first();
    await expect(item.locator('img, [data-news-thumb]').first()).toBeVisible();
    await expect(item.locator('time')).toBeVisible();
    const lines = await item.locator('[data-news-title]').evaluate((element) => Math.round(element.getBoundingClientRect().height / parseFloat(getComputedStyle(element).lineHeight)));
    expect(lines, 'العنوان بسطرين على الأكثر').toBeLessThanOrEqual(2);
    await expect(page.locator('[data-news-all]')).toBeVisible();
});
