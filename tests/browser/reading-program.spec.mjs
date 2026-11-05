// برامج المكتبة (docs/decisions/2026-11-02-reading-program.md) على العروض الثلاثة: تبويبات «الدورات والبرامج» الستة من البيانات، ثم
// صفحة برنامج «قراءة كتاب» للمسجل فيه بتقدّمه وزر البدء، ثم قارئ الكتاب، و«البرامج المسجل بها» بشريط التقدّم. بلا تمرير أفقي.
// ولقطة لكل خطوة في docs/design/screenshots/reading-program/<الخطوة>-<العرض>.png.
//
// البيانات محلية تجريبية لا تُحفظ في قيت: ProgramTypesSeeder، وبرنامج «قراءة كتاب» منشور معرّفه READING_PROGRAM_SLUG
// (demo-reading-program افتراضياً) على كتاب منشور، والمستخدم التجريبي مسجل فيه (DEMO_CREDENTIALS من ejaz:demo-user --json).
import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const SHOTS = 'docs/design/screenshots/reading-program';
const SLUG = process.env.READING_PROGRAM_SLUG ?? 'demo-reading-program';
const DEMO = process.env.DEMO_CREDENTIALS ? JSON.parse(process.env.DEMO_CREDENTIALS) : null;

mkdirSync(SHOTS, { recursive: true });

const width = (page) => page.viewportSize().width;

async function expectRtlNoScroll(page) {
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'لا تمرير أفقي').toBeLessThanOrEqual(0);
}

async function settle(page) {
    await page.evaluate(() => document.fonts.ready);
    await page.waitForFunction(() =>
        document.getAnimations().every((animation) => animation.playState !== 'running' || animation.effect?.getTiming().iterations === Infinity),
    );
}

test('the programs page shows one bar of types from data, then competitions, scrolling to each type on the page', async ({ page }) => {
    await page.goto('/programs');
    await expectRtlNoScroll(page);

    // شريط واحد بالأنواع التي فيها برنامج منشور ثم «المسابقات» آخره (ملاحظات المدير التنفيذي، 4 نوفمبر 2026). والقاعدة المحلية قد تحمل
    // أنواعاً تجريبية أخرى، فيُفحص أن «قراءة الكتب» بين الأنواع (لبرنامج القراءة في الاختبار التالي) و«المسابقات» آخرها
    const bar = page.locator('[data-programs-bar]');
    const items = await bar.locator('[data-programs-bar-item]').evaluateAll((links) => links.map((link) => link.dataset.programsBarItem));
    expect(items).toContain('book-reading');
    expect(items.at(-1)).toBe('competitions');

    // الضغط ينزل إلى مقطع النوع في الصفحة نفسها، بلا تحميل ولا تغيّر في مسار الرابط
    const url = page.url();
    await bar.locator('[data-programs-bar-item="book-reading"]').click();
    await expect(page).toHaveURL(/\/programs#programs-book-reading$/);
    expect(page.url().split('#')[0]).toBe(url.split('#')[0]);
    await expect(page.locator('#programs-book-reading')).toBeInViewport();
    await settle(page);
    await page.screenshot({ path: `${SHOTS}/tabs-${width(page)}.png` });
});

test('a registered member starts a reading program and sees the progress', async ({ page }) => {
    test.skip(!DEMO, 'يحتاج مستخدماً تجريبياً: DEMO_CREDENTIALS من ejaz:demo-user --json');

    await page.goto('/login');
    await page.fill('#field-login', DEMO.email);
    await page.fill('#field-password', DEMO.password);
    await Promise.all([page.waitForURL((url) => !url.pathname.includes('login')), page.locator('main form button[type=submit]').first().click()]);

    await page.goto(`/programs/${SLUG}`);
    await expectRtlNoScroll(page);
    const panel = page.locator('[data-library-program="book-reading"]');
    await expect(panel).toBeVisible();
    await expect(panel.locator('[data-library-program-progress]')).toBeVisible();
    await settle(page);
    await page.screenshot({ path: `${SHOTS}/program-${width(page)}.png`, fullPage: true });

    await Promise.all([page.waitForURL(new RegExp(`/programs/${SLUG}/read`)), panel.getByRole('link', { name: 'ابدأ القراءة' }).click()]);
    await expect(page.locator('[data-pdf-reader]')).toBeVisible();
    await expectRtlNoScroll(page);
    await settle(page);
    await page.screenshot({ path: `${SHOTS}/reader-${width(page)}.png` });

    await page.goto('/account/programs');
    await expect(page.locator('[data-my-program-progress]').first()).toBeVisible();
    await expectRtlNoScroll(page);
    await page.screenshot({ path: `${SHOTS}/my-programs-${width(page)}.png`, fullPage: true });
});
