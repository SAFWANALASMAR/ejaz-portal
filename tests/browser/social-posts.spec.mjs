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

        // «يكبر شوية» (صفوان، 4 نوفمبر 14:14): 48 بكسل على الحاسوب و44 على الجوال حداً للمس، ومربع الشعار 28، والخط 16
        const desktop = testInfo.project.name === 'desktop';
        expect(heights[0]).toBe(desktop ? 48 : 44);
        const tile = await pills.first().locator('[data-icon-tile]').evaluate((item) => Math.round(item.getBoundingClientRect().width));
        expect(tile).toBe(28);
        expect(await pills.first().evaluate((item) => getComputedStyle(item).fontSize)).toBe('16px');

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
        expect(Math.round(kindHeight)).toBe(desktop ? 42 : 40);

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

        if (desktop) {
            // صف واحد على 1440، وعلى عرض أضيق يلتف وكل صف في الوسط
            const rows = (list) => {
                const box = list.getBoundingClientRect();
                const lines = new Map();
                for (const item of list.children) {
                    const rect = item.getBoundingClientRect();
                    const top = Math.round(rect.top);
                    const line = lines.get(top) ?? { left: Infinity, right: -Infinity };
                    lines.set(top, { left: Math.min(line.left, rect.left), right: Math.max(line.right, rect.right) });
                }

                return [...lines.values()].map((line) => ({ start: Math.round(box.right - line.right), end: Math.round(line.left - box.left) }));
            };
            expect(await track.evaluate(rows)).toHaveLength(1);
            await page.setViewportSize({ width: 820, height: 900 });
            const wrapped = await track.evaluate(rows);
            expect(wrapped.length).toBeGreaterThan(1);
            for (const line of wrapped) {
                expect(Math.abs(line.start - line.end)).toBeLessThanOrEqual(2);
            }
            await expectNoHorizontalScroll(page);
            await page.setViewportSize({ width: 1440, height: 900 });

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

    // «لما نضغط على الأيقونة تتحرك» (صفوان، 4 نوفمبر 16:06): انضغاط ثم ارتداد، وقفزة مربع الشعار، ولون بانسياب، وتوسيط بنعومة
    test('the filter pills move when pressed and chosen, and keep still for reduced motion', async ({ page }, testInfo) => {
        const scale = (locator) => locator.evaluate((item) => {
            const transform = getComputedStyle(item).transform;

            return transform === 'none' ? 1 : Math.round(new DOMMatrix(transform).a * 100) / 100;
        });

        await page.emulateMedia({ reducedMotion: 'no-preference' });
        await page.goto('/media/social');
        const pills = page.locator('[data-social-filter="platform"] [data-social-filter-pill]');
        const youtube = pills.filter({ hasText: 'يوتيوب' });
        await youtube.scrollIntoViewIfNeeded();
        expect(await youtube.evaluate((item) => getComputedStyle(item, '::before').transitionDuration)).toBe('0.3s');

        // الضغط: تنضغط إلى 0.94، وترجع عند الرفع
        const box = await youtube.boundingBox();
        await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
        await page.mouse.down();
        await expect.poll(() => scale(youtube)).toBe(0.94);
        await page.mouse.up();

        // الاختيار: التصفية كما هي، والنشطة الجديدة يقفز مربعها، وعلى الجوال تتوسط الشريط
        await expect(page).toHaveURL(/platform=youtube/, { timeout: 30_000 });
        const active = page.locator('[data-social-filter="platform"] [aria-current="page"]');
        await expect(active).toContainText('يوتيوب');
        await expect(page.locator('[data-social-post]').first()).toBeVisible();
        expect(await active.locator('[data-icon-tile]').evaluate((tile) => getComputedStyle(tile).animationName)).toBe('social-tile-pop');

        if (testInfo.project.name === 'mobile') {
            const centred = () => page.locator('[data-social-filter="platform"] ul').evaluate((list) => {
                const box = list.getBoundingClientRect();
                const pill = list.querySelector('[aria-current="page"]').getBoundingClientRect();

                return Math.abs(pill.left + pill.width / 2 - (box.left + box.width / 2)) <= 4;
            });
            await expect.poll(centred).toBe(true);
            // ويبقى في الوسط بعد أن يستقر التمرير (لا يعيده الالتقاط إلى حافة الشريط)
            await page.waitForTimeout(1000);
            expect(await centred()).toBe(true);
        }

        // الرجوع يعيد «الكل» نشطة كما كانت
        await page.goBack();
        await expect(page).not.toHaveURL(/platform=/, { timeout: 30_000 });
        await expect(active).toHaveCount(1);
        await expect(active).not.toContainText('يوتيوب');

        // لمن طلب تقليل الحركة: لا انضغاط ولا قفزة ولا انتقال
        await page.emulateMedia({ reducedMotion: 'reduce' });
        const instagram = pills.filter({ hasText: 'إنستغرام' });
        expect(await instagram.evaluate((item) => getComputedStyle(item, '::before').transitionDuration)).toBe('0s');
        const second = await instagram.boundingBox();
        await page.mouse.move(second.x + second.width / 2, second.y + second.height / 2);
        await page.mouse.down();
        expect(await scale(instagram)).toBe(1);
        await page.mouse.up();
        await expect(page).toHaveURL(/platform=instagram/, { timeout: 30_000 });
        await expect(active).toContainText('إنستغرام');
        expect(await active.locator('[data-icon-tile]').evaluate((tile) => getComputedStyle(tile).animationName)).toBe('none');
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
