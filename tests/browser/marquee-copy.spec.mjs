// نسخة الشريط المتحرك الثانية تُضغط (fix/marquee-copy-clickable — ملاحظة المدير التنفيذي على الإطلاق التجريبي، 12 نوفمبر 2026):
// كانت inert، فنصف ما يظهر من الشريط في أي لحظة لا يفتح شيئاً («مرة يضغط ومرات لا»). وتبقى مخفية عن قارئ الشاشة (aria-hidden)،
// وروابطها خارج ترتيب لوحة المفاتيح (tabindex=-1 — home.js). على 1440 و390.
import { expect, test } from '@playwright/test';

test.skip(({ viewport }) => viewport.width === 820, 'الحاسوب والجوال يكفيان');

test('a card from the second copy of the YouTube strip opens its video page', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => document.documentElement.style.setProperty('scroll-behavior', 'auto', 'important'));
    // الحركة موقوفة، فلا تتحرك البطاقة بين القياس والضغط
    await page.addStyleTag({ content: '.marquee-track { animation-play-state: paused !important; }' });

    const card = page.locator('[data-media-strip="videos"] [data-marquee-copy] a[href*="/library/videos/"]').first();
    test.skip((await card.count()) === 0, 'لا مقاطع في شريط يوتيوب على هذه البيئة');

    const href = await card.getAttribute('href');
    // البطاقة في وسط الشريط والشاشة، ثم الضغط في موضعها كما يضغط الزائر: والنقطة لها هي لا لغيرها
    const point = await card.evaluate((element) => {
        element.scrollIntoView({ block: 'center', inline: 'center', behavior: 'instant' });
        const box = element.getBoundingClientRect();
        const [x, y] = [box.left + box.width / 2, box.top + box.height / 2];

        return { x, y, hit: element.contains(document.elementFromPoint(x, y)) };
    });
    expect(point.hit, 'the copy card receives the pointer').toBe(true);

    await page.mouse.click(point.x, point.y);
    await expect(page).toHaveURL(href);
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
