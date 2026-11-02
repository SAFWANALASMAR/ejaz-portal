// إيقاف الأشرطة المتحركة (x-marquee) بالمرور والتركيز بلوحة المفاتيح وبزر الإيقاف (fix/marquee-pause): كانت قاعدة الحركة
// (.marquee[data-direction=horizontal|vertical][data-inview] .marquee-track) أعلى تحديداً من قاعدة الإيقاف، فتعيد animation المختصرة حالة التشغيل.
// لكل شريط متحرك في الرئيسية (أفقي وعمودي): يتحرك أولاً، ثم يثبت بكل طريق (animationPlayState وtransform على 500 ms).
// على 1440 و390، وللأفقي مع تقليل الحركة أيضاً (يبقى متحركاً أبطأ ويتوقف).
import { expect, test } from '@playwright/test';

test.skip(({ viewport }) => viewport.width === 820, 'الحاسوب والجوال يكفيان');

async function movingMarquees(page) {
    return page.evaluate(() => [...document.querySelectorAll('[data-marquee]')]
        // المتحرك هو ما ظهر زر إيقافه (يُخفى للصف الثابت على الحاسوب وللعمودي مع تقليل الحركة)
        .filter((marquee) => document.querySelector(`[data-ticker-toggle="${marquee.id}"]`)?.getClientRects().length > 0)
        .map((marquee) => ({ id: marquee.id, direction: marquee.dataset.direction })));
}

async function sample(page, id, wait = 500) {
    return page.evaluate(async ([marqueeId, ms]) => {
        const track = document.querySelector(`#${marqueeId} .marquee-track`);
        // يستقر تغيير حالة التشغيل في الإطار التالي، فالقراءة الأولى بعد إطارين
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        const before = getComputedStyle(track).transform;
        await new Promise((resolve) => setTimeout(resolve, ms));
        const style = getComputedStyle(track);

        return { name: style.animationName, state: style.animationPlayState, moved: style.transform !== before };
    }, [id, wait]);
}

// التمرير الناعم يحرّك الصفحة أثناء محاولة الضغط على الجوال، فيُطفأ في الاختبار وحده. وعدة أشرطة تُفحص بطرقها الثلاث، فالمهلة أطول
async function openHome(page) {
    test.setTimeout(300_000);
    await page.goto('/');
    await page.evaluate(() => document.documentElement.style.setProperty('scroll-behavior', 'auto', 'important'));
}

// زر الإيقاف بلوحة المفاتيح (Enter)، كما يستعمله قارئ الشاشة: الضغط بالفأرة على الجوال كان يصيب أحياناً ما يتحرك حوله (البطاقات والرأس الثابت)
async function press(page, toggle) {
    await toggle.focus();
    await page.keyboard.press('Enter');
}

async function release(page) {
    await page.mouse.move(1, 1);
    await page.evaluate(() => document.activeElement?.blur());
}

async function expectPausePaths(page, { id, direction }, wait = 500) {
    const marquee = page.locator(`#${id}`);
    await marquee.scrollIntoViewIfNeeded();
    await expect(marquee).toHaveAttribute('data-inview', '');
    await release(page);

    const running = await sample(page, id, wait);
    expect(running.name, `${id} يتحرك`).not.toBe('none');
    expect(running.state, `${id} يعمل`).toBe('running');
    expect(running.moved, `${id} يتحرك فعلاً`).toBe(true);

    // المرور
    await marquee.hover();
    let paused = await sample(page, id, wait);
    expect(paused.state, `${id} بالمرور`).toBe('paused');
    expect(paused.moved, `${id} ثابت بالمرور`).toBe(false);
    await release(page);
    expect((await sample(page, id, wait)).moved, `${id} يعود بعد المرور`).toBe(true);

    // التركيز بلوحة المفاتيح داخله (أول عنصر قابل للتركيز في النسخة الظاهرة)، ويُعاد هل فُحص
    const focusable = marquee.locator('.marquee-group:not([data-marquee-copy])').locator('a[href], button').first();
    const focusChecked = (await focusable.count()) > 0;
    if (focusChecked) {
        await focusable.focus();
        expect(await marquee.evaluate((element) => element.matches(':focus-within'))).toBe(true);
        paused = await sample(page, id, wait);
        expect(paused.state, `${id} بالتركيز`).toBe('paused');
        expect(paused.moved, `${id} ثابت بالتركيز`).toBe(false);
        await release(page);
    }

    // زر الإيقاف، ثم التشغيل
    const toggle = page.locator(`[data-ticker-toggle="${id}"]`);
    await press(page, toggle);
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await release(page);
    paused = await sample(page, id, wait);
    expect(paused.state, `${id} بزر الإيقاف`).toBe('paused');
    expect(paused.moved, `${id} ثابت بزر الإيقاف`).toBe(false);
    await press(page, toggle);
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await release(page);
    expect((await sample(page, id, wait)).moved, `${id} (${direction}) يعود بزر التشغيل`).toBe(true);

    return focusChecked;
}

