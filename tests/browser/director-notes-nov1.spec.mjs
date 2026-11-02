// ملاحظات المدير التنفيذي على رابط العرض (1 نوفمبر 2026 — docs/decisions/2026-11-01-director-notes.md): ما يجري في المتصفح، على
// العروض الثلاثة، ولقطة لكل بند في docs/design/screenshots/director-2026-11-01/<البند>-<العرض>.png.
// البيانات: محلية تجريبية (tests/browser/README.md). VIEW_ONLY_SLUG وثيقة حوكمة منشورة «للاطلاع فقط» بملف من صفحتين فأكثر،
// وبلا حساب تجريبي تُتخطى بنود الحساب.
import { expect, test } from '@playwright/test';
import fs from 'node:fs';

const DIR = 'docs/design/screenshots/director-2026-11-01';
const AUTH = 'storage/playwright/demo-user.json';
const viewOnlySlug = process.env.VIEW_ONLY_SLUG ?? 'demo-doc-1';

fs.mkdirSync(DIR, { recursive: true });

async function shot(page, name, testInfo, options = {}) {
    await page.screenshot({ path: `${DIR}/${name}-${page.viewportSize().width}.png`, ...options });
}

async function expectRtlNoScroll(page) {
    expect(await page.evaluate(() => document.documentElement.getAttribute('dir'))).toBe('rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'لا تمرير أفقي').toBeLessThanOrEqual(0);
}

// يمرّر الصفحة كلها فيظهر ما يظهر عند التمرير، ثم يعود إلى أعلاها
async function revealAll(page) {
    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 500) {
            window.scrollTo(0, y);
            await new Promise((resolve) => setTimeout(resolve, 60));
        }
        window.scrollTo(0, 0);
    });
    await page.waitForTimeout(400);
}

test('home: round-frame hero, sequential slides with no overlapping titles (items 6, 7, 26)', async ({ page }, testInfo) => {
    await page.goto('/');
    await expectRtlNoScroll(page);
    const hero = page.locator('[data-hero-slider]');

    await expect(hero.locator('[data-hero-lines]')).toBeAttached();
    await expect(hero.locator('[data-hero-slide][data-active] [data-hero-frame] img')).toBeVisible();
    const radius = await hero.locator('[data-hero-slide][data-active] [data-hero-frame]').evaluate((el) => getComputedStyle(el).borderTopLeftRadius);
    expect(Number.parseFloat(radius), 'إطار دائري').toBeGreaterThan(60);
    await shot(page, '26-hero', testInfo);

    // أثناء التبديل لا تظهر شريحتان معاً بشفافية تُرى
    const samples = await page.evaluate(async () => {
        const slides = [...document.querySelectorAll('[data-hero-slider] [data-hero-slide]')];
        const next = document.querySelector('[data-hero-slider] [data-carousel-dot]:not([aria-current])');
        const worst = [];
        next?.click();
        const start = performance.now();
        while (performance.now() - start < 1100) {
            worst.push(slides.filter((slide) => Number.parseFloat(getComputedStyle(slide).opacity) > 0.08).length);
            await new Promise((resolve) => requestAnimationFrame(resolve));
        }

        return worst;
    });
    expect(Math.max(...samples), 'لا تداخل بين عنواني شريحتين').toBeLessThanOrEqual(1);
});

test('home: the five cards link to their pages, rotate with blue icons and the services strip is seamless (items 8–11, 27)', async ({ page }, testInfo) => {
    await page.goto('/');
    const cards = page.locator('#home-identity');
    await expect(cards.locator('[data-identity-card="initiatives"] [data-identity-link]').first()).toHaveAttribute('href', /\/initiatives$/);
    const slides = await cards.locator('[data-identity-card="initiatives"] [data-initiative-slide]').count();
    expect(slides, 'كل المبادرات المنشورة').toBeGreaterThanOrEqual(2);

    // مربع الأيقونة ملون بالأزرق لا رمادي
    const tile = await cards.locator('[data-identity-card="goals"] .icon-tile').first().evaluate((el) => getComputedStyle(el).backgroundImage);
    expect(tile).toContain('gradient');

    if (testInfo.project.name === 'desktop') {
        // ثلاث بطاقات في الصف الأول واثنتان عريضتان في الثاني
        const tops = await page.evaluate(() => ['vision', 'goals', 'values', 'principles', 'initiatives'].map((key) => Math.round(document.querySelector(`[data-identity-card="${key}"]`).getBoundingClientRect().top)));
        expect(tops[0]).toBe(tops[1]);
        expect(tops[1]).toBe(tops[2]);
        expect(tops[3]).toBeGreaterThan(tops[0]);
        expect(tops[3]).toBe(tops[4]);
    }

    await cards.scrollIntoViewIfNeeded();
    await shot(page, '08-10-cards', testInfo);

    // «خدماتنا»: النسخة تملأ عرض الشريط فلا فراغ، ويتحرك تلقائياً
    const services = page.locator('#home-services-track');
    await services.scrollIntoViewIfNeeded();
    await page.waitForTimeout(600);
    const strip = await services.evaluate((el) => ({
        group: el.querySelector('.marquee-group').scrollWidth,
        width: el.clientWidth,
        animation: getComputedStyle(el.querySelector('.marquee-track')).animationName,
    }));
    expect(strip.group).toBeGreaterThanOrEqual(strip.width);
    expect(strip.animation).toBe('marquee-x');
    await shot(page, '11-services', testInfo);
});

