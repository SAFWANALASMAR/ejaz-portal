// الدفعة الثانية من التحسين البصري (feature/visual-refresh-2 — docs/decisions/2026-10-30-visual-refresh-2.md): الرئيسية من
// مرجع المدير، وشريط العودة، والبحث السريع، والدوّارات، وتقليل الحركة. على العروض الثلاثة (playwright.config.mjs).
import { expect, test } from '@playwright/test';

async function expectRtlNoScroll(page) {
    expect(await page.evaluate(() => document.documentElement.getAttribute('dir'))).toBe('rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'لا تمرير أفقي').toBeLessThanOrEqual(0);
}

test('home: the dark header, the live hero and the identity slider', async ({ page }) => {
    await page.goto('/');
    await expectRtlNoScroll(page);

    const header = page.locator('[data-site-header]');
    const background = await header.evaluate((element) => getComputedStyle(element).backgroundColor);
    expect(background, 'الرأس أزرق داكن صلب').toBe('rgb(31, 69, 101)');
    await expect(header.locator('[data-logo-calligraphy]')).toBeVisible();

    const hero = page.locator('[data-hero-slider]');
    await expect(hero).toBeVisible();
    await expect(hero.locator('h1 [data-highlight]')).toBeVisible();

    // زر الإيقاف متى كانت للعارض شرائح
    const toggle = hero.locator('[data-carousel-toggle]');
    if ((await toggle.count()) > 0) {
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await expect(toggle).toHaveAttribute('aria-label', 'تشغيل العرض');
    }

    const identity = page.locator('[data-identity-slider]');
    if ((await identity.count()) > 0) {
        await expect(identity).toHaveAttribute('aria-roledescription', /.+/);
        await expect(identity.locator('[data-carousel-item][data-active]')).toHaveCount(1);
        await expect(identity.locator('[data-carousel-item]').first()).toHaveAttribute('aria-roledescription', /.+/);
    }
});

test('the identity slider pauses on hover and focus, and moves by its arrows', async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'المرور بالفأرة على الحاسوب');
    await page.goto('/');
    const identity = page.locator('[data-identity-slider]');
    test.skip((await identity.count()) === 0 || (await identity.locator('[data-carousel-item]').count()) < 2, 'شريحة واحدة');

    await identity.scrollIntoViewIfNeeded();
    await expect(identity).toHaveAttribute('data-running', '');
    await identity.hover();
    await expect(identity).not.toHaveAttribute('data-running', '');
    await expect(identity.locator('[data-carousel-track]')).toHaveAttribute('aria-live', 'polite');

    const active = () => identity.locator('[data-carousel-item]').evaluateAll((items) => items.findIndex((item) => item.hasAttribute('data-active')));
    const before = await active();
    await identity.locator('[data-carousel-next]').click();
    expect(await active()).not.toBe(before);

    await page.mouse.move(0, 0);
    await identity.locator('[data-carousel-next]').focus();
    await expect(identity).not.toHaveAttribute('data-running', '');
});

test('reduced motion: no autoplay and no endless animation running', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');

    expect(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--motion').trim())).toBe('0');
    await expect(page.locator('[data-carousel][data-running]')).toHaveCount(0);
    const toggle = page.locator('[data-hero-slider] [data-carousel-toggle]');
    if ((await toggle.count()) > 0) {
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    }

    const endless = await page.evaluate(() =>
        document.getAnimations().filter((animation) => animation.playState === 'running' && animation.effect?.getTiming().iterations === Infinity).length,
    );
    expect(endless, 'لا حركة لا تنتهي').toBe(0);
    await expectRtlNoScroll(page);
});

test('x-back-nav: a governance document and a program go back to their lists', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('[data-back-nav]')).toHaveCount(0);

    let checked = 0;
    for (const [list, selector, parent] of [
        ['/governance', 'main a[href*="/governance/"]:not([href*="?"])', /\/governance$/],
        ['/programs', 'main a[href*="/programs/"]:not([href*="?"]):not([href$="/register"])', /\/programs$/],
    ]) {
        await page.goto(list);
        const link = page.locator(selector).first();
        if ((await link.count()) === 0) {
            continue;
        }
        await page.goto(await link.getAttribute('href'));
        const back = page.getByRole('link', { name: 'العودة للقائمة السابقة' });
        await expect(back).toBeVisible();
        await expect(page.getByRole('link', { name: 'العودة للرئيسية' })).toBeVisible();
        await expectRtlNoScroll(page);

        await back.focus();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(parent);
        checked++;
    }
    test.skip(checked === 0, 'لا وثيقة ولا برنامج منشور');
});

test('Ctrl+K opens the quick search, finds a page by keyboard, and Esc closes it', async ({ page }, info) => {
    test.skip(info.project.name === 'mobile', 'لوحة المفاتيح على الحاسوب واللوحي');
    await page.goto('/about');

    const dialog = page.locator('[data-search-dialog]');
    await expect(dialog).not.toBeVisible();
    await page.keyboard.press('Control+k');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('[data-search-input]')).toBeFocused();

    await page.keyboard.type('الحوكمة');
    const options = dialog.getByRole('option');
    expect(await options.count()).toBeGreaterThan(0);
    await expect(options.first()).toHaveAttribute('aria-selected', 'true');
    await page.keyboard.press('ArrowDown');
    await expect(options.nth(1)).toHaveAttribute('aria-selected', 'true');

    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();

    // وبزر البحث في الرأس
    await page.locator('[data-site-header] [data-search-open]').click();
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
});