test('home marquees pause on hover, keyboard focus and the pause button', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await openHome(page);

    const marquees = await movingMarquees(page);
    expect(marquees.some((marquee) => marquee.direction === 'horizontal'), 'شريط أفقي واحد على الأقل').toBe(true);
    expect(marquees.some((marquee) => marquee.direction === 'vertical'), 'شريط الأخبار العمودي').toBe(true);
    expect(marquees.map((marquee) => marquee.id), 'شريط يوتيوب').toContain('home-strip-videos-track');

    let focused = 0;
    for (const marquee of marquees) {
        focused += await expectPausePaths(page, marquee) ? 1 : 0;
    }
    expect(focused, 'التركيز فُحص في شريط واحد على الأقل').toBeGreaterThan(0);
});

test('reduced motion: the horizontal marquees still pause, the vertical news stays still', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await openHome(page);

    expect(await page.locator('#home-news-track .marquee-track').evaluate((element) => getComputedStyle(element).animationName)).toBe('none');

    const marquees = (await movingMarquees(page)).filter((marquee) => marquee.direction === 'horizontal');
    expect(marquees.length, 'شريط أفقي واحد على الأقل').toBeGreaterThan(0);

    // أبطأ مع تقليل الحركة، فالعينة ثانية كاملة
    let focused = 0;
    for (const marquee of marquees) {
        focused += await expectPausePaths(page, marquee, 1000) ? 1 : 0;
    }
    expect(focused, 'التركيز فُحص في شريط واحد على الأقل').toBeGreaterThan(0);
});

// ملاحظة المدير ٢٤: شريطا يوتيوب والكتب «تحت خدماتنا» مباشرة، ولقطة لهما في docs/design/screenshots/marquee-pause/
test('home: the YouTube and books strips sit directly under «خدماتنا»', async ({ page }) => {
    await openHome(page);

    const services = page.locator('section[aria-labelledby="home-shortcuts"]');
    const strips = page.locator('section[data-home-strips]');
    expect(await services.evaluate((element) => element.nextElementSibling?.hasAttribute('data-home-strips'))).toBe(true);
    expect(await strips.evaluate((element) => element.nextElementSibling?.querySelector('#home-news-track') !== null)).toBe(true);

    // يمر على المقطعين فيظهر ما يظهر بالتمرير (data-reveal)، ثم يقيس الحدود في الصفحة دفعة واحدة. والرأس الثابت يُخفى للقطة وحدها
    const clip = await page.evaluate(async () => {
        document.querySelector('[data-site-header]')?.style.setProperty('visibility', 'hidden');
        const first = document.querySelector('section[aria-labelledby="home-shortcuts"]');
        const last = document.querySelector('section[data-home-strips]');
        for (const element of [first, last, first]) {
            element.scrollIntoView({ block: 'start' });
            await new Promise((resolve) => setTimeout(resolve, 700));
        }
        const top = first.getBoundingClientRect().top + window.scrollY;

        return { x: 0, y: top, width: document.documentElement.clientWidth, height: last.getBoundingClientRect().bottom + window.scrollY - top };
    });
    await page.screenshot({ path: `docs/design/screenshots/marquee-pause/home-services-strips-${page.viewportSize().width}.png`, fullPage: true, clip });
});

