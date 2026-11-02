// ملاحظات المدير التنفيذي (25 أكتوبر 2026): الرئيسية بمقاطعها، والقوائم ومكتبة إعجاز، والإعلانات، والتسجيل والدخول،
// ورئيسية المشرف العام. على العروض الثلاثة: الاتجاه من اليمين، والعناصر الأساسية، ولا تمرير أفقي، وفحص axe، ولقطة كاملة
// في docs/reviews/screenshots/2026-10-25-director/<الشاشة>-<العرض>.png.
// التشغيل (المشرف العام في DEMO_CREDENTIALS لرئيسيته):
//   docker compose run --rm -e DEMO_CREDENTIALS="$creds" browser \
//     npx playwright test --config=tests/browser/playwright.config.mjs director-notes.spec.mjs
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { existsSync } from 'node:fs';
import { AUTH_STATE } from './global-setup.mjs';

const DIR = 'docs/reviews/screenshots/2026-10-25-director';

const guestScreens = [
    { name: 'home', path: '/', heading: '#home-king' },
    { name: 'announcements', path: '/media/announcements', heading: 'h1' },
    { name: 'media', path: '/media', heading: 'h1' },
    { name: 'library', path: '/library', heading: 'h1' },
    { name: 'library-audio', path: '/library/audio', heading: 'h1' },
    { name: 'register', path: '/register', heading: 'h1' },
    { name: 'login', path: '/login', heading: 'h1' },
    { name: 'forgot-password', path: '/forgot-password', heading: 'h1' },
    { name: 'contact', path: '/contact', heading: 'h1' },
];

async function check(page, testInfo, screen) {
    const response = await page.goto(screen.path);
    expect(response?.status(), screen.path).toBeLessThan(400);
    await page.evaluate(() => document.fonts.ready);

    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator(screen.heading).first()).toBeAttached();

    // الظهور عند التمرير: يمر على الصفحة كلها فتظهر المقاطع قبل اللقطة
    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 400) {
            window.scrollTo({ top: y, behavior: 'instant' });
            await new Promise((resolve) => setTimeout(resolve, 60));
        }
        window.scrollTo({ top: 0, behavior: 'instant' });
    });
    await page.waitForTimeout(800);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `${screen.path}: تمرير أفقي`).toBeLessThanOrEqual(0);

    const { violations } = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .exclude('.cf-turnstile')
        .analyze();
    expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 4).join(' | ')}`), screen.path).toEqual([]);

    await page.screenshot({ path: `${DIR}/${screen.name}-${testInfo.project.use.viewport.width}.png`, fullPage: true });
}

test.describe('director notes: guest screens', () => {
    for (const screen of guestScreens) {
        test(screen.name, async ({ page }, testInfo) => check(page, testInfo, screen));
    }

    test('the main menu has «مكتبة إعجاز» with its four branches, and the media centre news and announcements only', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', 'القائمة الأفقية على الحاسوب');
        await page.goto('/');

        const library = page.locator('[data-main-nav] button', { hasText: 'مكتبة إعجاز' });
        await library.click();
        const branches = page.locator(`#${await library.getAttribute('aria-controls')} a`);
        await expect(branches).toHaveText(['المكتبة المرئية', 'المكتبة الصوتية', 'الكتب والمؤلفات', 'الأبحاث والدراسات']);
        await page.screenshot({ path: `${DIR}/menu-library-${testInfo.project.use.viewport.width}.png` });

        const media = page.locator('[data-main-nav] button', { hasText: 'المركز الإعلامي' });
        await media.click();
        await expect(page.locator(`#${await media.getAttribute('aria-controls')} a`)).toHaveText(['الأخبار', 'الإعلانات']);
    });

    test('the news ticker pauses with its button', async ({ page }) => {
        await page.goto('/');
        const toggle = page.locator('[data-ticker-toggle="home-news-ticker-strip"]');
        test.skip((await toggle.count()) === 0, 'لا أخبار منشورة');

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('#home-news-ticker-strip')).toHaveAttribute('data-paused', '');
    });

    test('«اقرأ الكلمة كاملة» opens the full introduction in a dialog', async ({ page }) => {
        await page.goto('/');
        await page.locator('[data-dialog-open="chairman-word"]').click();
        await expect(page.locator('#chairman-word')).toBeVisible();
        await expect(page.locator('#chairman-word')).toContainText('النمل:93');
    });

    // المراجعة النهائية (26 أكتوبر 2026): التذييل بهويته، والمخطوطة في المبررات، والمقتطف بفقرته الفعلية
    test('the footer carries the identity, and the rationale the calligraphy file', async ({ page }) => {
        await page.goto('/');
        const footer = page.locator('[data-site-footer]');
        await expect(footer).toBeVisible();
        await expect(footer.locator('img[alt]').first()).toBeAttached();
        await expect(footer).toContainText('رقم الترخيص');
        await expect(page.locator('[data-rationale-calligraphy]')).toBeAttached();
        await expect(page.locator('#home-chairman').locator('..')).toContainText('فإن القرآن الكريم كلام الله المُعجز');
    });
});

test.describe('director notes: super admin dashboard', () => {
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المشرف العام: DEMO_CREDENTIALS');
    test.use({ storageState: AUTH_STATE });

    test('account', async ({ page }, testInfo) => check(page, testInfo, { name: 'super-admin-dashboard', path: '/account', heading: 'h1' }));
});
