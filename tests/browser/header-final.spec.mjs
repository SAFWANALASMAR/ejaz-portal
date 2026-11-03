// الرأس النهائي (ترتيب صفوان، 2 نوفمبر 2026 — docs/decisions/2026-11-02-header-final.md، ويلغي البند ٢٥ من ملاحظات 1 نوفمبر):
// صفّان ثابتان معاً عند التمرير بلا أي صغر. الصف الأول بعرض الشاشة بهامش صغير: الرمز في أقصى اليمين، والمخطوطة في منتصف الشاشة،
// ومربع الترخيص بجانبها من اليسار بفاصل صغير، والدخول والبحث في أقصى اليسار. والصف الثاني القائمة بكبسولاتها.
// ومن 1240 ترتيب المدير التنفيذي (3 نوفمبر 2026 — docs/decisions/2026-11-03-director-home.md): الرمز والمخطوطة والمربع كتلة يميناً
// مزاحة قليلاً عن الحافة، والبحث والدخول يساراً مزاحان كذلك.
// اللقطات: docs/design/screenshots/header-final/<العرض>-top.png و<العرض>-scrolled.png
import { expect, test } from '@playwright/test';

const WIDTHS = [1920, 1440, 1280, 1263, 820, 390];
const SHOTS = 'docs/design/screenshots/header-final';

// العروض صريحة في الاختبار، فيكفي مشروع واحد
test.beforeEach(async ({}, testInfo) => test.skip(testInfo.project.name !== 'desktop', 'العروض في الاختبار نفسه'));

function layout(page) {
    return page.evaluate(() => {
        const header = document.querySelector('[data-site-header]');
        const box = (selector) => {
            const element = selector === 'header' ? header : header.querySelector(selector);
            if (!element || getComputedStyle(element).display === 'none' || element.offsetParent === null) {
                return null;
            }
            const { left, right, top, bottom, width, height } = element.getBoundingClientRect();

            return { left, right, top, bottom, width, height };
        };

        return {
            viewport: document.documentElement.clientWidth,
            dir: document.documentElement.dir,
            noHorizontalScroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
            header: box('header'),
            top: box('.header-top'),
            nav: box('[data-main-nav]'),
            mark: box('[data-brand-mark]'),
            calligraphy: box('[data-logo-calligraphy]'),
            licence: box('[data-header-licence]'),
            login: box('.header-top .login-pill'),
            search: box('[data-search-open]'),
            burger: box('[data-toggle="mobile-menu"]'),
            capsule: box('.nav-capsule'),
            capsules: header.querySelectorAll('.nav-capsule').length,
        };
    });
}

// كل مقاس بعد التمرير كما كان في أعلى الصفحة (لا صغر)
function expectSameSizes(before, after) {
    for (const key of ['header', 'top', 'nav', 'mark', 'calligraphy', 'licence', 'login', 'search', 'burger', 'capsule']) {
        expect(after[key] === null, `${key}: الظهور نفسه`).toBe(before[key] === null);
        if (before[key]) {
            expect(Math.abs(after[key].width - before[key].width), `${key}: العرض`).toBeLessThan(0.5);
            expect(Math.abs(after[key].height - before[key].height), `${key}: الارتفاع`).toBeLessThan(0.5);
        }
    }
}

