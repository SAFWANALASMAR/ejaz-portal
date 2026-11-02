// «البوابة بالأرقام» في آخر الرئيسية (البند H9 — docs/decisions/2026-11-01-site-stats-v2.md): الاتجاه، ولا تمرير أفقي على 390،
// والعدّ التصاعدي حتى الرقم النهائي بفواصل الآلاف، ولمن طلب تقليل الحركة الرقم النهائي والرسم كاملاً فوراً، ولقطتا 1440 و390.
// يلزمه أرقام محسوبة (ejaz:refresh-stats بعد زيارات أو تسجيل في مسابقة منشورة)، وإلا تُتخطى اختباراته لأن القسم لا يظهر فارغاً.
import { expect, test } from '@playwright/test';

const format = (value) => new Intl.NumberFormat('en-US').format(value);

async function section(page) {
    await page.goto('/');
    const stats = page.locator('[data-portal-stats]');
    test.skip((await stats.count()) === 0, 'لا أرقام محسوبة في هذه البيئة');

    return stats;
}

test('portal stats: right to left, no horizontal scroll, last before the partners', async ({ page }) => {
    const stats = await section(page);

    expect(await page.evaluate(() => document.documentElement.getAttribute('dir'))).toBe('rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'لا تمرير أفقي').toBeLessThanOrEqual(0);

    // لا قسم بعده إلا «شركاؤنا»
    const after = await stats.evaluate((element) => {
        const siblings = [];
        for (let node = element.nextElementSibling; node; node = node.nextElementSibling) {
            if (node.matches('section')) {
                siblings.push(node.getAttribute('aria-labelledby'));
            }
        }

        return siblings;
    });
    expect(after.every((id) => id === 'home-partners')).toBe(true);

    // داخل القسم لا شيء أعرض من الشاشة
    const widest = await stats.evaluate((element) => Math.max(...[...element.querySelectorAll('*')].map((node) => node.getBoundingClientRect().right)));
    expect(widest).toBeLessThanOrEqual(page.viewportSize().width + 1);
});

test('portal stats: the numbers count up to their final value with thousands separators', async ({ page }) => {
    const stats = await section(page);
    await stats.scrollIntoViewIfNeeded();

    const numbers = stats.locator('[data-count-to]');
    const count = await numbers.count();
    expect(count).toBeGreaterThan(0);

    for (let index = 0; index < count; index++) {
        const number = numbers.nth(index);
        const target = Number(await number.getAttribute('data-count-to'));
        await expect(number).toHaveText(format(target), { timeout: 5000 });
    }
});

test('portal stats: reduced motion shows the final numbers and the whole chart at once', async ({ page }, info) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const stats = await section(page);
    await stats.scrollIntoViewIfNeeded();

    const numbers = stats.locator('[data-count-to]');

    for (let index = 0; index < (await numbers.count()); index++) {
        const number = numbers.nth(index);
        // فوراً بلا انتظار للعدّ: النص كما كتبه الخادم
        expect(await number.textContent()).toBe(format(Number(await number.getAttribute('data-count-to'))));
    }

    const line = stats.locator('.portal-chart-line');

    if ((await line.count()) > 0) {
        await expect.poll(() => line.evaluate((element) => Number.parseFloat(getComputedStyle(element).strokeDashoffset))).toBe(0);
        expect(await line.evaluate((element) => getComputedStyle(element).transitionDuration)).toBe('0s');
    }

    const width = info.project.use.viewport.width;

    if (width === 1440 || width === 390) {
        await page.waitForTimeout(800);
        await stats.screenshot({ path: `docs/design/screenshots/site-stats-v2-${width}.png` });
    }
});
