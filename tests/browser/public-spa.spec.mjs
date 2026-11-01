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

    // العنصر الرئيسي رابطاً، أو داخل قائمته المنسدلة (أولها الصفحة الأم — ملاحظات المدير، 1 نوفمبر 2026، البند ٤): تُفتح بزرها
    const root = (await desktopNav(page)) ? page.locator('[data-main-nav]') : (await openMobileMenu(page), page.locator('#mobile-menu nav'));
    link = root.locator(`a[href$="${path}"]`).first();

    if (!(await link.isVisible())) {
        const menu = link.locator('xpath=ancestor::ul[@id][1]');
        await root.locator(`[data-toggle="dropdown"][aria-controls="${await menu.getAttribute('id')}"]`).click();
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

            // التركيز على عنوان الصفحة الجديدة، وعنوان التبويب عنوانها ويُعلَن لقارئ الشاشة، والعنصر النشط في القائمة
            expect(await page.evaluate(() => document.activeElement?.matches('#main h1'))).toBe(true);
            const heading = (await page.locator('main h1').first().innerText()).trim();
            headings[path] = heading;
            expect(await page.title()).toContain(heading);
            await expect(page.locator('[data-page-announcer][aria-live="polite"]')).toHaveText(await page.title());
            const current = page.locator(`[data-site-header] a[aria-current="page"][href$="${path}"]`);
            expect(await current.count()).toBeGreaterThan(0);
        }

        // والرابع الرئيسية من الشعار
        await follow(page, page.locator('[data-site-header] .header-top > a').first(), '/');
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
        // سطر الاسم نفسه (.truncate) لا ارتفاع الكبسولة الثابت (44 بكسل وسطرها 24.5، فكان يُحسب سطرين)
        const lines = await page.evaluate(() => [...document.querySelectorAll('[data-main-nav] > ul > li > :is(a, button) > .truncate')].map((link) => {
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
            ? '[data-site-header] .header-top a[href$="/login"]'
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

test('focuses the labelled main content after navigation to a page without an h1', async ({ page }) => {
    // الصفحة نفسها بلا عنوان h1، كما يصلها المتصفح
    await page.route('**/contact', async (route) => {
        const response = await route.fetch();
        const body = (await response.text()).replace(/<h1\b([^>]*)>([\s\S]*?)<\/h1>/g, '<p$1>$2</p>');
        await route.fulfill({ response, body });
    });
    await page.goto('/');
    await follow(page, page.locator('footer a[href$="/contact"]'), '/contact');

    expect(await page.locator('main h1').count()).toBe(0);
    expect(await page.evaluate(() => document.activeElement?.id)).toBe('main');
    await expect(page.locator('#main')).toHaveAttribute('aria-label', /\S/);
});

// رأس الصفحة يدمجه Livewire مع رأس كل صفحة جديدة: ما تكرر منه لا يُضاف ثانية، فعدد عناصره ثابت، وLivewire واحد
test('keeps the head the same size across navigations, with a single Livewire running', async ({ page }) => {
    const warnings = [];
    page.on('console', (message) => warnings.push(message.text()));

    const head = () => page.evaluate(() => ({
        links: document.head.querySelectorAll('link').length,
        scripts: document.head.querySelectorAll('script').length,
        styles: document.head.querySelectorAll('style').length,
        livewire: document.querySelectorAll('script[src*="/livewire.js"], script[src*="/livewire.min.js"]').length,
    }));

    await page.goto('/');
    await markPage(page);
    const first = await head();
    expect(first.livewire).toBe(1);

    for (const path of ['/contact', '/governance', '/media', '/contact', '/']) {
        await navigateTo(page, path);
        expect(await head()).toEqual(first);
    }

    await page.goBack();
    await page.waitForURL((url) => url.pathname === '/contact');
    expect(await head()).toEqual(first);
    expect(await marker(page)).toBe('kept');
    expect(warnings.filter((text) => /multiple instances/i.test(text))).toEqual([]);
});

// ── وظائف الصفحة بعد التنقّل السريع: المعرض، والعدّاد، والحفظ التلقائي والرفع في المسابقة، والاختبار الإلكتروني.
// تحتاج بيانات تجريبية تُنشأ قبل التشغيل وتُحذف بعده (tests/browser/README.md)، وبدونها يُتخطى الاختبار
const FIXTURES = {
    album: '/media/albums/demo-spa-album',
    entry: '/competitions/demo-spa-entry',
    exam: '/competitions/demo-spa-exam',
};

const fixture = async (page, path) => (await page.request.get(path)).ok();

// تنقّل سريع بـ Livewire.navigate، لصفحة لا رابط ظاهراً إليها
async function navigateTo(page, path) {
    await page.evaluate(() => {
        window.__navigated = false;
        document.addEventListener('livewire:navigated', () => { window.__navigated = true; }, { once: true });
    });
    await page.evaluate((target) => window.Livewire.navigate(target), path);
    await page.waitForURL((url) => url.pathname === path);
    await page.waitForFunction(() => window.__navigated === true);
}

// تنقّل لا يبدّل الصفحة: الحدثان navigating ثم navigated على المحتوى نفسه، ثم رابط # داخل الصفحة بالتنقّل السريع
// (يطلق Livewire له navigated وحده)
async function navigationWithoutSwap(page) {
    await page.evaluate(() => {
        document.dispatchEvent(new CustomEvent('livewire:navigating', { detail: { onSwap: () => {} } }));
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
    });
    await page.evaluate(() => window.Livewire.navigate(`${window.location.pathname}#main`));
    await page.waitForURL((url) => url.hash === '#main');
}

// ورابط إلى الصفحة نفسها بلا #: يجلبها Livewire من جديد ويبدّلها، فتُهيّأ وظائفها على عناصرها الجديدة
const samePageNavigation = (page) => navigateTo(page, new URL(page.url()).pathname);

async function expectTicking(page) {
    const countdown = page.locator('[data-countdown]');
    await expect(countdown).not.toBeEmpty();
    const before = await countdown.innerText();
    await expect(countdown).not.toHaveText(before, { timeout: 3000 });
}

test('opens the gallery viewer on an album reached by navigation, one photo per press', async ({ page }) => {
    test.skip(!(await fixture(page, FIXTURES.album)), 'يحتاج الألبوم التجريبي (README)');

    await page.goto('/media/albums');
    await markPage(page);
    await follow(page, page.locator(`main a[href$="${FIXTURES.album}"]`).first(), FIXTURES.album);

    const dialog = page.locator('[data-gallery-dialog]');
    const counter = dialog.locator('[data-gallery-counter]');
    const browse = async () => {
        await page.locator('[data-gallery-open]').first().click();
        await expect(dialog).toBeVisible();
        await expect(counter).toHaveText(/^1\D+3$/);
        await dialog.locator('[data-gallery-next]').click();
        await expect(counter).toHaveText(/^2\D+3$/);
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(page.locator('html')).not.toHaveClass(/overflow-hidden/);
    };

    await browse();

    // وبعد مغادرته والرجوع إليه، وبعد تنقّل لا يبدّل الصفحة، وبعد رابط إليها: مستمع واحد لكل زر
    await follow(page, page.locator('footer a[href$="/contact"]'), '/contact');
    await page.goBack();
    await page.waitForURL((url) => url.pathname === FIXTURES.album);
    await browse();
    await navigationWithoutSwap(page);
    await browse();
    await samePageNavigation(page);
    await browse();
    expect(await marker(page)).toBe('kept');
});

// صفحة الخطأ تُعطى لطلب التنقّل كما هي (FullPageLoadOnNavigate): بلا تحميل كامل، ولا يحفظ Livewire رمز خطأ يحوّل
// الرجوع بعدها، فلا يذهب الرجوع والتقدّم إلى /undefined بعد رابط التخطي (مدخل في السجل بلا حالة)
test('shows an error page reached by navigation in place, and Back and Forward still work after it', async ({ page }) => {
    const visited = [];
    page.on('framenavigated', (frame) => frame === page.mainFrame() && visited.push(frame.url()));

    await page.goto('/governance');
    await markPage(page);
    const governance = (await page.locator('main h1').first().innerText()).trim();

    await navigateTo(page, '/media/news/no-such-article');
    await expect(page.locator('main h1').first()).not.toHaveText(governance);
    expect(await marker(page)).toBe('kept');

    await page.locator('a[href="#main"]').first().focus();
    await page.keyboard.press('Enter');
    await page.waitForURL((url) => url.hash === '#main');

    await page.goBack();
    await page.waitForURL((url) => url.pathname === '/media/news/no-such-article' && url.hash === '');
    await page.goForward();
    await page.waitForURL((url) => url.hash === '#main');
    await page.goBack();
    await page.goBack();
    await page.waitForURL((url) => url.pathname === '/governance');
    await expect(page.locator('main h1').first()).toHaveText(governance);
    expect(await marker(page)).toBe('kept');
    expect(visited.filter((url) => url.includes('undefined'))).toEqual([]);
});

test('keeps the live countdown running after navigation, and after a navigation that does not replace the page', async ({ page }) => {
    test.skip(!(await fixture(page, '/live')), 'يحتاج بثاً تجريبياً قادماً (README)');

    await page.goto('/governance');
    await markPage(page);
    await navigateTo(page, '/live');
    await expectTicking(page);

    await navigationWithoutSwap(page);
    await expectTicking(page);
    await samePageNavigation(page);
    await expectTicking(page);

    // والعودة إليه بعد مغادرته
    await follow(page, page.locator('footer a[href$="/contact"]'), '/contact');
    await page.goBack();
    await page.waitForURL((url) => url.pathname === '/live');
    await expectTicking(page);
    expect(await marker(page)).toBe('kept');
});

test.describe('competition pages reached by navigation', () => {
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS');
    test.use({ storageState: AUTH_STATE });

    // صفحة المسابقة ثم «أكمل مشاركتك» بالتنقّل السريع. والمسودة تُنشأ مرة بنموذجها (تحميل كامل)
    async function openEntry(page, competition, path) {
        test.skip(!(await fixture(page, competition)), 'يحتاج المسابقة التجريبية (README)');

        await page.goto(competition);
        const apply = page.locator('main form[action$="/entry/start"] button');

        if (await apply.count()) {
            await Promise.all([page.waitForURL((url) => url.pathname !== competition), apply.click()]);
            await page.goto(competition);
        }

        await markPage(page);
        await follow(page, page.locator(`main a[href$="${competition}/entry"]`).first(), path);
    }

    const autosaved = (page) => page.waitForResponse((response) => response.url().endsWith('/entry/autosave') && response.request().method() === 'POST');

    test('saves the entry automatically, also after a navigation that does not replace the page', async ({ page }) => {
        await openEntry(page, FIXTURES.entry, `${FIXTURES.entry}/entry`);
        const status = page.locator('[data-autosave-status]');
        const answer = page.locator('main textarea').first();

        const [first] = await Promise.all([autosaved(page), answer.fill(`إجابة تجريبية ${Date.now()}`)]);
        expect(first.ok()).toBe(true);
        await expect(status).toHaveText(await status.getAttribute('data-saved'));

        // تنقّل لا يبدّل الصفحة أثناء مهلة الحفظ (ثانيتان ونصف بعد آخر كتابة): يُحفظ ما كُتب
        const [second] = await Promise.all([autosaved(page), (async () => {
            await answer.fill(`إجابة تجريبية ثانية ${Date.now()}`);
            await navigationWithoutSwap(page);
        })()]);
        expect(second.ok()).toBe(true);

        // ورابط إلى الصفحة نفسها يبدّلها: الحفظ على النموذج الجديد
        await samePageNavigation(page);
        const [third] = await Promise.all([autosaved(page), page.locator('main textarea').first().fill(`إجابة تجريبية ثالثة ${Date.now()}`)]);
        expect(third.ok()).toBe(true);
        expect(await marker(page)).toBe('kept');
    });

    // الجزء معلّق حتى يُطلق، فيبقى الرفع جارياً
    async function startHeldUpload(page) {
        let release;
        const held = new Promise((resolve) => { release = resolve; });
        await page.route('**/competition-videos/*/chunks/*', async (route) => {
            await held;
            await route.continue().catch(() => null);
        });

        const box = page.locator('[data-video-upload]').first();
        await Promise.all([
            page.waitForRequest((request) => request.url().includes('/chunks/')),
            box.locator('[data-video-input]').setInputFiles({ name: 'clip.mp4', mimeType: 'video/mp4', buffer: Buffer.alloc(64 * 1024) }),
        ]);

        return { release, leave: JSON.parse(await box.getAttribute('data-messages')).leave };
    }

    test('asks before leaving while a video uploads, and lets go once it ends', async ({ page }) => {
        await openEntry(page, FIXTURES.entry, `${FIXTURES.entry}/entry`);
        const { release, leave } = await startHeldUpload(page);
        const entry = `${FIXTURES.entry}/entry`;
        const dialogs = [];
        page.on('dialog', async (dialog) => {
            dialogs.push([dialog.type(), dialog.message()]);
            await dialog.dismiss();
        });

        // تنقّل لا يبدّل الصفحة لا يقطع الرفع ولا يُسأل عنه، وكذلك الرجوع منه في الصفحة نفسها
        await navigationWithoutSwap(page);
        await page.goBack();
        await page.waitForURL((url) => url.pathname === entry && url.hash === '');
        expect(dialogs).toEqual([]);

        // رابط بالتنقّل السريع: تأكيد بنصه من ملفات الترجمة، والبقاء يُبقي الصفحة والرفع
        const contact = page.locator('footer a[href$="/contact"]');
        await expect(contact).toHaveAttribute('wire:navigate', '');
        await contact.click();
        await expect.poll(() => dialogs.length).toBe(1);
        expect(dialogs[0]).toEqual(['confirm', leave]);

        // وزر الرجوع إلى صفحة المسابقة: العنوان يعود إلى الصفحة الباقية
        await page.goBack();
        await expect.poll(() => dialogs.length).toBe(2);
        expect(dialogs[1]).toEqual(['confirm', leave]);
        await page.waitForURL((url) => url.pathname === entry);

        // والتحميل الكامل: يسأل المتصفح نفسه (beforeunload)
        await page.evaluate(() => setTimeout(() => window.location.reload(), 0));
        await expect.poll(() => dialogs.length).toBe(3);
        expect(dialogs[2][0]).toBe('beforeunload');
        await expect(page.locator('html[data-navigating]')).toHaveCount(0);
        expect(await marker(page)).toBe('kept');

        // اكتمال الرفع يزيل الحارس
        await Promise.all([page.waitForResponse((response) => response.url().endsWith('/complete')), release()]);
        await follow(page, contact, '/contact');
        expect(dialogs).toHaveLength(3);
        expect(await marker(page)).toBe('kept');

        // والرجوع بعدها يمر بصفحة المشاركة ثم صفحة المسابقة، كلٌّ بمحتواها (لصاحب الحساب من الخادم)
        await Promise.all([page.waitForEvent('load'), page.goBack()]);
        await page.waitForURL((url) => url.pathname === entry);
        await expect(page.locator('[data-video-upload]').first()).toBeVisible();
        await page.goBack();
        await page.waitForURL((url) => url.pathname === FIXTURES.entry);
        await expect(page.locator(`main a[href$="${entry}"]`).first()).toBeVisible();
        expect(dialogs).toHaveLength(3);
    });

    test('keeps the upload when Back stays on the same page, also after the page was reloaded', async ({ page }) => {
        await openEntry(page, FIXTURES.entry, `${FIXTURES.entry}/entry`);

        // بعد تحديث الصفحة تبقى حالة مدخلها من المستند السابق، فلولا الحارس لجلبها Livewire من جديد عند الرجوع إليه
        await page.reload();
        const { release } = await startHeldUpload(page);
        const dialogs = [];
        page.on('dialog', async (dialog) => {
            dialogs.push(dialog.type());
            await dialog.dismiss();
        });

        // رابط التخطي مدخل في السجل للصفحة نفسها، والرجوع منه لا يغادرها
        await page.locator('a[href="#main"]').first().focus();
        await page.keyboard.press('Enter');
        await page.waitForURL((url) => url.hash === '#main');
        await page.goBack();
        await page.waitForURL((url) => url.hash === '');

        expect(dialogs).toEqual([]);
        await expect(page.locator('[data-video-progress]').first()).toBeVisible();
        await Promise.all([page.waitForResponse((response) => response.url().endsWith('/complete')), release()]);
    });

    test('stops the upload when leaving is confirmed, without asking twice', async ({ page }) => {
        await openEntry(page, FIXTURES.entry, `${FIXTURES.entry}/entry`);
        const { release } = await startHeldUpload(page);
        const dialogs = [];
        const completed = [];
        page.on('dialog', async (dialog) => {
            dialogs.push(dialog.type());
            await dialog.accept();
        });
        page.on('request', (request) => request.url().endsWith('/complete') && completed.push(request.url()));

        await follow(page, page.locator('footer a[href$="/contact"]'), '/contact');
        expect(dialogs).toEqual(['confirm']);
        expect(await marker(page)).toBe('kept');

        // الرفع توقف: الجزء المعلّق لا يكمله
        release();
        await page.waitForTimeout(500);
        expect(completed).toEqual([]);

        // ولا حارس بعدها: التحميل الكامل لا يُسأل عنه
        await page.reload();
        expect(dialogs).toEqual(['confirm']);
    });

    test('enables the exam registration button once its fields are complete', async ({ page }) => {
        await openEntry(page, FIXTURES.exam, `${FIXTURES.exam}/exam`);
        const form = page.locator('form[data-exam-gate]');
        test.skip((await form.count()) === 0, 'المستخدم التجريبي تجاوز التسجيل في الاختبار التجريبي');

        const button = form.locator('[data-exam-gate-submit]');
        await expect(button).toBeDisabled();
        await form.locator('[name="full_name"]').fill('مستخدم تجريبي');
        await form.locator('[name="education_stage"]').selectOption({ index: 1 });
        await form.locator('[name="pledge"]').check();
        await expect(button).toBeEnabled();
        expect(await marker(page)).toBe('kept');
    });
});
