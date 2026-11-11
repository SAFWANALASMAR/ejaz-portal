// الرحلات الكاملة قبل الإصدار 0.15.0، على عرضي 1440 (desktop) و390 (mobile): الزائر، والمستفيد، والطالب، وعضو المجلس،
// والمدير التنفيذي، والرفض (بلا دخول، وبلا صلاحية، ومدخلات خاطئة). وفي كل خطوة: الاتجاه من اليمين، وبلا تمرير أفقي،
// وبلا أخطاء في وحدة التحكم، وبلا استجابة 4xx/5xx غير متوقعة من البوابة (v015-e2e.helpers.mjs).
//
// يكتب بيانات (حسابات وتسجيلات واستفسارات)، فللبيئة المحلية وحدها. التجهيز من جذر شجرة العمل:
//   mkdir -p storage/playwright/v015-creds
//   docker compose exec -T app php artisan tinker --execute="require base_path('tests/browser/v015-e2e.fixtures.php');" < /dev/null > storage/playwright/v015-creds/accounts.json
//     (tests/browser/v015-e2e.fixtures.php ينشئ حسابات member وassembly_member وboard_member وexecutive_director بكلمات مرور عشوائية وتحقق
//      ثنائي للأخيرين، وكتاباً منشوراً «للقراءة فقط» بمعرّف read_only_slug، وإنجازاً منشوراً. انظر تقرير الاختبار)
//   docker compose exec -T app php artisan ejaz:demo-student-competition --json > storage/playwright/v015-creds/student-competition.json
// ثم (الأصول مبنية بـ npm run build، وبلا public/hot):
//   docker compose run --rm --no-deps -e VITE_HOST=none browser npx playwright test --config=tests/browser/v015-e2e.config.mjs
// ورحلة الطالب تسجّل مرة لكل حساب: إعادة التشغيل تلزمها إعادة ejaz:demo-student-competition.
import { randomUUID } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { ACCOUNTS, STUDENTS, checkStep, login, state, visit, watch } from './v015-e2e.helpers.mjs';
import { verifyEmailCode } from './mail.helpers.mjs';

const ARABIC = /[\u0600-\u06FF]/;

// صفحات الزائر الثابتة، وتُضاف إليها روابط القائمة الرئيسية كلها
const PUBLIC_PAGES = [
    '/', '/achievements', '/assembly', '/competitions', '/contact', '/governance', '/initiatives',
    '/library', '/library/books', '/library/videos', '/library/audio', '/library/research',
    '/media', '/media/albums', '/media/announcements', '/media/news', '/media/partners',
    '/programs', '/services', '/services/assembly-membership', '/services/jobs', '/services/volunteering',
    '/login', '/register', '/forgot-password',
];

const isMobile = (testInfo) => testInfo.project.name === 'mobile';

async function menuLinks(page, testInfo) {
    if (isMobile(testInfo)) {
        await page.locator('[data-toggle="mobile-menu"]').click();
        const menu = page.locator('#mobile-menu');
        await expect(menu).toBeVisible();
        // تُفتح كل قائمة ثم تُغلق بالضغط نفسه قبل التي تليها، فتُقرأ روابط كل قائمة وحدها. وفتح التالية وأخرى فوقها مفتوحة
        // مختبر وحده في «the mobile menu opens the next dropdown in one tap» (تقرير الاختبار، البند ١)
        const hrefs = [];
        for (const button of await menu.locator('[data-toggle="dropdown"]').all()) {
            const list = page.locator(`#${await button.getAttribute('aria-controls')}`);
            await button.tap();
            await expect(list).toBeVisible();
            hrefs.push(...(await list.locator('a[href]').evaluateAll((links) => links.map((link) => link.href))));
            await button.tap();
            await expect(list).toBeHidden();
        }
        return [...hrefs, ...(await menu.locator('a[href]').evaluateAll((links) => links.map((link) => link.href)))];
    }

    const nav = page.locator('[data-main-nav]');
    for (const button of await nav.locator('[data-toggle="dropdown"]').all()) {
        await button.click();
        const list = page.locator(`#${await button.getAttribute('aria-controls')}`);
        await expect(list).toBeVisible();
        await expect(list.locator('a').first()).toBeVisible();
        await page.keyboard.press('Escape');
    }
    return nav.locator('a[href]').evaluateAll((links) => links.map((link) => link.href));
}

