// مكتبة إعجاز بأقسامها الأربعة (docs/decisions/2026-11-02-library-four-sections.md — البنود R9 وR20 وR24): البلاطات بالترتيب
// المرئية، الصوتية، الكتب والمؤلفات، الأبحاث والدراسات، والأشرطة بالترتيب نفسه لما فيه عناصر، وصفحة «الأبحاث والدراسات» تعمل،
// ولا تمرير أفقي. يعمل على العروض الثلاثة 1440 و820 و390 (ولقطة 820 من ملاحظة #119 المنخفضة):
//   docker compose run --rm browser npx playwright test --config=tests/browser/playwright.config.mjs library-four-sections.spec.mjs
// واللقطات في docs/design/screenshots/library-four-sections/<الشاشة>-<العرض>.png.
import { expect, test } from '@playwright/test';

const ORDER = ['videos', 'audio', 'books', 'research'];
const LABELS = ['المكتبة المرئية', 'المكتبة الصوتية', 'الكتب والمؤلفات', 'الأبحاث والدراسات'];
const shot = (name, testInfo) => `docs/design/screenshots/library-four-sections/${name}-${testInfo.project.use.viewport.width}.png`;

async function expectNoHorizontalScroll(page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
}

test.describe('library four sections', () => {
    test('shows the four tiles in order, the strips in the same order, and no horizontal scroll', async ({ page }, testInfo) => {
        await page.goto('/library');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

        const tiles = page.locator('[data-library-tiles] a');
        await expect(tiles).toHaveCount(4);
        await expect(tiles).toHaveText(LABELS);
        const hrefs = await tiles.evaluateAll((links) => links.map((link) => new URL(link.href).pathname));
        expect(hrefs).toEqual(ORDER.map((section) => `/library/${section}`));

        // الأشرطة لما فيه عناصر وحده، بالترتيب نفسه
        const strips = await page.locator('[data-library-strip]').evaluateAll((nodes) => nodes.map((node) => node.dataset.libraryStrip));
        expect(strips).toEqual(ORDER.filter((section) => strips.includes(section)));

        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('library', testInfo), fullPage: true });

        await page.goto('/library/research');
        await expect(page.locator('h1')).toContainText('الأبحاث والدراسات');
        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('library-research', testInfo), fullPage: true });
    });
});
