// قارئ المكتبة «للقراءة فقط — بلا تحميل» (docs/decisions.md — 30 أكتوبر 2026): الصفحة الأولى ترسم في <canvas>،
// والتقليب ورقم الصفحة والانتقال والتكبير تعمل، ولا زر تنزيل ولا تمرير أفقي على أي عرض.
// يلزمه كتاب منشور للقراءة فقط معرّفه في READ_ONLY_SLUG (المجموعة التجريبية في README، لا في البذور)، وإلا يُتخطى.
// ووثيقة حوكمة «للاطلاع فقط» منشورة في GOVERNANCE_VIEW_ONLY_SLUG لاختبارها، اختيارياً.
// واللقطات في docs/design/screenshots/library-read-only/<SHOT_PREFIX>-<الشاشة>-<العرض>.png (after افتراضياً).
import { expect, test } from '@playwright/test';

const slug = process.env.READ_ONLY_SLUG;
const prefix = process.env.SHOT_PREFIX ?? 'after';
const shot = (name, testInfo) => `docs/design/screenshots/library-read-only/${prefix}-${name}-${testInfo.project.use.viewport.width}.png`;

async function expectNoHorizontalScroll(page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
}

test.describe('library read-only reader', () => {
    test.skip(!slug, 'READ_ONLY_SLUG غير مضبوط');

    test('lists the book and shows it in the canvas reader without a download button', async ({ page }, testInfo) => {
        await page.goto('/library/books');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('library-section', testInfo), fullPage: true });

        await page.goto(`/library/books/${slug}`);

        if (prefix === 'before') {
            await page.screenshot({ path: shot('library-item', testInfo), fullPage: true });

            return;
        }

        const reader = page.locator('[data-pdf-reader]');
        await expect(reader).toBeVisible();
        await expect(page.locator(`a[href$="/library/books/${slug}/download"]`)).toHaveCount(0);
        await expect(page.locator('iframe')).toHaveCount(0);

        // الصفحة الأولى مرسومة، والعدد معروف
        await expect(reader).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        const total = Number(await reader.getAttribute('data-pages'));
        expect(total).toBeGreaterThan(1);
        const canvas = reader.locator('canvas');
        expect(await canvas.evaluate((element) => element.width)).toBeGreaterThan(0);

        // التالية، ثم الانتقال إلى الأخيرة برقمها
        await reader.locator('[data-pdf-next]').click();
        await expect(reader.locator('[data-pdf-page]')).toHaveValue('2');
        await reader.locator('[data-pdf-page]').fill(String(total));
        await reader.locator('[data-pdf-page]').press('Enter');
        await expect(reader.locator('[data-pdf-page]')).toHaveValue(String(total));
        await expect(reader.locator('[data-pdf-next]')).toBeDisabled();

        // التكبير يكبّر اللوحة، والقائمة السياقية ممنوعة على القارئ
        const before = await canvas.evaluate((element) => element.getBoundingClientRect().width);
        await reader.locator('[data-pdf-zoom-in]').click();
        await expect.poll(() => canvas.evaluate((element) => element.getBoundingClientRect().width)).toBeGreaterThan(before);
        const prevented = await canvas.evaluate((element) => !element.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true })));
        expect(prevented).toBe(true);

        await reader.locator('[data-pdf-zoom-reset]').click();
        await reader.locator('[data-pdf-page]').fill('1');
        await reader.locator('[data-pdf-page]').press('Enter');
        await expect(reader.locator('[data-pdf-page]')).toHaveValue('1');
        await expectNoHorizontalScroll(page);
        await page.screenshot({ path: shot('library-item', testInfo), fullPage: true });
    });

    // من القسم إلى الكتاب بالتنقّل السريع، والنقر المتتابع لا يُسقط القارئ، والرجوع والتقدّم يعيدانه سليماً
    test('survives quick clicks and fast navigation back and forth', async ({ page }) => {
        test.skip(prefix === 'before', 'القارئ بعد البناء وحده');

        await page.goto('/library/books');
        await page.locator(`a[href$="/library/books/${slug}"]`).first().click();
        const reader = page.locator('[data-pdf-reader]');
        await expect(reader).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });

        await reader.locator('[data-pdf-next]').dblclick();
        await reader.locator('[data-pdf-zoom-in]').click();
        await expect(reader.locator('[data-pdf-page]')).toHaveValue('3');
        await expect(reader).toHaveAttribute('data-state', 'ready');

        await page.goBack();
        await expect(page.locator('[data-pdf-reader]')).toHaveCount(0);
        await page.goForward();
        await expect(page.locator('[data-pdf-reader]')).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        await page.locator('[data-pdf-next]').click();
        await expect(page.locator('[data-pdf-page]')).toHaveValue('2');
        await expect(page.locator('[data-pdf-reader]')).toHaveAttribute('data-state', 'ready');
    });

    // حماية الملف من برامج التحميل (docs/decisions/2026-11-10-feat-reader-download-protection.md): القارئ يرسم صفحة فعلاً من
    // بايتات مخلوطة بنوع octet-stream، والرابط نفسه بلا ترويسة القارئ أو برمز مستعمل 404
    test('draws a page from the mixed file, while the same link fails without the reader header or with a spent ticket', async ({ page }) => {
        test.skip(prefix === 'before', 'القارئ بعد البناء وحده');

        const filePath = `/library/books/${slug}/file`;
        const fileResponse = page.waitForResponse((response) => new URL(response.url()).pathname === filePath);
        await page.goto(`/library/books/${slug}`);
        const response = await fileResponse;

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toBe('application/octet-stream');
        expect(response.headers()['content-disposition']).toBeUndefined();
        expect(new URL(response.url()).search).toMatch(/^\?t=[0-9a-f]{64}$/);
        const head = (await response.body()).subarray(0, 4).toString('latin1');
        expect(head).not.toBe('%PDF');

        const reader = page.locator('[data-pdf-reader]');
        await expect(reader).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        expect(Number(await reader.getAttribute('data-pages'))).toBeGreaterThan(1);
        // الرمز والمفتاح لا يبقيان في الصفحة بعد استعمالهما
        expect(await reader.getAttribute('data-pdf-ticket')).toBeNull();
        expect(await reader.getAttribute('data-pdf-key')).toBeNull();

        // اللوحة فيها رسم فعلاً (بكسلات داكنة)، لا لوحة فارغة
        const inked = await reader.locator('canvas').evaluate((canvas) => {
            const { data } = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
            let dark = 0;
            for (let i = 0; i < data.length; i += 4) {
                if (data[i] < 128 && data[i + 1] < 128 && data[i + 2] < 128) {
                    dark++;
                }
            }

            return dark;
        });
        expect(inked).toBeGreaterThan(100);

        // برنامج التحميل يطلب الرابط الملتقط بكوكي الجلسة نفسها: بلا الترويسة، وبها برمز مستعمل، وبلا رمز، 404
        const captured = response.url();
        expect((await page.request.get(captured)).status()).toBe(404);
        expect((await page.request.get(captured, { headers: { 'X-Ejaz-Reader': '1' } })).status()).toBe(404);
        expect((await page.request.get(filePath, { headers: { 'X-Ejaz-Reader': '1' } })).status()).toBe(404);
    });

    // رمز منتهٍ في الصفحة: القارئ يطلب رمزاً جديداً بـ POST مرة واحدة، بلا إعادة تحميل الصفحة
    test('recovers from a stale ticket in the page by asking for a fresh one', async ({ page }) => {
        test.skip(prefix === 'before', 'القارئ بعد البناء وحده');

        await page.route(`**/library/books/${slug}`, async (route) => {
            const response = await route.fetch();
            const html = (await response.text()).replace(/data-pdf-ticket="[0-9a-f]{64}"/, () => `data-pdf-ticket="${'0'.repeat(64)}"`);
            await route.fulfill({ response, body: html });
        });
        const ticketRequest = page.waitForRequest((request) => request.method() === 'POST' && request.url().endsWith(`/library/books/${slug}/reader-ticket`));

        await page.goto(`/library/books/${slug}`);
        await ticketRequest;
        await expect(page.locator('[data-pdf-reader]')).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
    });

    // وثيقة حوكمة «للاطلاع فقط» بالحماية نفسها، إن ضُبط معرّفها في GOVERNANCE_VIEW_ONLY_SLUG
    test('draws a view-only governance document the same way', async ({ page }) => {
        const documentSlug = process.env.GOVERNANCE_VIEW_ONLY_SLUG;
        test.skip(!documentSlug || prefix === 'before', 'GOVERNANCE_VIEW_ONLY_SLUG غير مضبوط');

        const fileResponse = page.waitForResponse((response) => new URL(response.url()).pathname === `/governance/${documentSlug}/file`);
        await page.goto(`/governance/${documentSlug}`);
        const response = await fileResponse;

        expect(response.headers()['content-type']).toBe('application/octet-stream');
        await expect(page.locator('[data-pdf-reader]')).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        expect((await page.request.get(response.url(), { headers: { 'X-Ejaz-Reader': '1' } })).status()).toBe(404);
    });

    // عنوان فيه أنماط الاستبدال ($& و$') يظهر كما هو، لا يُفسَّر (المشرف على #101، ٣). العنوان يُحقن في الصفحة بالاعتراض
    test('shows a title holding replacement patterns literally', async ({ page }) => {
        const title = "كتاب $& و$' :page";
        await page.route(`**/library/books/${slug}`, async (route) => {
            const response = await route.fetch();
            // البديل بدالة هنا أيضاً، فلا يُفسَّر $& في العنوان المحقون نفسه
            const html = (await response.text()).replace(/data-title="[^"]*"/, () => `data-title="${title.replace(/'/g, '&#039;')}"`);
            await route.fulfill({ response, body: html });
        });

        await page.goto(`/library/books/${slug}`);
        await expect(page.locator('[data-pdf-reader]')).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        expect(await page.locator('[data-pdf-reader] canvas').first().getAttribute('aria-label')).toContain(title);
    });
});