// ── الزائر ───────────────────────────────────────────────────────────────────────────────────────────────

test.describe('visitor', () => {
    test('home and the dropdown menus, then every public page', async ({ page, baseURL }, testInfo) => {
        test.setTimeout(600_000);
        const watcher = watch(page, baseURL);
        await visit(page, testInfo, watcher, '/', 'home');
        await expect(page.locator('[data-site-header]')).toBeVisible();

        const links = await menuLinks(page, testInfo);
        await checkStep(page, testInfo, watcher, 'menus-open');
        expect(links.length).toBeGreaterThan(3);

        const origin = new URL(baseURL).origin;
        const paths = new Set(PUBLIC_PAGES);
        for (const href of links) {
            const url = new URL(href);
            // المتجر يحوّل إلى متجر سلة خارج البوابة
            if (url.origin === origin && url.pathname !== '/store' && !url.hash) paths.add(url.pathname + url.search);
        }

        for (const path of paths) {
            const response = await page.goto(path);
            expect.soft(response?.status(), `status ${path}`).toBeLessThan(400);
            await expect.soft(page.locator('h1').first(), `h1 ${path}`).toBeVisible();
            await expect.soft(page.locator('[data-page-edit-button]'), `no edit button for visitors ${path}`).toHaveCount(0);
            await checkStep(page, testInfo, watcher, `page${path.replace(/[^a-z0-9]+/gi, '-')}`);
        }
        testInfo.annotations.push({ type: 'pages', description: [...paths].join(' ') });
    });

    test('the mobile menu opens the next dropdown in one tap', async ({ page }, testInfo) => {
        test.skip(!isMobile(testInfo), 'قائمة الجوال');
        // تقرير الاختبار 2026-11-03، البند ١، وأُصلح (docs/decisions/2026-11-03-mobile-menu.md): بقائمة مفتوحة فوقها، كانت أول
        // ضغطة على القائمة التالية تغلق الاثنتين
        await page.goto('/');
        await page.locator('[data-toggle="mobile-menu"]').click();
        const buttons = page.locator('#mobile-menu [data-toggle="dropdown"]');
        await buttons.nth(0).tap();
        await expect(buttons.nth(0)).toHaveAttribute('aria-expanded', 'true');
        await buttons.nth(1).tap();
        await expect(buttons.nth(1)).toHaveAttribute('aria-expanded', 'true', { timeout: 3_000 });
    });

    test('search finds a page from the header and opens it', async ({ page, baseURL }, testInfo) => {
        const watcher = watch(page, baseURL);
        await visit(page, testInfo, watcher, '/', 'home');
        await page.locator('[data-search-open]').first().click();
        const dialog = page.locator('[data-search-dialog]');
        await expect(dialog).toBeVisible();
        await dialog.locator('[data-search-input]').fill('المكتبة');
        const option = dialog.locator('[role="option"]').first();
        await expect(option).toBeVisible();
        await checkStep(page, testInfo, watcher, 'search-results');
        const target = new URL(await option.getAttribute('href'), baseURL).pathname;
        await Promise.all([page.waitForURL((url) => url.pathname === target), option.click()]);
        await checkStep(page, testInfo, watcher, 'search-opened');

        // البحث داخل المكتبة
        await visit(page, testInfo, watcher, '/library/books?q=' + encodeURIComponent('كتاب'), 'library-search');
    });

    test('contact: an inquiry is sent and the success message appears without a page reload', async ({ page, baseURL }, testInfo) => {
        const watcher = watch(page, baseURL);
        await visit(page, testInfo, watcher, '/contact', 'contact');
        const form = page.locator('form[data-async-form]');
        await form.locator('[name=name]').fill('زائر تجريبي للاختبار');
        await form.locator('[name=email]').fill(`e2e-${Date.now()}@example.com`);
        await form.locator('[name=type]').selectOption({ index: 1 });
        await form.locator('[name=subject]').fill('استفسار تجريبي للاختبار');
        await form.locator('[name=message]').fill('هذه رسالة تجريبية من اختبار الرحلات قبل الإصدار، لا تحتاج إلى رد.');
        // النموذج يرفض ما يُرسل قبل ثلاث ثوانٍ من فتحه (ContactRequest::MIN_SECONDS)
        await page.waitForTimeout(3_500);

        await page.evaluate(() => (window.__e2eNoReload = true));
        const posted = page.waitForResponse((response) => response.url().endsWith('/contact') && response.request().method() === 'POST');
        await form.locator('button[type=submit], button:not([type])').last().click();
        expect((await posted).status()).toBe(201);

        const success = page.locator('[data-contact-success]');
        await expect(success).toBeVisible();
        await expect(success.locator('[data-contact-reference]')).not.toBeEmpty();
        expect(await page.evaluate(() => window.__e2eNoReload === true), 'no full page reload').toBe(true);
        await checkStep(page, testInfo, watcher, 'contact-sent');
    });
});

