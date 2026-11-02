// «قراءاتي» (docs/decisions/2026-11-01-my-reading.md): القارئ يبلّغ الصفحات التي رُسمت لمسجّل الدخول، والصفحة الأولى فوراً،
// ثم تظهر في «قراءاتي» بزر «أكمل» إلى الصفحة المحفوظة، بلا تمرير أفقي على أي عرض.
// يلزمه المستخدم التجريبي (DEMO_CREDENTIALS) وكتاب منشور «للقراءة فقط» من صفحتين فأكثر معرّفه في READ_ONLY_SLUG (README)،
// وإلا يُتخطى. واللقطات في docs/design/screenshots/my-reading/<الشاشة>-<العرض>.png.
import { existsSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { AUTH_STATE } from './global-setup.mjs';

const slug = process.env.READ_ONLY_SLUG;
const shot = (name, testInfo) => `docs/design/screenshots/my-reading/${name}-${testInfo.project.use.viewport.width}.png`;

async function expectNoHorizontalScroll(page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
}

test.describe('my reading', () => {
    test.skip(!slug, 'READ_ONLY_SLUG غير مضبوط');
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS');
    test.use({ storageState: AUTH_STATE });

    test('the reader reports the pages it renders, and my reading continues from the saved page', async ({ page }, testInfo) => {
        const reports = [];
        page.on('request', (request) => {
            if (request.method() === 'POST' && request.url().endsWith(`/library/books/${slug}/progress`)) {
                reports.push(request.postDataJSON());
            }
        });

        // البلاغ الأول فوراً بالصفحة الأولى
        const first = page.waitForResponse((response) => response.url().endsWith(`/library/books/${slug}/progress`));
        await page.goto(`/library/books/${slug}`);
        const reader = page.locator('[data-pdf-reader]');
        await expect(reader).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        const firstResponse = await first;
        expect(firstResponse.status()).toBe(200);
        expect(await firstResponse.json()).toEqual({ pages: [1] });
        expect(reports[0]).toMatchObject({ pages: [1], page: 1 });

        // الصفحة الثانية، ثم إخفاء الصفحة يرسل ما تجمّع (keepalive)
        await reader.locator('[data-pdf-next]').click();
        await expect(reader.locator('[data-pdf-page]')).toHaveValue('2');
        await expect(reader).toHaveAttribute('data-state', 'ready');
        const second = page.waitForResponse((response) => response.url().endsWith(`/library/books/${slug}/progress`));
        await page.evaluate(() => {
            Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await second;
        expect(reports.at(-1)).toMatchObject({ pages: [2], page: 2 });

        // في «قراءاتي»: الكتاب بزر «أكمل» إلى الصفحة الثانية، بلا تمرير أفقي
        await page.goto('/account/my-reading');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        const continueLink = page.locator(`[data-reading-item] a[href*="/library/books/${slug}?resume=2"]`);
        await expect(continueLink).toBeVisible();
        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('account-my-reading', testInfo), fullPage: true });

        // «أكمل» يفتح القارئ على الصفحة المحفوظة
        await continueLink.click();
        await expect(page.locator('[data-pdf-reader]')).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        await expect(page.locator('[data-pdf-page]')).toHaveValue('2');

        // والقسم في رئيسية اللوحة
        await page.goto('/account');
        await expect(page.locator('[data-account-my-reading]')).toBeVisible();
        await expectNoHorizontalScroll(page);
        await page.locator('[data-account-my-reading]').screenshot({ path: shot('account-dashboard-my-reading', testInfo) });
    });
});
