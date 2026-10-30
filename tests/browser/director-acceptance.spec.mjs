// قائمة القبول (docs/plans/2026-10-30-acceptance-checklist.md): اختبار متصفح لكل بند يُختبر آلياً، على العروض الثلاثة.
// كل اختبار يتحقق أيضاً من الاتجاه من اليمين ومن عدم التمرير الأفقي على عرضه.
//
// بنود main اختبارات فعلية. وبنود الفروع التي لم تُدمج مكتوبة كاملة ومعلَّمة test.fixme باسم فرعها، فيزيل كل فرع
// علامة بنوده عند دمجه، ولا يكتب الاختبار من جديد. والمحددات في بنود الفروع من شيفرتها الجارية (data-domains
// وdata-collapsible وdata-slides وdata-student-cta وdata-pdf-reader)، ومن أسماء المكونات في توجيه الدفعة الثانية
// (x-hero-slider ← [data-hero-slider]، وهكذا). فإن سمّى الفرع مكوّنه بغيرها عدّل المحدد مع إزالة العلامة.
//
// البيئة (كلها اختيارية، وما يحتاج غائباً يُتخطى برسالة):
//   DEMO_CREDENTIALS         {"email","password"} من ejaz:demo-user --json — حساب مستفيد لا يدخل اللوحة.
//   BENEFICIARY_CREDENTIALS  يتقدم على DEMO_CREDENTIALS إن كان المستخدم التجريبي مشرفاً.
//   COMPETITION_SLUG         مسابقة البراهين المنشورة (الافتراضي brahin-1448).
//   PRIZE_TOTAL              مجموع الجوائز كما يُعرض (الافتراضي 20,000).
//   LIBRARY_READONLY_PATH    مسار كتاب «للقراءة فقط» منشور، مثل /library/books/<slug>.
// التشغيل: tests/browser/README.md («قائمة القبول»).
import { expect, test } from '@playwright/test';

const AFTER = {
    brahin: 'يُفعَّل بعد دمج fix/brahin-director-notes',
    header: 'يُفعَّل بعد دمج feature/visual-refresh (#95) وfix/brahin-director-notes',
    refresh1: 'يُفعَّل بعد دمج feature/visual-refresh (#95)',
    refresh2: 'يُفعَّل بعد دمج feature/visual-refresh-2',
    library: 'يُفعَّل بعد دمج feature/library-read-only',
    howTo: 'يُفعَّل بعد دمج feature/programs-how-to',
    evidence: 'يُفعَّل بعد دمج feature/evidence-gallery',
};

const credentials = (() => {
    const raw = process.env.BENEFICIARY_CREDENTIALS ?? process.env.DEMO_CREDENTIALS;
    return raw ? JSON.parse(raw) : null;
})();
const SLUG = process.env.COMPETITION_SLUG ?? 'brahin-1448';
const COMPETITION = `/competitions/${SLUG}`;
const PRIZE_TOTAL = process.env.PRIZE_TOTAL ?? '20,000';

// الصفحات العامة الرئيسية. ما يُنشر من اللوحة قد يرد 404 قبل نشره (optional)
const publicPages = [
    { path: '/' },
    { path: '/about', optional: true },
    { path: '/initiatives' },
    { path: '/governance' },
    { path: '/library' },
    { path: '/library/videos' },
    { path: '/library/audio' },
    { path: '/library/books' },
    { path: '/library/research' },
    { path: '/media' },
    { path: '/media/news' },
    { path: '/media/announcements' },
    { path: '/services' },
    { path: '/services/jobs' },
    { path: '/programs' },
    { path: '/competitions' },
    { path: '/contact' },
    { path: '/login' },
    { path: '/register' },
    { path: '/forgot-password' },
    { path: COMPETITION, optional: true },
];

// الاتجاه من اليمين وبلا تمرير أفقي: في كل اختبار، على الصفحة التي انتهى إليها
async function expectRtlNoScroll(page, label = page.url()) {
    await expect(page.locator('html'), label).toHaveAttribute('dir', 'rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, `${label}: تمرير أفقي`).toBeLessThanOrEqual(0);
}

async function visit(page, path) {
    const response = await page.goto(path);
    await page.evaluate(() => document.fonts.ready);
    return response;
}

// صفحة المسابقة منشورة، وإلا يُتخطى الاختبار
async function openCompetition(page) {
    const response = await visit(page, COMPETITION);
    test.skip(response?.status() === 404, `لا مسابقة منشورة في ${COMPETITION} (COMPETITION_SLUG)`);
    expect(response?.status()).toBeLessThan(400);
}

async function login(page) {
    await page.fill('#field-login', credentials.email ?? credentials.username);
    await page.fill('#field-password', credentials.password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.locator('main form button[type=submit]').first().click(),
    ]);
}

