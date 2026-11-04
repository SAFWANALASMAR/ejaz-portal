// «منشوراتنا» (docs/decisions/2026-11-04-social-posts.md): الصفحة العامة والبلاطة في المركز الإعلامي، من اليمين بلا تمرير أفقي، وروابط
// المنشورات في تبويب جديد بـ noopener noreferrer، ولا إطار ولا سكربت من المنصات. على العروض الثلاثة 1440 و820 و390، والبلاطات على 1280 أيضاً:
//   docker compose exec -T app php artisan db:seed --class=DemoSocialPostsSeeder
//   docker compose run --rm --no-deps -e VITE_HOST=none browser npx playwright test --config=tests/browser/playwright.config.mjs social-posts.spec.mjs
// واللقطات في docs/design/screenshots/social-posts/<الشاشة>-<العرض>.png.
import { expect, test } from '@playwright/test';

const shot = (name, testInfo) => `docs/design/screenshots/social-posts/${name}-${testInfo.project.use.viewport.width}.png`;

async function expectNoHorizontalScroll(page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
}

test.describe('social posts', () => {
    test.beforeEach(async ({ page }, testInfo) => {
        // بلا حركة الظهور، فتُلتقط البطاقات كاملة لا في منتصف ظهورها
        await page.emulateMedia({ reducedMotion: 'reduce' });
    });

    test('the page lists the posts with safe outbound links, filters by platform, and embeds nothing', async ({ page }, testInfo) => {
        await page.goto('/media/social');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('h1')).toContainText('منشوراتنا');
        await expect(page.locator('[data-social-filter="platform"]')).toBeVisible();
        await expect(page.locator('[data-social-post], [data-empty-state]').first()).toBeVisible();

        for (const link of await page.locator('[data-social-post-link]').all()) {
            await expect(link).toHaveAttribute('target', '_blank');
            await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
            expect(await link.getAttribute('href')).toMatch(/^https:\/\//);
        }

        await expect(page.locator('main iframe')).toHaveCount(0);
        const origin = new URL(page.url()).origin;
        const foreign = await page.locator('script[src]').evaluateAll((scripts, own) => scripts.map((script) => script.src).filter((src) => !src.startsWith(own)), origin);
        expect(foreign).toEqual([]);

        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('media-social', testInfo), fullPage: true });

        await page.locator('[data-social-filter="platform"] a', { hasText: 'إنستغرام' }).click();
        await expect(page).toHaveURL(/platform=instagram/, { timeout: 30_000 });
        await expectNoHorizontalScroll(page);
    });

    // ملاحظة صفوان على #165 (4 نوفمبر 2026): «شريط الأيقونات اجعله أجمل» — resources/css/social-filters.css
    test('the filter strip: equal pills with an icon tile per platform, a dark active pill, a smaller labelled type strip, a scrolling row on the phone', async ({ page }, testInfo) => {
        // واتساب آخر المنصات، فعلى الجوال تكون خارج الشريط لولا توسيطها
        await page.goto('/media/social?platform=whatsapp');
        const pills = page.locator('[data-social-filter="platform"] [data-social-filter-pill]');
        await expect(pills).toHaveCount(9);
        await expect(page.locator('[data-social-filter="platform"] [data-social-filter-pill] > [data-icon-tile]')).toHaveCount(9);

        const heights = await pills.evaluateAll((items) => items.map((item) => Math.round(item.getBoundingClientRect().height)));
        expect(new Set(heights).size).toBe(1);

        const active = page.locator('[data-social-filter="platform"] [aria-current="page"]');
        await expect(active).toContainText('واتساب');
        const look = await active.evaluate((item) => ({ border: getComputedStyle(item).borderTopColor, shadow: getComputedStyle(item).boxShadow, colour: getComputedStyle(item).color }));
        expect(look.border).toBe('rgb(142, 197, 240)');
        expect(look.shadow).toContain('rgba(142, 197, 240');
        expect(look.colour).toBe('rgb(255, 255, 255)');

        // النوع شريط أصغر منفصل بعنوانه
        await expect(page.locator('[data-social-filter="kind"] [data-social-filter-label]')).toHaveText('النوع');
        const kindHeight = await page.locator('[data-social-filter="kind"] [data-social-filter-pill]').first().evaluate((item) => item.getBoundingClientRect().height);
        expect(kindHeight).toBeLessThan(heights[0]);

        await expectNoHorizontalScroll(page);
        const track = page.locator('[data-social-filter="platform"] ul');

        if (testInfo.project.name === 'mobile') {
            // صف واحد يُمرَّر داخله، بتلاشٍ عند حافته، والنشطة ظاهرة فيه
            const row = await track.evaluate((list) => {
                const style = getComputedStyle(list);
                const tops = [...list.children].map((item) => Math.round(item.getBoundingClientRect().top));
                const box = list.getBoundingClientRect();
                const current = list.querySelector('[aria-current="page"]').getBoundingClientRect();

                return {
                    wrap: style.flexWrap,
                    overflow: style.overflowX,
                    mask: style.maskImage || style.webkitMaskImage,
                    scrolls: list.scrollWidth > list.clientWidth,
                    oneRow: new Set(tops).size === 1,
                    activeInView: current.left >= box.left - 1 && current.right <= box.right + 1,
                };
            });
            expect(row).toMatchObject({ wrap: 'nowrap', overflow: 'auto', scrolls: true, oneRow: true, activeInView: true });
            expect(row.mask).toContain('linear-gradient');
        }

        if (testInfo.project.name === 'desktop') {
            // ارتفاع بسيط عند المرور، ويُلغى لمن طلب تقليل الحركة
            const pill = pills.nth(1);
            await page.emulateMedia({ reducedMotion: 'no-preference' });
            await pill.hover();
            await expect.poll(() => pill.evaluate((item) => getComputedStyle(item).transform)).not.toBe('none');

            await page.mouse.move(5, 5);
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await pill.hover();
            expect(await pill.evaluate((item) => getComputedStyle(item).transform)).toBe('none');
        }
    });

    test('the media center shows the «منشوراتنا» tile', async ({ page }, testInfo) => {
        await page.goto('/media');
        const tile = page.locator('[data-media-tiles] a[href$="/media/social"]');
        await expect(tile).toBeVisible();
        await expect(tile).toContainText('منشوراتنا');

        await expectNoHorizontalScroll(page);
        await page.locator('[data-media-tiles]').screenshot({ path: shot('media-tiles', testInfo) });

        // البلاطات الخمس في صف واحد على أضيق حاسوب: لا يلتف اسم ولا يفيض
        if (testInfo.project.name === 'desktop') {
            await page.setViewportSize({ width: 1280, height: 900 });
            await expectNoHorizontalScroll(page);
            const tops = await page.locator('[data-media-tiles] > li').evaluateAll((items) => items.map((item) => Math.round(item.getBoundingClientRect().top)));
            expect(new Set(tops).size).toBe(1);
            await page.locator('[data-media-tiles]').screenshot({ path: 'docs/design/screenshots/social-posts/media-tiles-1280.png' });
        }
    });
});