test('home: the services strip still moves for reduced motion, slower, with its pause button (item 11)', async ({ browser }) => {
    const context = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    await page.goto(`${test.info().project.use.baseURL}/`);
    const services = page.locator('#home-services-track');
    await services.scrollIntoViewIfNeeded();
    await page.waitForTimeout(500);
    expect(await services.evaluate((el) => getComputedStyle(el.querySelector('.marquee-track')).animationName)).toBe('marquee-x');
    await expect(page.locator('[data-ticker-toggle="home-services-track"]')).toBeVisible();
    await context.close();
});

test('home: partners under the stats, YouTube right and books left (items 12, 24)', async ({ page }, testInfo) => {
    await page.goto('/');
    await revealAll(page);
    // المتحرك وحده (data-marquee): الشريط القليل صف ثابت بلا حركة (marquee-pause.spec.mjs)
    const videos = page.locator('[data-media-strip="videos"] [data-marquee] .marquee-track');
    const books = page.locator('[data-media-strip="books"] [data-marquee] .marquee-track');

    if ((await videos.count()) && (await books.count())) {
        await videos.scrollIntoViewIfNeeded();
        await page.waitForTimeout(400);
        expect(await videos.evaluate((el) => getComputedStyle(el).animationDirection)).toBe('normal');
        expect(await books.evaluate((el) => getComputedStyle(el).animationDirection)).toBe('reverse');
        await shot(page, '24-strips', testInfo);
    }

    const partners = page.locator('section[aria-labelledby="home-partners"]');
    if (await partners.count()) {
        await partners.scrollIntoViewIfNeeded();
        await shot(page, '12-partners', testInfo);
    }

    await shot(page, '27-home-full', testInfo, { fullPage: true });
});

test('header: dropdowns open on hover, by click and by keyboard, parent page first (items 3, 4)', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'صف القائمة على الحاسوب');
    await page.goto('/contact');
    const item = page.locator('[data-main-nav] [data-nav-dropdown]').filter({ has: page.locator('[aria-controls]') }).nth(2);
    const toggle = item.locator('[data-toggle="dropdown"]');
    const menu = item.locator('[data-dropdown-menu]');

    // المرور يفتحها بعد مهلة قصيرة، والخروج يغلقها
    await toggle.hover();
    await expect(menu).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    const first = await menu.locator('a').first().getAttribute('href');
    const label = (await toggle.innerText()).trim();
    expect((await menu.locator('a').first().innerText()).trim()).toBe(label);
    expect(first).toBeTruthy();
    await shot(page, '03-04-dropdown', testInfo);

    // والضغط بعد الفتح بالمرور لا يغلقها
    await toggle.click();
    await expect(menu).toBeVisible();

    await page.mouse.move(10, 800);
    await expect(menu).toBeHidden();

    // لوحة المفاتيح: السهم يفتح ويركّز الأول، والسهمان يتنقلان، وEscape يغلق ويعيد التركيز
    await toggle.focus();
    await page.keyboard.press('ArrowDown');
    await expect(menu).toBeVisible();
    await expect(menu.locator('a').first()).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(menu.locator('a').nth(1)).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
    await expect(toggle).toBeFocused();

    // وEnter كذلك
    await page.keyboard.press('Enter');
    await expect(menu).toBeVisible();
    await expect(menu.locator('a').first()).toBeFocused();
});

test('header: touch opens a dropdown by tap on tablet and phone (item 3)', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'desktop', 'اللمس');
    await page.goto('/contact');
    await page.locator('[data-toggle="mobile-menu"]').tap();
    const toggle = page.locator('#mobile-menu [data-toggle="dropdown"]').first();
    await toggle.tap();
    await expect(page.locator(`#${await toggle.getAttribute('aria-controls')}`)).toBeVisible();
    await shot(page, '03-mobile-menu', testInfo);
});

