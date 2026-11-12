// نسخة الشريط المتحرك الثانية تُضغط (fix/marquee-copy-clickable — ملاحظة المدير التنفيذي على الإطلاق التجريبي، 12 نوفمبر 2026):
// كانت inert، فنصف ما يظهر من الشريط في أي لحظة لا يفتح شيئاً («مرة يضغط ومرات لا»). وتبقى مخفية عن قارئ الشاشة (aria-hidden)،
// وروابطها خارج ترتيب لوحة المفاتيح (tabindex=-1 — home.js). على 1440 و390.
import { expect, test } from '@playwright/test';

test.skip(({ viewport }) => viewport.width === 820, 'الحاسوب والجوال يكفيان');

test('a card from the second copy of the YouTube strip opens its video page', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => document.documentElement.style.setProperty('scroll-behavior', 'auto', 'important'));
    const strip = page.locator('[data-media-strip="videos"]');
    test.skip((await strip.locator('[data-marquee-copy] a[href*="/library/videos/"]').count()) === 0, 'لا مقاطع في شريط يوتيوب على هذه البيئة');

    await strip.evaluate((element) => element.scrollIntoView({ block: 'center', behavior: 'instant' }));
    // الرأس الثابت يصغر بعد التمرير فتنزاح الصفحة: يُقاس الموضع بعد استقرارها
    await page.waitForTimeout(800);

    // المسار موقوف وقد حمل أول بطاقة من النسخة الثانية إلى وسط الشريط، كما تبلغه الحركة بعد ثوانٍ (أياً كان اتجاهها وعرض الشاشة)
    await strip.evaluate((root) => {
        const track = root.querySelector('.marquee-track');
        track.style.setProperty('animation', 'none', 'important');
        track.style.transform = 'none';
        const card = root.querySelector('[data-marquee-copy] a[href*="/library/videos/"]').getBoundingClientRect();
        const box = root.getBoundingClientRect();
        track.style.transform = `translateX(${box.left + box.width / 2 - (card.left + card.width / 2)}px)`;
    });

    // أول بطاقة من النسخة الثانية تستقبل الضغط في وسطها (لها هي لا لغيرها)
    const target = await strip.evaluate((root) => {
        for (const card of root.querySelectorAll('[data-marquee-copy] a[href*="/library/videos/"]')) {
            const box = card.getBoundingClientRect();
            const [x, y] = [box.left + box.width / 2, box.top + box.height / 2];

            if (x > 0 && x < window.innerWidth && y > 0 && y < window.innerHeight && card.contains(document.elementFromPoint(x, y))) {
                return { x, y, href: card.href };
            }
        }

        return null;
    });
    expect(target, 'a copy card receives the pointer').not.toBeNull();

    await page.mouse.click(target.x, target.y);
    await expect(page).toHaveURL(target.href);
});

test('the copies stay hidden from screen readers and out of the keyboard order, and are not inert', async ({ page }) => {
    await page.goto('/');

    const copies = await page.evaluate(() => [...document.querySelectorAll('[data-marquee-copy]')].map((copy) => ({
        hidden: copy.getAttribute('aria-hidden') === 'true',
        inert: copy.hasAttribute('inert'),
        tabbable: [...copy.querySelectorAll('a, button, input, select, textarea, summary, [tabindex]')].filter((element) => element.tabIndex >= 0).length,
    })));

    expect(copies.length).toBeGreaterThan(0);
    copies.forEach((copy) => expect(copy).toEqual({ hidden: true, inert: false, tabbable: 0 }));
});
