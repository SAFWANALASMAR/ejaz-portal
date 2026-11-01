// متابعات التحديث البصري 2 (fix/visual-refresh-2-followups — docs/decisions/2026-11-01-visual-refresh-2-followups.md): طبقة الانتقال
// لا تعلق، وشريط العودة لا يخرج من البوابة بعد الرجوع، ورابط المتجر في التذييل والخدمات، ورقم الترخيص في الرأس. على العروض الثلاثة.
import { expect, test } from '@playwright/test';

async function expectRtlNoScroll(page) {
    expect(await page.evaluate(() => document.documentElement.getAttribute('dir'))).toBe('rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'لا تمرير أفقي').toBeLessThanOrEqual(0);
}

// تنقّل سريع بدأ ولم يكتمل: حدث Livewire وحده بلا طلب يتم
async function startStalledNavigation(page) {
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: {}, cancelable: true })));
}

test('the page transition overlay hides by itself when a navigation stalls (low note 1)', async ({ page }) => {
    await page.goto('/contact');
    const overlay = page.locator('[data-page-transition]');

    await startStalledNavigation(page);
    await expect(overlay).toBeVisible();
    // نحو أربع ثوانٍ ثم الإخفاء المتدرج (450 + 300 ملّي ثانية)، لا خمس عشرة
    await expect(overlay).toBeHidden({ timeout: 7000 });
});

test('a page restored from the back-forward cache shows no stuck overlay (low note 1)', async ({ page }) => {
    await page.goto('/contact');
    const overlay = page.locator('[data-page-transition]');

    await startStalledNavigation(page);
    await expect(overlay).toBeVisible();

    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
    await expect(overlay).toBeHidden({ timeout: 1000 });
    expect(await page.evaluate(() => document.documentElement.hasAttribute('data-navigating'))).toBe(false);
});

test('the history back link does not leave the portal after the browser back button (low note 2)', async ({ page }) => {
    await page.goto('/contact');
    await page.evaluate(() => {
        window.__backs = 0;
        window.history.back = () => {
            window.__backs++;
        };
        document.body.insertAdjacentHTML('beforeend', '<a href="#stay" data-history-back id="probe-back">رجوع</a>');
    });

    // تنقّل سريع إلى الأمام ثم رجوع بزر المتصفح: لا سابقة في البوابة بعده، فالرابط نفسه (لا history.back)
    await page.evaluate(() => {
        document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: {}, cancelable: true }));
        document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: { history: true }, cancelable: true }));
    });
    await page.locator('#probe-back').click();
    expect(await page.evaluate(() => window.__backs)).toBe(0);

    // وبعد تنقّل إلى الأمام وحده يعود بتاريخ المتصفح كما كان
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: {}, cancelable: true })));
    await page.locator('#probe-back').click();
    expect(await page.evaluate(() => window.__backs)).toBe(1);
});

test('services: the store card and the footer store link open in a new tab', async ({ page }) => {
    await page.goto('/services');
    await expectRtlNoScroll(page);

    const card = page.locator('[data-store-card] a');
    if ((await card.count()) === 0) {
        test.skip(true, 'لا رابط متجر صالح في هذه البيئة');
    }
    await expect(card).toHaveAttribute('target', '_blank');
    await expect(card).toHaveAttribute('rel', /noopener/);
    await expect(page.locator('[data-footer-box="links"] [data-store-link]')).toHaveAttribute('target', '_blank');
});

test('header: the licence box shows the approved number on wide screens', async ({ page }, testInfo) => {
    await page.goto('/');
    const licence = page.locator('[data-header-licence]');

    if (testInfo.project.name === 'desktop') {
        await expect(licence).toBeVisible();
        await expect(licence).toContainText('1000723700');
        await expect(licence).not.toContainText('ناقص');
    } else {
        await expect(licence).toBeHidden();
    }
});

test('governance: the page renders right to left with no horizontal scroll', async ({ page }) => {
    await page.goto('/governance');
    await expectRtlNoScroll(page);
    await expect(page.locator('[data-page-hero] h1')).toBeVisible();
});
