// «كيف تسجّل» في «الدورات والبرامج» وصفحة البرنامج (ملاحظة المدير التنفيذي، 29 أكتوبر 2026): قائمة مرقمة من اليمين،
// وزر الخطوة الأولى يصله لوحة المفاتيح، ولا تمرير أفقي على 390. واللقطات في docs/design/screenshots/programs-how-to/
// بادئتها SHOT (after افتراضياً، وbefore للقطات ما قبل البناء بلا فحص للقسم). البرنامج المعروض PROGRAM_SLUG من بيانات
// تجريبية محلية (tests/browser/README.md).
import { expect, test } from '@playwright/test';

const shot = process.env.SHOT ?? 'after';
const slug = process.env.PROGRAM_SLUG ?? 'demo-course';
const pages = [
    { name: 'index', path: '/programs' },
    { name: 'show', path: `/programs/${slug}` },
];

for (const target of pages) {
    test(`programs ${target.name} explains how to register`, async ({ page }) => {
        await page.goto(target.path);
        await page.evaluate(() => document.fonts.ready);
        const width = page.viewportSize().width;

        await page.screenshot({ path: `docs/design/screenshots/programs-how-to/${shot}-${target.name}-${width}.png`, fullPage: true });

        if (shot === 'before') {
            return;
        }

        expect(await page.locator('html').getAttribute('dir')).toBe('rtl');

        const section = page.locator('[data-programs-how-to]');
        await expect(section).toBeVisible();
        await expect(section.locator('ol > li')).toHaveCount(5);

        // زر الخطوة الأولى رابط يصله Tab ويظهر إطار تركيزه
        const start = section.locator('[data-how-to-start]').first();
        await start.focus();
        await expect(start).toBeFocused();

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
    });
}
