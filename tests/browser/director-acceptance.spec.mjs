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
import AxeBuilder from '@axe-core/playwright';
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

// روابط الوثائق والبرامج في قوائمها: السمة الثابتة إن وُجدت (data-document-link وdata-program-link)، وإلا الرابط بمساره
// إلى أن تُضاف السمتان في الدفعة الثانية
const DOCUMENT_LINK = 'main [data-document-link], main a[href*="/governance/"]:not([href*="?"])';
const PROGRAM_LINK = 'main [data-program-link], main a[href*="/programs/"]:not([href*="?"])';

// تاريخ أو وقت بأي صيغة تعرضها البوابة: ميلادي (20xx) أو هجري (14xx أو «هـ») أو أرقام عربية أو ساعة
const DATE_PATTERN = /20\d\d|14\d\d|هـ|[٠-٩]|\d{1,2}:\d{2}/u;

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

        const documentLink = page.locator(DOCUMENT_LINK).first();
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
        await page.waitForLoadState('load');
        // الحساب المسجل من قبل تعيده صفحة التسجيل إلى المسابقة برسالته، فلا يُحكم به على الرجوع
        const alreadyRegistered = new URL(page.url()).pathname === COMPETITION && (await page.getByText('أنت مسجّل في هذه المسابقة من قبل.').count()) > 0;
        test.skip(alreadyRegistered, 'الحساب مسجل في المسابقة من قبل: استعمل حساباً غير مسجل');
        await expect(page).toHaveURL(new RegExp(`/competitions/${SLUG}/register$`));
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

        const prize = page.locator('[data-prize-total]').first();
        await expect(prize).toContainText('مجموع الجوائز');
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

        const rounds = page.locator('[data-rounds]');
        await expect(rounds).toContainText('تمر المسابقة بأربع مراحل متتابعة، وتُعقد كل مرحلة يوم الخميس بحسب الجدول الزمني والتواريخ المعتمدة لها.');
        await expect(rounds).not.toContainText('المشاركة في جميع المراحل شرط');
        const cards = rounds.locator('[data-round-card]');
        await expect(cards).toHaveCount(4);
        await expect(cards.first()).toContainText('المرحلة الأولى');
        await expect(cards.first()).toContainText('المجال الأول: الحقائق العلمية في الأنفس');

        // العنوان والمجال والحالة وحدها: لا عدد أسئلة ولا مدة ولا تاريخ بأي تقويم أو أرقام (التواريخ بخيار «إظهار مواعيد المراحل» وحده)
        for (const card of await cards.allInnerTexts()) {
            expect(card).not.toMatch(/سؤال|أسئلة|دقيقة|دقائق/u);
            expect(card).not.toMatch(DATE_PATTERN);
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
        await expect(cards.first()).not.toHaveAttribute('open', '');
        await expect(summary).toHaveAttribute('aria-expanded', 'false');

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
        // «(اختياري)» لا يلحق حقلي الجوال والمدرسة (وقد يلحق حقلاً مخصصاً آخر اختيارياً)
        await expect(page.locator('label[for="field-guardian_phone"]')).not.toContainText('اختياري');
        await expect(page.locator('label').filter({ hasText: 'الجهة التعليمية / المدرسة' })).not.toContainText('اختياري');
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
        const images = await header.locator('img').all();
        expect(images.length, 'الشعار والمخطوطة في الرأس').toBeGreaterThan(0);
        for (const image of images) {
            expect(await image.getAttribute('src')).toMatch(/white/);
        }

        // الرأس الثابت عند التمرير داكن أيضاً (ولا يتغير مقاسه — header-final.spec.mjs)
        await page.evaluate(() => window.scrollTo({ top: 1200, behavior: 'instant' }));
        await expect.poll(async () => rgb(await header.evaluate((element) => getComputedStyle(element).backgroundColor))).toEqual(footerColor);

        if (testInfo.project.name === 'mobile') {
            const calligraphy = await header.locator('[data-logo-calligraphy]').boundingBox();
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
            { list: '/governance', link: DOCUMENT_LINK, parent: /\/governance$/ },
            { list: '/programs', link: PROGRAM_LINK, parent: /\/programs$/ },
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
            const home = page.getByRole('link', { name: 'العودة إلى الرئيسية' });
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
        // الخط المنحني تحت الكلمة المميزة، يُرسم عند الظهور (stroke-dashoffset يصل إلى 0)
        const swoosh = hero.locator('h1 [data-swoosh] path').first();
        await expect(swoosh).toBeAttached();
        await expect.poll(() => swoosh.evaluate((element) => parseFloat(getComputedStyle(element).strokeDashoffset) || 0)).toBe(0);

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

        // الخط تحت النشط مرسوم فعلاً: ::after أو حد سفلي بعرض وارتفاع
        const active = header.locator('[data-main-nav] [aria-current="page"]').first();
        test.skip((await active.count()) === 0, '«عن الجمعية» غير منشورة، فلا عنصر نشط في القائمة');
        const underline = await active.evaluate((element) => {
            const after = getComputedStyle(element, '::after');
            const own = getComputedStyle(element);
            return Math.max(parseFloat(after.width) * parseFloat(after.height) || 0, parseFloat(own.borderBottomWidth) * element.offsetWidth || 0);
        });
        expect(underline, 'خط مرسوم تحت النشط').toBeGreaterThan(0);

        // زر الدخول كبسولي أبيض بأيقونة داخل دائرة (اعتماد 11:58)
        const login = header.getByRole('link', { name: /تسجيل الدخول|حسابي/ }).first();
        const loginStyle = await login.evaluate((element) => ({ background: getComputedStyle(element).backgroundColor, radius: getComputedStyle(element).borderRadius }));
        expect(loginStyle.background, 'زر أبيض').toMatch(/rgba?\(255, 255, 255/);
        expect(loginStyle.radius, 'كبسولي').toMatch(/9999px|\d{3,}px|50%/);
        const circle = login.locator('[data-icon-circle]');
        await expect(circle.locator('svg')).toBeAttached();
        expect(await circle.evaluate((element) => getComputedStyle(element).borderRadius), 'الأيقونة في دائرة').toMatch(/50%|9999px/);
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
        test.setTimeout(120_000);
        // الشريحة النشطة بفهرسها (data-active على شريحة واحدة، أو aria-current على نقطتها)، لا بالظهور ولا بالشفافية
        const activeIndex = () => page.evaluate(() => {
            const slides = [...document.querySelectorAll('[data-hero-slider] [data-hero-slide]')];
            const byFlag = slides.findIndex((slide) => slide.hasAttribute('data-active'));
            if (byFlag >= 0) {
                return byFlag;
            }
            return [...document.querySelectorAll('[data-hero-slider] [data-hero-progress] > *')].findIndex((dot) => dot.hasAttribute('aria-current'));
        });
        const activeTab = () => page.evaluate(() => [...document.querySelectorAll('[data-hero-slider] [data-tabs-autoplay] [role="tab"]')].findIndex((tab) => tab.getAttribute('aria-selected') === 'true'));

        // بلا تقليل الحركة: الشرائح والتبويبات تتبدل فعلاً، وإلا فلا معنى لثباتها بعده
        await visit(page, '/');
        const first = await activeIndex();
        const firstTab = await activeTab();
        expect(first, 'شريحة نشطة معلَّمة').toBeGreaterThanOrEqual(0);
        await expect.poll(activeIndex, { timeout: 20_000, message: 'الشرائح تتبدل تلقائياً' }).not.toBe(first);
        await expect.poll(activeTab, { timeout: 20_000, message: 'التبويبات تتبدل تلقائياً' }).not.toBe(firstTab);

        // مع تقليل الحركة: تُعدّ تغيّرات الشريحة والتبويب النشطين في أطول من دورة كاملة، فلا تغيّر
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await visit(page, '/');
        const changes = await page.evaluate(() => new Promise((resolve) => {
            let count = 0;
            const observer = new MutationObserver((records) => {
                count += records.filter((record) => ['data-active', 'aria-current', 'aria-selected'].includes(record.attributeName)).length;
            });
            observer.observe(document.querySelector('[data-hero-slider]'), { attributes: true, subtree: true, attributeFilter: ['data-active', 'aria-current', 'aria-selected'] });
            setTimeout(() => {
                observer.disconnect();
                resolve(count);
            }, 20_000);
        }));
        expect(changes, 'لا تبديل تلقائي مع تقليل الحركة').toBe(0);
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2: five goal cards with large icons on the about page', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        const response = await visit(page, '/about');
        test.skip(response?.status() === 404, '«عن الجمعية» غير منشورة');

        const goals = page.locator('[data-goal-card]');
        await expect(goals).toHaveCount(5);
        for (const goal of await goals.all()) {
            const icon = goal.locator('[data-icon-tile] svg');
            await expect(icon).toBeVisible();
            expect((await icon.boundingBox()).width, 'أيقونة كبيرة').toBeGreaterThanOrEqual(40);
            await expectBefore(icon, goal.locator('h3, h4').first(), 'الأيقونة فوق العنوان');
        }
        await expectRtlNoScroll(page, '/about');
    });
});

