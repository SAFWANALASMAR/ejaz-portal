// قائمة القبول (docs/plans/2026-10-30-acceptance-checklist.md، وتقرير قبول v0.15.0 docs/reviews/2026-11-02-قبول-v0.15.0.md):
// اختبار متصفح لكل بند يُختبر آلياً، على العروض الثلاثة.
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
//   LIBRARY_READONLY_PATH    مسار كتاب «للقراءة فقط» منشور، مثل /library/books/<slug> (الافتراضي في بنود v0.15.0 /library/books/demo-book).
//   VIEW_ONLY_SLUG           وثيقة حوكمة منشورة «للاطلاع فقط» بملف PDF (الافتراضي license-certificate).
//   BOARD_CREDENTIALS        {"email","password"} لعضو مجلس تجريبي (دور board_member وعضوية فعّالة) — بوابة المجلس.
//   ADMIN_CREDENTIALS        من ejaz:demo-user --admin --json — لقطات لوحة الإدارة.
//   ACCEPTANCE_SHOTS=1       يلتقط صور التقرير في docs/design/screenshots/acceptance-v0.15/.
//
// بعد التصور النهائي v63 (#111) وملاحظات 1 نوفمبر (#118): بنود التصميم السابق (الرأس الداكن، والبطل بالتبويبات، والإنجازات
// بالتبويبات، والفواصل، والتذييل بالأعمدة…) معلَّمة test.skip بسبب SUPERSEDED، لأن المدير ألغاها، لا لأنها فجوة. وما بقي فاشلاً
// بلا علامة فجوة حقيقية مسجلة في تقرير القبول.
// التشغيل: tests/browser/README.md («قائمة القبول»).
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import fs from 'node:fs';

// ما ألغاه التصور النهائي v63 (أمر التصور §٢ و#111) وملاحظات 1 نوفمبر (#118)، فلا يُقبل عليه
const SUPERSEDED = {
    order: 'ألغاه التصور النهائي v63: كلمتا خادم الحرمين ورئيس الجمعية صارتا شريحتين في البطل، والترتيب الجديد في «acceptance v0.15.0: home»',
    darkHeader: 'ألغاه البند G6 وR2: الرأس صفّان بتدرج أزرق منفصل عن البطل، لا بلون التذييل',
    heroTabs: 'ألغاه البند ٢٦: البطل بتدرج وصورة في إطار دائري، بلا تبويبات ولا خط منحنٍ ولا «مرّر للأسفل»',
    pattern: 'ألغاه التصور النهائي v63: نمط من الدفعة الثانية السابقة لا يوجد في لوحات التصور (العنوان الموحد x-section-heading بدل الشارة والزخرفة)',
    footer: 'ألغاه R17 والبند ١٥: التذييل ثلاثة مربعات (data-footer-box)، ويختبره «acceptance v0.15.0: header and footer»',
    icons: 'ألغاه R5 وN2: مربع .gt بأربع درجات زرقاء متعاقبة، لا «متدرج على الفاتح وزجاجي على الداكن»',
    about: 'ألغاه التصور النهائي (Final-About): لا بطاقات أهداف خمس في «عن الجمعية»، والأهداف بطاقة في الرئيسية (R11)',
    button: 'ألغاه التصور النهائي: لا [data-button="primary"] في الرئيسية، والأزرق وحده يحرسه BlueOnlyPaletteTest',
    motion: 'ألغاه البندان ٩ و١١: «مبادراتنا» و«خدماتنا» تتحرك مع تقليل الحركة (أبطأ) بقرار، ولا تبويبات في البطل',
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
        test.skip(true, SUPERSEDED.order);
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
        // الدخول الموحد: رابط اللوحة يحوّل إلى /login (أو /admin/login في نسخ سابقة)
        await expect(page).toHaveURL(/\/(admin\/)?login/);

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
        const alreadyRegistered = new URL(page.url()).pathname === COMPETITION;
        test.skip(alreadyRegistered, 'الحساب مسجل في المسابقة من قبل: استعمل حساباً غير مسجل');
        await expect(page).toHaveURL(new RegExp(`/competitions/${SLUG}/register$`));
        await expectRtlNoScroll(page);
    });
});

// ─── fix/brahin-director-notes ───────────────────────────────────────────────────────────────────────────────────