// ── المستفيد ─────────────────────────────────────────────────────────────────────────────────────────────

test.describe('beneficiary: registration', () => {
    test('registers a new account, verifies the email code and lands on the dashboard', async ({ page, baseURL, request }, testInfo) => {
        const watcher = watch(page, baseURL);
        // جوال لكل مشروع وتشغيل: الرقم فريد على كل الحسابات (decisions/2026-11-05-phone-login.md)
        const phone = `05${testInfo.project.name.length % 10}${String(Date.now()).slice(-7)}`;
        // والبريد إلزامي وفريد، وإليه رمز التوثيق (decisions/2026-11-06-email-otp.md)
        const email = `e2e-register-${testInfo.project.name}-${Date.now()}@example.com`;
        const password = `E2e-${randomUUID()}`;

        await visit(page, testInfo, watcher, '/register', 'register');
        await page.fill('#field-name', 'مستفيد تجريبي للاختبار');
        await page.fill('#field-phone', phone);
        await page.fill('#field-email', email);
        await page.fill('#field-password', password);
        await page.fill('#field-password_confirmation', password);
        await page.locator('input[name=privacy]').check();
        await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/register')), page.locator('main form button[type=submit]').first().click()]);
        await expect(page).toHaveURL(/\/email\/verify$/);
        await checkStep(page, testInfo, watcher, 'email-code');

        await verifyEmailCode(page, request, email);
        await expect(page).toHaveURL(/\/account/);
        await checkStep(page, testInfo, watcher, 'registered');

        // الخروج ثم الدخول بالجوال
        await page.context().clearCookies();
        await login(page, { email: phone, password });
        await expect(page).toHaveURL(/\/account/);
        await checkStep(page, testInfo, watcher, 'login-phone');
    });
});

