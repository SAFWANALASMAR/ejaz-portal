// تنقّل تجاوزه تنقّل أحدث لا يضع صفحته (fix/navigate-supersede — ملاحظة على الإطلاق التجريبي، 12 نوفمبر 2026: «أضغط منشوراتنا فتفتح
// الدورات والبرامج»). Livewire 4.4.8 لا يلغي طلب الصفحة الأولى، فإن وصل ردها بعد رد الثانية حلّت مكانها. هنا رد «الدورات والبرامج»
// يتأخر ثانيتين، فيصل بعد «المركز الإعلامي» المضغوط بعده. والضغط برمجي على رابطين بـ wire:navigate (التذييل أو الرأس)، فيمر
// بالجلب المسبق كضغط الزائر. على 1440 و390.
import { expect, test } from '@playwright/test';

const DELAY = 2000;
const link = (path) => `a[wire\\:navigate][href$="${path}"]`;

test.skip(({ viewport }) => viewport.width === 820, 'الحاسوب والجوال يكفيان');

test('the last pressed link wins when an earlier navigation answers later, and the earlier page still opens afterwards', async ({ page }) => {
    test.setTimeout(60_000);

    await page.goto('/');
    test.skip((await page.locator(link('/programs')).count()) === 0 || (await page.locator(link('/media')).count()) === 0, 'لا رابط للدورات أو للمركز الإعلامي على هذه البيئة');

    // «الدورات والبرامج» وحدها تتأخر. والطلب الملغى لا يُكمَل
    await page.route((url) => url.pathname === '/programs', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, DELAY));
        await route.continue().catch(() => {});
    });

    await page.evaluate(([first, second]) => {
        window.navigatedTo = [];
        document.addEventListener('livewire:navigated', () => window.navigatedTo.push(window.location.pathname));
        document.querySelector(first).click();
        document.querySelector(second).click();
    }, [link('/programs'), link('/media')]);

    await expect(page).toHaveURL(/\/media$/);
    const heading = (await page.locator('#main h1').first().innerText()).trim();

    // بعد مهلة رد الصفحة الأولى: صفحة الضغطة الأخيرة باقية، ولم تحل الأولى مكانها
    await page.waitForTimeout(DELAY + 1000);
    await expect(page).toHaveURL(/\/media$/);
    expect((await page.locator('#main h1').first().innerText()).trim()).toBe(heading);
    expect(await page.evaluate(() => window.navigatedTo)).toEqual(['/media']);

    // والوجهة الملغاة تُفتح حين تُضغط من جديد
    await page.evaluate((selector) => document.querySelector(selector).click(), link('/programs'));
    await expect(page).toHaveURL(/\/programs$/, { timeout: 15_000 });
    expect((await page.locator('#main h1').first().innerText()).trim()).not.toBe(heading);
});
