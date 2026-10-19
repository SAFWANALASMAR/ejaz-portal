// نافذة المساعد الذكي على العروض الثلاثة (requirements/10): الزر والنافذة، ولوحة المفاتيح، والجواب بمصادره وزر الانتقال،
// والتقييم، وبقاء المحادثة بعد التنقّل السريع، والاتجاه، ولا تمرير أفقي. ولقطة في docs/design/screenshots/assistant-<العرض>.png
//
// يلزمه في البيئة المحلية: المساعد مشغّل بالمزود الوهمي ومقالات دليل مفعّلة (docs/guides/ai-assistant.md — «التجربة
// المحلية»). وكل عرض يسأل سؤالاً واحداً، فلا يبلغ حد الأسئلة لكل زائر.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('opens the assistant, answers with sources, and keeps the chat across navigation', async ({ page }) => {
    await page.goto('/');

    const toggle = page.locator('[data-assistant-toggle]');
    const dialog = page.getByRole('dialog', { name: 'مساعد البوابة' });

    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(dialog).toBeHidden();

    // الفتح بلوحة المفاتيح، والتركيز في حقل السؤال، والتعريف والتنبيه ظاهران
    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#assistant-question')).toBeFocused();
    await expect(dialog).toContainText('مساعد آلي — قد يخطئ، والمرجع صفحات الجمعية.');
    await expect(dialog).toContainText('لا تكتب بيانات شخصية');

    // Escape يغلق ويعيد التركيز إلى الزر
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(toggle).toBeFocused();

    await toggle.click();
    await expect(dialog).toBeVisible();

    const width = page.viewportSize().width;

    // على الجوال تفتح بعرض الشاشة
    if (width < 640) {
        const box = await dialog.boundingBox();
        expect(Math.round(box.width)).toBe(width);
    }

    // سؤال مقترح من عناوين الدليل المفعّلة
    const suggestion = page.locator('[data-assistant-suggestion]').first();
    await expect(suggestion).toBeVisible();
    await suggestion.click();

    const answer = dialog.locator('[data-rating]:not([hidden])').first().locator('xpath=..');
    await expect(answer).toBeVisible({ timeout: 20_000 });
    await expect(answer.locator('[data-sources]')).toBeVisible();
    await expect(answer.locator('[data-source-list] a').first()).toHaveAttribute('href', /^\//);

    const go = answer.locator('[data-go] a').first();
    await expect(go).toBeVisible();

    // التقييم
    const helpful = answer.locator('[data-rate="helpful"]');
    await helpful.click();
    await expect(helpful).toHaveAttribute('aria-pressed', 'true');

    // الوصول (WCAG 2.1 AA) في النافذة المفتوحة بجوابها، كفحص a11y.spec.mjs
    const { violations } = await new AxeBuilder({ page })
        .include('#assistant-panel')
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();
    expect(violations.map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)).toEqual([]);

    // لا تمرير أفقي، واللقطة
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
    await page.screenshot({ path: `docs/design/screenshots/assistant-${width}.png` });

    // التنقّل السريع إلى صفحة أخرى: تبقى النافذة بمحادثتها (@persist)
    await page.evaluate(() => window.Livewire.navigate('/contact'));
    await page.waitForURL((url) => url.pathname === '/contact');
    await expect(page.locator('h1')).toBeVisible();
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('[data-rating]:not([hidden])')).toHaveCount(1);

    // «اذهب إلى الصفحة» يفتح صفحة المصدر (سريعاً أو كاملاً بحسب الصفحة — SpaLink)، وعلى الجوال تُغلق النافذة
    const target = await go.getAttribute('href');
    await go.click();
    await page.waitForURL((url) => url.pathname === target);

    if (width < 640 && (await go.count()) > 0) {
        await expect(dialog).toBeHidden();
    }
});