test.describe('beneficiary: account and reading', () => {
    test.skip(!ACCOUNTS, 'accounts.json');
    test.use({ storageState: state('beneficiary') });

    test('dashboard, account pages, my reading and the read-only book', async ({ page, baseURL, request }, testInfo) => {
        const watcher = watch(page, baseURL);
        await visit(page, testInfo, watcher, '/account', 'dashboard');
        await page.goto('/programs');
        await expect(page.locator('[data-page-edit-button]'), 'no edit button for a beneficiary').toHaveCount(0);
        await page.goto('/account');
        await expect(page.locator('h1').first()).toBeVisible();

        for (const path of ['/account/profile', '/account/requests', '/account/programs', '/account/competitions', '/account/security', '/account/data']) {
            const response = await page.goto(path);
            expect.soft(response?.status(), path).toBe(200);
            await checkStep(page, testInfo, watcher, `account${path.replace(/[^a-z0-9]+/gi, '-')}`);
        }

        // القارئ: الصفحة الأولى مرسومة، ولا تنزيل ولا إطار ولا رابط للملف
        const slug = ACCOUNTS.read_only_slug;
        await visit(page, testInfo, watcher, `/library/books/${slug}`, 'book');
        const reader = page.locator('[data-pdf-reader]');
        await expect(reader).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });
        await expect(page.locator(`a[href*="/library/books/${slug}/download"]`)).toHaveCount(0);
        await expect(page.locator('a[download], [download]')).toHaveCount(0);
        await expect(page.locator(`a[href*="/library/books/${slug}/file"]`)).toHaveCount(0);
        await expect(page.locator('iframe, embed, object')).toHaveCount(0);
        await reader.locator('[data-pdf-next]').click();
        await expect(reader.locator('[data-pdf-page]')).toHaveValue('2');
        await checkStep(page, testInfo, watcher, 'book-page-2');

        // التنزيل 404، وفتح الملف نفسه في تبويب يعود إلى صفحة الكتاب لا إلى عارض المتصفح
        watcher.allow.push(`/library/books/${slug}/download`);
        expect((await request.get(`/library/books/${slug}/download`, { maxRedirects: 0 })).status()).toBe(404);
        const direct = await page.goto(`/library/books/${slug}/file`);
        expect(direct?.headers()['content-type'] ?? '').not.toContain('application/pdf');
        await expect(page).toHaveURL(new RegExp(`/library/books/${slug}$`));
        watcher.allow.length = 0;
        await checkStep(page, testInfo, watcher, 'book-direct-file');

        // «قراءاتي» بالكتاب وزر «أكمل»
        await visit(page, testInfo, watcher, '/account/my-reading', 'my-reading');
        await expect(page.locator(`[data-reading-item] a[href*="/library/books/${slug}"]`).first()).toBeVisible();
    });
});

// ── الطالب: التسجيل في مسابقة البراهين والاختبار ──────────────────────────────────────────────────────────────

test.describe('student: competition', () => {
    test.skip(!STUDENTS, 'student-competition.json');

    test('registers in the competition and takes the exam', async ({ page, baseURL }, testInfo) => {
        test.setTimeout(300_000);
        const watcher = watch(page, baseURL);
        const student = STUDENTS.students[testInfo.project.name];
        await login(page, { email: student, password: STUDENTS.password });

        await visit(page, testInfo, watcher, `/competitions/${STUDENTS.slug}`, 'competition');
        await expect(page.getByRole('heading', { name: 'مراحل المسابقة' })).toBeVisible();

        await page.getByRole('link', { name: 'سجّل في المسابقة' }).click();
        await page.waitForURL('**/register');
        await checkStep(page, testInfo, watcher, 'competition-register');

        await page.fill('#field-full_name', 'طالب تجريبي بن مثال للاختبار');
        if (await page.locator('#field-national_id').count()) {
            await page.fill('#field-national_id', nationalId(testInfo.project.name === 'desktop' ? 21 : 23));
        }
        await page.selectOption('#field-category_id', { label: 'المرحلة الثانوية' });
        await page.selectOption('#field-grade', { label: 'الأول الثانوي' });
        await page.selectOption('#field-gender', 'male');
        await page.fill('#field-guardian_phone', '0551112233');
        await page.check('input[name="pledge"]');
        await Promise.all([page.waitForURL(`**/competitions/${STUDENTS.slug}`), page.getByRole('button', { name: 'تسجيل', exact: true }).click()]);
        await expect(page.getByText('تم تسجيلك في المسابقة')).toBeVisible();
        await checkStep(page, testInfo, watcher, 'competition-registered');

        await page.getByRole('link', { name: 'ادخل الاختبار' }).click();
        await page.waitForURL(`**/rounds/${STUDENTS.round}`);
        await checkStep(page, testInfo, watcher, 'exam-start');
        await Promise.all([page.waitForURL('**/units/1'), page.getByRole('button', { name: 'ابدأ الاختبار' }).click()]);
        await expect(page.locator('[data-exam-timer-value]')).toHaveText(/^\d\d:\d\d$/);

        for (let guard = 0; guard < 20 && /\/units\/\d+$/.test(page.url()); guard++) {
            await checkStep(page, testInfo, watcher, `exam-unit-${guard + 1}`);
            for (const group of await page.locator('fieldset').all()) {
                const radio = group.locator('input[type=radio]');
                if (await radio.count()) await radio.first().check();
            }
            // التنقّل بين الأسئلة داخل الصفحة منذ #213 (decisions/2026-11-07-exam-fullscreen.md): لا تحميل للصفحة، والعنوان يُستبدل
            // بعد الحفظ، فيُنتظر عنوان الخطوة التالية ثم زر غير مشغول قبل الخطوة التي بعدها
            const from = page.url();
            await page.getByRole('button', { name: /التالي|المراجعة/ }).first().click();
            await page.waitForURL((url) => url.href !== from && /\/(units\/\d+|review)$/.test(url.pathname));
            await expect(page.locator('[data-exam-step][aria-busy="true"]')).toHaveCount(0);
        }

        await expect(page).toHaveURL(/\/review$/);
        await checkStep(page, testInfo, watcher, 'exam-review');
        await page.getByRole('button', { name: 'تسليم الإجابات' }).click();
        await expect(page.getByText('هل تريد تسليم إجاباتك الآن؟')).toBeVisible();
        await Promise.all([page.waitForURL(`**/rounds/${STUDENTS.round}`), page.getByRole('button', { name: 'تأكيد الإرسال' }).click()]);
        await expect(page.getByText('تم استلام إجاباتك', { exact: true })).toBeVisible();
        await checkStep(page, testInfo, watcher, 'exam-received');

        await visit(page, testInfo, watcher, '/account/competitions', 'account-competitions');
        await expect(page.getByText('المرحلة التجريبية الحالية')).toBeVisible();
    });
});

