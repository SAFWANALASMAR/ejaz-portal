// فحص الوصول (WCAG 2.1 AA) في المتصفح: axe-core على كل شاشة، ثم التنقل بلوحة المفاتيح.
// التشغيل: tests/browser/README.md — «فحص الوصول». والتقرير: docs/reviews/2026-10-13-accessibility.md
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { existsSync } from 'node:fs';
import { AUTH_STATE } from './global-setup.mjs';

const guestPages = [
    '/', '/about', '/initiatives', '/governance', '/library', '/library/books', '/media', '/media/news',
    '/media/albums', '/media/partners', '/contact', '/services', '/services/jobs', '/programs', '/competitions',
    '/assembly', '/login', '/register', '/forgot-password', '/no-such-page',
];

const accountPages = ['/account', '/account/profile', '/account/data', '/account/requests', '/account/programs', '/account/competitions', '/account/forms'];

// لوحة الإدارة تشترط التحقق الثنائي، فتُفحص بجلسة محفوظة لحساب إداري في ADMIN_STATE (ملف storageState)
const ADMIN_STATE = process.env.ADMIN_STATE;
// شاشات البوابات الخمس (App\Support\Portals): المجلس، والمدير التنفيذي، والموظفون، والمحكّمون، ولوحة البداية لكل منها
const adminPages = [
    '/admin', '/admin/meetings', '/admin/meetings/create', '/admin/documents', '/admin/assembly-files', '/admin/users',
    '/admin/programs', '/admin/programs/create', '/admin/media-assets', '/admin/competitions', '/admin/archive-items', '/admin/form-templates', '/admin/reviews',
];

async function expectNoAxeViolations(page, path) {
    const response = await page.goto(path);
    test.skip(response?.status() === 404 && path !== '/no-such-page', `${path} لم تُنشر بعد`);
    test.skip(response?.status() === 403, `${path} لغير صاحب الصلاحية`);

    await page.evaluate(() => document.fonts.ready);

    const { violations } = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        // تحدي Turnstile إطار من خارج البوابة
        .exclude('.cf-turnstile')
        .analyze();

    const summary = violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.map((n) => n.target.join(' ')).slice(0, 5).join(' | ')}`);
    expect(summary, `${path}`).toEqual([]);
}

test.describe('axe: guest pages', () => {
    for (const path of guestPages) {
        test(path, async ({ page }) => expectNoAxeViolations(page, path));
    }
});

test.describe('axe: account pages', () => {
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS');
    test.use({ storageState: AUTH_STATE });

    for (const path of accountPages) {
        test(path, async ({ page }) => expectNoAxeViolations(page, path));
    }
});

test.describe('axe: admin panel', () => {
    test.skip(!ADMIN_STATE || !existsSync(ADMIN_STATE), 'يحتاج جلسة إدارية محفوظة: ADMIN_STATE');
    test.use({ storageState: ADMIN_STATE ?? AUTH_STATE });

    for (const path of adminPages) {
        test(path, async ({ page }) => expectNoAxeViolations(page, path));
    }
});

test.describe('keyboard', () => {
    test('the first Tab reaches a visible skip link that moves focus to the content', async ({ page }) => {
        await page.goto('/');
        await page.keyboard.press('Tab');

        const skip = page.locator('a[href="#main"]');
        await expect(skip).toBeFocused();
        await expect(skip).toBeVisible();

        await page.keyboard.press('Enter');
        await expect(page.locator('#main')).toBeFocused();
    });

    test('focus is always visible, and Tab walks the whole page without a trap', async ({ page }) => {
        await page.goto('/');
        const seen = new Set();

        for (let i = 0; i < 80; i++) {
            await page.keyboard.press('Tab');
            const focused = await page.evaluate(() => {
                const el = document.activeElement;

                if (!el || el === document.body) {
                    return null;
                }

                const style = getComputedStyle(el);

                return {
                    key: `${el.tagName}|${el.getAttribute('href') ?? ''}|${el.textContent.trim().slice(0, 30)}`,
                    visible: el.checkVisibility(),
                    outline: style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0,
                    inFooter: el.closest('footer') !== null,
                };
            });

            if (!focused) {
                break;
            }

            if (focused.visible) {
                expect(focused.outline, `focus ring on ${focused.key}`).toBe(true);
            }

            seen.add(focused.key);

            if (focused.inFooter) {
                break;
            }
        }

        // التركيز تقدّم عبر عناصر كثيرة ولم يعلق في عنصر واحد
        expect(seen.size).toBeGreaterThan(3);
    });

    test('a dropdown of the main menu opens with Enter, closes with Escape, and closes when focus leaves it', async ({ page }) => {
        test.skip(page.viewportSize().width < 1280, 'القائمة الكاملة على الحاسوب');
        await page.goto('/');

        const toggle = page.locator('header nav button[data-toggle="dropdown"]').first();
        test.skip((await toggle.count()) === 0, 'لا قائمة منسدلة في القائمة الحالية');

        const menu = page.locator(`#${await toggle.getAttribute('aria-controls')}`);

        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(menu).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(toggle).toBeFocused();

        await page.keyboard.press('Enter');
        const links = await menu.locator('a').count();
        for (let i = 0; i <= links; i++) {
            await page.keyboard.press('Tab');
        }
        await expect(menu).toBeHidden();
    });

    test('the mobile menu opens and closes by keyboard', async ({ page }) => {
        test.skip(page.viewportSize().width >= 1280, 'القائمة كاملة على الحاسوب');
        await page.goto('/');

        const toggle = page.locator('[aria-controls="mobile-menu"]');
        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(page.locator('#mobile-menu')).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(page.locator('#mobile-menu')).toBeHidden();
        await expect(toggle).toBeFocused();
    });
});
