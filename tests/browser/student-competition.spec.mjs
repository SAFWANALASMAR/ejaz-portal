// مسابقة الطلاب (docs/plans/student-competition.md) على العروض الثلاثة: رحلة الطالب كاملة (صفحة المسابقة، والتسجيل،
// والبدء، والصفحات بالعداد والنص والمقطع، والمراجعة، والتسليم، و«تم استلام إجاباتك»، و«مشاركاتي» بلا درجة قبل
// النشر وبها بعده)، ورحلة المدير في اللوحة (المراحل، والبنك، والنصوص، والحقول، والمسجلون بالاطلاع على رقم
// الهوية، والنتائج، والقائمتان). وفي كل صفحة: الاتجاه من اليمين، وبلا تمرير أفقي ولا أخطاء في وحدة التحكم ولا
// مخالفة axe. واللقطات في docs/reviews/screenshots/2026-10-25-competition/<الخطوة>-<العرض>.png
//
// يحتاج: ejaz:demo-student-competition --json (STUDENT_COMPETITION) وejaz:demo-user --admin --json (ADMIN_STATS_CREDENTIALS).
// التشغيل في tests/browser/README.md («مسابقة الطلاب»).
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const DEMO = process.env.STUDENT_COMPETITION ? JSON.parse(process.env.STUDENT_COMPETITION) : null;
const ADMIN = process.env.ADMIN_STATS_CREDENTIALS ? JSON.parse(process.env.ADMIN_STATS_CREDENTIALS) : null;
const SHOTS = 'docs/reviews/screenshots/2026-10-25-competition';
const SERIALS = { desktop: 11, tablet: 12, mobile: 13 };

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