// رقم هوية صحيح الصيغة ورقم التحقق (كـ RegisterLoadStudents::nationalId)
function nationalId(serial) {
    const body = '19' + String(serial).padStart(7, '0');
    let sum = 0;
    [...body].forEach((digit, position) => {
        const number = Number(digit) * (position % 2 === 0 ? 2 : 1);
        sum += number > 9 ? number - 9 : number;
    });
    return body + ((10 - (sum % 10)) % 10);
}

// ── عضو مجلس الإدارة ────────────────────────────────────────────────────────────────────────────────────────

test.describe('board member', () => {
    test.skip(!ACCOUNTS, 'accounts.json');
    test.use({ storageState: state('board') });

    test('the board portal with its achievements, meetings and the public achievements page', async ({ page, baseURL }, testInfo) => {
        const watcher = watch(page, baseURL);
        await visit(page, testInfo, watcher, '/assembly', 'board-portal');
        const section = page.locator('[data-board-achievements]');
        await expect(section).toBeVisible();
        await expect(section.locator('[data-achievements-this-year]')).toContainText('عدد الإنجازات هذا العام');
        await expect(section.locator('[data-achievements-timeline]')).toBeVisible();

        for (const path of ['/assembly/meetings', '/assembly/minutes', '/assembly/files', '/account']) {
            const response = await page.goto(path);
            expect.soft(response?.status(), path).toBe(200);
            await checkStep(page, testInfo, watcher, `board${path.replace(/[^a-z0-9]+/gi, '-')}`);
        }

        await visit(page, testInfo, watcher, '/achievements', 'achievements');
        await expect(page.locator('[data-achievements-timeline]')).toBeVisible();
    });
});

// ── المدير التنفيذي ─────────────────────────────────────────────────────────────────────────────────────────