// ─── الأيقونات والتصور المعتمدان (صفوان، 30 أكتوبر 11:58) — feature/visual-refresh-2 ─────────────────────────────

const iconPages = ['/', '/services', '/media', '/library', '/governance', '/programs', '/contact'];

// تباين لونين بصيغة rgb() وفق WCAG
function contrast(foreground, background) {
    const luminance = (value) => {
        const [r, g, b] = value.match(/[\d.]+/g).slice(0, 3).map((channel) => {
            const c = Number(channel) / 255;
            return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const [light, dark] = [luminance(foreground), luminance(background)].sort((a, b) => b - a);
    return (light + 0.05) / (dark + 0.05);
}

test.describe('acceptance: approved icons and home concept', () => {
    test('approved icons: one x-icon-tile, gradient on light and glass on dark', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        for (const path of iconPages) {
            await visit(page, path);
            // لا مربع أيقونة خارج المكوّن
            expect(await page.locator('main .icon-tile:not([data-icon-tile])').count(), `${path}: مربع خارج x-icon-tile`).toBe(0);
            const tiles = await page.locator('[data-icon-tile]').evaluateAll((elements) => elements.map((element) => {
                const style = getComputedStyle(element);
                const dark = Boolean(element.closest('.section-dark, .bg-primary-dark, [data-site-footer], [data-site-header]'));
                return { variant: element.dataset.variant, dark, image: style.backgroundImage, blur: style.backdropFilter || style.webkitBackdropFilter || 'none' };
            }));
            expect(tiles.length, path).toBeGreaterThan(0);
            for (const tile of tiles) {
                expect(tile.variant, `${path}: الحالة بحسب الخلفية`).toBe(tile.dark ? 'glass' : 'gradient');
                if (tile.variant === 'gradient') {
                    expect(tile.image, `${path}: مربع متدرج`).toMatch(/gradient/);
                } else {
                    expect(tile.blur, `${path}: زجاجي`).not.toBe('none');
                }
            }
            await expectRtlNoScroll(page, path);
        }
    });

    test('approved icons: the tile moves on hover and not with reduced motion', async ({ page }, testInfo) => {
        test.fixme(true, AFTER.refresh2);
        test.skip(testInfo.project.name !== 'desktop', 'المرور بالمؤشر على الحاسوب');
        const tileOf = () => page.locator('main a:has([data-icon-tile])').first().locator('[data-icon-tile]');
        const transform = () => tileOf().evaluate((element) => getComputedStyle(element).transform);

        await visit(page, '/services');
        const before = await transform();
        await page.locator('main a:has([data-icon-tile])').first().hover();
        await expect.poll(transform, { message: 'الأيقونة تتحرك عند المرور' }).not.toBe(before);

        // مع تقليل الحركة: لا انتقال متحرك (مدة صفر)، ولا حركة مستمرة
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await visit(page, '/services');
        await page.locator('main a:has([data-icon-tile])').first().hover();
        const motion = await tileOf().evaluate((element) => ({
            durations: getComputedStyle(element).transitionDuration.split(',').map((value) => parseFloat(value)),
            running: element.getAnimations({ subtree: true }).filter((animation) => animation.playState === 'running').length,
        }));
        expect(Math.max(...motion.durations), 'لا انتقال متحرك مع تقليل الحركة').toBe(0);
        expect(motion.running, 'لا حركة مستمرة مع تقليل الحركة').toBe(0);
        await expectRtlNoScroll(page, '/services');
    });

    test('approved identity: computed blue on the primary button with AA contrast', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const button = page.locator('main [data-button="primary"]').first();
        await expect(button).toBeVisible();
        const { background, color } = await button.evaluate((element) => ({ background: getComputedStyle(element).backgroundColor, color: getComputedStyle(element).color }));
        const [r, g, b] = background.match(/[\d.]+/g).map(Number);
        expect(b, `أزرق الهوية لا الأخضر ولا الذهبي (${background})`).toBeGreaterThan(Math.max(r, g));
        expect(contrast(color, background), 'تباين نص الزر').toBeGreaterThanOrEqual(4.5);

        // السطر الثاني من عنوان البطل بأزرق فاتح يجتاز التباين على خلفيته الداكنة
        const highlight = page.locator('[data-hero-slider] h1 [data-highlight]');
        const highlightColor = await highlight.evaluate((element) => getComputedStyle(element).color);
        const heroBackground = await page.locator('[data-hero-slider]').evaluate((element) => getComputedStyle(element).backgroundColor);
        expect(contrast(highlightColor, heroBackground === 'rgba(0, 0, 0, 0)' ? 'rgb(31, 69, 101)' : heroBackground), 'تباين السطر الثاني').toBeGreaterThanOrEqual(3);
        await expectRtlNoScroll(page, '/');
    });

    test('approved home concept: header, hero, services, Brahin strip, values, library and footer', async ({ page }) => {
        test.fixme(true, AFTER.refresh2);
        await visit(page, '/');

        const hero = page.locator('[data-hero-slider]');
        expect(await hero.evaluate((element) => getComputedStyle(element).backgroundImage), 'تدرج أزرق شعاعي').toMatch(/radial-gradient/);
        await expect(hero.locator('[data-hero-pattern]')).toBeAttached();
        await expect(hero.locator('[data-tabs-autoplay] [role="tab"]').first()).toBeVisible();
        expect(await hero.locator('[data-events-slider] [data-hero-progress] > *').count(), 'عارض الفعاليات بشريط مقسّم').toBeGreaterThanOrEqual(2);
        const prize = hero.locator('[data-prize-total]');
        await expect(prize).toContainText('مجموع جوائز البراهين');
        expect(await prize.evaluate((element) => getComputedStyle(element).backdropFilter), 'بطاقة زجاجية').not.toBe('none');

        const services = page.locator('[data-home-services] [data-service-card]');
        await expect(services).toHaveCount(6);
        const gradients = await services.locator('[data-icon-tile]').evaluateAll((elements) => elements.map((element) => getComputedStyle(element).backgroundImage));
        expect(new Set(gradients).size, 'تدرجات من ألوان الهوية تختلف بين البطاقات').toBeGreaterThanOrEqual(3);

        const brahin = page.locator('[data-home-brahin]');
        await expect(brahin.locator('[data-domain]')).toHaveCount(4);
        await expect(brahin.locator('[data-prize-circle]')).toBeVisible();
        await expect(brahin.getByRole('link', { name: /شارك واربح/ })).toBeVisible();

        await expect(page.locator('[data-home-values] [data-value-number]').first()).toBeVisible();
        await expect(page.locator('[data-home-library] [data-play-halo]')).toBeVisible();
        await expect(page.locator('[data-home-library] [data-book-card]')).toBeVisible();
        expect(await page.locator('[data-home-numbers]').evaluate((element) => element.className), 'شريط الأرقام داكن').toMatch(/section-dark|bg-primary-dark/);
        expect(await page.locator('[data-site-footer] [data-footer-column]').count(), 'تذييل بثلاثة أعمدة').toBe(3);
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
            expect(await section.getByRole('group', { name: 'ابدأ التسجيل' }).getByRole('link').count(), `${path}: زر يبدأ الخطوة`).toBeGreaterThan(0);
            await expectRtlNoScroll(page, path);
        };

        await visit(page, '/programs');
        await check('/programs');

        const program = page.locator(PROGRAM_LINK).first();
        test.skip((await program.count()) === 0, 'لا برنامج منشور: فُحصت صفحة «الدورات والبرامج» وحدها');
        const href = await program.getAttribute('href');
        await visit(page, href);
        await check(href);
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

        // التوثيق مطفأ (الافتراضي): إلى التسجيل في المسابقة مباشرة. ومفعّلاً يمر بتوثيق الجوال، ولا رمز يصل في الاختبار،
        // فيغطيه Pest: RegisterIntendedTest › «verifies the phone first, then brings the account back to the competition registration»
        test.skip(/verif/.test(new URL(page.url()).pathname), 'التوثيق مفعّل: العودة بعده في RegisterIntendedTest');
        await expect(page).toHaveURL(new RegExp(`/competitions/${SLUG}/register$`));
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

        // ومعرض صحيح (EVIDENCE_GALLERY_PATH، رابط يولّده المسؤول من اللوحة في البيئة المحلية) لا يُفهرس ولا يُخزَّن أيضاً.
        // والانتهاء والإلغاء وسجل الاطلاع في Pest: EvidenceGalleryTest › «answers a wrong, expired or revoked link with the
        // same 404» و«records one visit per network a minute» و«masks the visitor address to its network»
        const valid = process.env.EVIDENCE_GALLERY_PATH;
        test.skip(!valid, 'يحتاج رابط معرض صحيحاً: EVIDENCE_GALLERY_PATH');
        const gallery = await visit(page, valid);
        expect(gallery?.status()).toBe(200);
        expect(gallery?.headers()['x-robots-tag'] ?? '').toMatch(/noindex/);
        expect(gallery?.headers()['cache-control'] ?? '').toMatch(/no-store/);
        await expectRtlNoScroll(page, 'evidence gallery');
    });
});