// القليل صف ثابت (مراجعة المشرف): أقل من ستة شعارات أو ستة مقاطع أو ثمانية كتب يظهر كل عنصر فيه مرة واحدة بلا حركة ولا زر إيقاف،
// وما بلغها يتحرك. يتبع بيانات البيئة: يُشغَّل مرة بها (الشركاء سبعة فيتحرك) ومرة بشريك واحد منشور (يظهر مرة واحدة)
test('home: a few partners, videos or books sit still once; enough of them move', async ({ page }) => {
    await page.goto('/');

    const strips = [
        { id: 'home-partners-track', item: '.partner-logo', from: 6 },
        { id: 'home-strip-videos-track', item: '.media-tile', from: 6 },
        { id: 'home-strip-books-track', item: '.media-tile', from: 8 },
    ];
    let checked = 0;

    for (const { id, item, from } of strips) {
        const strip = page.locator(`#${id}`);
        if (! (await strip.count())) {
            continue;
        }
        checked++;
        await strip.scrollIntoViewIfNeeded();
        await page.waitForTimeout(400);
        const state = await strip.evaluate((element, selector) => {
            const items = [...element.querySelectorAll(selector)].filter((node) => ! node.closest('[data-marquee-copy]') && ! node.hasAttribute('data-marquee-fill'));
            const texts = [...element.querySelectorAll(selector)].map((node) => node.textContent.trim());

            return {
                originals: items.length,
                total: texts.length,
                unique: new Set(texts).size,
                animation: getComputedStyle(element.querySelector('.marquee-track')).animationName,
                toggle: document.querySelector(`[data-ticker-toggle="${element.id}"]`) !== null,
            };
        }, item);

        if (state.originals < from) {
            expect(state.total, `${id}: كل عنصر مرة واحدة`).toBe(state.originals);
            expect(state.unique, `${id}: بلا تكرار`).toBe(state.originals);
            expect(state.animation, `${id}: ثابت`).toBe('none');
            expect(state.toggle, `${id}: بلا زر إيقاف`).toBe(false);
        } else {
            expect(state.animation, `${id}: يتحرك`).not.toBe('none');
            expect(state.toggle, `${id}: بزر إيقاف`).toBe(true);
        }
    }
    expect(checked, 'شريط واحد على الأقل').toBeGreaterThan(0);

    // الصف الثابت يلتف ولا يمد الصفحة أفقياً
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, 'لا تمرير أفقي').toBeLessThanOrEqual(0);
});

// «كيف تسجّل» في صفحة البرامج (مراجعة المشرف): رقم كل خطوة ظاهر على دائرته بتباين 4.5:1 فأكثر، ومنها الخطوات على البطاقات الداكنة
test('programs: every how-to step number is visible with at least 4.5:1 contrast', async ({ page }) => {
    await page.goto('/programs');

    const numbers = await page.locator('[data-programs-how-to] .how-to-compact-number').evaluateAll((nodes) => {
        const luminance = (color) => {
            const [r, g, b] = color.match(/[\d.]+/g).slice(0, 3).map(Number).map((value) => {
                const channel = value / 255;

                return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
            });

            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };

        return nodes.map((node) => {
            const style = getComputedStyle(node);
            const [light, dark] = [luminance(style.color), luminance(style.backgroundColor)].sort((a, b) => b - a);

            return { text: node.textContent.trim(), ratio: (light + 0.05) / (dark + 0.05), alpha: style.backgroundColor.startsWith('rgba') };
        });
    });

    expect(numbers.length, 'خطوات «كيف تسجّل»').toBeGreaterThanOrEqual(5);
    numbers.forEach(({ text, ratio, alpha }, index) => {
        expect(text, `رقم الخطوة ${index + 1}`).toBe(String(index + 1));
        expect(alpha, `دائرة الخطوة ${index + 1} معتمة`).toBe(false);
        expect(ratio, `تباين الخطوة ${index + 1}`).toBeGreaterThanOrEqual(4.5);
    });
});