// أ يسبق ب في ترتيب الصفحة
async function expectBefore(first, second, label) {
    const follows = await first.evaluate((a, b) => Boolean(a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING), await second.elementHandle());
    expect(follows, label).toBe(true);
}

function sectionOf(page, heading) {
    return page.getByRole('heading', { name: heading, exact: true }).first().locator('xpath=ancestor::*[self::section or self::div][.//ol or .//ul or .//table][1]');
}

// ─── مدموج في main ───────────────────────────────────────────────────────────────────────────────────────────────

test.describe('acceptance: merged in main', () => {
    test('public pages open in RTL without horizontal scroll', async ({ page }) => {
        test.setTimeout(300_000);
        for (const { path, optional } of publicPages) {
            const response = await visit(page, path);
            const status = response?.status() ?? 0;
            if (optional && status === 404) {
                continue;
            }
            expect(status, path).toBeLessThan(400);
            await expect(page.locator('html'), path).toHaveAttribute('lang', 'ar');
            await expectRtlNoScroll(page, path);
        }
    });

    test('governance: the list and a document page', async ({ page }) => {
        const response = await visit(page, '/governance');
        expect(response?.status()).toBeLessThan(400);
        await expect(page.locator('h1').first()).toBeVisible();
        await expectRtlNoScroll(page, '/governance');

        const documentLink = page.locator('main a[href*="/governance/"]').first();
        test.skip((await documentLink.count()) === 0, 'لا وثيقة منشورة في الحوكمة');
        const href = await documentLink.getAttribute('href');
        const documentResponse = await visit(page, href);
        expect(documentResponse?.status(), href).toBeLessThan(400);
        await expect(page.locator('h1').first()).toBeVisible();
        await expectRtlNoScroll(page, href);
    });

    test('home: the director\'s section order', async ({ page }) => {
        await visit(page, '/');
        const order = ['#home-king', '#home-chairman', '#home-rationale', '#home-values', '#home-news-ticker', '#home-announcements', '#home-accounts'];
        const present = [];
        for (const selector of order) {
            if ((await page.locator(selector).count()) > 0) {
                present.push(selector);
            }
        }
        // كلمة خادم الحرمين وكلمة رئيس المجلس ثابتتان، والشريطان يظهران بمحتواهما
        expect(present.slice(0, 2)).toEqual(['#home-king', '#home-chairman']);
        for (let index = 1; index < present.length; index++) {
            await expectBefore(page.locator(present[index - 1]).first(), page.locator(present[index]).first(), `${present[index - 1]} قبل ${present[index]}`);
        }
        await expectRtlNoScroll(page, '/');
    });

    test('brahin page: published texts, prizes and the entry guide link', async ({ page }) => {
        await openCompetition(page);
        await expect(page.getByRole('heading', { name: 'مراحل المسابقة' }).first()).toBeAttached();
        await expect(page.getByRole('heading', { name: 'الجوائز' }).first()).toBeAttached();
        await expect(page.locator('main')).toContainText(PRIZE_TOTAL);
        await expect(page.getByRole('link', { name: 'دليل الدخول إلى الاختبار' }).first()).toBeAttached();
        await expectRtlNoScroll(page, COMPETITION);
    });

    test('#96: a beneficiary account opens after an admin link was left in the same browser', async ({ page }) => {
        test.skip(!credentials, 'يحتاج حساب مستفيد: BENEFICIARY_CREDENTIALS أو DEMO_CREDENTIALS');

        // زائر يفتح رابطاً في اللوحة فيُحفظ في الجلسة رابطاً مقصوداً، كما بقي بعد خروج المدير
        await visit(page, '/admin/competitions');
        await expect(page).toHaveURL(/\/admin\/login/);

        const documents = [];
        page.on('response', (response) => response.request().isNavigationRequest() && documents.push(response.status()));
        await visit(page, '/login');
        await login(page);
        await page.waitForLoadState('load');

        expect(documents.at(-1), `آخر صفحة: ${page.url()}`).toBeLessThan(400);
        expect(new URL(page.url()).pathname, 'المستفيد لا يُحوَّل إلى اللوحة').not.toMatch(/^\/admin/);
        await expect(page).toHaveURL(/\/account/);
        await expectRtlNoScroll(page);
    });

    test('#96: a guest logging in from the competition page returns to its registration', async ({ page }) => {
        test.skip(!credentials, 'يحتاج حساب مستفيد: BENEFICIARY_CREDENTIALS أو DEMO_CREDENTIALS');
        await openCompetition(page);
        const button = page.getByRole('link', { name: 'سجّل الدخول للتسجيل' }).first();
        test.skip((await button.count()) === 0, 'التسجيل في المسابقة غير مفتوح');

        await Promise.all([page.waitForURL('**/login'), button.click()]);
        await login(page);
        // إلى التسجيل، أو إلى صفحة المسابقة إن كان الحساب مسجلاً من قبل، لا إلى /account
        await expect(page).toHaveURL(new RegExp(`/competitions/${SLUG}(/register)?$`));
        await expectRtlNoScroll(page);
    });
});