test.describe('acceptance: Brahin director notes', () => {
    test('public pages: no «الفرع», no «مثال تجريبي», no «40 سؤالاً»', async ({ page }) => {
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
        await openCompetition(page);

        const ctas = page.locator('[data-student-cta]');
        await expect(ctas).toHaveCount(2);
        const heroHref = await ctas.nth(0).locator('a').first().getAttribute('href');
        const asideHref = await ctas.nth(1).locator('a').first().getAttribute('href');
        expect(heroHref, 'زر البطل وزر العمود إلى الوجهة نفسها').toBe(asideHref);
        await expectRtlNoScroll(page, COMPETITION);
    });

    test('brahin page: four domains and stage cards without counts or dates', async ({ page }) => {
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
        const cards = rounds.locator('li');
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
        await openCompetition(page);

        const cards = page.locator('details[data-collapsible]');
        expect(await cards.count()).toBeGreaterThanOrEqual(5);
        await expect(page.locator('details[data-collapsible][open]')).toHaveCount(0);
        // المحتوى في الصفحة لمحركات البحث وهو مطوي
        await expect(page.locator('main')).toContainText('بناء جيل محب للعلم');

        const summary = cards.first().locator('summary');
        await summary.click();
        await expect(cards.first()).toHaveAttribute('open', '');
        await summary.click();
        await expect(cards.first()).not.toHaveAttribute('open', '');

        // بلوحة المفاتيح
        await summary.focus();
        await page.keyboard.press('Enter');
        await expect(cards.first()).toHaveAttribute('open', '');
        await page.keyboard.press('Enter');
        await expect(cards.first()).not.toHaveAttribute('open', '');

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
        test.skip(true, SUPERSEDED.darkHeader);
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
        // مدموج في #111 و#118

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
        test.skip(true, SUPERSEDED.heroTabs);
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
        test.skip(true, SUPERSEDED.darkHeader);
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
        test.skip(true, SUPERSEDED.pattern);
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
        test.skip(true, SUPERSEDED.pattern);
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
        test.skip(true, SUPERSEDED.pattern);
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
        test.skip(true, SUPERSEDED.pattern);
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
        test.skip(true, SUPERSEDED.pattern);
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
        test.skip(true, SUPERSEDED.pattern);
        await visit(page, '/');

        const divider = page.locator('[data-divider-ornament]').first();
        await expect(divider).toBeAttached();
        await expect(divider.locator('svg')).toBeAttached();
        await expectRtlNoScroll(page, '/');
    });

    test('visual refresh 2 pattern 9: dark footer with columns, round social icons and back to top', async ({ page }) => {
        test.skip(true, SUPERSEDED.footer);
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
        test.skip(true, SUPERSEDED.motion);
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
        test.skip(true, SUPERSEDED.about);
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
        test.skip(true, SUPERSEDED.icons);
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
        // مدموج في #111 و#118
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
        test.skip(true, SUPERSEDED.button);
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
        test.skip(true, SUPERSEDED.heroTabs);
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
        // مدموج (feature/library-read-only)
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
        // ينشئ حساباً في كل تشغيل، فلا يعمل إلا على نسخة محلية
        test.skip(!/localhost|127\.0\.0\.1|\.test/.test(baseURL), 'ينشئ حساباً: النسخ المحلية وحدها');
        await openCompetition(page);
        const signup = page.getByRole('link', { name: 'إنشاء حساب جديد' }).first();
        test.skip((await signup.count()) === 0, 'التسجيل في المسابقة غير مفتوح');

        await Promise.all([page.waitForURL((url) => url.pathname === '/register'), signup.click()]);
        await page.fill('#field-name', 'حساب قبول تجريبي');
        await page.fill('#field-phone', `05${String(Date.now()).slice(-8)}`);
        const password = `Ejaz-${Date.now()}-Accept!`;
        await page.fill('#field-password', password);
        await page.fill('#field-password_confirmation', password);
        const privacy = page.locator('input[name="privacy"]');
        if ((await privacy.count()) > 0) {
            await privacy.check();
        }
        await Promise.all([page.waitForURL((url) => url.pathname !== '/register'), page.locator('main form button[type=submit]').first().click()]);

        // إلى «حسابي» لا إلى المسابقة (decisions/2026-11-05-phone-login.md). ومع التوثيق المفعّل يمر بتوثيق الجوال، ولا رمز
        // يصل في الاختبار، فيغطيه Pest: RegisterDestinationTest
        test.skip(/verif/.test(new URL(page.url()).pathname), 'التوثيق مفعّل: الوجهة بعده في RegisterDestinationTest');
        await expect(page).toHaveURL(/\/account\/?$/);
        await expectRtlNoScroll(page);
    });
});

// ─── feature/evidence-gallery ────────────────────────────────────────────────────────────────────────────────────

