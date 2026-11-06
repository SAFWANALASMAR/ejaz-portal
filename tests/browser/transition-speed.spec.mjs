// سرعة طبقة الانتقال (ملاحظات المدير التنفيذي، ليلة 5 نوفمبر 2026، البند ٥ — docs/decisions/2026-11-06-transition-speed.md): تظهر بعد
// 400 ملّي ثانية من التنقّل كما قرّر صفوان، وتختفي فور حلول الصفحة (تلاشٍ 120، وفوراً مع تقليل الحركة)، ولا خطأ انتقال في وحدة التحكم.
// وكانت قبل التعديل تبقى نحو 200 ملّي ثانية بعد حلول الصفحة في الحالين. على الحاسوب: رابط «تواصل معنا» في قائمة الرأس.
import { expect, test } from '@playwright/test';

const DELAY = 1500;

for (const motion of ['no-preference', 'reduce']) {
    test(`the overlay shows after 400 ms and leaves as the page arrives (motion: ${motion})`, async ({ browser }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', 'قائمة الرأس ظاهرة على الحاسوب');
        test.setTimeout(60_000);

        const context = await browser.newContext({ ...testInfo.project.use, reducedMotion: motion });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(String(error)));

        await page.goto('/services');
        // الوجهة وحدها تتأخر، فتظهر الطبقة مهما كانت سرعة الخادم
        await page.route((url) => url.pathname === '/contact', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, DELAY));
            await route.continue();
        });

        await page.evaluate(() => {
            window.marks = [];
            const mark = (what) => window.marks.push([what, performance.now()]);
            const overlay = document.querySelector('[data-page-transition]');
            new MutationObserver(() => mark(overlay.hidden ? 'hidden' : 'shown')).observe(overlay, { attributes: true, attributeFilter: ['hidden'] });
            document.addEventListener('livewire:navigate', () => mark('navigate'), { once: true });
            document.addEventListener('livewire:navigated', () => mark('navigated'), { once: true, capture: true });
        });

        await page.locator('header a[href$="/contact"]').filter({ visible: true }).first().click();
        await expect(page).toHaveURL(/\/contact$/, { timeout: 20_000 });
        await expect(page.locator('[data-page-transition]')).toBeHidden({ timeout: 5000 });

        const marks = await page.evaluate(() => window.marks);
        const at = (what) => marks.find(([name]) => name === what)?.[1];
        const shownAfter = at('shown') - at('navigate');
        const hideLag = at('hidden') - at('navigated');

        testInfo.annotations.push({ type: 'timing', description: `shown after ${Math.round(shownAfter)} ms, hidden ${Math.round(hideLag)} ms after the page arrived` });
        // عتبة 400 (قرار صفوان) كما هي: لا قبلها، ولا بعدها بكثير
        expect(shownAfter, 'تظهر بعد 400 ملّي ثانية').toBeGreaterThanOrEqual(390);
        expect(shownAfter, 'تظهر بعد 400 ملّي ثانية').toBeLessThan(700);
        // تختفي فور حلول الصفحة: التلاشي 120 وحده، وبلا حركة فوراً
        expect(hideLag, 'تختفي فور حلول الصفحة').toBeLessThan(motion === 'reduce' ? 50 : 170);
        expect(errors, 'لا خطأ في وحدة التحكم').toEqual([]);

        await context.close();
    });
}