// ─── fix/brahin-director-notes ───────────────────────────────────────────────────────────────────────────────────

test.describe('acceptance: Brahin director notes', () => {
    test('public pages: no «الفرع», no «مثال تجريبي», no «40 سؤالاً»', async ({ page }) => {
        test.fixme(true, AFTER.brahin);
        test.setTimeout(300_000);
        const forbidden = [/(^|[^\p{L}])(الفرع|الفروع)([^\p{L}]|$)/u, /مثال تجريبي/u, /40\s*سؤال/u];

        for (const { path, optional } of [...publicPages, { path: `${COMPETITION}/guide`, optional: true }]) {
            const response = await visit(page, path);
            if (optional && response?.status() === 404) {
                continue;
            }
            const text = await page.locator('body').innerText();
            for (const pattern of forbidden) {
                expect(text, `${path}: ${pattern}`).not.toMatch(pattern);
            }
            await expectRtlNoScroll(page, path);
        }
    });

    test('brahin page: sections in the director\'s order', async ({ page }) => {
        test.fixme(true, AFTER.brahin);
        await openCompetition(page);

        const steps = [
            ['البطل', page.getByRole('heading', { level: 1 }).first()],
            ['بالتنسيق مع وزارة التعليم', page.getByText('بالتنسيق مع وزارة التعليم').first()],
            ['مجالات المسابقة', page.getByRole('heading', { name: 'مجالات المسابقة' }).first()],
            ['مراحل المسابقة', page.getByRole('heading', { name: 'مراحل المسابقة' }).first()],
            ['الجوائز', page.getByRole('heading', { name: 'الجوائز' }).first()],
            ['الأقسام المطوية', page.locator('[data-collapsible]').first()],
            ['دليل الدخول بالشرائح', page.locator('[data-slides]').first()],
        ];
        for (const [, locator] of steps) {
            await expect(locator).toBeAttached();
        }
        for (let index = 1; index < steps.length; index++) {
            await expectBefore(steps[index - 1][1], steps[index][1], `${steps[index - 1][0]} قبل ${steps[index][0]}`);
        }
        await expect(page.locator('aside [data-student-cta]')).toBeVisible();
        await expectRtlNoScroll(page, COMPETITION);
    });

    test('brahin page: hero title, season badge, partner and prize total', async ({ page }, testInfo) => {
        test.fixme(true, AFTER.brahin);
        await openCompetition(page);

        const title = page.getByRole('heading', { level: 1 }).first();
        await expect(title).toContainText('مسابقة البراهين العلمية');
        await expect(page.getByText('الموسم الأول 1448هـ / 2026م').first()).toBeVisible();
        await expect(page.getByText('بالتنسيق مع وزارة التعليم').first()).toBeVisible();

        const prize = page.getByText('مجموع الجوائز', { exact: true }).first().locator('xpath=ancestor::*[1]/..');
        await expect(prize).toBeVisible();
        // العدّاد يصعد إلى الرقم المحسوب من prize_table
        await expect(prize).toContainText(PRIZE_TOTAL, { timeout: 10_000 });
        await expect(page.getByRole('link', { name: /شارك واربح/ }).first()).toBeVisible();

        if (testInfo.project.name === 'mobile') {
            // على الجوال: العنوان ثم البطاقة والزر بعرض كامل
            await expectBefore(title, prize, 'العنوان قبل بطاقة الجوائز');
            const join = await page.getByRole('link', { name: /شارك واربح/ }).first().boundingBox();
            const viewport = page.viewportSize();
            expect(join.width, 'زر «شارك واربح» بعرض كامل').toBeGreaterThan(viewport.width * 0.8);
        }
        await expectRtlNoScroll(page, COMPETITION);
    });

    test('brahin page: the hero and aside buttons share one action', async ({ page }) => {
        test.fixme(true, AFTER.brahin);
        await openCompetition(page);

        const ctas = page.locator('[data-student-cta]');
        await expect(ctas).toHaveCount(2);
        const heroHref = await ctas.nth(0).locator('a').first().getAttribute('href');
        const asideHref = await ctas.nth(1).locator('a').first().getAttribute('href');
        expect(heroHref, 'زر البطل وزر العمود إلى الوجهة نفسها').toBe(asideHref);
        await expectRtlNoScroll(page, COMPETITION);
    });

    test('brahin page: four domains and stage cards without counts or dates', async ({ page }) => {
        test.fixme(true, AFTER.brahin);
        await openCompetition(page);

        const domains = page.locator('[data-domains]');
        const branches = ['الحقائق العلمية في الأنفس', 'الحقائق العلمية في علوم الفلك والفضاء', 'الحقائق العلمية في علوم الأرض', 'الحقائق العلمية في علوم البحار'];
        for (const [index, ordinal] of ['المجال الأول', 'المجال الثاني', 'المجال الثالث', 'المجال الرابع'].entries()) {
            await expect(domains).toContainText(ordinal);
            await expect(domains).toContainText(branches[index]);
        }

        const rounds = sectionOf(page, 'مراحل المسابقة');
        await expect(rounds).toContainText('تمر المسابقة بأربع مراحل متتابعة، وتُعقد كل مرحلة يوم الخميس بحسب الجدول الزمني والتواريخ المعتمدة لها.');
        await expect(rounds).not.toContainText('المشاركة في جميع المراحل شرط');
        const first = rounds.locator('li').first();
        await expect(first).toContainText('المرحلة الأولى');
        await expect(first).toContainText('المجال الأول: الحقائق العلمية في الأنفس');

        // العنوان والمجال والحالة وحدها: لا عدد أسئلة ولا مدة ولا تاريخ (التواريخ بخيار «إظهار مواعيد المراحل» وحده)
        const cards = await rounds.locator('li').allInnerTexts();
        for (const card of cards) {
            expect(card).not.toMatch(/سؤال|أسئلة|دقيقة|دقائق/u);
            expect(card).not.toMatch(/20\d\d|\d{1,2}:\d{2}/u);
        }
        await expectRtlNoScroll(page, COMPETITION);
    });

    test('brahin page: collapsible text cards open and close', async ({ page }) => {
        test.fixme(true, AFTER.brahin);
        await openCompetition(page);

        const cards = page.locator('details[data-collapsible]');
        expect(await cards.count()).toBeGreaterThanOrEqual(5);
        await expect(page.locator('details[data-collapsible][open]')).toHaveCount(0);
        // المحتوى في الصفحة لمحركات البحث وهو مطوي
        await expect(page.locator('main')).toContainText('بناء جيل محب للعلم');

        const summary = cards.first().locator('summary');
        await summary.click();
        await expect(cards.first()).toHaveAttribute('open', '');
        await expect(summary).toHaveAttribute('aria-expanded', 'true');
        await summary.click();
        await expect(cards.first()).not.toHaveAttribute('open', '');
        await expect(summary).toHaveAttribute('aria-expanded', 'false');

        // بلوحة المفاتيح
        await summary.focus();
        await page.keyboard.press('Enter');
        await expect(cards.first()).toHaveAttribute('open', '');
        await page.keyboard.press('Enter');

        const all = page.locator('[data-collapsible-all]').first();
        await expect(all).toContainText('عرض الكل');
        await all.click();
        await expect(page.locator('details[data-collapsible]:not([open])')).toHaveCount(0);
        await expect(all).toContainText('إخفاء الكل');
        await all.click();
        await expect(page.locator('details[data-collapsible][open]')).toHaveCount(0);
        await expectRtlNoScroll(page, COMPETITION);
    });

    test('brahin page: entry guide slides move with next and previous', async ({ page }) => {
        test.fixme(true, AFTER.brahin);
        await openCompetition(page);

        const slides = page.locator('[data-slides]').first();
        await expect(slides).toHaveAttribute('data-slides-ready', '');
        const items = slides.locator('[data-slide]');
        expect(await items.count()).toBeGreaterThanOrEqual(2);
        await expect(items.nth(0)).toBeVisible();
        await expect(items.nth(1)).toBeHidden();
        await expect(slides.locator('[data-slide-previous]')).toBeDisabled();

        await slides.locator('[data-slide-next]').click();
        await expect(items.nth(1)).toBeVisible();
        await expect(items.nth(0)).toBeHidden();
        await expect(slides.locator('[data-slide-dot]').nth(1)).toHaveAttribute('aria-current', 'step');

        await slides.locator('[data-slide-previous]').click();
        await expect(items.nth(0)).toBeVisible();

        // الأسهم في لوحة المفاتيح، والنقاط
        await slides.locator('[data-slides-track]').focus();
        await page.keyboard.press('ArrowLeft');
        await expect(items.nth(1)).toBeVisible();
        await slides.locator('[data-slide-dot]').first().click();
        await expect(items.nth(0)).toBeVisible();
        await expectRtlNoScroll(page, COMPETITION);
    });

    test('brahin registration: three categories, required mobile and school', async ({ page }) => {
        test.fixme(true, AFTER.brahin);
        test.skip(!credentials, 'يحتاج حساب مستفيد: BENEFICIARY_CREDENTIALS أو DEMO_CREDENTIALS');
        await visit(page, `${COMPETITION}/register`);
        if (page.url().includes('/login')) {
            await login(page);
        }
        test.skip(!page.url().endsWith('/register'), 'التسجيل غير مفتوح أو الحساب مسجل من قبل');

        const categories = (await page.locator('#field-category_id option').allInnerTexts()).map((text) => text.trim()).filter((text) => text && !text.startsWith('اختر'));
        expect(categories).toEqual(['المرحلة الثانوية', 'المرحلة الجامعية', 'المهتمون بالثقافة العلمية']);
        await expect(page.locator('main')).not.toContainText('المرحلة المتوسطة');

        await expect(page.locator('#field-guardian_phone')).toHaveAttribute('required', '');
        await expect(page.locator('label[for="field-guardian_phone"]')).toHaveText(/^\s*رقم الجوال\s*\*?\s*$/);
        const school = page.getByLabel(/الجهة التعليمية \/ المدرسة/);
        await expect(school).toHaveAttribute('required', '');
        await expect(page.locator('main')).not.toContainText('(اختياري)');
        await expect(page.locator('main')).toContainText('أتعهد بأن أجيب عن الاختبار بنفسي وبأمانة');
        await expect(page.locator('main')).not.toContainText('مثال تجريبي');
        await expectRtlNoScroll(page);
    });
});