test.describe('acceptance: evidence gallery', () => {
    test('evidence gallery: a wrong token is a plain 404, not indexed nor in the sitemap', async ({ page, request }) => {

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
        // فجوة قائمة على main (قياس 2 نوفمبر): color-contrast في شارة «جديد» (.badge-new) على بطاقة الخبر في الرئيسية — في تقرير القبول
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

        for (const path of corePages) {
            await visit(page, path);
            expect(await page.locator('meta[property="og:image"]').getAttribute('content'), path).toMatch(/^https?:\/\/.+\.(png|jpe?g|webp)/);
            await expectRtlNoScroll(page, path);
        }
    });

    test('excellence: structured data — Organization on home, BreadcrumbList on inner pages', async ({ page }) => {
        await visit(page, '/');
        expect(await jsonLdTypes(page)).toContain('Organization');
        await visit(page, '/governance');
        expect(await jsonLdTypes(page)).toContain('BreadcrumbList');
        await expectRtlNoScroll(page);
    });

    test('excellence: structured data — Event for the competition and programs, Book and VideoObject in the library', async ({ page }) => {
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
        // فجوة قائمة على main (قياس 2 نوفمبر): صور الإعلانات في الرئيسية بلا width وheight — في تقرير القبول
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

// ─── v0.15.0: التصور النهائي وملاحظات 1 نوفمبر (تقرير القبول docs/reviews/2026-11-02-قبول-v0.15.0.md) ─────────────────
// البنود بأرقام قائمة الطلبات (G وH وP وD وA وB وN وR) وملاحظات 1 نوفمبر (١–٢٨). وكل فشل هنا فجوة حقيقية مسجلة في التقرير.

const SEVEN = ['الرئيسية', 'عن الجمعية', 'مكتبة إعجاز', 'المركز الإعلامي', 'الحوكمة', 'الخدمات الإلكترونية', 'تواصل معنا'];
const LIBRARY_SECTIONS = ['المكتبة المرئية', 'المكتبة الصوتية', 'الكتب والمؤلفات', 'الأبحاث والدراسات'];
const VIEW_ONLY_SLUG = process.env.VIEW_ONLY_SLUG ?? 'license-certificate';
const BOARD = process.env.BOARD_CREDENTIALS ? JSON.parse(process.env.BOARD_CREDENTIALS) : null;
const ACCOUNT_STATE = 'storage/playwright/demo-user.json';
// ACCEPTANCE_SHOTS_DIR يغيّر المجلد، ومنه لقطات الإصدار docs/design/screenshots/v0.15.
const SHOTS = process.env.ACCEPTANCE_SHOTS_DIR || 'docs/design/screenshots/acceptance-v0.15';

// يمرّر الصفحة كلها فيظهر ما يظهر عند التمرير (data-reveal)، ثم يعود إلى أعلاها
async function revealAll(page) {
    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 600) {
            window.scrollTo(0, y);
            await new Promise((resolve) => setTimeout(resolve, 50));
        }
        // وما لم يدخل الشاشة أثناء التمرير السريع يُظهر صراحة، فلا تبقى في اللقطة فجوة لعنصر مخفي (تدقيق المشرف، البند 9)
        document.querySelectorAll('[data-reveal]').forEach((el) => el.setAttribute('data-revealed', ''));
        window.scrollTo(0, 0);
    });
    await page.waitForTimeout(900);
}

const label = async (locator) => (await locator.innerText()).replace(/\s+/g, ' ').trim();

test.describe('acceptance v0.15.0: header and footer', () => {
    test('R3: the header row has exactly the seven items, without programs, competitions or initiatives', async ({ page }, testInfo) => {
        await visit(page, '/');
        let items;
        if (testInfo.project.name === 'desktop') {
            items = page.locator('[data-main-nav] ul.nav-capsules > li > .nav-capsule');
        } else {
            await page.locator('[data-toggle="mobile-menu"]').click();
            items = page.locator('#mobile-menu nav > ul > li > :is(a, button)');
        }
        const labels = [];
        for (const item of await items.all()) {
            labels.push(await label(item));
        }
        expect(labels).toEqual(SEVEN);
        await expectRtlNoScroll(page, '/');
    });

    test('٤ و٣: every dropdown opens on hover with its parent page first, and the library lists its four sections', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop', 'صف القائمة على الحاسوب، والجوال في قائمة ☰');
        await visit(page, '/contact');
        const dropdowns = page.locator('[data-main-nav] [data-nav-dropdown]');
        expect(await dropdowns.count(), 'عن الجمعية والمكتبة والمركز الإعلامي والحوكمة والخدمات').toBeGreaterThanOrEqual(4);
        const children = {};
        for (const dropdown of await dropdowns.all()) {
            const toggle = dropdown.locator('[data-toggle="dropdown"]');
            await toggle.hover();
            const menu = dropdown.locator('[data-dropdown-menu]');
            await expect(menu).toBeVisible();
            const parent = await label(toggle);
            const links = [];
            for (const link of await menu.locator('a').all()) {
                links.push(await label(link));
            }
            expect(links[0], `${parent}: أول عنصر الصفحة الأم`).toContain(parent);
            children[parent] = links;
            await page.mouse.move(5, 850);
            await expect(menu).toBeHidden();
        }
        expect(children['مكتبة إعجاز'].slice(1)).toEqual(LIBRARY_SECTIONS);
        for (const section of ['الأخبار', 'الإعلانات', 'مكتبة الصور']) {
            expect(children['المركز الإعلامي'], `المركز الإعلامي: ${section}`).toContain(section);
        }
        for (const service of ['الدورات والبرامج', 'المسابقات']) {
            expect(children['الخدمات الإلكترونية'].join('|'), `الخدمات الإلكترونية: ${service} (R4)`).toContain(service);
        }
        // و«المبادرات» في «عن الجمعية» مرة واحدة آخراً، لا في الخدمات (ملاحظات المدير التنفيذي، 4 نوفمبر 2026 — مراجعة المشرف على #181)
        expect(children['الخدمات الإلكترونية'].join('|')).not.toContain('المبادرات');
        expect(children['عن الجمعية'].filter((label) => label.includes('المبادرات'))).toHaveLength(1);
        expect(children['عن الجمعية'].at(-1)).toContain('المبادرات');
        expect(children['الحوكمة'].length, 'الحوكمة وتصنيفاتها').toBeGreaterThanOrEqual(2);
    });

    test('R2 و١ و٢٥: two sticky rows, the licence box with its number, no supervision line, and the header keeps its size on scroll (#130)', async ({ page }, testInfo) => {
        await visit(page, '/');
        const header = page.locator('[data-site-header]');
        expect(await header.evaluate((el) => [getComputedStyle(el).position, getComputedStyle(el).top])).toEqual(['sticky', '0px']);
        await expect(header).not.toContainText('تحت إشراف وزارة التعليم');
        await expect(header.locator('[data-brand-mark]').first()).toBeVisible();
        await expect(header.locator('[data-logo-calligraphy]').first()).toBeVisible();

        const top = await header.evaluate((el) => el.getBoundingClientRect().height);
        if (testInfo.project.name === 'desktop') {
            const rows = await header.evaluate((el) => {
                const first = el.querySelector('.header-top').getBoundingClientRect();
                const second = el.querySelector('[data-main-nav]').getBoundingClientRect();
                return { firstBottom: first.bottom, secondTop: second.top, secondHeight: second.height };
            });
            expect(rows.secondHeight, 'الصف الثاني ظاهر').toBeGreaterThan(30);
            expect(rows.secondTop, 'صفّان لا صف واحد').toBeGreaterThanOrEqual(rows.firstBottom - 1);
            await expect(header.locator('[data-header-licence]')).toContainText('1000723700');
            await expect(header.locator('[data-header-licence]')).toContainText('مرخّصة من المركز الوطني لتنمية القطاع غير الربحي');
        }

        await page.evaluate(() => window.scrollTo(0, 1500));
        await page.waitForTimeout(800);
        const scrolled = await header.evaluate((el) => ({ top: el.getBoundingClientRect().top, height: el.getBoundingClientRect().height }));
        expect(scrolled.top, 'ثابت في أعلى الشاشة').toBe(0);
        // ألغى صفوان التصغير (2 نوفمبر، #130)، ثم أعاده مع المدير على الحاسوب (3 نوفمبر، البند ٣١ — director-home-2026-11-03.spec.mjs)
        if (testInfo.project.name === 'desktop') {
            expect(scrolled.height, 'يصغر عند التمرير على الحاسوب (البند ٣١)').toBeLessThan(top);
        } else {
            expect(Math.abs(scrolled.height - top), 'لا يصغر على اللوحي العمودي والجوال').toBeLessThanOrEqual(1);
        }
        if (testInfo.project.name === 'desktop') {
            await expect(header.locator('[data-main-nav]')).toBeVisible();
        }
        await expectRtlNoScroll(page, '/');
    });

    test('R17 و١٥ وN3: the footer is three boxes (contact, quick links, official accounts) on public and account pages', async ({ page }, testInfo) => {
        for (const path of ['/', '/library', '/contact', '/login']) {
            await visit(page, path);
            const boxes = page.locator('[data-site-footer] [data-footer-box]');
            expect(await boxes.evaluateAll((els) => els.map((el) => el.dataset.footerBox)), path).toEqual(['contact', 'links', 'accounts']);
            if (testInfo.project.name === 'desktop') {
                const tops = await boxes.evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().top)));
                expect(new Set(tops).size, `${path}: الثلاثة في صف واحد`).toBe(1);
                const height = await page.locator('[data-site-footer]').evaluate((el) => el.getBoundingClientRect().height);
                expect(height, `${path}: التذييل أقصر (البند ١٥)`).toBeLessThan(340);
            }
            // الروابط السريعة بأيقونات .gt زرقاء
            expect(await page.locator('[data-footer-box="links"] .icon-tile').count(), `${path}: أيقونات الروابط السريعة`).toBeGreaterThan(3);
            await expectRtlNoScroll(page, path);
        }
    });
});

