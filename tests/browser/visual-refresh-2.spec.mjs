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
    // الرأس بتدرج الهوية الداكن منذ المرحلة أ (--chrome-gradient، القرار ٦): ينتهي بالأزرق الداكن #1F4565، بحد سفلي #8EC5F0
    const style = await header.evaluate((element) => {
        const computed = getComputedStyle(element);

        return { image: computed.backgroundImage, border: computed.borderBottomColor };
    });
    expect(style.image, 'الرأس بتدرج الهوية الداكن').toContain('linear-gradient');
    expect(style.image, 'ينتهي بالأزرق الداكن').toContain('rgb(31, 69, 101)');
    expect(style.border, 'الحد السفلي #8EC5F0').toBe('rgb(142, 197, 240)');
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

test('the identity slider pauses on hover and focus, and moves by its dots', async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'المرور بالفأرة على الحاسوب');
    await page.goto('/');
    const identity = page.locator('[data-identity-slider]');
    test.skip((await identity.count()) === 0 || (await identity.locator('[data-carousel-item]').count()) < 2, 'شريحة واحدة');

    await identity.scrollIntoViewIfNeeded();
    await expect(identity).toHaveAttribute('data-running', '');
    // البطاقة كلها رابط يغطي الدوّار وهو أخ له (47bb4e67)، والدوّار يتوقف بالمرور على البطاقة (carousel.js)، فالمرور عليها كالزائر
    await page.locator('[data-identity-card]', { has: identity }).hover();
    await expect(identity).not.toHaveAttribute('data-running', '');
    await expect(identity.locator('[data-carousel-track]')).toHaveAttribute('aria-live', 'polite');

    const active = () => identity.locator('[data-carousel-item]').evaluateAll((items) => items.findIndex((item) => item.hasAttribute('data-active')));
    const before = await active();
    await identity.locator('[data-carousel-dot]').nth(1).click();
    expect(await active()).not.toBe(before);

    await page.mouse.move(0, 0);
    await identity.locator('[data-carousel-dot]').first().focus();
    await expect(identity).not.toHaveAttribute('data-running', '');
});

test('reduced motion: no autoplay and no endless animation running', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');

    expect(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--motion').trim())).toBe('0');
    // إلا بطاقات الهوية (تبديل فوري بلا انتقال) والأشرطة الأفقية (أبطأ بزر إيقاف): ملاحظات المدير التنفيذي، 1 نوفمبر 2026،
    // البندان ١٠ و١١ (docs/decisions/2026-11-01-director-notes.md)
    await expect(page.locator('[data-carousel][data-running]:not([data-autoplay-always])')).toHaveCount(0);
    const toggle = page.locator('[data-hero-slider] [data-carousel-toggle]');
    if ((await toggle.count()) > 0) {
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    }

    const endless = await page.evaluate(() =>
        document.getAnimations().filter((animation) => animation.playState === 'running' && animation.effect?.getTiming().iterations === Infinity
            && !animation.effect?.target?.closest?.('.marquee[data-direction="horizontal"]')).length,
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
        // الرجوع بتاريخ المتصفح أو بديله تحميلٌ كامل، وخادم التطوير بطيء
        await expect(page).toHaveURL(parent, { timeout: 20_000 });
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

// قائمة التحقق في التصور النهائي (صفوان 19:15): كل صفحة بلغة الرئيسية، بالمكونات المشتركة نفسها
for (const path of ['/about', '/initiatives', '/programs', '/governance', '/library', '/media', '/services', '/contact']) {
    test(`final concept markers on ${path}`, async ({ page }) => {
        const response = await page.goto(path);
        test.skip(response?.status() === 404, 'غير منشورة');

        await expect(page.locator('[data-site-header] [data-logo-calligraphy]')).toBeVisible();
        await expect(page.locator('[data-site-footer] [data-logo-calligraphy]')).toBeAttached();

        const hero = page.locator('[data-page-hero]');
        await expect(hero).toBeVisible();
        await expect(hero.locator('h1')).toBeVisible();
        await expect(hero.locator('.title-bar')).toBeAttached();
        await expect(hero.locator('[data-hero-badge]')).toBeVisible();
        await expect(hero.locator('[data-back-nav]')).toBeVisible();
        await expect(page.locator('[data-back-nav]'), 'شريط عودة واحد').toHaveCount(1);

        // البطاقات والأيقونات بالأنماط المشتركة متى وُجدت في الصفحة: الملونة (.tone-card — البند G2، المرحلة ج) أو الفاتحة
        // وبطاقة الخدمة المشتركة (x-service-card) في «خدماتنا» منذ إضافة توجيه المدير (3 نوفمبر 2026 — decisions/2026-11-03-director-home.md)
        const cards = page.locator('main .tone-card, main .card-lift, main .card-glow, main .concept-card, main .service-card, main [data-empty-state]');
        expect(await cards.count(), 'بطاقات مشتركة أو حالة فارغة').toBeGreaterThan(0);
        await expectRtlNoScroll(page);
    });
}

// بطاقات «خدماتنا» في الرئيسية صارت بطاقات ملونة في شريط متحرك (النسخة 63): تعاقب درجاتها في home-final.spec.mjs