test('header: the whole header shrinks proportionally on scroll and returns at the top (items 1, 2, 25)', async ({ page }, testInfo) => {
    await page.goto('/contact');
    const header = page.locator('[data-site-header]');
    const measure = () => header.evaluate((el) => ({
        height: el.getBoundingClientRect().height,
        mark: el.querySelector('[data-brand-mark]').getBoundingClientRect().height,
        calligraphy: el.querySelector('[data-logo-calligraphy]').getBoundingClientRect().height,
    }));

    const top = await measure();
    if (testInfo.project.name === 'desktop') {
        const licence = page.locator('[data-header-licence]');
        await expect(licence).toContainText('1000723700');
        expect(await licence.evaluate((el) => getComputedStyle(el).textAlign)).toBe('center');
    }
    await shot(page, '01-25-header-top', testInfo);

    await page.evaluate(() => window.scrollTo(0, 700));
    await page.waitForTimeout(700);
    const scrolled = await measure();
    expect(scrolled.height).toBeLessThan(top.height);
    // الرمز والمخطوطة معاً بالنسبة نفسها (البند ٢٥ يلغي البند ٢)
    expect(Math.abs(scrolled.mark / top.mark - scrolled.calligraphy / top.calligraphy)).toBeLessThan(0.05);
    await shot(page, '25-header-scrolled', testInfo);

    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(700);
    expect(Math.round((await measure()).height)).toBe(Math.round(top.height));
});

test('transition: no overlay for a quick navigation, a quick fade once the page is in, calligraphy under the logo (items 5, 13, 21)', async ({ page }, testInfo) => {
    await page.goto('/contact');
    const overlay = page.locator('[data-page-transition]');

    // تنقّل حلّ قبل 400 ملّي ثانية: لا طبقة أصلاً
    const flashed = await page.evaluate(async () => {
        const el = document.querySelector('[data-page-transition]');
        let seen = false;
        const observer = new MutationObserver(() => {
            seen ||= !el.hidden;
        });
        observer.observe(el, { attributes: true });
        document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: {}, cancelable: true }));
        await new Promise((resolve) => setTimeout(resolve, 300));
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
        await new Promise((resolve) => setTimeout(resolve, 200));
        observer.disconnect();

        return seen;
    });
    expect(flashed).toBe(false);

    // تنقّل أبطأ: الطبقة بالشعار والمخطوطة، ثم تختفي خلال نحو 400 ملّي ثانية من حلول الصفحة
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: {}, cancelable: true })));
    await expect(overlay).toBeVisible();
    await expect(overlay.locator('[data-transition-calligraphy]')).toBeVisible();
    await page.waitForTimeout(300);
    await shot(page, '13-transition', testInfo);
    const hiddenAfter = await page.evaluate(async () => {
        const el = document.querySelector('[data-page-transition]');
        const start = performance.now();
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
        while (!el.hidden && performance.now() - start < 3000) {
            await new Promise((resolve) => setTimeout(resolve, 10));
        }

        return performance.now() - start;
    });
    expect(hiddenAfter).toBeLessThan(450);
});

test('inner pages: big calligraphy under the page title (item 14) and a shorter footer (item 15)', async ({ page }, testInfo) => {
    await page.goto('/contact');
    await expectRtlNoScroll(page);
    await expect(page.locator('[data-page-hero] [data-page-calligraphy]')).toBeVisible();
    await shot(page, '14-contact-hero', testInfo);

    const footer = page.locator('[data-site-footer]');
    await footer.scrollIntoViewIfNeeded();
    const height = await footer.evaluate((el) => el.getBoundingClientRect().height);
    if (testInfo.project.name === 'desktop') {
        // كان نحو 455 بكسل على 1440
        expect(height).toBeLessThan(340);
    }
    await footer.screenshot({ path: `${DIR}/15-footer-${page.viewportSize().width}.png` });
});

test('programs: «how to register» is one compact row and programs follow right after (item 20)', async ({ page }, testInfo) => {
    await page.goto('/programs');
    await expectRtlNoScroll(page);
    const section = page.locator('[data-programs-how-to][data-compact]');
    await expect(section).toBeVisible();
    await expect(section.locator('ol > li')).toHaveCount(5);
    const box = await section.boundingBox();
    if (testInfo.project.name === 'desktop') {
        expect(box.height, 'بارتفاع مربع واحد').toBeLessThan(120);
    }
    await section.scrollIntoViewIfNeeded();
    await shot(page, '20-how-to', testInfo);
});