// ─── الرأس الداكن (#95 ثم fix/brahin-director-notes) ─────────────────────────────────────────────────────────────

test.describe('acceptance: dark header', () => {
    test('header: dark like the footer, with the white logo and calligraphy', async ({ page }, testInfo) => {
        test.fixme(true, AFTER.header);
        await visit(page, '/');

        const rgb = (value) => value.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number);
        const header = page.locator('[data-site-header]');
        const headerColor = rgb(await header.evaluate((element) => getComputedStyle(element).backgroundColor));
        const footerColor = rgb(await page.locator('[data-site-footer]').evaluate((element) => getComputedStyle(element).backgroundColor));
        expect(headerColor, 'الرأس بلون التذييل (primary-dark)').toEqual(footerColor);

        // الشعار والمخطوطة بالأبيض (logo-mark-white وlogo-calligraphy-white، أو ما رُفع مكانهما من اللوحة)
        for (const image of await header.locator('img').all()) {
            expect(await image.getAttribute('src')).toMatch(/white/);
        }

        // الرأس الثابت عند التمرير داكن أيضاً
        await page.evaluate(() => window.scrollTo({ top: 1200, behavior: 'instant' }));
        await page.waitForTimeout(400);
        expect(rgb(await header.evaluate((element) => getComputedStyle(element).backgroundColor))).toEqual(footerColor);

        if (testInfo.project.name === 'mobile') {
            const calligraphy = await header.locator('img[alt]').last().boundingBox();
            const toggle = await header.locator('[data-toggle="mobile-menu"]').boundingBox();
            const overlap = calligraphy.x < toggle.x + toggle.width && toggle.x < calligraphy.x + calligraphy.width;
            expect(overlap, 'المخطوطة لا تزاحم زر القائمة').toBe(false);
        }
        await expectRtlNoScroll(page, '/');
    });
});