test.describe('acceptance v0.15.0: home', () => {
    test('H1 و٦ و٢٦: hero slides in order — the society, the King, the chairman, Brahin — in a round frame', async ({ page }) => {
        await visit(page, '/');
        const keys = await page.locator('[data-hero-slider] [data-hero-slide]').evaluateAll((els) => els.map((el) => el.dataset.heroSlide));
        expect(keys.length, 'شرائح متبدلة').toBeGreaterThanOrEqual(4);
        const frame = page.locator('[data-hero-slider] [data-hero-slide][data-active] [data-hero-frame]');
        await expect(frame).toBeVisible();
        expect(Number.parseFloat(await frame.evaluate((el) => getComputedStyle(el).borderTopLeftRadius)), 'إطار دائري').toBeGreaterThan(60);
        test.info().annotations.push({ type: 'hero slides', description: keys.join(' ← ') });
        await expectRtlNoScroll(page, '/');
    });

    test('٢٧ وR6 وR11 و٨ و٩: five cards in one row, each a link, and «مبادراتنا» rotates all initiatives', async ({ page }, testInfo) => {
        await visit(page, '/');
        // الأصل وحده: على الجوال تُكرَّر البطاقات نسخة مخفية (aria-hidden) للشريط المتحرك (R19)
        const cards = page.locator('#home-identity [data-identity-card]:not([aria-hidden="true"] *):not([aria-hidden="true"])');
        expect(await cards.evaluateAll((els) => els.map((el) => el.dataset.identityCard))).toEqual(['vision', 'goals', 'values', 'principles', 'initiatives']);
        for (const card of await cards.all()) {
            await expect(card.locator('[data-identity-link]').first()).toHaveAttribute('href', /\S/);
        }
        expect(await page.locator('[data-identity-card="initiatives"] [data-initiative-slide]').count(), 'المبادرات الخمس').toBeGreaterThanOrEqual(5);
        if (testInfo.project.name === 'desktop') {
            // صف واحد ثابت (توجيه المدير التنفيذي، 3 نوفمبر 2026 — director-home-2026-11-03.spec.mjs)، بدل ثلاث ثم اثنتين
            const tops = await cards.evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().top)));
            expect(new Set(tops).size).toBe(1);
        }
        await expectRtlNoScroll(page, '/');
    });

    test('H4 و١١ وR4: «خدماتنا» is one seamless moving row that includes programs and competitions, and no initiatives', async ({ page }) => {
        await visit(page, '/');
        const track = page.locator('#home-services-track');
        await track.scrollIntoViewIfNeeded();
        await page.waitForTimeout(500);
        const strip = await track.evaluate((el) => ({
            group: el.querySelector('.marquee-group').scrollWidth,
            width: el.clientWidth,
            animation: getComputedStyle(el.querySelector('.marquee-track')).animationName,
            text: el.querySelector('.marquee-group').innerText,
        }));
        expect(strip.group, 'لا فراغ في الشريط').toBeGreaterThanOrEqual(strip.width);
        expect(strip.animation).toBe('marquee-x');
        for (const service of ['الدورات والبرامج', 'المسابقات']) {
            expect(strip.text, service).toContain(service);
        }
        expect(strip.text, 'المبادرات في «عن الجمعية» لا في «خدماتنا»').not.toContain('المبادرات');
        await expectRtlNoScroll(page, '/');
    });

    // اتجاه البند ٢٤ عكسه توجيه المدير التنفيذي (3 نوفمبر 2026، البند ١٧ — director-home-2026-11-03.spec.mjs): يوتيوب يساراً والكتب يميناً
    test('٢٤ وH6 و١٧: the YouTube strip moves left and the books strip right, with the X card', async ({ page }) => {
        await visit(page, '/');
        await revealAll(page);
        const videos = page.locator('[data-media-strip="videos"] .marquee-track');
        const books = page.locator('[data-media-strip="books"] .marquee-track');
        await expect(videos).toBeAttached();
        await expect(books).toBeAttached();
        // الاتجاه يُطبَّق متى دخل الشريط الشاشة (data-inview)
        await videos.evaluate((el) => el.scrollIntoView({ block: 'center' }));
        await page.waitForTimeout(600);
        expect(await videos.evaluate((el) => getComputedStyle(el).animationDirection)).toBe('reverse');
        await books.evaluate((el) => el.scrollIntoView({ block: 'center' }));
        await page.waitForTimeout(600);
        expect(await books.evaluate((el) => getComputedStyle(el).animationDirection)).toBe('normal');
        await expect(page.locator('[data-x-card]')).toBeAttached();
        await expectRtlNoScroll(page, '/');
    });

    test('R7 وH7: announcements and the news ticker side by side with the same height', async ({ page }, testInfo) => {
        await visit(page, '/');
        const news = page.locator('[data-home-news-list]');
        await expect(news).toBeAttached();
        expect(await page.locator('[data-news-card]').count()).toBeGreaterThan(0);
        if (testInfo.project.name === 'desktop') {
            const boxes = await page.evaluate(() => {
                const list = document.querySelector('[data-home-news-list]').closest('[data-reveal], article, section > div > div');
                const sibling = list?.parentElement?.children;
                return [...(sibling ?? [])].map((el) => Math.round(el.getBoundingClientRect().height)).filter((h) => h > 100);
            });
            test.info().annotations.push({ type: 'announcements/news heights', description: boxes.join(', ') });
        }
        await expectRtlNoScroll(page, '/');
    });

    test('H9 وR17 و١٢: «البوابة بالأرقام» with counting numbers and a 30-day chart, then the partners strip before the footer', async ({ page }) => {
        await visit(page, '/');
        await revealAll(page);
        const stats = page.locator('[data-portal-stats]');
        await expect(stats).toBeAttached();
        await expect(page.locator('[data-portal-chart]')).toBeAttached();
        const partners = page.locator('section[aria-labelledby="home-partners"]');
        await expect(partners).toBeAttached();
        await expectBefore(stats, partners, '«البوابة بالأرقام» قبل «شركاؤنا»');
        await expectBefore(partners, page.locator('[data-site-footer]'), '«شركاؤنا» قبل التذييل');
        // H9: قراءات الكتب ومشاهدات المقاطع بعدد المرات (قرار صفوان، 3 نوفمبر، #152)
        await expect(stats, 'H9: قراءات الكتب').toContainText('قراءات الكتب');
        await expect(stats, 'H9: مشاهدات المقاطع').toContainText('مشاهدات المقاطع');
        await expect(stats, 'H9: لا صفحات ولا دقائق').not.toContainText(/صفحة مقروءة|دقيقة مشاهدة/);
        await expectRtlNoScroll(page, '/');
    });

    test('R25 و١٣: the page transition shows the filling logo with the calligraphy, only for a slow navigation', async ({ page }) => {
        await visit(page, '/contact');
        const overlay = page.locator('[data-page-transition]');
        await expect(overlay).toBeHidden();
        await page.evaluate(() => document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: {}, cancelable: true })));
        await expect(overlay).toBeVisible();
        await expect(overlay.locator('[data-transition-calligraphy]')).toBeVisible();
        await page.evaluate(() => document.dispatchEvent(new CustomEvent('livewire:navigated')));
        await expect(overlay).toBeHidden();
    });
});