// ─── معايير التفوق (صفوان، 30 أكتوبر 12:00) ──────────────────────────────────────────────────────────────────────
// ما يُقاس آلياً في المتصفح الآن. وLighthouse وCore Web Vitals وsecurityheaders.com بأوامرها في README («معايير التفوق»).
// وما لم يُبنَ أو فشل في القياس الأول معلَّم fixme بسببه، ومسجَّل «فجوة حالية» في قائمة القبول.

const corePages = ['/', '/governance', '/library', '/library/books', '/media', '/programs', '/competitions', '/services', '/contact'];

// البيانات المنظمة schema.org في الصفحة: أنواع كل كتل JSON-LD
async function jsonLdTypes(page) {
    return (await page.locator('script[type="application/ld+json"]').allTextContents()).flatMap((text) => {
        const data = JSON.parse(text);
        return (Array.isArray(data) ? data : data['@graph'] ?? [data]).map((item) => item['@type']);
    });
}

test.describe('acceptance: excellence criteria', () => {
    test('excellence: axe finds no WCAG 2.2 AA violation on the main public pages', async ({ page }) => {
        test.fixme(true, 'فجوة حالية (قياس 30 أكتوبر على feature/visual-refresh): color-contrast في كلمة رئيس المجلس على الرئيسية (#home-chairman)، على العروض الثلاثة. والصفحات الثماني الأخرى بلا مخالفة');
        test.setTimeout(300_000);
        const found = [];
        for (const path of [...corePages, COMPETITION]) {
            const response = await visit(page, path);
            if (response?.status() === 404) {
                continue;
            }
            const { violations } = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
                .exclude('.cf-turnstile')
                .analyze();
            found.push(...violations.map((v) => `${path} ${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')}`));
            await expectRtlNoScroll(page, path);
        }
        expect(found).toEqual([]);
    });

    test('excellence: every page has a title, a description, a canonical link and Open Graph', async ({ page }) => {
        for (const path of corePages) {
            await visit(page, path);
            await expect(page, path).toHaveTitle(/\S/);
            for (const selector of ['meta[name="description"]', 'meta[property="og:title"]', 'meta[property="og:description"]', 'meta[property="og:url"]', 'meta[name="twitter:card"]']) {
                expect(await page.locator(selector).getAttribute('content'), `${path}: ${selector}`).toMatch(/\S/);
            }
            await expect(page.locator('link[rel="canonical"]'), path).toHaveAttribute('href', /^https?:\/\//);
            await expectRtlNoScroll(page, path);
        }
    });

    test('excellence: a share image on every page', async ({ page }) => {
        test.fixme(true, 'فجوة حالية: og:image لا يظهر إلا لصفحة تمرّر صورتها، ولا صورة مشاركة افتراضية للموقع');
        for (const path of corePages) {
            await visit(page, path);
            expect(await page.locator('meta[property="og:image"]').getAttribute('content'), path).toMatch(/^https?:\/\/.+\.(png|jpe?g|webp)/);
            await expectRtlNoScroll(page, path);
        }
    });

    test('excellence: structured data — Organization on home, BreadcrumbList on inner pages', async ({ page }) => {
        test.fixme(true, 'فجوة حالية: لا بيانات منظمة (JSON-LD) في القوالب');
        await visit(page, '/');
        expect(await jsonLdTypes(page)).toContain('Organization');
        await visit(page, '/governance');
        expect(await jsonLdTypes(page)).toContain('BreadcrumbList');
        await expectRtlNoScroll(page);
    });

    test('excellence: structured data — Event for the competition and programs, Book and VideoObject in the library', async ({ page }) => {
        test.fixme(true, 'فجوة حالية: لا بيانات منظمة (JSON-LD) في القوالب');
        const response = await visit(page, COMPETITION);
        if (response?.status() !== 404) {
            expect(await jsonLdTypes(page), COMPETITION).toContain('Event');
        }
        const cases = [
            { list: '/programs', link: 'main a[href*="/programs/"]', type: 'Event' },
            { list: '/library/books', link: 'main a[href*="/library/books/"]', type: 'Book' },
            { list: '/library/videos', link: 'main a[href*="/library/videos/"]', type: 'VideoObject' },
        ];
        for (const { list, link, type } of cases) {
            await visit(page, list);
            const target = page.locator(link).first();
            if ((await target.count()) === 0) {
                continue;
            }
            await visit(page, await target.getAttribute('href'));
            expect(await jsonLdTypes(page), list).toContain(type);
            await expectRtlNoScroll(page);
        }
    });

    test('excellence: a helpful Arabic 404 page', async ({ page }) => {
        const response = await visit(page, '/no-such-page-acceptance');
        expect(response?.status()).toBe(404);
        await expect(page.locator('h1').first()).toBeVisible();
        expect(await page.locator('main a').evaluateAll((links) => links.some((link) => new URL(link.href).pathname === '/')), 'رابط إلى الرئيسية').toBe(true);
        await expect(page).toHaveTitle(/\S/);
        await expectRtlNoScroll(page);
    });

    test('excellence: security headers on every public page', async ({ page }) => {
        for (const path of corePages) {
            const headers = (await visit(page, path)).headers();
            expect(headers['content-security-policy'], `${path}: CSP`).toMatch(/default-src/);
            expect(headers['content-security-policy'], `${path}: CSP`).toMatch(/frame-ancestors/);
            expect(headers['x-content-type-options'], path).toBe('nosniff');
            expect(headers['referrer-policy'], path).toMatch(/\S/);
            expect(headers['permissions-policy'], path).toMatch(/camera=\(\)/);
            await expectRtlNoScroll(page, path);
        }
    });

    test('excellence: touch targets of at least 44px in the mobile header and main buttons', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile', 'أهداف اللمس على الجوال');
        for (const path of ['/', '/programs', '/governance', '/contact']) {
            await visit(page, path);
            const small = await page.evaluate(() => [...document.querySelectorAll('[data-site-header] a, [data-site-header] button, main button')]
                .filter((element) => element.offsetParent !== null)
                .map((element) => ({ element, box: element.getBoundingClientRect() }))
                .filter(({ box }) => box.width > 0 && (box.width < 44 || box.height < 44))
                .map(({ element, box }) => `${element.tagName.toLowerCase()} «${(element.getAttribute('aria-label') || element.textContent).trim().slice(0, 30)}» ${Math.round(box.width)}×${Math.round(box.height)}`));
            expect(small, path).toEqual([]);
            await expectRtlNoScroll(page, path);
        }
    });

    test('excellence: images carry explicit width and height', async ({ page }) => {
        test.fixme(true, 'فجوة حالية (قياس 30 أكتوبر): 11 صورة في الرئيسية بلا width وheight (صور الكلمتين والمبررات والقيم الأربع، والمخطوطة، وصورة المقطع، والزخرفة)');
        for (const path of ['/', '/media', '/library']) {
            await visit(page, path);
            const missing = await page.locator('main img').evaluateAll((images) => images
                .filter((image) => !image.getAttribute('width') || !image.getAttribute('height'))
                .map((image) => image.getAttribute('src')));
            expect(missing, `${path}: صور بلا width وheight`).toEqual([]);
            await expectRtlNoScroll(page, path);
        }
    });

    test('excellence: fonts preloaded', async ({ page }) => {
        await visit(page, '/');
        expect(await page.locator('link[rel="preload"][as="font"]').count()).toBeGreaterThan(0);
        await expectRtlNoScroll(page, '/');
    });

    test('excellence: the home page works on slow 3G', async ({ page }) => {
        test.setTimeout(180_000);
        const cdp = await page.context().newCDPSession(page);
        await cdp.send('Network.enable');
        // «Slow 3G» في أدوات المطور: 400ms، و400 كيلوبت في الثانية تنزيلاً ورفعاً
        await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 400, downloadThroughput: (400 * 1024) / 8, uploadThroughput: (400 * 1024) / 8 });
        const started = Date.now();
        await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 120_000 });
        await expect(page.locator('main h1, main h2').first()).toBeVisible({ timeout: 60_000 });
        console.log(`slow 3G ${page.viewportSize().width}: first heading after ${Date.now() - started}ms`);
        await expectRtlNoScroll(page, '/');
    });
});