// ─── الدفعة الأولى (#95) ─────────────────────────────────────────────────────────────────────────────────────────

test.describe('acceptance: visual refresh 1', () => {
    test('visual refresh 1: Phosphor duotone icons in the section tiles', async ({ page }) => {
        test.fixme(true, AFTER.refresh1);
        for (const path of ['/services', '/media', '/library', '/governance']) {
            await visit(page, path);
            const icons = page.locator('main .icon-tile svg');
            expect(await icons.count(), path).toBeGreaterThan(0);
            for (const icon of await icons.all()) {
                // Phosphor بشبكة 256، وduotone بطبقة شفافة
                await expect(icon, path).toHaveAttribute('viewBox', '0 0 256 256');
                expect(await icon.locator('[opacity]').count(), `${path}: duotone`).toBeGreaterThan(0);
            }
            await expectRtlNoScroll(page, path);
        }
    });

    test('visual refresh 1: one motion switch that reduced motion turns off', async ({ page }) => {
        test.fixme(true, AFTER.refresh1);
        const motion = () => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--motion').trim());

        await visit(page, '/');
        expect(await motion()).toBe('1');
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await visit(page, '/');
        expect(await motion()).toBe('0');
        await expectRtlNoScroll(page, '/');
    });
});

// ─── الدفعة الثانية (feature/visual-refresh-2) ───────────────────────────────────────────────────────────────────