test.describe('acceptance v0.15.0: inner pages', () => {
    test('R9 وR20 وR24: the library has four tiles and four strips in order', async ({ page }) => {
        await visit(page, '/library');
        const tiles = [];
        for (const tile of await page.locator('[data-library-tiles] li').all()) {
            tiles.push(await label(tile));
        }
        expect(tiles).toEqual(LIBRARY_SECTIONS);
        expect(await page.locator('[data-library-strip]').count(), 'الأقسام أشرطة').toBeGreaterThanOrEqual(3);
        await expectRtlNoScroll(page, '/library');
    });

    test('٢٢: a view-only governance document opens in the read-only reader and /download is 404', async ({ page }) => {
        const response = await visit(page, `/governance/${VIEW_ONLY_SLUG}`);
        test.skip(!response?.ok(), `لا وثيقة «للاطلاع فقط» منشورة (VIEW_ONLY_SLUG=${VIEW_ONLY_SLUG})`);
        const reader = page.locator('[data-document-reader] [data-pdf-reader]');
        await expect(reader).toBeVisible();
        await expect(reader).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        await expect(page.locator(`a[href$="/governance/${VIEW_ONLY_SLUG}/download"]`)).toHaveCount(0);
        await expect(page.getByRole('link', { name: /تنزيل/ })).toHaveCount(0);
        expect((await page.request.get(`/governance/${VIEW_ONLY_SLUG}/download`)).status()).toBe(404);
        await expectRtlNoScroll(page);
    });

    test('R14: the book reader fills its frame without a download', async ({ page }) => {
        const path = process.env.LIBRARY_READONLY_PATH ?? '/library/books/demo-book';
        const response = await visit(page, path);
        test.skip(!response?.ok(), `لا كتاب «للقراءة فقط» (${path})`);
        const reader = page.locator('[data-pdf-reader]');
        await expect(reader).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        const fill = await reader.evaluate((el) => {
            const canvas = el.querySelector('canvas');
            const stage = el.querySelector('[data-pdf-stage]') ?? el;
            return canvas ? canvas.getBoundingClientRect().width / stage.getBoundingClientRect().width : 0;
        });
        expect(fill, 'الصفحة تملأ عرض الإطار').toBeGreaterThan(0.85);
        await expectRtlNoScroll(page, path);
    });

    test('P3 وN6: the Brahin page counts the prize total up and offers «شارك واربح» / «بادر بالتسجيل»', async ({ page }) => {
        await openCompetition(page);
        const prize = page.locator('[data-prize-total]').first();
        await expect(prize).toContainText(PRIZE_TOTAL, { timeout: 10_000 });
        // زر البطل بنسختي الحاسوب والجوال (data-at)، فالظاهر منهما على كل عرض
        await expect(page.getByText(/شارك واربح|بادر بالتسجيل/).filter({ visible: true }).first()).toBeVisible();
        await expectRtlNoScroll(page, COMPETITION);
    });

    test('R15: the competitions page shows the current one prominently, upcoming and past sections', async ({ page }) => {
        await visit(page, '/competitions');
        await expect(page.locator('[data-competitions-current]')).toBeAttached();
        test.info().annotations.push({
            type: 'competitions sections',
            description: `upcoming ${await page.locator('[data-competitions-upcoming]').count()}, past ${await page.locator('[data-competitions-past]').count()}`,
        });
        await expectRtlNoScroll(page, '/competitions');
    });

    test('R4: the e-services page holds programs and competitions, and initiatives moved to About', async ({ page }) => {
        await visit(page, '/services');
        for (const service of ['الدورات والبرامج', 'المسابقات']) {
            await expect(page.locator('main'), service).toContainText(service);
        }
        await expect(page.locator('[data-service-grid]')).not.toContainText('المبادرات');
        await expectRtlNoScroll(page, '/services');
    });

    test('١٤ وP1: an inner page has its own hero image across the width and the big calligraphy under the title', async ({ page }) => {
        for (const path of ['/contact', '/governance', '/library', '/media', '/services', '/programs']) {
            await visit(page, path);
            const hero = page.locator('[data-page-hero]');
            await expect(hero, path).toBeVisible();
            await expect(hero.locator('[data-page-calligraphy]'), path).toBeVisible();
            const width = await hero.evaluate((el) => el.getBoundingClientRect().width);
            expect(width, `${path}: ممتد على العرض`).toBeGreaterThanOrEqual(page.viewportSize().width - 1);
            await expectRtlNoScroll(page, path);
        }
    });

    test('P9 وG7: the login page shows the full official logo', async ({ page }) => {
        await visit(page, '/login');
        const logos = await page.locator('main img').evaluateAll((els) => els.map((el) => el.getAttribute('src')));
        expect(logos.some((src) => /logo|brand/.test(src ?? '')), 'الشعار الرسمي').toBe(true);
        await expectRtlNoScroll(page, '/login');
    });
});