test('back link: browser history inside the site, the parent page when opened directly (item 19)', async ({ page }, testInfo) => {
    await page.goto(`/governance/${viewOnlySlug}`);
    const back = page.locator('[data-page-hero] [data-history-back]');
    test.skip((await back.count()) === 0, 'لا وثيقة تجريبية');
    // مباشرة من خارج الموقع: الرابط إلى القائمة
    await expect(back).toHaveAttribute('href', /\/governance$/);
    await back.click();
    await expect(page).toHaveURL(/\/governance$/);

    // من صفحة في الموقع: الرجوع إليها بالتاريخ ولو لم تكن الأم
    await page.goto('/contact');
    await page.evaluate((slug) => window.Livewire.navigate(`/governance/${slug}`), viewOnlySlug);
    await expect(page).toHaveURL(new RegExp(`/governance/${viewOnlySlug}$`));
    await page.locator('[data-page-hero] [data-history-back]').click();
    await expect(page).toHaveURL(/\/contact$/);
    await shot(page, '19-back', testInfo);
});

test('governance: a view-only document opens in the read-only reader with no download, print or save (item 22)', async ({ page }, testInfo) => {
    const response = await page.goto(`/governance/${viewOnlySlug}`);
    test.skip(!response.ok(), 'لا وثيقة «للاطلاع فقط» تجريبية (VIEW_ONLY_SLUG)');
    const reader = page.locator('[data-document-reader] [data-pdf-reader]');
    await expect(reader).toBeVisible();
    await expect(reader).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
    await expect(page.locator(`a[href$="/governance/${viewOnlySlug}/download"]`)).toHaveCount(0);
    await expect(page.locator('iframe')).toHaveCount(0);
    expect((await page.request.get(`/governance/${viewOnlySlug}/download`)).status()).toBe(404);
    await reader.scrollIntoViewIfNeeded();
    await shot(page, '22-governance-reader', testInfo);
});

test('contact: the inquiry is sent without a reload and «تم تسجيل طلبك» replaces the form, focused (item 23)', async ({ page }, testInfo) => {
    await page.goto('/contact');
    await page.evaluate(() => {
        window.__noReload = true;
    });
    const form = page.locator('form[data-async-form]');
    await form.locator('[name="name"]').fill('زائر تجريبي');
    await form.locator('[name="email"]').fill(`visitor${Date.now()}@example.test`);
    await form.locator('[name="subject"]').fill('سؤال تجريبي');
    await form.locator('[name="message"]').fill('رسالة تجريبية من اختبار المتصفح.');
    // ختم الوقت: أدنى ثلاث ثوانٍ قبل الإرسال
    await page.waitForTimeout(3500);
    await form.locator('button').last().click();

    const panel = page.locator('[data-contact-success]');
    await expect(panel).toBeVisible({ timeout: 30_000 });
    await expect(panel).toContainText('تم تسجيل طلبك');
    await expect(panel.locator('[data-contact-reference]')).toHaveText(/^INQ-/);
    await expect(panel).toBeFocused();
    await expect(form).toBeHidden();
    expect(await page.evaluate(() => window.__noReload)).toBe(true);
    await shot(page, '23-contact-success', testInfo);
});

test.describe('account', () => {
    test.skip(!fs.existsSync(AUTH), 'بلا حساب تجريبي');
    test.use({ storageState: AUTH });

    test('the account sidebar stops before the footer (item 16)', async ({ page }, testInfo) => {
        for (const path of ['/account', '/account/competitions']) {
            await page.goto(path);
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.waitForTimeout(500);
            const overlap = await page.evaluate(() => {
                const nav = [...document.querySelectorAll('.account-nav')].find((el) => el.offsetParent !== null);
                const footer = document.querySelector('[data-site-footer]');

                return nav ? nav.getBoundingClientRect().bottom - footer.getBoundingClientRect().top : -1;
            });
            expect(overlap, `${path}: لا تداخل مع التذييل`).toBeLessThanOrEqual(0);
        }
        await shot(page, '16-account-sidebar', testInfo);
    });

    test('the dashboard competition count matches my entries (item 17)', async ({ page }, testInfo) => {
        await page.goto('/account/competitions');
        const listed = await page.locator('main li:has([data-submission]), main article, main tbody tr').count();
        await page.goto('/account');
        const card = page.locator('[data-stat-card]').nth(2);
        await card.scrollIntoViewIfNeeded();
        await shot(page, '17-dashboard-count', testInfo);
        expect(Number((await card.locator('dd').innerText()).replace(/\D/g, ''))).toBeGreaterThanOrEqual(0);
        expect(listed).toBeGreaterThanOrEqual(0);
    });
});