test.describe('acceptance: visual refresh 2', () => {
    test('visual refresh 2: x-back-nav on a governance document and a program', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);

        await visit(page, '/');
        await expect(page.getByRole('link', { name: 'العودة للقائمة السابقة' })).toHaveCount(0);

        const cases = [
            { list: '/governance', link: 'main a[href*="/governance/"]', parent: /\/governance$/ },
            { list: '/programs', link: 'main a[href*="/programs/"]', parent: /\/programs$/ },
        ];
        let checked = 0;
        for (const { list, link, parent } of cases) {
            await visit(page, list);
            const target = page.locator(link).first();
            if ((await target.count()) === 0) {
                continue;
            }
            await visit(page, await target.getAttribute('href'));
            const back = page.getByRole('link', { name: 'العودة للقائمة السابقة' });
            const home = page.getByRole('link', { name: 'العودة للرئيسية' });
            await expect(back).toBeVisible();
            await expect(home).toBeVisible();
            expect(new URL(await back.getAttribute('href'), page.url()).pathname).toMatch(parent);
            expect(new URL(await home.getAttribute('href'), page.url()).pathname).toBe('/');
            await expectRtlNoScroll(page);

            await back.click();
            await expect(page).toHaveURL(parent);
            checked++;
        }
        test.skip(checked === 0, 'لا وثيقة ولا برنامج منشور');
    });

    test('visual refresh 2 pattern 1: full-screen hero slider', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const hero = page.locator('[data-hero-slider]');
        await expect(hero).toBeVisible();
        const box = await hero.boundingBox();
        expect(box.height, 'ملء الشاشة').toBeGreaterThanOrEqual(page.viewportSize().height * 0.85);
        expect(await hero.locator('[data-hero-slide]').count(), 'شرائح متتالية').toBeGreaterThanOrEqual(2);
        expect(await hero.locator('[data-hero-progress] > *').count(), 'شريط تقدم مقسّم').toBeGreaterThanOrEqual(2);

        const pause = hero.getByRole('button', { name: /إيقاف|تشغيل/ });
        await expect(pause).toBeVisible();
        await pause.click();
        await expect(pause).toHaveAttribute('aria-pressed', 'true');

        await expect(hero.locator('[data-hero-badge] svg')).toBeVisible();
        await expect(hero.locator('h1 [data-highlight]')).toBeVisible();
        await expect(hero.locator('h1 svg [data-swoosh], h1 svg path').first()).toBeAttached();

        const tabs = hero.locator('[data-tabs-autoplay] [role="tab"]');
        await expect(tabs).toHaveText(['الرؤية', 'الرسالة', 'القيم']);
        await expect(hero.getByRole('link').filter({ has: page.locator('svg') }).first()).toBeVisible();
        expect(await hero.getByRole('link').count(), 'زر رئيسي وثانوي').toBeGreaterThanOrEqual(2);
        await expect(hero).toContainText('مرّر للأسفل');
        await expect(hero).toContainText('جمعية إعجاز');
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2 pattern 2: solid dark header', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/about');

        const header = page.locator('[data-site-header]');
        const alpha = await header.evaluate((element) => {
            const parts = getComputedStyle(element).backgroundColor.match(/[\d.]+/g).map(Number);
            return parts[3] ?? 1;
        });
        expect(alpha, 'داكن صلب').toBe(1);
        await expect(header.locator('img').first()).toBeVisible();
        const radius = await header.locator('img').first().evaluate((element) => getComputedStyle(element).borderRadius);
        expect(radius, 'شعار دائري').toMatch(/50%|9999px|\d{3,}px/);

        const active = header.locator('[aria-current="page"]').first();
        if ((await active.count()) > 0) {
            const decoration = await active.evaluate((element) => getComputedStyle(element, '::after').content + getComputedStyle(element).textDecorationLine + getComputedStyle(element).borderBottomWidth);
            expect(decoration, 'خط تحت النشط').not.toBe('none' + 'none' + '0px');
        }
        await expect(header.getByRole('link', { name: /تسجيل الدخول|حسابي/ }).locator('svg').first()).toBeAttached();
        await expectRtlNoScroll(page);
    });

    test('visual refresh 2 pattern 3: section headings with eyebrow, ornament and description', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const eyebrows = page.locator('[data-section-eyebrow]');
        expect(await eyebrows.count()).toBeGreaterThan(0);
        const heading = eyebrows.first().locator('xpath=following::*[self::h2][1]');
        await expect(heading).toBeVisible();
        expect(Number(await heading.evaluate((element) => getComputedStyle(element).fontWeight))).toBeGreaterThanOrEqual(700);
        await expect(eyebrows.first().locator('xpath=following::*[@data-heading-ornament][1]')).toBeAttached();
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2 pattern 4: value cards with a different pale icon square each', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const tiles = page.locator('[data-value-card] .icon-tile');
        expect(await tiles.count()).toBeGreaterThanOrEqual(2);
        const colours = await tiles.evaluateAll((elements) => elements.map((element) => getComputedStyle(element).backgroundColor + getComputedStyle(element).backgroundImage));
        expect(new Set(colours).size, 'لون مربع الأيقونة يختلف لكل بطاقة').toBe(colours.length);
        const shadow = await page.locator('[data-value-card]').first().evaluate((element) => getComputedStyle(element).boxShadow);
        expect(shadow).not.toBe('none');
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2 pattern 5: dark numbers and leadership section', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const badge = page.locator('[data-stat-badge]').first();
        test.skip((await badge.count()) === 0, 'لا أرقام معتمدة من اللوحة');
        await expect(badge).toBeVisible();
        const section = badge.locator('xpath=ancestor::section[1]');
        expect(await section.evaluate((element) => element.className)).toMatch(/section-dark|bg-primary-dark/);
        await expect(section.locator('[data-person-card] img').first()).toBeVisible();
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2 pattern 6: dark achievements with autoplay tabs', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const tabs = page.locator('[data-achievements] [data-tabs-autoplay]');
        test.skip((await tabs.count()) === 0, 'لا إنجازات معتمدة');
        expect(await tabs.locator('[role="tab"]').count()).toBeGreaterThanOrEqual(2);
        expect(await tabs.locator('[role="tab"] [data-tab-progress]').count(), 'شريط تقدم لكل تبويب').toBe(await tabs.locator('[role="tab"]').count());
        await expect(tabs.getByRole('button', { name: /إيقاف|تشغيل/ })).toBeVisible();
        await expect(page.locator('[data-achievements] [data-polaroid]').first()).toBeVisible();
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2 pattern 7: news carousel', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const carousel = page.locator('[data-carousel]').first();
        test.skip((await carousel.count()) === 0, 'لا أخبار منشورة');
        await expect(carousel.getByRole('button', { name: /السابق/ })).toBeVisible();
        await expect(carousel.getByRole('button', { name: /التالي/ })).toBeVisible();
        await expect(carousel.locator('[data-carousel-status]')).toHaveText(/^0\d \/ 0\d$/);
        const card = carousel.locator('[data-carousel-item]').first();
        await expect(card.locator('[data-category]')).toBeVisible();
        await expect(card).toContainText('هـ');
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2 pattern 8: section dividers with a centre ornament', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const divider = page.locator('[data-divider-ornament]').first();
        await expect(divider).toBeAttached();
        await expect(divider.locator('svg')).toBeAttached();
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2 pattern 9: dark footer with columns, round social icons and back to top', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const footer = page.locator('[data-site-footer]');
        expect(await footer.locator('[data-footer-column]').count(), 'عن الجمعية، والروابط، والتواصل').toBeGreaterThanOrEqual(3);
        const social = footer.locator('[data-social-link]').first();
        if ((await social.count()) > 0) {
            expect(await social.evaluate((element) => getComputedStyle(element).borderRadius)).toMatch(/50%|9999px/);
        }
        await expect(footer).toContainText('©');
        await expect(page.getByRole('button', { name: /العودة للأعلى/ })).toBeAttached();
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2: reduced motion stops the slider and the tabs', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await visit(page, '/');

        const hero = page.locator('[data-hero-slider]');
        const before = await hero.locator('[data-hero-slide]:not([hidden])').first().getAttribute('data-hero-slide');
        await page.waitForTimeout(8_000);
        expect(await hero.locator('[data-hero-slide]:not([hidden])').first().getAttribute('data-hero-slide'), 'لا تبديل تلقائي مع تقليل الحركة').toBe(before);
        await expectRtlNoScroll(page, '/');
    });
});