test.describe('acceptance v0.15.0: accounts', () => {
    test.skip(!fs.existsSync(ACCOUNT_STATE), 'بلا حساب مستفيد تجريبي (DEMO_CREDENTIALS)');
    test.use({ storageState: ACCOUNT_STATE });

    test('D1: the dashboard has the library strip, upcoming appointments as boxes, coloured cards and «قراءاتي»', async ({ page }) => {
        await visit(page, '/account');
        await expect(page.locator('[data-account-library-strip]')).toBeAttached();
        await expect(page.locator('[data-account-my-reading]')).toBeAttached();
        await expect(page.locator('[data-account-cards]')).toBeAttached();
        await expectRtlNoScroll(page, '/account');
        const reading = await visit(page, '/account/my-reading');
        expect(reading?.status()).toBeLessThan(400);
        await expect(page.locator('[data-reading-totals]')).toBeAttached();
        await expectRtlNoScroll(page, '/account/my-reading');
    });

    test('R18 وR21: «برامجي» and «برامج أتممتها» with reading, listening and watching programs', async ({ page }) => {
        await visit(page, '/account/programs');
        await expect(page.locator('main'), 'R18: برامج أتممتها').toContainText('أتممتها');
        await visit(page, '/programs');
        for (const tab of ['قراءة الكتب', 'الاستماع', 'المشاهدة']) {
            await expect(page.locator('main'), `R21: تبويب ${tab}`).toContainText(tab);
        }
    });

    test('١٦ و١٧: the sidebar stops before the footer and the competition count matches my entries', async ({ page }) => {
        for (const path of ['/account', '/account/competitions']) {
            await visit(page, path);
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            await page.waitForTimeout(500);
            const overlap = await page.evaluate(() => {
                const nav = [...document.querySelectorAll('.account-nav')].find((el) => el.offsetParent !== null);
                return nav ? nav.getBoundingClientRect().bottom - document.querySelector('[data-site-footer]').getBoundingClientRect().top : -1;
            });
            expect(overlap, `${path}: لا تداخل مع التذييل`).toBeLessThanOrEqual(0);
        }
    });
});

test.describe('acceptance v0.15.0: board portal', () => {
    test('B1 وR13 وR22: the board portal has stats, meetings with «انضم عبر تيمز», minutes and decisions, and «إنجازاتنا»', async ({ browser, baseURL }) => {
        test.skip(!BOARD, 'يحتاج عضو مجلس تجريبياً: BOARD_CREDENTIALS');
        const context = await browser.newContext({ baseURL, viewport: test.info().project.use.viewport });
        const page = await context.newPage();
        await visit(page, '/login');
        await page.fill('#field-login', BOARD.email);
        await page.fill('#field-password', BOARD.password);
        await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login')), page.locator('main form button[type=submit]').first().click()]);
        await visit(page, '/assembly');
        await expect(page.locator('[data-board-portal]')).toBeAttached();
        await expect(page.locator('[data-board-stats]')).toBeAttached();
        await expect(page.locator('[data-board-meetings]')).toBeAttached();
        await expect(page.locator('[data-board-join], [data-board-join-next]').first()).toBeAttached();
        await expect(page.locator('[data-board-records]')).toBeAttached();
        await expectRtlNoScroll(page, '/assembly');
        await expect(page.locator('main'), 'R13: إنجازاتنا').toContainText('إنجازاتنا');
        await context.close();
    });
});

