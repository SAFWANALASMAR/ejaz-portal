// صفحة المسابقة (مراجعة الجودة، 3 نوفمبر 2026): البطاقة الجانبية ثابتة عند التمرير على الحاسوب (1440)، وجدول الجوائز يُمرَّر
// داخل غلافه على الجوال (390) بلا تمرير أفقي للصفحة.
//
// يحتاج: مسابقة طلاب منشورة بجدول جوائز (COMPETITION_SLUG، افتراضياً demo-students من ejaz:demo-student-competition).
// التشغيل: docker compose run --rm --no-deps browser npx playwright test --config=tests/browser/playwright.config.mjs competition-layout.spec.mjs
import { expect, test } from '@playwright/test';

const SLUG = process.env.COMPETITION_SLUG ?? 'demo-students';

test('the competition sidebar stays in view while the page scrolls on desktop', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'الشريط الجانبي عمود مستقل على الحاسوب وحده');

    await page.goto(`/competitions/${SLUG}`);
    const aside = page.locator('aside.competition-aside');
    await expect(aside).toBeVisible();
    expect(await aside.evaluate((element) => getComputedStyle(element).position)).toBe('sticky');

    // التمرير إلى آخر عمود المحتوى، والبطاقة ما زالت في الشاشة تحت الرأس
    const column = page.locator('section .min-w-0.space-y-8').first();
    const columnHeight = await column.evaluate((element) => element.getBoundingClientRect().height);
    expect(columnHeight, 'the content column is taller than the screen').toBeGreaterThan(900);
    await column.evaluate((element) => window.scrollTo(0, element.getBoundingClientRect().bottom + window.scrollY - window.innerHeight - 40));
    await page.waitForTimeout(300);

    const box = await aside.boundingBox();
    expect(box).not.toBeNull();
    expect(box.y, 'the sidebar top is in view').toBeGreaterThanOrEqual(0);
    expect(box.y, 'the sidebar sits under the header').toBeLessThan(200);
});

test('the prize table scrolls inside its wrapper on mobile, and the page does not scroll sideways', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'جدول الجوائز على عرض الجوال');

    await page.goto(`/competitions/${SLUG}`);
    await page.evaluate(() => document.fonts.ready);
    const wrapper = page.locator('[data-prize-table]');
    await wrapper.scrollIntoViewIfNeeded();
    await expect(wrapper.locator('table')).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    const metrics = await wrapper.evaluate((element) => ({
        overflowX: getComputedStyle(element).overflowX,
        scrollWidth: element.scrollWidth,
        clientWidth: element.clientWidth,
        tableWidth: element.querySelector('table').getBoundingClientRect().width,
    }));
    expect(metrics.overflowX).toBe('auto');
    // الجدول كله داخل غلافه: يتسع له أو يُمرَّر، فلا يُقص منه شيء
    expect(metrics.tableWidth).toBeLessThanOrEqual(metrics.scrollWidth + 1);

    // ومبالغ أطول من عرض الجوال (أرقام الجمعية تتغير من اللوحة): يُمرَّر الجدول داخل غلافه
    await wrapper.evaluate((element) => {
        element.querySelectorAll('tbody td').forEach((cell) => {
            cell.textContent = `${cell.textContent} ${cell.textContent}`;
        });
    });
    const wide = await wrapper.evaluate((element) => ({ scrollWidth: element.scrollWidth, clientWidth: element.clientWidth }));
    expect(wide.scrollWidth, 'a wider table scrolls inside its wrapper').toBeGreaterThan(wide.clientWidth);

    // يتحرك داخل غلافه (الاتجاه من اليمين: التمرير بالسالب)
    const moved = await wrapper.evaluate((element) => {
        element.scrollLeft = -(element.scrollWidth - element.clientWidth);
        return element.scrollLeft;
    });
    expect(Math.abs(moved)).toBeGreaterThan(0);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, 'no horizontal scroll on the page').toBeLessThanOrEqual(0);
});
