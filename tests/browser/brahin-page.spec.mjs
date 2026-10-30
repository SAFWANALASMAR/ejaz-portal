// صفحة مسابقة البراهين بملاحظات المدير التنفيذي (fix/brahin-director-notes) على العروض الثلاثة: المجالات والمراحل بلا
// عدد ولا مدة ولا تاريخ، والأقسام النصية بطاقات مطوية تُفتح وتُغلق، ودليل الدخول شرائح تتقدم بالزر والأسهم، وصفحة
// بدء الاختبار بالعدد والمدة. وفي كل صفحة: الاتجاه من اليمين وبلا تمرير أفقي. واللقطات في
// docs/design/screenshots/brahin-director-notes/<الصفحة>-<before|after>-<العرض>.png
//
// يحتاج: مسابقة البراهين منشورة (BRAHIN_SLUG، افتراضياً brahin-1448)، وejaz:demo-student-competition --json
// (STUDENT_COMPETITION) لصفحة البدء بالطالب التجريبي «veteran». وSHOTS_LABEL=before يلتقط اللقطات وحدها.
import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const SLUG = process.env.BRAHIN_SLUG ?? 'brahin-1448';
const DEMO = process.env.STUDENT_COMPETITION ? JSON.parse(process.env.STUDENT_COMPETITION) : null;
const LABEL = process.env.SHOTS_LABEL ?? 'after';
const SHOTS = 'docs/design/screenshots/brahin-director-notes';

async function shoot(page, testInfo, name) {
    await page.evaluate(() => document.fonts.ready);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, `${name}: no horizontal scroll`).toBeLessThanOrEqual(0);
    mkdirSync(SHOTS, { recursive: true });
    await page.screenshot({ path: `${SHOTS}/${name}-${LABEL}-${testInfo.project.name}.png`, fullPage: true });
}

test('the Brahin competition page', async ({ page }, testInfo) => {
    await page.goto(`/competitions/${SLUG}`);
    await expect(page.getByRole('heading', { name: 'مراحل المسابقة' })).toBeVisible();
    await shoot(page, testInfo, 'competition');
});

test('the exam start page shows the question count and the duration', async ({ page }, testInfo) => {
    test.skip(!DEMO, 'يحتاج STUDENT_COMPETITION من ejaz:demo-student-competition --json');
    await page.goto('/login');
    await page.fill('#field-login', DEMO.students.veteran);
    await page.fill('#field-password', DEMO.password);
    await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login')), page.locator('main form button[type=submit]').first().click()]);

    await page.goto(`/competitions/${DEMO.slug}/rounds/${DEMO.round}`);
    await expect(page.getByText('6 أسئلة')).toBeVisible();
    await expect(page.getByText('30 دقيقة')).toBeVisible();
    await shoot(page, testInfo, 'exam-start');
});

test.describe('interactions', () => {
    test.skip(LABEL === 'before', 'قبل التعديل لا بطاقات مطوية ولا شرائح');

    test('a collapsible card opens and closes, and "show all" opens them all', async ({ page }) => {
        await page.goto(`/competitions/${SLUG}`);
        const card = page.locator('[data-collapsible]').first();
        const summary = card.locator('summary');
        await expect(card).not.toHaveAttribute('open', '');
        await summary.click();
        await expect(card).toHaveAttribute('open', '');
        await summary.focus();
        await page.keyboard.press('Enter');
        await expect(card).not.toHaveAttribute('open', '');

        const toggle = page.locator('[data-collapsible-all]');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        for (const each of await page.locator('[data-collapsible]').all()) {
            await expect(each).toHaveAttribute('open', '');
        }
        await toggle.click();
        await expect(page.locator('[data-collapsible][open]')).toHaveCount(0);
    });

    test('the entry guide slides move with the buttons, the dots and the arrow keys', async ({ page }) => {
        await page.goto(`/competitions/${SLUG}`);
        const slides = page.locator('[data-slides]');
        await slides.scrollIntoViewIfNeeded();
        const current = slides.locator('[data-slide]:not([hidden])');
        await expect(current).toHaveCount(1);
        await expect(current).toHaveAttribute('data-slide', '1');
        await expect(slides.getByRole('button', { name: 'السابق' })).toBeDisabled();

        await slides.getByRole('button', { name: 'التالي' }).click();
        await expect(current).toHaveAttribute('data-slide', '2');
        await expect(slides.locator('[data-slide-dot][aria-current="step"]')).toHaveCount(1);

        await slides.locator('[data-slides-track]').focus();
        // في RTL «التالي» جهة اليسار
        await page.keyboard.press('ArrowLeft');
        await expect(current).toHaveAttribute('data-slide', '3');
        await page.keyboard.press('ArrowRight');
        await expect(current).toHaveAttribute('data-slide', '2');

        await slides.locator('[data-slide-dot]').last().click();
        await expect(slides.getByRole('button', { name: 'التالي' })).toBeDisabled();

        // التركيز لا يسقط حين يُعطَّل الزر المضغوط عند الطرف
        await slides.locator('[data-slide-dot]').nth(1).click();
        await slides.getByRole('button', { name: 'السابق' }).click();
        await expect(slides.getByRole('button', { name: 'السابق' })).toBeDisabled();
        await expect(slides.getByRole('button', { name: 'التالي' })).toBeFocused();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        expect(overflow).toBeLessThanOrEqual(0);
    });
});
