// «المتجر والتبرعات» (docs/decisions/2026-11-04-store-page.md): الصفحة من اليمين بلا تمرير أفقي، وبطاقاتها بأزرار إلى متجر سلة في
// تبويب جديد بـ noopener noreferrer، وزر الرأس إليها (الصف الأول من 1024، وقائمة ☰ دونها). على العروض الثلاثة 1440 و820 و390:
//   docker compose exec -T app php artisan db:seed --class=DemoStoreItemsSeeder
//   docker compose run --rm --no-deps vite npm run build
//   docker compose run --rm --no-deps -e VITE_HOST=none browser npx playwright test --config=tests/browser/playwright.config.mjs store-page.spec.mjs
// واللقطات في docs/design/screenshots/store-page/store-<العرض>.png، والحالة الفارغة store-empty-<العرض>.png (بلا عناصر منشورة، و-e STORE_EMPTY_SHOT=1).
import { expect, test } from '@playwright/test';

const shot = (name, testInfo) => `docs/design/screenshots/store-page/${name}-${testInfo.project.use.viewport.width}.png`;

async function expectNoHorizontalScroll(page) {
    const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
    expect(scroll).toBe(client);
}

test.describe('store page', () => {
    test.beforeEach(async ({ page }) => {
        // بلا حركة الظهور، فتُلتقط البطاقات كاملة لا في منتصف ظهورها
        await page.emulateMedia({ reducedMotion: 'reduce' });
    });

    test('shows the cards with outbound buttons in a new tab, the store button, and no horizontal scroll', async ({ page }, testInfo) => {
        await page.goto('/store');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('h1')).toContainText('المتجر والتبرعات');

        const cards = page.locator('[data-store-item]');
        expect(await cards.count()).toBeGreaterThan(0);
        await expect(cards.first()).toBeVisible();

        // «الكل» دائماً، وتبويب لكل تصنيف فيه عنصر منشور وحده
        const categories = new Set(await cards.evaluateAll((items) => items.map((item) => item.dataset.storeItem)));
        await expect(page.locator('nav.tabs-bar a')).toHaveCount(1 + categories.size);
        await expect(page.locator('nav.tabs-bar a[href*="category=publication"]')).toHaveCount(categories.has('publication') ? 1 : 0);

        for (const link of [...(await page.locator('[data-store-buy]').all()), page.locator('[data-store-enter]')]) {
            await expect(link).toHaveAttribute('target', '_blank');
            await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
            expect(await link.getAttribute('wire:navigate')).toBeNull();
            expect(await link.getAttribute('href')).toMatch(/^https:\/\//);
        }

        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('store', testInfo), fullPage: true });
    });

    // الحالة الفارغة المرسومة حين لا عنصر منشور (لقطتها بعد إخفاء العناصر التجريبية محلياً: STORE_EMPTY_SHOT=1)
    test('without published items the page draws the empty state', async ({ page }, testInfo) => {
        test.skip(!process.env.STORE_EMPTY_SHOT, 'needs a store without published items');
        await page.goto('/store');
        await expect(page.locator('[data-empty-state]')).toBeVisible();
        await expect(page.locator('nav.tabs-bar a')).toHaveCount(1);
        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('store-empty', testInfo), fullPage: true });
    });

    test('the header leads to the store page: first row from 1024, inside the menu below it', async ({ page }, testInfo) => {
        await page.goto('/');

        if (testInfo.project.name === 'desktop') {
            const button = page.locator('[data-header-store]');
            await expect(button).toBeVisible();
            await expect(button.locator('[data-icon-tile]')).toBeVisible();
            await expect(button).toHaveAttribute('aria-label', 'المتجر والتبرعات');
            await expect(button).toHaveAttribute('title', 'المتجر والتبرعات');
            await button.click();
        } else {
            await expect(page.locator('[data-header-store]')).toBeHidden();
            await page.locator('[data-toggle="mobile-menu"]').click();
            const entry = page.locator('[data-menu-store]');
            await expect(entry).toBeVisible();
            await expect(entry).toHaveText('المتجر والتبرعات');
            await expect(entry.locator('[data-icon-tile]')).toBeVisible();
            await entry.click();
        }

        await expect(page).toHaveURL(/\/store$/, { timeout: 30_000 });
        await expect(page.locator('main h1').first()).toContainText('المتجر والتبرعات', { timeout: 30_000 });
    });
});