for (const width of WIDTHS) {
    test(`header at ${width}px: positions, both rows pinned, no shrinking on scroll`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);

        const top = await layout(page);
        const vw = top.viewport;
        expect(top.dir).toBe('rtl');
        expect(top.noHorizontalScroll).toBe(true);

        if (width >= 1240) {
            // من 1240 (تصحيح المدير التنفيذي، 3 نوفمبر 2026، البند ١٢ — director-home-2026-11-03.spec.mjs، ويحل محل الفراغات المتساوية
            // في #154 ومحل «الكتلة اليمنى»): المخطوطة في منتصف الشاشة، والرمز بجانبها يميناً بفاصل صغير لا في أقصى اليمين
            expect(Math.abs((top.calligraphy.left + top.calligraphy.right) / 2 - vw / 2)).toBeLessThanOrEqual(3);
            expect(top.mark.left - top.calligraphy.right).toBeGreaterThanOrEqual(4);
            expect(top.mark.left - top.calligraphy.right).toBeLessThanOrEqual(16);
        } else {
            // الرمز في أقصى اليمين بهامش صغير
            expect(vw - top.mark.right).toBeLessThanOrEqual(24);
            expect(vw - top.mark.right).toBeGreaterThanOrEqual(8);
        }

        if (width >= 1240) {
            // سبق في الشرط أعلاه
        } else if (width >= 800) {
            // المخطوطة في منتصف الشاشة
            expect(Math.abs((top.calligraphy.left + top.calligraphy.right) / 2 - vw / 2)).toBeLessThanOrEqual(3);
        } else {
            // الجوال كما كان: المخطوطة بجانب الرمز، وزر القائمة في أقصى اليسار بلا تراكب
            expect(top.mark.left - top.calligraphy.right).toBeGreaterThanOrEqual(4);
            expect(top.calligraphy.left).toBeGreaterThan(top.search.right);
        }

        if (width >= 1240) {
            // الصف الثاني تحت الأول، بكبسولاته
            expect(top.nav).not.toBeNull();
            expect(top.nav.top).toBeGreaterThanOrEqual(top.top.bottom - 0.5);
            // سبعة عناصر بعد MainMenuSevenSeeder (البيئة المحلية والعرض)
            expect(top.capsules).toBe(7);
            expect(top.burger).toBeNull();

            // مربع الترخيص يسار المخطوطة قريباً جداً منها (توجيه المدير التنفيذي، 3 نوفمبر 2026)
            expect(top.licence).not.toBeNull();
            const gap = top.calligraphy.left - top.licence.right;
            expect(gap).toBeGreaterThanOrEqual(4);
            expect(gap).toBeLessThanOrEqual(16);

            // الدخول في اليسار مزاحاً قليلاً عن الحافة، والبحث بجانبه، وكلاهما يسار مربع الترخيص
            expect(top.login.left).toBeLessThanOrEqual(80);
            expect(top.login.left).toBeGreaterThanOrEqual(36);
            expect(top.search.left - top.login.right).toBeGreaterThanOrEqual(0);
            expect(top.search.left - top.login.right).toBeLessThanOrEqual(16);
            expect(top.licence.left).toBeGreaterThanOrEqual(top.search.right);
        } else {
            // اللوحي والجوال: زر القائمة في أقصى اليسار، والبحث (والدخول على اللوحي) بجانبه
            expect(top.burger.left).toBeLessThanOrEqual(24);
            const next = top.login ?? top.search;
            expect(next.left - top.burger.right).toBeLessThanOrEqual(16);
            expect(top.calligraphy.left).toBeGreaterThan((top.login ?? top.search).right);
        }

        await page.screenshot({ path: `${SHOTS}/${width}-top.png`, clip: { x: 0, y: 0, width, height: Math.ceil(top.header.bottom) + 120 } });

        await page.evaluate(() => window.scrollTo({ top: 1400, behavior: 'instant' }));
        await page.waitForTimeout(500);
        const scrolled = await layout(page);

        // الصفّان معاً ثابتان في أعلى الشاشة، بالمقاسات نفسها
        expect(Math.round(scrolled.header.top)).toBe(0);
        expect(Math.round(scrolled.top.top)).toBe(0);
        if (scrolled.nav) {
            expect(Math.abs(scrolled.nav.top - top.nav.top)).toBeLessThan(0.5);
        }
        expectSameSizes(top, scrolled);
        expect(Math.abs(scrolled.calligraphy.left - top.calligraphy.left)).toBeLessThan(0.5);
        expect(scrolled.noHorizontalScroll).toBe(true);

        await page.screenshot({ path: `${SHOTS}/${width}-scrolled.png`, clip: { x: 0, y: 0, width, height: Math.ceil(scrolled.header.bottom) + 120 } });
    });
}

test('header: search comes before the login button, so login sits at the far left', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    const login = page.locator('.header-top .login-pill');
    await expect(login).toHaveAttribute('href', /\/login$/);

    // الترتيب في الشيفرة: البحث قبل الدخول، فالدخول في أقصى اليسار (RTL)
    const order = await page.evaluate(() => {
        const actions = document.querySelector('.header-actions');
        const search = actions.querySelector('[data-search-open]');
        const pill = actions.querySelector('.login-pill');

        return search.compareDocumentPosition(pill) & Node.DOCUMENT_POSITION_FOLLOWING;
    });
    expect(order).toBeTruthy();
});

