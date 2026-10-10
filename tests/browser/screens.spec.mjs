// لكل شاشة: لقطة كاملة على العرض الحالي في docs/design/screenshots/<الشاشة>-<العرض>.png،
// والتحقق من الاتجاه من اليمين، والعناصر الأساسية، وعدم وجود تمرير أفقي.
import { expect, test } from '@playwright/test';
import { existsSync } from 'node:fs';
import { AUTH_STATE } from './global-setup.mjs';

const guestScreens = [
    { name: 'home', path: '/', heading: 'h1' },
    // صفحات المحتوى تُصوَّر بعد نشرها من اللوحة، وقبله ترد 404 فتُتخطى
    { name: 'about', path: '/about', heading: 'h1', whenPublished: true },
    { name: 'initiatives', path: '/initiatives', heading: 'h1' },
    { name: 'initiative', path: '/initiatives/qiyam', heading: 'h1', whenPublished: true },
    { name: 'governance', path: '/governance', heading: 'h1' },
    { name: 'document', path: '/governance/privacy-policy', heading: 'h1', whenPublished: true },
    { name: 'library', path: '/library', heading: 'h1' },
    { name: 'library-section', path: '/library/books', heading: 'h1' },
    // لا عنصر منشور بعد، فيُصوَّر من المجموعة التجريبية وحدها (README)
    { name: 'library-item', path: '/library/books/demo-book', heading: 'h1', whenPublished: true },
    { name: 'library-video', path: '/library/videos/demo-video', heading: 'h1', whenPublished: true },
    { name: 'media', path: '/media', heading: 'h1' },
    { name: 'news', path: '/media/news', heading: 'h1' },
    // الخبر والألبوم من المجموعة التجريبية وحدها (README)
    { name: 'news-article', path: '/media/news/demo-news', heading: 'h1', whenPublished: true },
    { name: 'albums', path: '/media/albums', heading: 'h1' },
    { name: 'album', path: '/media/albums/demo-album', heading: 'h1', whenPublished: true },
    { name: 'partners', path: '/media/partners', heading: 'h1' },
    { name: 'contact', path: '/contact', heading: 'h1' },
    { name: 'login', path: '/login', heading: 'h1' },
    { name: 'register', path: '/register', heading: 'h1' },
    { name: 'forgot-password', path: '/forgot-password', heading: 'h1' },
    { name: 'services', path: '/services', heading: 'h1' },
    { name: 'services-jobs', path: '/services/jobs', heading: 'h1' },
    { name: 'programs', path: '/programs', heading: 'h1' },
    // المسابقات: صفحة المسابقة تُصوَّر بمسابقة منشورة معرّفها في COMPETITION_SLUG
    { name: 'competitions', path: '/competitions', heading: 'h1' },
    { name: 'competition', path: `/competitions/${process.env.COMPETITION_SLUG ?? 'demo'}`, heading: 'h1', whenPublished: true },
    // استبيان منشور يقبل غير المسجلين، برمزه في SURVEY_TOKEN
    { name: 'survey', path: `/surveys/${process.env.SURVEY_TOKEN ?? 'x'.repeat(32)}`, heading: 'h1', whenPublished: true },
    // المتجر والتبرع يظهران بعد تفعيلهما من اللوحة
    { name: 'store', path: '/store', heading: 'h1', whenPublished: true },
    { name: 'donate', path: '/donate', heading: 'h1', whenPublished: true },
    // الجمعية العمومية للزائر: البلاطات الأربع (الصورة 05)
    { name: 'assembly', path: '/assembly', heading: 'h1' },
    // صفحة الخطأ العربية لمسار لا صفحة له ولا تحويل
    { name: 'not-found', path: '/no-such-page', heading: 'h1', status: 404 },
];

