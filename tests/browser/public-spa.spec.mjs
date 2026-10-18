// التنقّل السريع في الواجهة العامة (wire:navigate — docs/decisions.md، 15 أكتوبر 2026) على العروض الثلاثة: التنقّل
// بين الصفحات بلا إعادة تحميل، وقائمة الجوال والقوائم المنسدلة وقياس الشريط بعده، وتنزيل وثيقة، وزر الرجوع،
// والتركيز وعنوان التبويب، ولا أخطاء في وحدة التحكم، وفحص axe بعد التنقّل.
//
// اختبار التنزيل يحتاج وثيقة منشورة في الحوكمة، وبدونها يُتخطى. التشغيل في tests/browser/README.md.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { existsSync } from 'node:fs';
import { AUTH_STATE } from './global-setup.mjs';

// ── علامة في نافذة الصفحة: تبقى ما دام التنقّل بلا إعادة تحميل، وتزول عند أي تحميل كامل
async function markPage(page) {
    await page.evaluate(() => { window.__publicSpaMarker = 'kept'; });
}

async function marker(page) {
    return page.evaluate(() => window.__publicSpaMarker ?? null);
}

// ينقر الرابط وينتظر حتى يضع Livewire الصفحة الجديدة ويطلق livewire:navigated
async function follow(page, link, path) {
    await expect(link).toHaveAttribute('wire:navigate', '');
    await page.evaluate(() => {
        window.__navigated = false;
        document.addEventListener('livewire:navigated', () => { window.__navigated = true; }, { once: true });
    });
    await link.click();
    await page.waitForURL((url) => url.pathname === path);
    await page.waitForFunction(() => window.__navigated === true);
}

async function desktopNav(page) {
    return page.locator('[data-main-nav]').isVisible();
}

async function openMobileMenu(page) {
    const toggle = page.locator('[data-toggle="mobile-menu"]');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#mobile-menu')).toBeVisible();
}

// رابط في القائمة الرئيسية كما ينقره الزائر: في الشريط على الحاسب، ومن قائمة الجوال على اللوحي والجوال
async function viaMenu(page, path) {
    let link;

    if (await desktopNav(page)) {
        link = page.locator(`[data-main-nav] > ul > li > a[href$="${path}"]`).first();
    } else {
        await openMobileMenu(page);
        link = page.locator(`#mobile-menu nav > ul > li > a[href$="${path}"]`).first();
    }

    await follow(page, link, path);
}

test.describe.configure({ mode: 'serial' });

