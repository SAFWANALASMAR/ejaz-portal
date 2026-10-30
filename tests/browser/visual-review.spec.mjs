// مراجعة المشرف على #95 (30 أكتوبر 2026) في المتصفح: زر العودة للأعلى لا يُركَّز عليه وهو مخفي، وصور هيكل التحميل
// ظاهرة بلا JavaScript، وإطار التركيز على الأقسام الداكنة بالدرجة الفاتحة، ولا حلقة مستمرة لمن طلب تقليل الحركة.
// والمصادر مختبرة في tests/Feature/Platform/VisualRefreshReviewTest.php
import { expect, test } from '@playwright/test';

test('the hidden back-to-top button is out of the focus order until the page is scrolled', async ({ page }) => {
    await page.goto('/');
    const button = page.locator('[data-back-to-top]');

    await expect(button).toHaveCSS('visibility', 'hidden');
    await expect(button).not.toBeFocused();

    await page.evaluate(() => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' }));
    await expect(button).toHaveCSS('visibility', 'visible');
});

test('skeleton images stay visible without JavaScript', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/initiatives');

    const images = page.locator('.skeleton img');

    if ((await images.count()) > 0) {
        await expect(images.first()).toHaveCSS('opacity', '1');
    }

    await context.close();
});

test('focus on a dark section uses the light ring', async ({ page }) => {
    await page.goto('/');
    const link = page.locator('.section-dark a, .section-dark button').first();
    await link.focus();

    const [ring, light] = await link.evaluate((element) => {
        const probe = document.createElement('span');
        probe.style.color = 'var(--color-accent-light)';
        document.body.append(probe);
        const value = getComputedStyle(probe).color;
        probe.remove();

        return [getComputedStyle(element).outlineColor, value];
    });

    expect(ring).toBe(light);
});

test('no endless animation runs for reduced motion, even without JavaScript', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false, reducedMotion: 'reduce' });
    const page = await context.newPage();
    await page.goto('/');

    // الجارية وحدها: شريط الأخبار متوقف لمن طلب تقليل الحركة (app.css)
    const endless = await page.evaluate(
        () =>
            document
                .getAnimations()
                .filter((animation) => animation.playState === 'running' && animation.effect?.getTiming().iterations === Infinity)
                .map((animation) => animation.animationName ?? '?'),
    );

    expect(endless).toEqual([]);
    await context.close();
});