const accountScreens = [
    { name: 'dashboard', path: '/account', heading: 'main p.text-2xl' },
    { name: 'profile', path: '/account/profile', heading: 'h1' },
    { name: 'requests', path: '/account/requests', heading: 'h1' },
    { name: 'my-programs', path: '/account/programs', heading: 'h1' },
    { name: 'my-competitions', path: '/account/competitions', heading: 'h1' },
    { name: 'my-payments', path: '/account/payments', heading: 'h1' },
    // المسودة تُنشأ بزر «شارك الآن» (POST) في صفحة المسابقة، لا بفتح النموذج
    { name: 'competition-entry', path: `/competitions/${process.env.COMPETITION_SLUG ?? 'demo'}/entry`, heading: 'h1', whenPublished: true, startFrom: `/competitions/${process.env.COMPETITION_SLUG ?? 'demo'}` },
    // منطقة الجمعية العمومية: لعضو فعّال وحده (README)، ولغيره تُتخطى. واجتماع بمعرّفه في MEETING_UUID
    { name: 'assembly-member', path: '/assembly', heading: 'h1' },
    { name: 'assembly-meetings', path: '/assembly/meetings', heading: 'h1', whenMember: true },
    { name: 'assembly-meeting', path: `/assembly/meetings/${process.env.MEETING_UUID ?? '00000000-0000-0000-0000-000000000000'}`, heading: 'h1', whenMember: true, whenPublished: true },
    { name: 'assembly-files', path: '/assembly/files', heading: 'h1', whenMember: true },
];

async function checkScreen(page, testInfo, screen) {
    if (screen.startFrom) {
        await page.goto(screen.startFrom);
        const start = page.locator('form[action$="/entry/start"] button');

        if (await start.count()) {
            await start.first().click();
            await page.waitForLoadState();
        }
    }

    const response = await page.goto(screen.path);
    test.skip(screen.whenPublished === true && response?.status() === 404, `${screen.path} لم تُنشر بعد`);
    test.skip(screen.whenMember === true && response?.status() === 403, `${screen.path} لأعضاء الجمعية العمومية`);

    if (screen.status) {
        expect(response?.status(), `${screen.path} status`).toBe(screen.status);
    } else {
        expect(response?.status(), `${screen.path} status`).toBeLessThan(400);
    }

    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('header [aria-label]').first()).toBeVisible();
    await expect(page.locator(screen.heading).first()).toBeVisible();
    await expect(page.locator('footer')).toBeVisible();

    // الخط العربي محمّل من خادم البوابة
    await page.evaluate(() => document.fonts.ready);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, 'no horizontal scroll').toBeLessThanOrEqual(0);

    const width = page.viewportSize().width;
    await page.screenshot({ path: `docs/design/screenshots/${screen.name}-${width}.png`, fullPage: true });
}

test.describe('guest screens', () => {
    for (const screen of guestScreens) {
        test(screen.name, async ({ page }, testInfo) => {
            await checkScreen(page, testInfo, screen);
        });
    }

    test('mobile menu opens below the header', async ({ page }) => {
        test.skip(page.viewportSize().width >= 1280, 'القائمة كاملة على الحاسوب');

        await page.goto('/');
        await page.click('[aria-controls="mobile-menu"]');
        await expect(page.locator('#mobile-menu')).toBeVisible();
    });

    // لا يُحمَّل مشغّل يوتيوب حتى يضغط الزائر (المجموعة التجريبية وحدها — README)
    test('library video loads the player only when pressed', async ({ page }) => {
        const response = await page.goto('/library/videos/demo-video');
        test.skip(response?.status() === 404, 'يحتاج المجموعة التجريبية');

        await expect(page.locator('iframe')).toHaveCount(0);
        await page.click('[data-video-embed]');
        await expect(page.locator('iframe[src^="https://www.youtube-nocookie.com/embed/"]')).toHaveCount(1);
    });
});

test.describe('account screens', () => {
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_EMAIL وDEMO_PASSWORD');
    test.use({ storageState: AUTH_STATE });

    for (const screen of accountScreens) {
        test(screen.name, async ({ page }, testInfo) => {
            await checkScreen(page, testInfo, screen);
        });
    }
});