// مراجعة المشرف على #130: التركيز بلوحة المفاتيح لا يختفي تحت الرأس الثابت (scroll-padding-top على html)، ولا تحت شارة البث في
// صف القائمة: تُحقن شارة (data-live-badge) في آخر الصف كما يضعها الخادم أثناء البث
for (const withBadge of [false, true]) {
    test(`header: keyboard focus moving backwards is never hidden under the pinned header at 1440px${withBadge ? ', with the live badge' : ''}`, async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);

        if (withBadge) {
            await page.evaluate(() => {
                document.querySelector('[data-main-nav] [data-live-badge]')?.remove();
                const badge = document.createElement('a');
                badge.href = '/live';
                badge.dataset.liveBadge = '';
                badge.className = 'inline-flex h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-white px-4 text-sm font-bold text-red-700';
                badge.innerHTML = '<span data-live-dot class="size-2 shrink-0 rounded-full bg-red-600"></span><span data-live-label>بث</span>';
                document.querySelector('[data-main-nav] .header-nav-row').append(badge);
            });
        }

        // زرّان في وسط المحتوى بينهما 100 بكسل، والثاني ملاصق لأسفل الرأس: فالأول داخل الشاشة لكن تحت الرأس، ولا يمرّر المتصفح إليه
        // عند Shift+Tab إلا بـ scroll-padding-top (WCAG 2.4.11). ثم يُكمل الرجوع في عناصر الصفحة الحقيقية
        const started = await page.evaluate(() => {
            const box = document.createElement('div');
            box.innerHTML = '<button type="button" data-probe="a">a</button><div style="height: 100px"></div><button type="button" data-probe="b">b</button>';
            const main = document.querySelector('main');
            main.insertBefore(box, main.children[Math.floor(main.children.length / 2)]);
            const second = box.querySelector('[data-probe="b"]');
            const headerBottom = document.querySelector('[data-site-header]').getBoundingClientRect().bottom;
            window.scrollTo({ top: second.getBoundingClientRect().top + window.scrollY - headerBottom - 4, behavior: 'instant' });
            second.focus({ preventScroll: true });
            const first = box.querySelector('[data-probe="a"]').getBoundingClientRect();

            return { focused: document.activeElement === second, underHeader: first.top > 0 && first.bottom < headerBottom };
        });
        expect(started).toEqual({ focused: true, underHeader: true });

        for (let step = 0; step < 6; step++) {
            await page.keyboard.press('Shift+Tab');
            await page.waitForTimeout(400);
            const state = await page.evaluate(() => {
                const focused = document.activeElement;
                const header = document.querySelector('[data-site-header]');
                const box = focused.getBoundingClientRect();
                const badge = header.querySelector('[data-live-badge]')?.getBoundingClientRect();

                return {
                    inHeader: header.contains(focused),
                    top: box.top,
                    headerBottom: header.getBoundingClientRect().bottom,
                    // الشارة داخل الرأس لا خارجه، فلا تقاطع العنصر المركّز
                    underBadge: Boolean(badge) && box.left < badge.right && badge.left < box.right && box.top < badge.bottom && badge.top < box.bottom,
                };
            });
            if (state.inHeader) {
                break;
            }
            expect(state.top, `الخطوة ${step + 1}: العنصر المركّز تحت الرأس`).toBeGreaterThanOrEqual(state.headerBottom - 1);
            expect(state.underBadge, `الخطوة ${step + 1}: العنصر المركّز تحت شارة البث`).toBe(false);
        }
    });
}

