// انتقال الصفحات على شبكة بطيئة (عيب صوّره صفوان على رابط العرض، 4 نوفمبر 2026): كانت طبقة الشعار تُخفى قسراً بعد أربع ثوانٍ
// (TRANSITION_LIMIT) ولو لم تصل الصفحة، فتعود الصفحة القديمة ظاهرة حتى يرد الخادم ثم تتبدّل وحدها. والقرار
// (docs/decisions/2026-11-01-director-notes.md): الطبقة تبقى حتى تحل الصفحة الجديدة (livewire:navigated) ثم تتلاشى، وبعد أربع ثوانٍ
// يظهر تحت الشعار سطر «جارٍ التحميل…». على الحاسوب وحده: رابط «تواصل معنا» في قائمة الرأس الظاهرة.
import { expect, test } from '@playwright/test';
import fs from 'node:fs';

const DIR = 'docs/design/screenshots/page-transition';
const DELAY = 6000;

test('the logo overlay stays until the destination page is in, and the old page never shows again (6 s network delay)', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'قائمة الرأس ظاهرة على الحاسوب');
    test.setTimeout(60_000);

    await page.goto('/services');
    const source = { title: await page.title(), heading: (await page.locator('#main h1').first().innerText()).trim() };

    // الصفحة الوجهة وحدها تتأخر ست ثوانٍ (طلب التنقّل السريع إلى /contact)
    await page.route((url) => url.pathname === '/contact', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, DELAY));
        await route.continue();
    });

    // سجل كل 50 ملّي ثانية: هل الطبقة ظاهرة، وعنوان الصفحة وعنوان التبويب الظاهران تحتها
    await page.evaluate(() => {
        window.swapLog = [];
        window.swapTimer = setInterval(() => {
            const overlay = document.querySelector('[data-page-transition]');
            window.swapLog.push({
                t: performance.now(),
                covered: Boolean(overlay) && !overlay.hidden && overlay.dataset.state !== 'leaving',
                slow: Boolean(overlay?.querySelector('[data-transition-slow]')?.checkVisibility()),
                heading: document.querySelector('#main h1')?.textContent.trim() ?? '',
                title: document.title,
            });
        }, 50);
    });

    await page.locator('header a[href$="/contact"]').filter({ visible: true }).first().click();

    // بعد أربع ثوانٍ: الطبقة باقية وفيها سطر «جارٍ التحميل…»
    await page.waitForTimeout(4600);
    fs.mkdirSync(DIR, { recursive: true });
    await page.screenshot({ path: `${DIR}/slow-${page.viewportSize().width}.png` });

    await expect(page).toHaveURL(/\/contact$/, { timeout: 20_000 });
    await expect(page.locator('[data-page-transition]')).toBeHidden({ timeout: 5000 });

    const log = await page.evaluate(() => {
        clearInterval(window.swapTimer);

        return window.swapLog;
    });
    const firstCovered = log.findIndex((entry) => entry.covered);
    expect(firstCovered, 'ظهرت الطبقة أثناء التنقّل البطيء').toBeGreaterThanOrEqual(0);

    const uncovered = log.slice(firstCovered).filter((entry) => !entry.covered);
    expect(uncovered.length, 'اختفت الطبقة').toBeGreaterThan(0);
    // بعد اختفاء الطبقة لا تظهر الصفحة القديمة أبداً: العنوان وعنوان التبويب للوجهة
    const stale = uncovered.filter((entry) => entry.title === source.title || entry.heading === source.heading);
    expect(stale, 'الصفحة القديمة ظهرت بعد اختفاء الطبقة').toEqual([]);
    expect(uncovered[0].title).not.toBe(source.title);
    expect(log.some((entry) => entry.covered && entry.slow), 'سطر «جارٍ التحميل…» تحت الشعار بعد أربع ثوانٍ').toBe(true);

    expect(await page.locator('#main h1').first().innerText()).not.toBe(source.heading);
});