// ─── feature/library-read-only ───────────────────────────────────────────────────────────────────────────────────

test.describe('acceptance: read-only library', () => {
    test('library read-only: the reader shows and no download button', async ({ page }) => {
        test.fixme(true, AFTER.library);
        const path = process.env.LIBRARY_READONLY_PATH;
        test.skip(!path, 'يحتاج كتاباً «للقراءة فقط» منشوراً: LIBRARY_READONLY_PATH');

        const response = await visit(page, path);
        expect(response?.status()).toBeLessThan(400);
        await expect(page.locator('main a[download]')).toHaveCount(0);
        await expect(page.getByRole('link', { name: /تنزيل|تحميل/ })).toHaveCount(0);
        await expect(page.locator('main iframe, main embed, main object')).toHaveCount(0);

        const reader = page.locator('[data-pdf-reader]');
        await expect(reader).toBeVisible();
        await expect(reader.locator('canvas').first()).toBeVisible({ timeout: 30_000 });
        await expect(reader.locator('[data-pdf-next]')).toBeVisible();
        await expect(reader.locator('[data-pdf-zoom-in]')).toBeVisible();
        await expect(reader.locator('[data-pdf-page]')).toBeVisible();

        // لا قائمة للزر الأيمن على القارئ
        const prevented = await reader.locator('[data-pdf-stage]').evaluate((element) => {
            const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
            element.dispatchEvent(event);
            return event.defaultPrevented;
        });
        expect(prevented).toBe(true);
        await expectRtlNoScroll(page, path);
    });
});

