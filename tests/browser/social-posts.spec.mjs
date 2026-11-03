// «منشوراتنا» (docs/decisions/2026-11-04-social-posts.md): الصفحة العامة والبلاطة في المركز الإعلامي، من اليمين بلا تمرير أفقي، وروابط
// المنشورات في تبويب جديد بـ noopener noreferrer، ولا إطار ولا سكربت من المنصات. على عرضي 1440 و390 (المشروعان desktop وmobile):
//   docker compose exec -T app php artisan db:seed --class=DemoSocialPostsSeeder
//   docker compose run --rm --no-deps -e VITE_HOST=none browser npx playwright test --config=tests/browser/playwright.config.mjs social-posts.spec.mjs --project=desktop --project=mobile
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
        test.skip(!['desktop', 'mobile'].includes(testInfo.project.name), '1440 و390 وحدهما');
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

    test('the media center shows the «منشوراتنا» tile', async ({ page }, testInfo) => {
        await page.goto('/media');
        const tile = page.locator('[data-media-tiles] a[href$="/media/social"]');
        await expect(tile).toBeVisible();
        await expect(tile).toContainText('منشوراتنا');

        await expectNoHorizontalScroll(page);
        await page.locator('[data-media-tiles]').screenshot({ path: shot('media-tiles', testInfo) });
    });
});