test.describe('executive director', () => {
    test.skip(!ACCOUNTS, 'accounts.json');
    test.use({ storageState: state('director') });

    test('moving from the site to the panel raises no view-transition error', async ({ page }) => {
        // تقرير الاختبار 2026-11-03، البند ٢: كان الموقع العام يطلب انتقال المستندات واللوحة لا تطلبه، فيُلغى الانتقال بزر
        // «تعديل هذه الصفحة» بخطأ غير ملتقط. وأُسقط @view-transition (decisions/2026-11-06-transition-speed.md)، والاختبار حارس من عودته
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto('/programs');
        await Promise.all([page.waitForURL(/\/admin\/site-pages\/\d+\/edit/), page.locator('[data-page-edit-button]').click()]);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1_500);
        expect(errors).toEqual([]);
    });

    test('admin panel, statistics and the page editors', async ({ page, baseURL }, testInfo) => {
        test.setTimeout(300_000);
        const watcher = watch(page, baseURL);
        await visit(page, testInfo, watcher, '/admin', 'admin-dashboard');
        await expect(page).toHaveURL(/\/admin$/);
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        await page.waitForLoadState('networkidle');
        await expect(page.locator('.fi-wi-stats-overview').first()).toBeVisible();
        // رسم الزيارات مرسوماً، أو حالته الفارغة قبل أول حساب للإحصائيات (ejaz:refresh-stats)
        await expect(page.locator('.fi-wi-chart canvas:visible, .fi-wi-chart :text("لا زيارات معدودة بعد")').first()).toBeVisible();
        await checkStep(page, testInfo, watcher, 'admin-stats');

        // «تحليل الإحصائيات» بالذكاء الاصطناعي مطفأ افتراضياً (StatsAnalysisPage::canAccess)، فلا يُزار هنا

        // محرر محتوى الرئيسية
        const home = await page.goto('/admin/home-content');
        expect.soft(home?.status(), 'home-content').toBe(200);
        await expect(page.locator('.fi-main form').first()).toBeVisible();
        await page.waitForLoadState('networkidle');
        await checkStep(page, testInfo, watcher, 'admin-home-content');

        // محرر الصفحات: القائمة ثم تعديل أول صفحة
        await visit(page, testInfo, watcher, '/admin/content-pages', 'admin-content-pages');
        const edit = page.locator('a[href*="/admin/content-pages/"][href$="/edit"]').first();
        if (await edit.count()) {
            await Promise.all([page.waitForURL(/\/edit$/), edit.click()]);
            await page.waitForLoadState('networkidle');
            await expect(page.locator('.fi-main form').first()).toBeVisible();
            await checkStep(page, testInfo, watcher, 'admin-content-page-edit');
        } else {
            const row = page.locator('.fi-ta-row').first();
            await expect(row, 'a content page to edit').toBeVisible();
            await Promise.all([page.waitForURL(/\/admin\/content-pages\/[^/]+(\/edit)?$/), row.locator('a, td').first().click()]);
            await page.waitForLoadState('networkidle');
            await checkStep(page, testInfo, watcher, 'admin-content-page-edit');
        }

        // «محرر الصفحات» (#124): زر «تعديل هذه الصفحة» في الموقع، ثم مسودة ومعاينتها برابطها الموقّع، والصفحة العامة كما هي
        const draftTitle = `برامجنا — مسودة اختبار ${testInfo.project.name}`;
        await visit(page, testInfo, watcher, '/programs', 'programs-edit-button');
        const button = page.locator('[data-page-edit-button]');
        await expect(button).toBeVisible();
        await Promise.all([page.waitForURL(/\/admin\/site-pages\/\d+\/edit/), button.click()]);
        await page.waitForLoadState('networkidle');
        await checkStep(page, testInfo, watcher, 'page-editor');
        await page.getByRole('textbox', { name: /^العنوان/ }).first().fill(draftTitle);
        await page.getByRole('button', { name: 'حفظ المسودة' }).click();
        await expect(page.getByText('حُفظت المسودة')).toBeVisible({ timeout: 15_000 });
        await checkStep(page, testInfo, watcher, 'page-editor-saved');
        const previewLink = page.getByRole('link', { name: 'معاينة' }).first();
        await expect(previewLink).toBeVisible({ timeout: 15_000 });
        const preview = await page.context().newPage();
        const previewWatcher = watch(preview, baseURL);
        await preview.goto(await previewLink.getAttribute('href'));
        await expect(preview.locator('[data-page-preview-banner]')).toBeVisible();
        await expect(preview.locator('[data-page-hero] h1')).toHaveText(draftTitle);
        await checkStep(preview, testInfo, previewWatcher, 'page-preview');
        await preview.close();
        await visit(page, testInfo, watcher, '/programs', 'programs-unchanged');
        await expect(page.locator('[data-page-hero] h1')).not.toHaveText(draftTitle);

        for (const path of ['/admin/site-pages', '/admin/users', '/admin/competitions', '/admin/achievements', '/admin/library-items']) {
            const response = await page.goto(path);
            expect.soft(response?.status(), path).toBe(200);
            await checkStep(page, testInfo, watcher, `admin${path.replace(/[^a-z0-9]+/gi, '-')}`);
        }
    });
});