// مراجعة المشرف على #130: شارة البث عنصر في آخر صف القائمة (في مجراه)، بنصها متى اتسع الصف وإلا فنقطة نابضة، والقائمة ظاهرة بلا
// قص والمخطوطة في منتصف الشاشة أثناء البث. يلزمه بث جارٍ أو قادم منشور في البيئة (القالب نفسه في HeaderRowTest بلا شرط)
for (const width of [1920, 1440, 1280, 1263]) {
    test(`header at ${width}px with a live stream: the badge sits in the menu row and the calligraphy stays centred`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);
        test.skip((await page.locator('[data-live-badge]').count()) === 0, 'لا بث جارٍ أو قادم في هذه البيئة');

        const state = await page.evaluate(() => {
            const header = document.querySelector('[data-site-header]');
            const nav = header.querySelector('[data-main-nav]');
            const row = nav.getBoundingClientRect();
            const capsules = nav.querySelector('.nav-capsules').getBoundingClientRect();
            const calligraphy = header.querySelector('[data-logo-calligraphy]').getBoundingClientRect();
            const badge = nav.querySelector('[data-live-badge]');
            const box = badge?.getBoundingClientRect();

            return {
                // داخل صف القائمة نفسه، يسار الكبسولات بلا تراكب (RTL: آخر الصف)
                inMenuRow: Boolean(box) && box.height > 0 && box.top >= row.top && box.bottom <= row.bottom && box.right <= capsules.left + 1,
                inFirstRow: Boolean(header.querySelector('.header-top [data-live-badge]')),
                compact: badge?.hasAttribute('data-compact'),
                name: badge?.textContent.trim(),
                centreOffset: Math.abs((calligraphy.left + calligraphy.right) / 2 - document.documentElement.clientWidth / 2),
                navShown: getComputedStyle(nav).display !== 'none',
                burger: getComputedStyle(header.querySelector('[data-toggle="mobile-menu"]')).display !== 'none',
                clipped: [...nav.querySelectorAll('.nav-capsule > .truncate')].filter((label) => label.scrollWidth > label.clientWidth + 1).length,
                noHorizontalScroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
            };
        });
        expect(state.inMenuRow).toBe(true);
        expect(state.inFirstRow).toBe(false);
        // الاسم كاملاً في الشيفرة في الحالين (مخفي بصرياً في النقطة)
        expect(state.name).toMatch(/بث/);
        expect(state.centreOffset).toBeLessThanOrEqual(3);
        expect(state.navShown).toBe(true);
        expect(state.burger).toBe(false);
        expect(state.clipped).toBe(0);
        expect(state.noHorizontalScroll).toBe(true);
        console.log(`${width}: ${state.compact ? 'compact dot' : 'full text'}`);
        await page.screenshot({ path: `${SHOTS}/${width}-live.png`, clip: { x: 0, y: 0, width, height: 260 } });
    });
}

// حين لا يتسع الصف للشارة بنصها (قائمة أطول أو أسماء أطول من اللوحة) تصير نقطة نابضة قبل أن يظهر زر ☰ بدل القائمة. يُضيَّق
// الصف بفراغ أوسع بين الكبسولات خطوة خطوة، ويُعاد القياس بتغيير العرض
test('header with a live stream: the badge turns into a dot before the menu button replaces the menu', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);
    test.skip((await page.locator('[data-main-nav] [data-live-badge]').count()) === 0, 'لا بث جارٍ أو قادم في هذه البيئة');

    const seen = [];
    for (let gap = 8; gap <= 40; gap += 2) {
        await page.evaluate((value) => {
            let style = document.getElementById('gap-probe');
            if (!style) {
                style = document.head.appendChild(document.createElement('style'));
                style.id = 'gap-probe';
            }
            style.textContent = `.header-nav-row > .nav-capsules { gap: ${value}px !important; }`;
        }, gap);
        await page.setViewportSize({ width: 1440 - (gap % 4 === 0 ? 1 : 0), height: 900 });
        await page.waitForTimeout(150);
        seen.push(await page.evaluate(() => {
            const header = document.querySelector('[data-site-header]');
            const badge = header.querySelector('[data-main-nav] [data-live-badge]');

            return {
                compact: badge.hasAttribute('data-compact'),
                overflow: header.hasAttribute('data-nav-overflow'),
                label: getComputedStyle(badge.querySelector('[data-live-label]')).clipPath,
                width: Math.round(badge.getBoundingClientRect().width),
            };
        }));
    }

    // أولاً بنصها، ثم نقطة والقائمة ظاهرة، ثم زر القائمة
    const firstCompact = seen.findIndex((state) => state.compact);
    const firstOverflow = seen.findIndex((state) => state.overflow);
    expect(seen[0]).toMatchObject({ compact: false, overflow: false });
    expect(firstCompact).toBeGreaterThan(0);
    expect(seen[firstCompact].overflow).toBe(false);
    expect(seen[firstCompact].width).toBeLessThanOrEqual(44);
    expect(seen[firstCompact].label).not.toBe('none');
    expect(firstOverflow === -1 || firstOverflow > firstCompact).toBe(true);
});