async function inspect(page, testInfo, step, errors) {
    await page.evaluate(() => document.fonts.ready);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, `${step}: no horizontal scroll`).toBeLessThanOrEqual(0);

    const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).exclude('.cf-turnstile').analyze();
    const summary = violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.map((n) => n.target.join(' ')).slice(0, 5).join(' | ')}`);
    expect(summary, `${step}: axe`).toEqual([]);

    mkdirSync(SHOTS, { recursive: true });
    await page.screenshot({ path: `${SHOTS}/${step}-${testInfo.project.name}.png`, fullPage: true });
    expect(errors, `${step}: console`).toEqual([]);
}

function collectErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));
    return errors;
}

async function login(page, email, password) {
    await page.goto('/login');
    await page.fill('#field-login', email);
    await page.fill('#field-password', password);
    await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login')), page.locator('main form button[type=submit]').first().click()]);
}

test.describe('student competition: the student journey', () => {
    test.skip(!DEMO, 'يحتاج مسابقة تجريبية: STUDENT_COMPETITION من ejaz:demo-student-competition --json');

    test('registers, takes the timed exam and sees only "answers received"', async ({ page }, testInfo) => {
        const errors = collectErrors(page);
        const width = testInfo.project.name;
        await login(page, DEMO.students[width], DEMO.password);

        await page.goto(`/competitions/${DEMO.slug}`);
        await expect(page.getByRole('heading', { name: 'مراحل المسابقة' })).toBeVisible();
        await inspect(page, testInfo, '01-competition', errors);

        await page.getByRole('link', { name: 'سجّل في المسابقة' }).click();
        await page.waitForURL('**/register');
        await inspect(page, testInfo, '02-register', errors);

        await page.fill('#field-full_name', 'طالب تجريبي بن مثال للاختبار');
        await page.fill('#field-national_id', nationalId(SERIALS[width]));
        await page.selectOption('#field-category_id', { label: 'المرحلة الثانوية' });
        await page.selectOption('#field-grade', { label: 'الأول الثانوي' });
        await page.fill('#field-guardian_phone', '0551112233');
        await page.check('input[name="pledge"]');
        await Promise.all([page.waitForURL(`**/competitions/${DEMO.slug}`), page.getByRole('button', { name: 'تسجيل', exact: true }).click()]);
        await expect(page.getByText('تم تسجيلك في المسابقة')).toBeVisible();

        await page.getByRole('link', { name: 'ادخل الاختبار' }).click();
        await page.waitForURL(`**/rounds/${DEMO.round}`);
        await inspect(page, testInfo, '03-start', errors);

        await Promise.all([page.waitForURL('**/units/1'), page.getByRole('button', { name: 'ابدأ الاختبار' }).click()]);
        await expect(page.locator('[data-exam-timer-value]')).toHaveText(/^\d\d:\d\d$/);

        let shotText = false;
        let shotVideo = false;
        for (let guard = 0; guard < 20 && /\/units\/\d+$/.test(page.url()); guard++) {
            if (guard === 0) {
                await inspect(page, testInfo, '04-question', errors);
            }

            if (!shotText && (await page.getByText('نص تجريبي للقراءة').count())) {
                shotText = true;
                await inspect(page, testInfo, '05-passage', errors);
            }

            if (!shotVideo && (await page.getByText('شاهد المقطع').count())) {
                shotVideo = true;
                await inspect(page, testInfo, '06-video', errors);
            }

            for (const group of await page.locator('fieldset').all()) {
                await group.locator('input[type=radio]').first().check();
            }

            await Promise.all([page.waitForLoadState('load'), page.getByRole('button', { name: /التالي|المراجعة/ }).first().click()]);
            await page.waitForURL(/\/(units\/\d+|review)$/);
        }

        await expect(page).toHaveURL(/\/review$/);
        await inspect(page, testInfo, '07-review', errors);

        await page.getByRole('button', { name: 'تسليم الإجابات' }).click();
        await expect(page.getByText('هل تريد تسليم إجاباتك الآن؟')).toBeVisible();
        await Promise.all([page.waitForURL(`**/rounds/${DEMO.round}`), page.getByRole('button', { name: 'تأكيد الإرسال' }).click()]);
        await expect(page.getByText('تم استلام إجاباتك', { exact: true })).toBeVisible();
        await expect(page.getByText('درجتك')).toHaveCount(0);
        await inspect(page, testInfo, '08-received', errors);

        await page.goto('/account/competitions');
        await expect(page.getByText('المرحلة التجريبية الحالية')).toBeVisible();
        await expect(page.getByText('درجتك')).toHaveCount(0);
        await inspect(page, testInfo, '09-account', errors);
    });

    test('shows the score in the account once the round results are published', async ({ page }, testInfo) => {
        const errors = collectErrors(page);
        await login(page, DEMO.students.veteran, DEMO.password);

        await page.goto('/account/competitions');
        await expect(page.getByText(/درجتك: \d/)).toBeVisible();
        await inspect(page, testInfo, '10-account-published', errors);
    });
});

// ── التحقق الثنائي: رمز TOTP لا يُقبل مرتين، وآخر نافذة في ملف admin-spa.spec.mjs نفسه
const WINDOW_FILE = 'storage/playwright/admin-spa-last-totp-window';

function base32(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '')) bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
    return Buffer.from(bytes);
}

async function freshCode() {
    const lastWindow = existsSync(WINDOW_FILE) ? Number(readFileSync(WINDOW_FILE, 'utf8')) : -1;
    let window = Math.floor(Date.now() / 30_000);
    while (window <= lastWindow || Date.now() % 30_000 > 25_000) {
        await new Promise((resolve) => setTimeout(resolve, 1000));
        window = Math.floor(Date.now() / 30_000);
    }
    mkdirSync('storage/playwright', { recursive: true });
    writeFileSync(WINDOW_FILE, String(window));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(window));
    const hmac = createHmac('sha1', base32(ADMIN.totp_secret)).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 15;
    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

test.describe('student competition: the director journey in the panel', () => {
    test.skip(!DEMO || !ADMIN, 'يحتاج STUDENT_COMPETITION وADMIN_STATS_CREDENTIALS');

    test('manages rounds, bank, fields, registrations and results', async ({ page }, testInfo) => {
        test.skip(testInfo.project.name === 'tablet', 'الحاسب والجوال يكفيان للوحة (حد محاولات التحقق الثنائي)');
        const errors = collectErrors(page);

        await page.goto('/login');
        await page.fill('#field-login', ADMIN.email);
        await page.fill('#field-password', ADMIN.password);
        await Promise.all([page.waitForURL('**/two-factor-challenge'), page.locator('main form button[type=submit]').first().click()]);
        await page.fill('input[name="code"]', await freshCode());
        await Promise.all([page.waitForURL((url) => !url.pathname.includes('two-factor-challenge')), page.locator('main form button[type=submit]').first().click()]);

        await page.goto('/admin/competitions');
        await page.getByText('مسابقة تجريبية للطلاب (للاختبار)').first().click();
        await page.waitForURL(/\/admin\/competitions\/\d+$/);
        const view = page.url();
        await page.waitForLoadState('networkidle');
        await inspect(page, testInfo, '20-admin-rounds', errors);

        for (const [tab, step] of [['بنك الأسئلة', '21-admin-bank'], ['النصوص والمقاطع', '22-admin-passages'], ['حقول التسجيل', '23-admin-fields'], ['المسجلون', '24-admin-registrations'], ['النتائج', '26-admin-results']]) {
            await page.getByRole('tab', { name: tab }).click();
            await page.waitForLoadState('networkidle');
            // محتوى التبويب المختار بعنوانه قبل الفحص واللقطة
            await expect(page.getByRole('tabpanel', { name: new RegExp(`^${tab}`) })).toBeVisible();
            await inspect(page, testInfo, step, errors);

            if (tab === 'المسجلون') {
                await expect(page.getByText(/••••••\d{4}/).first()).toBeVisible();
                await page.getByRole('button', { name: 'عرض رقم الهوية' }).first().click();
                await expect(page.getByText('يُسجَّل هذا الاطلاع في سجل العمليات.')).toBeVisible();
                await expect(page.getByRole('dialog').getByText(/\b1\d{9}\b/).first()).toBeVisible();
                await inspect(page, testInfo, '25-admin-reveal', errors);
                await page.keyboard.press('Escape');
            }
        }

        await page.getByRole('button', { name: 'سحب للمتساوين' }).click();
        await expect(page.getByText('عدد المراكز (الجوائز)')).toBeVisible();
        // تنتهي حركة فتح النافذة (شفافية متدرجة) قبل فحص التباين
        await page.waitForTimeout(600);
        await inspect(page, testInfo, '27-admin-tie-draw', errors);
        await page.keyboard.press('Escape');

        await page.goto(view.replace(/\/\d+$/, '') + '/' + view.split('/').pop() + '/edit');
        await page.getByRole('tab', { name: /الاختبار/ }).click();
        await expect(page.getByText('الفئات المفتوحة في هذا الإطلاق')).toBeVisible();
        await inspect(page, testInfo, '28-admin-form', errors);

        for (const [path, step] of [['/admin/competition-categories', '29-admin-categories'], ['/admin/competition-regions', '30-admin-regions']]) {
            await page.goto(path);
            await inspect(page, testInfo, step, errors);
        }
    });
});