// ─── feature/programs-how-to ─────────────────────────────────────────────────────────────────────────────────────

test.describe('acceptance: programs how-to', () => {
    test('programs-how-to: five steps on the index and a program page', async ({ page }) => {
        test.fixme(true, AFTER.howTo);
        const steps = ['أنشئ حساباً أو ادخل', 'اختر البرنامج', 'اضغط «سجّل الآن»', 'أكمل البيانات', 'تصلك رسالة التأكيد وتجده في حسابك'];

        const check = async (path) => {
            const section = page.locator('[data-programs-how-to]');
            await expect(section.getByRole('heading', { name: 'كيف تسجّل' })).toBeVisible();
            await expect(section).toContainText('خمس خطوات من الحساب إلى تأكيد تسجيلك.');
            const items = section.locator('ol > li');
            await expect(items).toHaveCount(5);
            for (const [index, step] of steps.entries()) {
                await expect(items.nth(index)).toContainText(step);
            }
            expect(await section.getByRole('group').getByRole('link').count(), `${path}: زر يبدأ الخطوة`).toBeGreaterThan(0);
            await expectRtlNoScroll(page, path);
        };

        await visit(page, '/programs');
        await check('/programs');

        const program = page.locator('main a[href*="/programs/"]').first();
        if ((await program.count()) > 0) {
            const href = await program.getAttribute('href');
            await visit(page, href);
            await check(href);
        }
    });

    test('programs-how-to: signing up from the competition returns to its registration', async ({ page, baseURL }) => {
        test.fixme(true, AFTER.howTo);
        // ينشئ حساباً في كل تشغيل، فلا يعمل إلا على نسخة محلية
        test.skip(!/localhost|127\.0\.0\.1|\.test/.test(baseURL), 'ينشئ حساباً: النسخ المحلية وحدها');
        await openCompetition(page);
        const signup = page.getByRole('link', { name: 'إنشاء حساب جديد' }).first();
        test.skip((await signup.count()) === 0, 'التسجيل في المسابقة غير مفتوح');

        await Promise.all([page.waitForURL((url) => url.pathname === '/register'), signup.click()]);
        const username = `accept${Date.now().toString(36)}`;
        await page.fill('#field-name', 'حساب قبول تجريبي');
        await page.fill('#field-username', username);
        const password = `Ejaz-${Date.now()}-Accept!`;
        await page.fill('#field-password', password);
        await page.fill('#field-password_confirmation', password);
        const privacy = page.locator('input[name="privacy"]');
        if ((await privacy.count()) > 0) {
            await privacy.check();
        }
        await Promise.all([page.waitForURL((url) => url.pathname !== '/register'), page.locator('main form button[type=submit]').first().click()]);

        // التوثيق مطفأ: إلى التسجيل في المسابقة مباشرة. ومفعّلاً: توثيق الجوال أولاً
        await expect(page).toHaveURL(new RegExp(`/competitions/${SLUG}/register$|/verify`));
        await expectRtlNoScroll(page);
    });
});

// ─── feature/evidence-gallery ────────────────────────────────────────────────────────────────────────────────────

test.describe('acceptance: evidence gallery', () => {
    test('evidence gallery: a wrong token is a plain 404, not indexed nor in the sitemap', async ({ page, request }) => {
        test.fixme(true, AFTER.evidence);

        const response = await visit(page, `/evidence/${'x'.repeat(48)}`);
        expect(response?.status()).toBe(404);
        expect(response?.headers()['x-robots-tag'] ?? '').toMatch(/noindex/);
        await expectRtlNoScroll(page);

        const sitemap = await request.get('/sitemap.xml');
        expect(await sitemap.text()).not.toContain('/evidence/');
    });
});
