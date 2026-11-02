// فجوات تقرير القبول v0.15.0 (docs/reviews/2026-11-02-قبول-v0.15.0.md، القسم ٧) على العروض الثلاثة:
// - البند ١٥: التذييل أقصر بنحو الثلث من 455 بكسل القديمة، ببيانات التواصل الكاملة (البريد والجوال والعنوان والعنوان المختصر
//   وواتساب والحسابات) — تُدخل من «إعدادات الموقع» في البيئة المحلية قبل التشغيل، وإلا تخطى الاختبار قياسه.
// - صور الرئيسية والمركز الإعلامي بعرض وارتفاع صريحين، فلا تزاح الصفحة عند تحميلها.
// - «شارك واربح» في بطل مسابقة الطلاب بعرض كامل تقريباً على الجوال (COMPETITION_SLUG، الافتراضي brahin-1448).
// - نقاط الدوّارات أهداف لمس 44 بكسل على الجوال.
//
// التشغيل (مع الأصول المبنية):
// docker compose run --rm --no-deps -e VITE_HOST=none -e COMPETITION_SLUG=brahin-1448 browser \
//   npx playwright test --config=tests/browser/playwright.config.mjs acceptance-polish.spec.mjs
import { expect, test } from '@playwright/test';

const SLUG = process.env.COMPETITION_SLUG ?? 'brahin-1448';

async function visit(page, path) {
    const response = await page.goto(path, { waitUntil: 'networkidle' });
    expect(response?.status(), path).toBe(200);
    // إظهار ما يظهر بالتمرير، فيُقاس كل عنصر في مكانه النهائي
    await page.evaluate(() => document.documentElement.removeAttribute('data-reveal-ready'));
}

async function expectRtlNoScroll(page, path) {
    expect(await page.evaluate(() => document.documentElement.dir), path).toBe('rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), `${path}: لا تمرير أفقي`).toBeLessThanOrEqual(0);
}

test('١٥: the footer is about a third shorter than 455px at 1440 with the full contact details', async ({ page }, testInfo) => {
    await visit(page, '/');
    const footer = page.locator('[data-site-footer]');
    const full = await footer.evaluate((element) => Boolean(element.querySelector('a[href^="mailto:"]') && element.querySelector('a[href^="tel:"]')
        && element.querySelector('[data-footer-box="contact"] bdi') && element.querySelectorAll('[data-social-link]').length >= 4));
    test.skip(!full, 'بيانات التواصل الكاملة غير مدخلة في هذه البيئة');

    const height = await footer.evaluate((element) => element.getBoundingClientRect().height);
    // المحتوى نفسه: المربعات الثلاثة وأسطر التواصل والحسابات
    expect(await footer.locator('[data-footer-box]').count()).toBe(3);
    if (testInfo.project.name === 'desktop') {
        expect(height, 'التذييل على 1440 (كان 455)').toBeLessThanOrEqual(305);
    }
    testInfo.annotations.push({ type: 'footer-height', description: `${testInfo.project.name}: ${Math.round(height)}px` });
    await expectRtlNoScroll(page, '/');
});

test('١٥: the floating assistant leaves the footer rights row whole on desktop widths', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'العروض الحاسوبية');
    await visit(page, '/');
    test.skip((await page.locator('[data-assistant-toggle]').count()) === 0, 'المساعد غير مفعّل في هذه البيئة');

    const overlaps = (a, b) => a.x < b.x + b.width && b.x < a.x + a.width && a.y < b.y + b.height && b.y < a.y + a.height;
    for (const width of [1024, 1280, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        await page.waitForTimeout(300);

        const assistant = await page.locator('[data-assistant-toggle]').boundingBox();
        const rights = page.locator('[data-footer-rights]');
        for (const target of [rights.locator('[data-back-to-top]'), rights.locator('p'), rights.locator('ul'), page.locator('[data-footer-box]').last()]) {
            expect(overlaps(assistant, await target.boundingBox()), `${width}: المساعد لا يغطي سطر الحقوق ولا المربعات`).toBe(false);
        }
        // سطر الحقوق سطر واحد: الزر والنص في صف واحد
        const [text, button] = [await rights.locator('p').boundingBox(), await rights.locator('[data-back-to-top]').boundingBox()];
        expect(Math.abs(text.y + text.height / 2 - (button.y + button.height / 2)), `${width}: سطر واحد`).toBeLessThan(12);
    }
});

test('images on the home and media pages carry width and height', async ({ page }) => {
    for (const path of ['/', '/media']) {
        await visit(page, path);
        const missing = await page.locator('main img').evaluateAll((images) => images
            .filter((image) => !image.getAttribute('width') || !image.getAttribute('height'))
            .map((image) => image.getAttribute('src')));
        expect(missing, `${path}: صور بلا width وheight`).toEqual([]);
        await expectRtlNoScroll(page, path);
    }
});

test('the «شارك واربح» button fills the mobile width under the prize card', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'العرض الكامل على الجوال');
    await visit(page, `/competitions/${SLUG}`);
    const join = page.locator('[data-prize-total] .hero-cta').first();
    test.skip((await join.count()) === 0, 'لا تسجيل مفتوح في هذه المسابقة');

    const box = await join.boundingBox();
    expect(box.width, 'زر «شارك واربح» بعرض كامل').toBeGreaterThan(page.viewportSize().width * 0.8);
    // العنوان ثم البطاقة ثم الزر
    const titleBottom = await page.getByRole('heading', { level: 1 }).first().evaluate((element) => element.getBoundingClientRect().bottom);
    const cardTop = await page.locator('[data-prize-total]').first().evaluate((element) => element.getBoundingClientRect().top);
    expect(titleBottom).toBeLessThanOrEqual(cardTop);
    await expectRtlNoScroll(page, `/competitions/${SLUG}`);
});

test('carousel dots are 44px touch targets on mobile, with a small drawn dot', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'أهداف اللمس على الجوال');
    await visit(page, '/');
    const dots = await page.locator('[data-carousel-dot]').evaluateAll((elements) => elements
        .filter((element) => element.offsetParent !== null)
        .map((element) => {
            const box = element.getBoundingClientRect();
            const dot = getComputedStyle(element, '::before');
            // النقطة المرسومة (مركز الزر) داخل بطاقتها أو دوّارها، فلا تُقص النقاط حين تكثر
            const frame = (element.closest('.home-card') ?? element.closest('[data-carousel]')).getBoundingClientRect();
            const [x, y] = [box.left + box.width / 2, box.top + box.height / 2];
            const inside = x > frame.left && x < frame.right && y > frame.top && y < frame.bottom;
            return { width: box.width, height: box.height, dot: parseFloat(dot.height), inside };
        }));
    expect(dots.length, 'نقاط ظاهرة في الرئيسية').toBeGreaterThan(0);
    for (const dot of dots) {
        expect(dot.width).toBeGreaterThanOrEqual(44);
        expect(dot.height).toBeGreaterThanOrEqual(44);
        expect(dot.dot, 'النقطة المرسومة صغيرة').toBeLessThanOrEqual(10);
        expect(dot.inside, 'النقطة داخل بطاقتها').toBe(true);
    }
    await expectRtlNoScroll(page, '/');
});
