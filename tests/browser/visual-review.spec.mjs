// مراجعة المشرف على #95 (30 أكتوبر 2026) في المتصفح: زر العودة للأعلى لا يُركَّز عليه وهو مخفي، وصور هيكل التحميل
// ظاهرة بلا JavaScript، وإطار التركيز على الأقسام الداكنة وعلى الزر البنفسجي، ولا حلقة مستمرة لمن طلب تقليل الحركة،
// والعنوان المقسّم كلمات يُقرأ جملة. والمصادر مختبرة في tests/Feature/Platform/VisualRefreshReviewTest.php
import { expect, test } from '@playwright/test';

// قيمة لون محسوبة لرمز من رموز الهوية
const token = (page, name) =>
    page.evaluate((variable) => {
        const probe = document.createElement('span');
        probe.style.color = `var(${variable})`;
        document.body.append(probe);
        const value = getComputedStyle(probe).color;
        probe.remove();

        return value;
    }, name);

test('the hidden back-to-top button is never reached by Tab, and shows after scrolling', async ({ page }) => {
    await page.goto('/governance');
    const button = page.locator('[data-back-to-top]');
    await expect(button).toHaveCSS('visibility', 'hidden');

    // كل عناصر الصفحة بالتتابع: لا يأخذ الزر التركيز إلا وهو ظاهر (التنقّل بـ Tab قد يمرّر الصفحة فيظهره، كعلى الجوال)
    const stops = await page.locator('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])').count();
    for (let i = 0; i < stops + 2; i++) {
        await page.keyboard.press('Tab');
        const [focused, visible] = await button.evaluate((element) => [document.activeElement === element, element.hasAttribute('data-visible')]);
        expect(focused && !visible).toBe(false);
    }

    // وبعد التمرير في صفحة أطول من الشاشة يظهر
    await page.evaluate(() => {
        document.body.style.minHeight = '400vh';
        window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' });
    });
    await expect(button).toHaveCSS('visibility', 'visible');
});

test('skeleton images stay visible without JavaScript, and no shimmer keeps running behind them', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    // مقطع الرئيسية المفرد بصورته (مفعّل في بيانات العرض)
    await page.goto('/');

    const images = page.locator('.skeleton img');
    expect(await images.count()).toBeGreaterThan(0);
    await expect(images.first()).toHaveCSS('opacity', '1');
    await expect(page.locator('.skeleton').first()).toHaveCSS('animation-name', 'none');

    await context.close();
});

test('focus in a dark section (not the footer) uses the light ring', async ({ page }) => {
    await page.goto('/governance');
    const hero = page.locator('[data-page-hero]');
    await hero.evaluate((section) => {
        const link = document.createElement('a');
        link.href = '#';
        link.textContent = 'x';
        link.setAttribute('data-probe', '');
        section.append(link);
    });
    const probe = hero.locator('[data-probe]');
    await probe.focus();

    expect(await probe.evaluate((element) => getComputedStyle(element).outlineColor)).toBe(await token(page, '--color-accent-light'));
});

test('the accent button on a light section gets the dark blue ring', async ({ page }) => {
    await page.goto('/governance');
    await page.evaluate(() => {
        const button = document.createElement('a');
        button.href = '#';
        button.textContent = 'x';
        button.className = 'bg-accent';
        button.setAttribute('data-probe', '');
        document.getElementById('main').append(button);
    });
    const probe = page.locator('#main > [data-probe]');
    await probe.focus();

    expect(await probe.evaluate((element) => getComputedStyle(element).outlineColor)).toBe(await token(page, '--color-primary-dark'));
});

test('a title revealed word by word is read as one sentence', async ({ page }) => {
    await page.goto('/governance');
    const title = page.locator('[data-page-hero] h1');
    await expect(title).toHaveAttribute('data-words-ready', '');

    const label = await title.getAttribute('aria-label');
    expect(label?.length).toBeGreaterThan(0);
    expect(await title.locator('[data-word]:not([aria-hidden="true"])').count()).toBe(0);
    await expect(page.getByRole('heading', { level: 1, name: label })).toBeVisible();
});

test('no endless animation runs for reduced motion, even without JavaScript', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false, reducedMotion: 'reduce' });
    const page = await context.newPage();
    await page.goto('/');

    // الجارية وحدها: شريط الأخبار متوقف لمن طلب تقليل الحركة (app.css)
    const endless = await page.evaluate(() =>
        document
            .getAnimations()
            .filter((animation) => animation.playState === 'running' && animation.effect?.getTiming().iterations === Infinity)
            .map((animation) => animation.animationName ?? '?'),
    );

    expect(endless).toEqual([]);
    await context.close();
});