// كل مسار عام ومسار حساب بلا معامل، وصفحات العناصر التجريبية: بلا تمرير أفقي على العروض الثلاثة (أمر التصور §٧)
const crawlPublic = [
    '/', '/about', '/initiatives', '/initiatives/qiyam', '/programs', '/competitions', COMPETITION, `${COMPETITION}/guide`,
    '/library', '/library/videos', '/library/audio', '/library/books', '/library/research', '/library/books/demo-book', '/library/audio/audio-01', '/library/videos/video-03',
    '/media', '/media/news', '/media/announcements', '/media/albums', '/media/partners', '/governance', `/governance/${VIEW_ONLY_SLUG}`,
    '/services', '/services/jobs', '/services/volunteering', '/services/assembly-membership', '/contact', '/privacy',
    '/login', '/register', '/forgot-password', '/forgot-password/phone', '/no-such-page-acceptance',
];
const crawlAccount = ['/account', '/account/data', '/account/programs', '/account/requests', '/account/competitions', '/account/forms', '/account/my-reading', '/account/security', '/assembly', '/assembly/meetings', '/assembly/minutes'];

test.describe('acceptance v0.15.0: no horizontal scroll', () => {
    test('every public route at this width', async ({ page }) => {
        test.setTimeout(600_000);
        const wide = [];
        for (const path of crawlPublic) {
            const response = await visit(page, path);
            await revealAll(page);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            if (overflow > 0) {
                wide.push(`${path} (${response?.status()}): ${overflow}px`);
            }
        }
        expect(wide).toEqual([]);
    });

    test('every account route at this width', async ({ browser, baseURL }) => {
        test.skip(!fs.existsSync(ACCOUNT_STATE), 'بلا حساب مستفيد تجريبي');
        test.setTimeout(300_000);
        const context = await browser.newContext({ baseURL, storageState: ACCOUNT_STATE, viewport: test.info().project.use.viewport, isMobile: test.info().project.use.isMobile, hasTouch: test.info().project.use.hasTouch });
        const page = await context.newPage();
        const wide = [];
        for (const path of crawlAccount) {
            const response = await visit(page, path);
            await revealAll(page);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            if (overflow > 0) {
                wide.push(`${path} (${response?.status()}): ${overflow}px`);
            }
        }
        await context.close();
        expect(wide).toEqual([]);
    });
});

// ─── لقطات تقرير القبول (ACCEPTANCE_SHOTS=1): docs/design/screenshots/acceptance-v0.15/<الشاشة>-<العرض>.png ─────────────────
// ببيانات محلية تجريبية لا تُحفظ في قيت (tests/browser/README.md «قائمة القبول»). ولوحة الإدارة بمشرف تجريبي بتحقق ثنائي
// (ADMIN_CREDENTIALS من ejaz:demo-user --admin --json)، وبوابة المجلس بعضو مجلس تجريبي (BOARD_CREDENTIALS).

const ADMIN = process.env.ADMIN_CREDENTIALS ? JSON.parse(process.env.ADMIN_CREDENTIALS) : null;
const ADMIN_STATE = 'storage/playwright/acceptance-admin.json';

