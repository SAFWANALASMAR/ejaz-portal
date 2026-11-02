// «إنجازاتنا» (docs/decisions/2026-11-02-achievements.md): الصفحة العامة بخطها الزمني، والخط الزمني وعدد إنجازات العام في بوابة
// المجلس والجمعية العمومية، من اليمين بلا تمرير أفقي. على عرضي 1440 و390 (المشروعان desktop وmobile):
//   docker compose run --rm -e DEMO_CREDENTIALS="$creds" browser npx playwright test --config=tests/browser/playwright.config.mjs achievements.spec.mjs --project=desktop --project=mobile
// والبوابة تلزمها جلسة المستخدم التجريبي (DEMO_CREDENTIALS) عضواً في الجمعية العمومية في البيئة المحلية، وإلا يُتخطى اختبارها.
// والخط الزمني يُرى بإنجاز منشور واحد على الأقل. واللقطات في docs/design/screenshots/achievements/<الشاشة>-<العرض>.png.
import { existsSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { AUTH_STATE } from './global-setup.mjs';

const shot = (name, testInfo) => `docs/design/screenshots/achievements/${name}-${testInfo.project.use.viewport.width}.png`;

async function expectNoHorizontalScroll(page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
}

test.describe('achievements', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(!['desktop', 'mobile'].includes(testInfo.project.name), '1440 و390 وحدهما');
    });

    test('the public page shows the published timeline from the right, linked from the footer', async ({ page }, testInfo) => {
        await page.goto('/achievements');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('h1')).toContainText('إنجازاتنا');
        await expect(page.locator('[data-achievements-timeline], [data-empty-state]').first()).toBeVisible();
        await expect(page.locator('[data-footer-box="links"] a[href$="/achievements"]')).toHaveCount(1);

        // مرفق المنشور برابط موقّع، لا رابط تخزين
        for (const href of await page.locator('[data-achievement-attachment]').evaluateAll((links) => links.map((link) => link.href))) {
            expect(href).toContain('signature=');
        }

        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('achievements', testInfo), fullPage: true });
    });

    test.describe('board portal', () => {
        test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS');
        test.use({ storageState: AUTH_STATE });

        test('the portal shows the year count and the timeline without horizontal scroll', async ({ page }, testInfo) => {
            await page.goto('/assembly');
            const section = page.locator('[data-board-achievements]');
            test.skip((await section.count()) === 0, 'المستخدم التجريبي ليس عضواً في الجمعية العمومية');

            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            await expect(section.locator('[data-achievements-this-year]')).toContainText('عدد الإنجازات هذا العام');
            await expect(section.locator('[data-achievements-timeline], [data-achievements-empty]').first()).toBeVisible();
            await expect(section.locator('[data-achievement-add]')).toHaveCount(0);

            await expectNoHorizontalScroll(page);
            await section.scrollIntoViewIfNeeded();
            await page.screenshot({ path: shot('board-portal', testInfo), fullPage: true });
        });
    });
});
