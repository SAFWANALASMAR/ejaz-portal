// الرئيسية بالنسخة 63 من التصور النهائي (أمر التصور النهائي، المرحلة ب — docs/decisions/2026-10-31-visual-refresh-2.md): البطاقات
// الخمس صفاً واحداً على الحاسوب وصفاً متحركاً على الجوال، و«خدماتنا» شريطاً متحركاً، والإعلانات والأخبار بارتفاع واحد والأخبار تصعد،
// ومقاسات الجوال (البند R12)، وتقليل الحركة. على العروض الثلاثة (playwright.config.mjs).
import { expect, test } from '@playwright/test';

async function expectRtlNoScroll(page) {
    expect(await page.evaluate(() => document.documentElement.getAttribute('dir'))).toBe('rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'لا تمرير أفقي').toBeLessThanOrEqual(0);
}

test('home: the five cards in order, one row on desktop and a moving row on mobile', async ({ page }, info) => {
    await page.goto('/');
    await expectRtlNoScroll(page);

    const section = page.locator('#home-identity');
    const order = await section.evaluate((element) => [...element.querySelectorAll('[data-identity-card]')]
        .filter((card) => ! card.closest('[data-marquee-copy]'))
        .map((card) => card.dataset.identityCard));
    expect(order.slice(0, 2)).toEqual(['vision', 'goals']);
    expect(order).toContain('principles');
    expect(order.indexOf('principles')).toBeGreaterThan(order.indexOf('goals'));

    const boxes = await section.evaluate((element) => [...element.querySelectorAll('[data-identity-card]')]
        .filter((card) => ! card.closest('[data-marquee-copy]'))
        .map((card) => {
            const box = card.getBoundingClientRect();

            return { top: Math.round(box.top), height: Math.round(box.height), width: Math.round(box.width) };
        }));

    if (info.project.name === 'desktop') {
        // الخمس صف واحد بعرض واحد (توجيه المدير التنفيذي، 3 نوفمبر 2026 — director-home-2026-11-03.spec.mjs)، بدل ثلاث ثم اثنتين
        expect(new Set(boxes.map((box) => box.top)).size, 'صف واحد').toBe(1);
        expect(Math.abs(boxes[3].width - boxes[0].width), 'عرض واحد').toBeLessThanOrEqual(2);
        for (const box of boxes) {
            expect(Math.abs(box.height - 168)).toBeLessThanOrEqual(2);
        }
        await expect(section.locator('[data-ticker-toggle="home-cards-track"]')).toBeHidden();
    } else {
        // الرؤية بعرض كامل، والبقية في الشريط تحتها
        expect(boxes[1].top).toBeGreaterThan(boxes[0].top);
        await expect(section.locator('#home-cards-track')).toHaveAttribute('data-direction', 'horizontal');
    }
});

test('home: services move in one row, and the mobile sizes follow R12', async ({ page }, info) => {
    await page.goto('/');
    const services = page.locator('#home-services-track');
    await expect(services).toBeVisible();
    expect(await services.locator('[data-service-card]').count()).toBeGreaterThanOrEqual(3);

    const row = await services.evaluate((element) => new Set([...element.querySelectorAll('[data-service-card]')].map((card) => Math.round(card.getBoundingClientRect().top))).size);
    expect(row, 'صف واحد').toBe(1);

    if (info.project.name === 'mobile') {
        const sizes = await page.evaluate(() => ({
            heading: getComputedStyle(document.querySelector('#home-shortcuts h2')).fontSize,
            title: getComputedStyle(document.querySelector('.service-card-name')).fontSize,
            text: getComputedStyle(document.querySelector('.service-card-text')).fontSize,
            icon: document.querySelector('.home-card-icon').getBoundingClientRect().width,
        }));
        expect(sizes).toEqual({ heading: '24px', title: '19px', text: '14px', icon: 38 });
    }
});

test('home: announcements and news side by side with the same height, the news rising', async ({ page }, info) => {
    await page.goto('/');
    const news = page.locator('#home-news-track');
    test.skip((await news.count()) === 0 || (await page.locator('#home-announcements').count()) === 0, 'لا إعلانات أو لا أخبار');

    await expect(news).toHaveAttribute('data-direction', 'vertical');

    if (info.project.name === 'desktop') {
        // بعد حركة الظهور: عمود الأخبار يتأخر عنها 120 ملّي ثانية، فقياسه أثناءها يزيح قمته (ملاحظة ٣٠)
        await page.locator('#home-news').scrollIntoViewIfNeeded();
        await page.waitForTimeout(1500);
        const [announcements, feed] = await page.evaluate(() => ['#home-announcements', '#home-news'].map((id) => {
            const column = document.querySelector(id).closest('[data-reveal]').getBoundingClientRect();

            return { top: Math.round(column.top), height: Math.round(column.height) };
        }));
        expect(Math.abs(feed.top - announcements.top)).toBeLessThanOrEqual(3);
        expect(Math.abs(feed.height - announcements.height)).toBeLessThanOrEqual(2);
    }

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    const animation = await page.locator('#home-news-track .marquee-track').evaluate((element) => getComputedStyle(element).animationName);
    expect(animation).toBe('none');
    await expect(page.locator('#home-news-track [data-marquee-copy]')).toBeHidden();
});