test.describe('public site quick navigation', () => {
    let page;
    const errors = [];
    // عنوان كل صفحة كما ظهر أول مرة، فيُعرف أن زر الرجوع أعاد الصفحة نفسها لا أبقى ما قبلها
    const headings = {};

    test.beforeAll(async ({ browser }, testInfo) => {
        const { baseURL, viewport, isMobile, hasTouch, locale, timezoneId } = testInfo.project.use;
        page = await (await browser.newContext({ baseURL, viewport, isMobile, hasTouch, locale, timezoneId, acceptDownloads: true })).newPage();
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('console', (message) => message.type() === 'error' && errors.push(`${message.text()} ${message.location().url}`));

        await page.goto('/');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await markPage(page);
    });

    test.afterAll(async () => page?.context().close());

    test('moves between four public pages without a full reload, with focus and the tab title updated', async () => {
        for (const path of ['/initiatives', '/governance', '/contact']) {
            await viaMenu(page, path);
            expect(await marker(page)).toBe('kept');

            // التركيز في المحتوى الرئيسي، وعنوان التبويب عنوان الصفحة الجديدة، والعنصر النشط في القائمة
            expect(await page.evaluate(() => document.activeElement?.id)).toBe('main');
            const heading = (await page.locator('main h1').first().innerText()).trim();
            headings[path] = heading;
            expect(await page.title()).toContain(heading);
            const current = page.locator(`[data-site-header] a[aria-current="page"][href$="${path}"]`);
            expect(await current.count()).toBeGreaterThan(0);
        }

        // والرابع الرئيسية من الشعار
        await follow(page, page.locator('[data-site-header] .container-page > a').first(), '/');
        expect(await marker(page)).toBe('kept');

        // شريط التقدّم من الموقع لا من Livewire، ويزول بعد التنقّل
        expect(await page.locator('#nprogress').count()).toBe(0);
        await expect(page.locator('html')).not.toHaveAttribute('data-navigating', /.*/);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
    });

    test('goes back and forward with the browser buttons without a full reload', async () => {
        // ينتظر حتى يضع Livewire النسخة المحفوظة، ثم يتحقق أنها الصفحة المطلوبة بعنوانها
        const historyStep = async (step, path) => {
            await page.evaluate(() => {
                window.__navigated = false;
                document.addEventListener('livewire:navigated', () => { window.__navigated = true; }, { once: true });
            });
            await step();
            await page.waitForURL((url) => url.pathname === path);
            await page.waitForFunction(() => window.__navigated === true);
            await expect(page.locator('main h1').first()).toHaveText(headings[path]);
        };

        await historyStep(() => page.goBack(), '/contact');
        await historyStep(() => page.goBack(), '/governance');
        await historyStep(() => page.goForward(), '/contact');
        expect(await marker(page)).toBe('kept');
    });

    test('opens and closes the mobile menu after navigation', async ({}, testInfo) => {
        test.skip(testInfo.project.name === 'desktop', 'زر القائمة على اللوحي والجوال');

        // القائمة التي فُتحت للتنقّل لا تبقى مفتوحة في الصفحة الجديدة
        await viaMenu(page, '/governance');
        const toggle = page.locator('[data-toggle="mobile-menu"]');
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(page.locator('#mobile-menu')).toBeHidden();

        await openMobileMenu(page);
        await toggle.click();
        await expect(page.locator('#mobile-menu')).toBeHidden();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');

        // Esc يغلقها أيضاً
        await openMobileMenu(page);
        await page.keyboard.press('Escape');
        await expect(page.locator('#mobile-menu')).toBeHidden();
        expect(await marker(page)).toBe('kept');
    });

    test('opens a dropdown after navigation and follows its link', async () => {
        const scope = (await desktopNav(page)) ? page.locator('[data-main-nav]') : page.locator('#mobile-menu');

        if (!(await desktopNav(page))) {
            await openMobileMenu(page);
        }

        // القائمة التي فيها «البرامج» (من بذرة القائمة)، لأن صفحات «عن الجمعية» مسودات حتى تعتمدها الجمعية
        const dropdown = scope.locator('li:has(> ul a[href$="/programs"]) > button[data-toggle="dropdown"]').first();
        await dropdown.click();
        await expect(dropdown).toHaveAttribute('aria-expanded', 'true');
        const submenu = page.locator(`#${await dropdown.getAttribute('aria-controls')}`);
        await expect(submenu).toBeVisible();

        await follow(page, submenu.locator('a[href$="/programs"]'), '/programs');
        expect(await marker(page)).toBe('kept');

        // والقائمة المنسدلة في الصفحة الجديدة تعمل، وEsc يغلقها
        if (!(await desktopNav(page))) {
            await openMobileMenu(page);
        }
        const again = scope.locator('button[data-toggle="dropdown"]').first();
        await again.click();
        await expect(page.locator(`#${await again.getAttribute('aria-controls')}`)).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(again).toHaveAttribute('aria-expanded', 'false');
    });

    test('keeps the header on one row after navigation, and still measures it', async ({}, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', 'الشريط ظاهر على الحاسب');

        await viaMenu(page, '/governance');
        const header = page.locator('[data-site-header]');
        await expect(page.locator('[data-main-nav]')).toBeVisible();
        await expect(header).not.toHaveAttribute('data-nav-overflow', /.*/);
        const lines = await page.evaluate(() => [...document.querySelectorAll('[data-main-nav] > ul > li > :is(a, button)')].map((link) => {
            const style = getComputedStyle(link);
            return Math.round((link.getBoundingClientRect().height - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom)) / parseFloat(style.lineHeight));
        }));
        expect(lines.every((count) => count === 1)).toBe(true);

        // القياس مربوط برأس الصفحة الجديدة: قائمة أطول مما يتسع تُظهر زر القائمة بدل الشريط
        await page.evaluate(() => {
            const list = document.querySelector('[data-main-nav] ul');
            const item = [...list.children].find((li) => li.querySelector(':scope > a'));
            for (const label of ['خدماتنا الإلكترونية', 'الدورات والبرامج', 'المركز الإعلامي']) {
                const copy = item.cloneNode(true);
                copy.querySelector('a').textContent = label;
                list.append(copy);
            }
        });
        await page.setViewportSize({ width: 1439, height: 900 });
        await expect(header).toHaveAttribute('data-nav-overflow', '');
        await page.setViewportSize(testInfo.project.use.viewport);

        // وصفحة جديدة تعيد الشريط كما هو
        await viaMenu(page, '/contact');
        await expect(header).not.toHaveAttribute('data-nav-overflow', /.*/);
    });

    test('downloads a governance document reached by navigation', async () => {
        await viaMenu(page, '/governance');
        const title = page.locator('main .file-card h3 a').first();
        test.skip((await title.count()) === 0, 'لا وثيقة منشورة في الحوكمة');

        await follow(page, title, new URL(await title.getAttribute('href')).pathname);

        const download = page.locator('main a[href$="/download"]').first();
        await expect(download).not.toHaveAttribute('wire:navigate', /.*/);
        const [file] = await Promise.all([page.waitForEvent('download'), download.click()]);
        expect(file.suggestedFilename()).toMatch(/\.pdf$/);
        expect(await marker(page)).toBe('kept');

        // والعرض في تبويب جديد لا يدخل في التنقّل
        await expect(page.locator('main a[href$="/file"]').first()).toHaveAttribute('target', '_blank');
    });

    test('passes axe on a page reached by navigation', async () => {
        await viaMenu(page, '/governance');
        await page.evaluate(() => document.fonts.ready);

        const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        const summary = violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.map((n) => n.target.join(' ')).slice(0, 5).join(' | ')}`);
        expect(summary).toEqual([]);
    });

    test('loads the login page fully, and logs no console errors on the way', async ({}, testInfo) => {
        const login = page.locator((await desktopNav(page)) || testInfo.project.name !== 'mobile'
            ? '[data-site-header] .container-page a[href$="/login"]'
            : '#mobile-menu a[href$="/login"]').first();

        if (testInfo.project.name === 'mobile') {
            await openMobileMenu(page);
        }

        await expect(login).not.toHaveAttribute('wire:navigate', /.*/);
        await Promise.all([page.waitForURL('**/login'), login.click()]);
        await expect(page.locator('#field-login')).toBeVisible();
        expect(await marker(page)).toBeNull();

        expect(errors).toEqual([]);
    });
});

test.describe('account area quick navigation', () => {
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS');
    test.use({ storageState: AUTH_STATE });

    test('moves between account pages from the side menu without a full reload', async ({ page }) => {
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));

        await page.goto('/account');
        await markPage(page);

        for (const path of ['/account/requests', '/account/programs', '/account/profile']) {
            // الشريط الجانبي على الحاسب، والقائمة المنسدلة على اللوحي والجوال
            const menu = page.locator('aside nav').filter({ visible: true }).first();

            if (!(await menu.isVisible())) {
                await page.locator('aside details > summary').click();
            }

            const link = page.locator(`aside a[href$="${path}"]`).filter({ visible: true }).first();
            await follow(page, link, path);
            expect(await marker(page)).toBe('kept');
            await expect(page.locator(`aside a[aria-current="page"][href$="${path}"]`).first()).toBeAttached();
        }

        // الرجوع لصاحب الحساب يطلب الصفحة من الخادم كاملة، لا نسخة Livewire المحفوظة (بيانات وحالات قديمة)
        await Promise.all([page.waitForEvent('load'), page.goBack()]);
        await page.waitForURL((url) => url.pathname === '/account/programs');
        expect(await marker(page)).toBeNull();
        await expect(page.locator('aside a[aria-current="page"][href$="/account/programs"]').first()).toBeAttached();

        expect(errors).toEqual([]);
    });
});

test('reloads the page fully when a deploy brings new assets during navigation', async ({ page }) => {
    await page.goto('/governance');
    await markPage(page);

    // نسخة ثانية من app.js كما يضيفها Livewire بعد نشر غيّر بصمة الملف. والأصول من خادم Vite (التشغيل الافتراضي) بلا
    // بصمة في اسمها، فلا نشر يُحاكى
    const built = page.locator('script[type="module"][src*="/build/assets/app-"]');
    test.skip((await built.count()) === 0, 'يحتاج الأصول المبنية (npm run build وVITE_HOST=none)');
    const src = await built.getAttribute('src');
    await Promise.all([
        page.waitForEvent('load'),
        page.evaluate((url) => import(`${url}?deploy`).catch(() => null), src).catch(() => null),
    ]);

    expect(await marker(page)).toBeNull();
    await expect(page.locator('main h1').first()).toBeVisible();
});

// ذاكرة الرجوع (bfcache) لا تعمل في متصفح Playwright المؤتمت ولو أُزيل مفتاح إطفائها: Chromium يرد عليها بـ
// BackForwardCacheDisabledForDelegate. فيُختبر هنا جزآن: مسار الخادم بعد الخروج كاملاً، وقرار page.js بحدث pageshow
// من الذاكرة يُطلق على الصفحة نفسها
test('shows the login page, not an account page, with the back button after signing out', async ({ browser }, testInfo) => {
    const credentials = process.env.DEMO_CREDENTIALS ? JSON.parse(process.env.DEMO_CREDENTIALS) : null;
    test.skip(!credentials, 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS');

    // دخول مستقل، فالخروج لا يُنهي الجلسة المحفوظة التي تستعملها الاختبارات الأخرى
    const { baseURL, viewport, isMobile, hasTouch, locale, timezoneId } = testInfo.project.use;
    const page = await (await browser.newContext({ baseURL, viewport, isMobile, hasTouch, locale, timezoneId })).newPage();
    await page.goto('/login');
    await page.fill('#field-login', credentials.email);
    await page.fill('#field-password', credentials.password);
    await Promise.all([page.waitForURL('**/account'), page.click('form button[type=submit]')]);

    // صفحة عامة بتحميل كامل أولاً، ثم الحساب بالتنقّل السريع، ثم الخروج
    await page.goto('/governance');
    await markPage(page);
    const account = () => page.locator('a[href$="/account"][wire\\:navigate]').filter({ visible: true }).first();
    if (!(await account().count())) {
        await openMobileMenu(page);
    }
    await follow(page, account(), '/account');
    expect(await marker(page)).toBe('kept');

    if (!(await page.locator('aside nav').filter({ visible: true }).count())) {
        await page.locator('aside details > summary').click();
    }
    await Promise.all([page.waitForURL((url) => url.pathname === '/'), page.locator('aside form[action$="/logout"] button').filter({ visible: true }).first().click()]);

    await page.goBack();
    await expect(page.locator('#field-login')).toBeVisible();
    await page.context().close();
});

test.describe('restore from the browser back cache', () => {
    const restoreFromBackCache = (page) => page.evaluate(() => {
        window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
    });

    test('keeps a guest page as it is', async ({ page }) => {
        await page.goto('/governance');
        await markPage(page);
        await restoreFromBackCache(page);
        await page.waitForTimeout(500);
        expect(await marker(page)).toBe('kept');
    });

    test.describe('signed in', () => {
        test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS');
        test.use({ storageState: AUTH_STATE });

        test('asks the server again for a page of a signed-in member', async ({ page }) => {
            // صفحة عامة بتحميل كامل ثم الحساب بالتنقّل: المستند عامّ الرؤوس وفيه صفحة حساب
            await page.goto('/governance');
            await markPage(page);
            const account = () => page.locator('a[href$="/account"][wire\\:navigate]').filter({ visible: true }).first();
            if (!(await account().count())) {
                await openMobileMenu(page);
            }
            await follow(page, account(), '/account');

            await Promise.all([page.waitForEvent('load'), restoreFromBackCache(page)]);
            expect(await marker(page)).toBeNull();
            await expect(page.locator('aside a[aria-current="page"][href$="/account"]').first()).toBeAttached();
        });
    });
});

test('puts the video link back before leaving, so Back does not start the video again', async ({ page }) => {
    // مقطع يوتيوب كما في الرئيسية وصفحة عنصر المكتبة، ولا يُطلب يوتيوب نفسه في الاختبار
    await page.route('https://www.youtube-nocookie.com/**', (route) => route.fulfill({ body: '' }));
    await page.goto('/governance');
    await page.evaluate(() => {
        const link = document.createElement('a');
        link.href = 'https://www.youtube.com/watch?v=test';
        link.dataset.videoEmbed = 'https://www.youtube-nocookie.com/embed/test?autoplay=1';
        link.dataset.videoTitle = 'مقطع تجريبي';
        link.textContent = 'مقطع تجريبي';
        link.id = 'test-video';
        document.querySelector('main').prepend(link);
    });
    await page.locator('#test-video').click();
    await expect(page.locator('main iframe[src*="youtube-nocookie"]')).toHaveCount(1);

    await follow(page, page.locator('footer a[href$="/contact"]'), '/contact');
    await page.goBack();
    await page.waitForURL((url) => url.pathname === '/governance');
    await expect(page.locator('#test-video')).toBeAttached();
    await expect(page.locator('main iframe[src*="youtube-nocookie"]')).toHaveCount(0);
});

test('loads the login page fully when a navigation reaches an account page without a session', async ({ page }) => {
    await page.goto('/governance');
    await markPage(page);

    // الخادم يرد بصفحة meta refresh (FullPageLoadOnNavigate) فيحمّل المتصفح صفحة الدخول كاملة
    await Promise.all([page.waitForURL('**/login'), page.evaluate(() => window.Livewire.navigate('/account'))]);
    await expect(page.locator('#field-login')).toBeVisible();
    expect(await marker(page)).toBeNull();
});

test('shows the site progress bar, not the Livewire one, while a navigation is slow', async ({ page }) => {
    await page.goto('/');
    await page.route('**/contact', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 800));
        await route.continue();
    });

    const link = page.locator('footer a[href$="/contact"]');
    await expect(link).toHaveAttribute('wire:navigate', '');
    await link.click();

    await expect(page.locator('html')).toHaveAttribute('data-navigating', '');
    expect(await page.locator('#nprogress').count()).toBe(0);
    await page.waitForURL((url) => url.pathname === '/contact');
    await expect(page.locator('html')).not.toHaveAttribute('data-navigating', /.*/);
});