// ── الرفض ─────────────────────────────────────────────────────────────────────────────────────────────────

test.describe('rejections', () => {
    test('protected pages without login redirect to the login page', async ({ page, baseURL }, testInfo) => {
        const watcher = watch(page, baseURL);
        for (const path of ['/account', '/account/my-reading', '/assembly/meetings', '/admin']) {
            await page.goto(path);
            await expect(page, path).toHaveURL(/\/login$/);
            await checkStep(page, testInfo, watcher, `guest${path.replace(/[^a-z0-9]+/gi, '-')}`);
        }
    });

    test.describe('without permission', () => {
        test.skip(!ACCOUNTS, 'accounts.json');

        for (const [role, denied] of [['beneficiary', ['/admin', '/assembly/meetings', '/assembly/files']], ['assembly', ['/admin']], ['board', ['/admin/users', '/admin/site-settings']]]) {
            // المدير التنفيذي مدير نظام بكل الصلاحيات (config/permissions.php)، فالمنع يُختبر بعضو المجلس
            test(`${role} gets 403/404 on ${denied.join(' ')}`, async ({ browser, baseURL }, testInfo) => {
                const context = await browser.newContext({ storageState: state(role), ...testInfo.project.use });
                const page = await context.newPage();
                const watcher = watch(page, baseURL);
                for (const path of denied) {
                    watcher.allow.push(path);
                    const response = await page.goto(path);
                    expect.soft([403, 404], `${role} ${path}`).toContain(response?.status());
                    await expect.soft(page.locator('body')).toContainText(ARABIC);
                    await checkStep(page, testInfo, watcher, `denied-${role}${path.replace(/[^a-z0-9]+/gi, '-')}`);
                    watcher.allow.length = 0;
                }
                await context.close();
            });
        }
    });

    test('invalid registration shows Arabic validation messages', async ({ page, baseURL }, testInfo) => {
        const watcher = watch(page, baseURL);
        await visit(page, testInfo, watcher, '/register', 'register');
        await page.locator('main form').first().evaluate((form) => (form.noValidate = true));
        await page.fill('#field-phone', '0412');
        await page.fill('#field-password', 'short');
        await page.fill('#field-password_confirmation', 'different');
        await page.fill('#field-email', 'not-an-email');
        await Promise.all([page.waitForURL('**/register'), page.locator('main form button[type=submit]').first().click()]);
        const messages = page.locator('main [id$="-error"], main .field-error, main [role=alert], main p.text-red-700, main p.text-danger');
        await expect(messages.first()).toBeVisible();
        const texts = (await messages.allInnerTexts()).map((text) => text.trim()).filter(Boolean);
        expect(texts.length).toBeGreaterThan(0);
        for (const text of texts) {
            expect.soft(text, 'Arabic message').toMatch(ARABIC);
            expect.soft(text, 'no translation key').not.toMatch(/validation\.|account\.|The [a-z ]+ field/);
        }
        await checkStep(page, testInfo, watcher, 'register-invalid');
    });

    test('invalid contact inquiry shows Arabic validation messages without a reload', async ({ page, baseURL }, testInfo) => {
        const watcher = watch(page, baseURL);
        await visit(page, testInfo, watcher, '/contact', 'contact');
        await page.waitForTimeout(3_500);
        const form = page.locator('form[data-async-form]');
        await form.locator('[name=email]').fill('not-an-email');
        watcher.allow.push('/contact');
        const posted = page.waitForResponse((response) => response.url().endsWith('/contact') && response.request().method() === 'POST');
        await form.locator('button[type=submit], button:not([type])').last().click();
        expect((await posted).status()).toBe(422);
        const errors = form.locator('[data-form-errors]');
        await expect(errors).toBeVisible();
        const text = await errors.innerText();
        expect(text).toMatch(ARABIC);
        expect(text).not.toMatch(/validation\.|contact\.|The [a-z ]+ field/);
        await checkStep(page, testInfo, watcher, 'contact-invalid');
    });
});