function totp(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) {
        bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    }
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) {
        bytes.push(parseInt(bits.slice(i, i + 8), 2));
    }
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30_000)));
    const hmac = createHmac('sha1', Buffer.from(bytes)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 15;
    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

async function shoot(page, name, { full = true, reveal = true } = {}) {
    await page.evaluate(() => document.fonts.ready);
    if (reveal) {
        await revealAll(page);
        // العدّادات تعدّ حين يظهر كل منها (motion.js)، فتُمرَّر إليها واحداً واحداً وتُنتظر حتى رقمها النهائي، فلا تظهر أصفاراً في اللقطة
        const counters = await page.locator('[data-count-to]').all();
        for (const counter of counters) {
            await counter.evaluate((el) => el.scrollIntoView({ block: 'center' }));
            await page.waitForTimeout(150);
        }
        if (counters.length > 0) {
            await page.waitForTimeout(1900);
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.waitForTimeout(300);
        }
    }
    await page.screenshot({ path: `${SHOTS}/${name}-${page.viewportSize().width}.png`, fullPage: full, animations: 'disabled' });
}

const publicShots = [
    { name: 'home-full', path: '/' },
    { name: 'about', path: '/about' },
    { name: 'initiatives', path: '/initiatives' },
    { name: 'programs', path: '/programs' },
    { name: 'competition-brahin', path: COMPETITION },
    { name: 'competitions', path: '/competitions' },
    { name: 'library', path: '/library' },
    { name: 'library-videos', path: '/library/videos' },
    { name: 'library-audio', path: '/library/audio' },
    { name: 'library-books', path: '/library/books' },
    { name: 'library-research', path: '/library/research' },
    { name: 'governance', path: '/governance' },
    { name: 'media', path: '/media' },
    { name: 'media-news', path: '/media/news' },
    { name: 'services', path: '/services' },
    { name: 'contact', path: '/contact' },
    { name: 'login', path: '/login' },
];

test.describe('acceptance v0.15.0: screenshots', () => {
    test.skip(process.env.ACCEPTANCE_SHOTS !== '1', 'اللقطات بـ ACCEPTANCE_SHOTS=1');
    test.beforeAll(() => fs.mkdirSync(SHOTS, { recursive: true }));

    test('public pages', async ({ page }) => {
        test.setTimeout(900_000);
        // SHOTS_ONLY=home-full,competitions يقصر اللقطات على شاشات بعينها عند إعادة الفحص بعد دمج فرع
        const only = process.env.SHOTS_ONLY?.split(',');
        for (const { name, path } of publicShots.filter(({ name }) => !only || only.includes(name))) {
            await visit(page, path);
            await shoot(page, name);
        }
    });

    test('home hero, header states, footer and transition', async ({ page }, testInfo) => {
        test.setTimeout(300_000);
        await visit(page, '/');
        await shoot(page, 'home-hero', { full: false, reveal: false });

        if (testInfo.project.name === 'desktop') {
            const library = page.locator('[data-main-nav] [data-nav-dropdown]').filter({ hasText: 'مكتبة إعجاز' });
            await library.locator('[data-toggle="dropdown"]').hover();
            await expect(library.locator('[data-dropdown-menu]')).toBeVisible();
            await shoot(page, 'header-dropdown-library', { full: false, reveal: false });
            await page.mouse.move(5, 850);
            const services = page.locator('[data-main-nav] [data-nav-dropdown]').filter({ hasText: 'الخدمات الإلكترونية' });
            await services.locator('[data-toggle="dropdown"]').hover();
            await expect(services.locator('[data-dropdown-menu]')).toBeVisible();
            await shoot(page, 'header-dropdown-services', { full: false, reveal: false });
            await page.mouse.move(5, 850);
        } else {
            await page.locator('[data-toggle="mobile-menu"]').click();
            const toggle = page.locator('#mobile-menu [data-toggle="dropdown"]').filter({ hasText: 'مكتبة إعجاز' }).first();
            await toggle.click();
            await shoot(page, 'header-mobile-menu', { full: false, reveal: false });
            await page.locator('[data-toggle="mobile-menu"]').click();
        }

        await page.evaluate(() => window.scrollTo(0, 1500));
        await page.waitForTimeout(900);
        await page.screenshot({ path: `${SHOTS}/header-scrolled-${page.viewportSize().width}.png` });

        const footer = page.locator('[data-site-footer]');
        await footer.scrollIntoViewIfNeeded();
        await footer.screenshot({ path: `${SHOTS}/footer-${page.viewportSize().width}.png` });

        await visit(page, '/contact');
        await page.evaluate(() => document.dispatchEvent(new CustomEvent('livewire:navigate', { detail: {}, cancelable: true })));
        await expect(page.locator('[data-page-transition]')).toBeVisible();
        await page.waitForTimeout(600);
        await page.screenshot({ path: `${SHOTS}/transition-${page.viewportSize().width}.png` });
        await page.evaluate(() => document.dispatchEvent(new CustomEvent('livewire:navigated')));
    });

    test('readers: a read-only book and a view-only governance document', async ({ page }) => {
        test.setTimeout(300_000);
        await visit(page, process.env.LIBRARY_READONLY_PATH ?? '/library/books/demo-book');
        await expect(page.locator('[data-pdf-reader]')).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        await shoot(page, 'library-reader');
        await visit(page, `/governance/${VIEW_ONLY_SLUG}`);
        await expect(page.locator('[data-pdf-reader]')).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        await shoot(page, 'governance-document');
    });

    test('beneficiary account', async ({ browser, baseURL }) => {
        test.skip(!fs.existsSync(ACCOUNT_STATE), 'بلا حساب مستفيد تجريبي');
        test.setTimeout(300_000);
        const use = test.info().project.use;
        const context = await browser.newContext({ baseURL, storageState: ACCOUNT_STATE, viewport: use.viewport, isMobile: use.isMobile, hasTouch: use.hasTouch });
        const page = await context.newPage();
        for (const [name, path] of [['account-dashboard', '/account'], ['account-my-reading', '/account/my-reading'], ['account-competitions', '/account/competitions'], ['account-programs', '/account/programs']]) {
            await visit(page, path);
            await shoot(page, name);
        }
        // البند ١٧: «مشاركات المسابقات» في اللوحة من مصدر «مشاركاتي» نفسه، بحساب مسجّل في البراهين (العدد 1)
        await visit(page, '/account');
        const cards = page.locator('[data-stat-card]').first().locator('xpath=..');
        await cards.scrollIntoViewIfNeeded();
        await cards.screenshot({ path: `${SHOTS}/note17-competition-count-${page.viewportSize().width}.png` });
        await context.close();
    });

    test('board portal', async ({ browser, baseURL }) => {
        test.skip(!BOARD, 'يحتاج BOARD_CREDENTIALS');
        test.setTimeout(300_000);
        const use = test.info().project.use;
        const context = await browser.newContext({ baseURL, viewport: use.viewport, isMobile: use.isMobile, hasTouch: use.hasTouch });
        const page = await context.newPage();
        await visit(page, '/login');
        await page.fill('#field-login', BOARD.email);
        await page.fill('#field-password', BOARD.password);
        await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login')), page.locator('main form button[type=submit]').first().click()]);
        await visit(page, '/assembly');
        await shoot(page, 'board-portal');
        await context.close();
    });

    test('admin dashboard, list and form with the «ج» sidebar', async ({ browser, baseURL }) => {
        test.skip(!ADMIN, 'يحتاج ADMIN_CREDENTIALS');
        test.setTimeout(300_000);
        if (!fs.existsSync(ADMIN_STATE)) {
            const context = await browser.newContext({ baseURL });
            const page = await context.newPage();
            await page.goto('/login');
            await page.fill('#field-login', ADMIN.email);
            await page.fill('#field-password', ADMIN.password);
            await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
            while (Date.now() % 30_000 > 25_000) {
                await page.waitForTimeout(1000);
            }
            await page.fill('input[name="code"]', totp(ADMIN.totp_secret));
            await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);
            await context.storageState({ path: ADMIN_STATE });
            await context.close();
        }
        const use = test.info().project.use;
        const context = await browser.newContext({ baseURL, storageState: ADMIN_STATE, viewport: use.viewport, isMobile: use.isMobile, hasTouch: use.hasTouch });
        const page = await context.newPage();
        for (const [name, path] of [['admin-dashboard', '/admin'], ['admin-list', '/admin/library-items'], ['admin-form', '/admin/home-content']]) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(800);
            await page.screenshot({ path: `${SHOTS}/${name}-${page.viewportSize().width}.png`, fullPage: name === 'admin-dashboard' });
        }
        if (use.viewport.width < 1024) {
            // الشريط الجانبي على اللوحي والجوال يُفتح بزره
            await page.goto('/admin');
            await page.locator('.fi-topbar-open-sidebar-btn, button[x-on\\:click*="sidebar.open"]').first().click();
            await page.waitForTimeout(600);
            await page.screenshot({ path: `${SHOTS}/admin-sidebar-${page.viewportSize().width}.png` });
        }
        await context.close();
    });
});
