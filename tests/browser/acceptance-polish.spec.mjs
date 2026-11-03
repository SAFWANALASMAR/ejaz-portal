// فجوات تقرير القبول v0.15.0 (docs/reviews/2026-11-02-قبول-v0.15.0.md، القسم ٧) على العروض الثلاثة:
// - البند ١٥: التذييل أقصر بنحو الثلث من 455 بكسل القديمة، ببيانات التواصل الكاملة (البريد والجوال والعنوان والعنوان المختصر
//   وواتساب والحسابات) — تُدخل من «إعدادات الموقع» في البيئة المحلية قبل التشغيل، وإلا تخطى الاختبار قياسه.
// - صور الرئيسية والمركز الإعلامي بعرض وارتفاع صريحين، فلا تزاح الصفحة عند تحميلها.
// - «شارك واربح» في بطل مسابقة الطلاب بعرض كامل تقريباً على الجوال (COMPETITION_SLUG، الافتراضي brahin-1448).
// - نقاط الدوّارات أهداف لمس 44 بكسل، ومركز كل نقطة لها لا لجارتها (elementFromPoint) على العروض الثلاثة.
//
// التشغيل (مع الأصول المبنية):
// docker compose run --rm --no-deps -e VITE_HOST=none -e COMPETITION_SLUG=brahin-1448 browser \
//   npx playwright test --config=tests/browser/playwright.config.mjs acceptance-polish.spec.mjs
import { expect, test } from '@playwright/test';

const SLUG = process.env.COMPETITION_SLUG ?? 'brahin-1448';

async function visit(page, path) {
    // الشجرة ثم «load» وهدوء الشبكة بمهلتين قصيرتين: الرئيسية على 1440 و820 فيها صور خارجية لا تكتمل في الحاوية، فكان الانتظار
    // يتجاوز 90 ثانية. والفحوص هنا للتخطيط والسمات، لا لاكتمال تحميل الصور الخارجية
    const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
    expect(response?.status(), path).toBe(200);
    await page.waitForLoadState('load', { timeout: 20_000 }).catch(() => {});
    await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => {});
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
    // صفحتان، والأولى بطيئة في الحاوية الباردة على 1440
    test.setTimeout(240_000);
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

// كل زر نقطة ظاهر: ارتفاعه 44 على الأقل، وعرضه 44 (أو 24 في الدوّار الصغير داخل بطاقات الهوية، بفاصل بينها — المعيار 2.5.8)،
// ونقطته المرسومة صغيرة داخل بطاقته، ونقاط مركزه وأطرافه له وحده لا لجاره (document.elementFromPoint — مراجعة المشرف على طلب الدمج 131)
async function touchTargets(page, selector) {
    // الحركة موقوفة، فلا يتحرك الصف بين القياس والفحص
    await page.addStyleTag({ content: '*, *::before, *::after { animation-play-state: paused !important; transition: none !important; }' });
    return page.locator(selector).evaluateAll((elements) => elements
        // نسخة الشريط المتحرك الثانية inert، لا تُلمس ولا تُفحص
        .filter((element) => element.offsetParent !== null && !element.closest('[inert]'))
        .map((element) => {
            element.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
            const box = element.getBoundingClientRect();
            const [x, y] = [box.left + box.width / 2, box.top + box.height / 2];
            const points = [[x, y], [box.left + 2, y], [box.right - 2, y], [x, box.top + 2], [x, box.bottom - 2]];
            // يُفحص الزر الظاهر كله: داخل النافذة وداخل كل أب يقص ما خارجه (البطاقة، وصندوق الشريط المتحرك الذي يمر بالبطاقات
            // فيقص الطرفية منها على 820). والمقصوص لا يُلمس هناك، فلا يُحسب تراكباً
            const clips = [{ left: 0, right: window.innerWidth, top: 0, bottom: window.innerHeight }];
            for (let parent = element.parentElement; parent; parent = parent.parentElement) {
                if (getComputedStyle(parent).overflow !== 'visible') {
                    clips.push(parent.getBoundingClientRect());
                }
            }
            const visible = points.every(([px, py]) => clips.every((clip) => px > clip.left && px < clip.right && py > clip.top && py < clip.bottom));
            const misses = points.filter(([px, py]) => {
                const hit = document.elementFromPoint(px, py);
                return !(hit === element || element.contains(hit));
            }).map(([px, py]) => { const hit = document.elementFromPoint(px, py); return `${Math.round(px)},${Math.round(py)}→${hit?.tagName}.${hit?.className}`; });
            const own = visible && points.every(([px, py]) => {
                const hit = document.elementFromPoint(px, py);
                return hit === element || element.contains(hit);
            });
            return {
                label: `${element.getAttribute('aria-label')} ${misses.join(' ')}`,
                width: box.width,
                height: box.height,
                minWidth: element.closest('.carousel-controls-compact') || element.matches('[data-slide-dot]') ? 24 : 44,
                dot: parseFloat(getComputedStyle(element, '::before').height) || element.firstElementChild?.getBoundingClientRect().height || 0,
                top: Math.round(box.top + window.scrollY),
                visible,
                own,
            };
        }));
}

function expectTargets(targets, what) {
    expect(targets.length, `${what}: نقاط ظاهرة`).toBeGreaterThan(0);
    for (const target of targets) {
        expect(target.height, `${target.label}: الارتفاع`).toBeGreaterThanOrEqual(44);
        expect(target.width, `${target.label}: العرض`).toBeGreaterThanOrEqual(target.minWidth);
        expect(target.dot, `${target.label}: النقطة المرسومة صغيرة`).toBeLessThanOrEqual(10);
        if (target.visible) {
            expect(target.own, `${target.label}: المركز والأطراف للزر نفسه`).toBe(true);
        }
    }
    expect(targets.filter((target) => target.visible).length, `${what}: نقاط فُحصت بـ elementFromPoint`).toBeGreaterThan(0);
}

// على العروض الثلاثة (1440 و820 و390): مراجعة المشرف على طلب الدمج 131
test('carousel dots are touch targets of their own, with a small drawn dot', async ({ page }) => {
    await visit(page, '/');
    const targets = await touchTargets(page, '[data-carousel-dot]');
    expectTargets(targets, 'الرئيسية');
    await expectRtlNoScroll(page, '/');
});

test('the Brahin guide dots stay in one row of own touch targets', async ({ page }) => {
    await visit(page, `/competitions/${SLUG}`);
    test.skip((await page.locator('[data-slide-dot]').count()) < 2, 'لا دليل دخول في هذه المسابقة');

    const targets = await touchTargets(page, '[data-slide-dot]');
    expectTargets(targets, 'دليل الدخول');
    expect(new Set(targets.map((target) => target.top)).size, 'النقاط صف واحد').toBe(1);
    await expectRtlNoScroll(page, `/competitions/${SLUG}`);
});
