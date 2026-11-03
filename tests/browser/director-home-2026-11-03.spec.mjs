// توجيه المدير التنفيذي (3 نوفمبر 2026 — docs/decisions/2026-11-03-director-home.md):
// ١. الصف الأول من الرأس: الرمز والمخطوطة ومربع الترخيص كتلة واحدة يميناً مزاحة قليلاً عن الحافة، والمخطوطة بجانب الرمز بفراغ صغير،
//    والمربع قريب جداً منها، والبحث والدخول مزاحان قليلاً عن الحافة اليسرى، وما قبلهما فارغ. على 1280 و1440 و1920، والجوال كما كان.
// ٢. الرئيسية: البطل، ثم البطاقات الخمس صفاً واحداً ثابتاً على الحاسوب، ثم «خدماتنا»، ثم شريط يوتيوب يتحرك نحو اليمين وشريط المكتبة
//    تحته نحو اليسار (يُقاس الاتجاه الفعلي من موضع الشريط على الشاشة لا من اسم الصنف)، ثم بطاقة X، ثم الإعلانات والأخبار نصفين
//    والأخبار تصعد، ثم «البوابة بالأرقام» أربع بطاقات 2×2 يميناً والرسم يساراً، ثم الشركاء، ثم التذييل. ولا تمرير أفقي على 390.
// اللقطات: docs/design/screenshots/director-2026-11-03-<header|home>-<العرض>.png
// تلزمها بيانات محلية: ستة مقاطع وثمانية كتب فأكثر (فيتحرك الشريطان)، وأخبار وإعلانات، و«البوابة بالأرقام» مفعّلة برقم وزيارات.
import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

const SHOTS = 'docs/design/screenshots';
const PHONE_BASELINE = `${SHOTS}/header-center/390-before.json`;
const widthOf = { desktop: 1440, tablet: 820, mobile: 390 };

function measureHeader(page) {
    return page.evaluate(() => {
        const header = document.querySelector('[data-site-header]');
        const box = (selector) => {
            const element = header.querySelector(selector);
            if (!element || element.offsetParent === null || getComputedStyle(element).display === 'none') {
                return null;
            }
            const { left, right, top, bottom, width, height } = element.getBoundingClientRect();

            return { left, right, top, bottom, width, height };
        };
        const round = (value) => Math.round(value * 10) / 10;
        const edge = header.getBoundingClientRect();
        const mark = box('[data-brand-mark]');
        const calligraphy = box('[data-logo-calligraphy]');
        const licence = box('[data-header-licence]');
        const search = box('[data-search-open]');
        const login = box('.header-top .login-pill');
        const burger = box('[data-toggle="mobile-menu"]');

        return {
            noHorizontalScroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
            boxes: { mark, calligraphy, licence, actions: box('.header-actions'), login, search, burger },
            markFromEdge: mark && round(edge.right - mark.right),
            markToCalligraphy: mark && calligraphy && round(mark.left - calligraphy.right),
            calligraphyToLicence: calligraphy && licence && round(calligraphy.left - licence.right),
            licenceToSearch: licence && search && round(licence.left - search.right),
            loginFromEdge: login && round(login.left - edge.left),
            verticalOffset: calligraphy && licence && round((licence.top + licence.bottom) / 2 - (calligraphy.top + calligraphy.bottom) / 2),
            calligraphyHeight: calligraphy && round(calligraphy.height),
        };
    });
}

async function headerShot(page, width, name) {
    const bottom = await page.evaluate(() => document.querySelector('[data-site-header]').getBoundingClientRect().bottom);
    await page.screenshot({ path: `${SHOTS}/director-2026-11-03-${name}.png`, clip: { x: 0, y: 0, width, height: Math.ceil(bottom) + 60 } });
}

// ── ١. الرأس ──
for (const width of [1280, 1440, 1920]) {
    test(`header at ${width}px: the mark, calligraphy and licence are one block inset from the right, search and login inset from the left`, async ({ page }, info) => {
        test.skip(info.project.name !== 'desktop', 'العروض في الاختبار نفسه');
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);
        const layout = await measureHeader(page);
        console.log(`header ${width}px:`, JSON.stringify({ ...layout, boxes: undefined }));

        expect(layout.noHorizontalScroll).toBe(true);
        // الرمز مزاح قليلاً عن أقصى اليمين (كان 20 بكسل)
        expect(layout.markFromEdge).toBeGreaterThanOrEqual(36);
        expect(layout.markFromEdge).toBeLessThanOrEqual(80);
        // المخطوطة بعده مباشرة: فراغ صغير غير ملاصق
        expect(layout.markToCalligraphy).toBeGreaterThanOrEqual(8);
        expect(layout.markToCalligraphy).toBeLessThanOrEqual(24);
        // مربع الترخيص قريب جداً من المخطوطة، وبمحاذاتها عمودياً
        expect(layout.calligraphyToLicence).toBeGreaterThanOrEqual(4);
        expect(layout.calligraphyToLicence).toBeLessThanOrEqual(16);
        expect(Math.abs(layout.verticalOffset)).toBeLessThanOrEqual(4);
        // الدخول مزاح قليلاً عن أقصى اليسار، والبحث بجانبه
        expect(layout.loginFromEdge).toBeGreaterThanOrEqual(36);
        expect(layout.loginFromEdge).toBeLessThanOrEqual(80);
        const { search, login } = layout.boxes;
        expect(search.left - login.right).toBeGreaterThanOrEqual(0);
        expect(search.left - login.right).toBeLessThanOrEqual(16);
        // والمنطقة قبل البحث فارغة: أوسع بوضوح من كل فراغ داخل الكتلة (نحو 60 بكسل على 1280، وتتسع مع الشاشة)
        expect(layout.licenceToSearch).toBeGreaterThanOrEqual(48);
        expect(layout.licenceToSearch).toBeGreaterThan(Math.max(layout.markToCalligraphy, layout.calligraphyToLicence) * 3);
        // حجم المخطوطة كما بعد #154 (80 إلى 108 بكسل ارتفاعاً)
        expect(layout.calligraphyHeight).toBeGreaterThanOrEqual(80);
        expect(layout.calligraphyHeight).toBeLessThanOrEqual(108);

        await headerShot(page, width, `header-${width}`);
    });
}

test('header on the tablet keeps the centred calligraphy, and the phone header is unchanged', async ({ page }, info) => {
    const width = widthOf[info.project.name];
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);
    const layout = await measureHeader(page);
    expect(layout.noHorizontalScroll).toBe(true);

    if (info.project.name === 'tablet') {
        const { calligraphy } = layout.boxes;
        expect(Math.abs((calligraphy.left + calligraphy.right) / 2 - width / 2)).toBeLessThanOrEqual(3);
    }

    if (info.project.name === 'mobile') {
        const baseline = JSON.parse(readFileSync(PHONE_BASELINE, 'utf8'));
        for (const [name, box] of Object.entries(baseline)) {
            expect(layout.boxes[name] === null, `${name}: الظهور نفسه`).toBe(box === null);
            for (const side of box ? ['left', 'top', 'width', 'height'] : []) {
                expect(Math.abs(layout.boxes[name][side] - box[side]), `${name}.${side}`).toBeLessThanOrEqual(0.5);
            }
        }
    }

    if (info.project.name !== 'desktop') {
        await headerShot(page, width, `header-${width}`);
    }
});

// ── ٢. الرئيسية ──
test('home: the sections in the director\'s order, the five cards, both strips, the feeds, the four numbers and no horizontal scroll', async ({ page }, info) => {
    const width = widthOf[info.project.name];
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);
    expect(await page.evaluate(() => document.documentElement.dir)).toBe('rtl');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'لا تمرير أفقي').toBeLessThanOrEqual(0);

    // الترتيب من الأعلى
    const order = await page.evaluate(() => {
        const marks = {
            hero: '[data-hero-slider]',
            identity: '#home-identity',
            services: '#home-services-track',
            videos: '[data-media-strip="videos"]',
            books: '[data-media-strip="books"]',
            x: '[data-x-card]',
            announcements: '#home-announcements',
            news: '#home-news',
            stats: '[data-portal-stats]',
            partners: '[aria-labelledby="home-partners"]',
            footer: 'footer',
        };

        return Object.entries(marks)
            .map(([name, selector]) => [name, document.querySelector(selector)])
            .filter(([, element]) => element)
            .sort(([, a], [, b]) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))
            .map(([name]) => name);
    });
    expect(order).toEqual(['hero', 'identity', 'services', 'videos', 'books', 'x', 'announcements', 'news', 'stats', 'partners', 'footer']);

    // البطاقات الخمس: صف واحد ثابت على الحاسوب بالترتيب من اليمين
    const cards = await page.locator('#home-identity').evaluate((section) => [...section.querySelectorAll('[data-identity-card]')]
        .filter((card) => !card.closest('[data-marquee-copy]'))
        .map((card) => {
            const box = card.getBoundingClientRect();

            return { name: card.dataset.identityCard, top: Math.round(box.top), right: Math.round(box.right), width: Math.round(box.width), height: Math.round(box.height) };
        }));
    expect(cards.map((card) => card.name)).toEqual(['vision', 'goals', 'values', 'principles', 'initiatives']);
    if (info.project.name === 'desktop') {
        expect(new Set(cards.map((card) => card.top)).size, 'صف واحد').toBe(1);
        for (let index = 1; index < cards.length; index++) {
            expect(cards[index].right, 'من اليمين إلى اليسار').toBeLessThan(cards[index - 1].right);
            expect(Math.abs(cards[index].width - cards[0].width), 'عرض واحد').toBeLessThanOrEqual(2);
            expect(Math.abs(cards[index].height - cards[0].height), 'ارتفاع واحد').toBeLessThanOrEqual(2);
        }
        // ثابت لا يتحرك: لا زر إيقاف ولا نسخة ظاهرة
        await expect(page.locator('[data-ticker-toggle="home-cards-track"]')).toBeHidden();
        await expect(page.locator('#home-cards-track [data-marquee-copy]')).toBeHidden();
    }

    // اتجاه الشريطين الفعلي: موضع أول بطاقة على الشاشة قبل ثانيتين وبعدهما
    await page.locator('[data-home-strips]').scrollIntoViewIfNeeded();
    await page.waitForTimeout(600);
    const position = () => page.evaluate(() => ['videos', 'books'].map((kind) => document.querySelector(`[data-media-strip="${kind}"] .marquee-track`).getBoundingClientRect().left));
    const [videosBefore, booksBefore] = await position();
    await page.waitForTimeout(2000);
    const [videosAfter, booksAfter] = await position();
    console.log(`strips ${width}px: videos ${videosBefore} -> ${videosAfter}, books ${booksBefore} -> ${booksAfter}`);
    expect(videosAfter - videosBefore, 'شريط يوتيوب نحو اليمين').toBeGreaterThan(2);
    expect(booksAfter - booksBefore, 'شريط المكتبة نحو اليسار').toBeLessThan(-2);
    // بعرض المحتوى كاملاً: الشريطان بعرض واحد
    const stripWidths = await page.evaluate(() => ['videos', 'books'].map((kind) => Math.round(document.querySelector(`[data-media-strip="${kind}"]`).getBoundingClientRect().width)));
    expect(Math.abs(stripWidths[0] - stripWidths[1])).toBeLessThanOrEqual(2);

    // الإعلانات والأخبار: نصفان متساويان على الحاسوب، والأخبار تصعد
    await page.locator('#home-news').scrollIntoViewIfNeeded();
    await page.waitForTimeout(1500);
    const columns = await page.evaluate(() => ['#home-announcements', '#home-news'].map((id) => {
        const box = document.querySelector(id).closest('[data-feed-column]').getBoundingClientRect();

        return { left: Math.round(box.left), top: Math.round(box.top), width: Math.round(box.width), height: Math.round(box.height) };
    }));
    if (info.project.name === 'desktop') {
        expect(columns[0].left, 'الإعلانات يميناً').toBeGreaterThan(columns[1].left);
        expect(Math.abs(columns[0].width - columns[1].width)).toBeLessThanOrEqual(2);
        expect(Math.abs(columns[0].height - columns[1].height)).toBeLessThanOrEqual(2);
        expect(Math.abs(columns[0].top - columns[1].top)).toBeLessThanOrEqual(3);
    }
    const newsTop = () => page.locator('#home-news-track .marquee-track').evaluate((track) => track.getBoundingClientRect().top);
    const newsBefore = await newsTop();
    await page.waitForTimeout(1500);
    expect((await newsTop()) - newsBefore, 'الأخبار تصعد').toBeLessThan(-1);

    // «البوابة بالأرقام»: أربع بطاقات 2×2 يميناً، والرسم يساراً
    await page.locator('[data-portal-stats]').scrollIntoViewIfNeeded();
    await page.waitForTimeout(800);
    const stats = await page.evaluate(() => {
        const section = document.querySelector('[data-portal-stats]');
        const cards = [...section.querySelectorAll('ul > li')].map((card) => card.getBoundingClientRect());
        const chart = section.querySelector('[data-portal-chart]')?.getBoundingClientRect();

        return {
            count: cards.length,
            // مواضع التخطيط (offsetTop وoffsetLeft) لا الشاشة، فحركة الظهور المتتابعة لا تفرّق صفاً واحداً
            rows: new Set([...section.querySelectorAll('ul > li')].map((card) => card.offsetTop)).size,
            columns: new Set([...section.querySelectorAll('ul > li')].map((card) => card.offsetLeft)).size,
            cardsLeft: Math.min(...cards.map((card) => card.left)),
            chartRight: chart?.right ?? null,
        };
    });
    expect(stats.count, 'أربع بطاقات').toBe(4);
    expect(stats.rows).toBe(2);
    expect(stats.columns).toBe(2);
    expect(stats.chartRight).not.toBeNull();
    if (info.project.name === 'desktop') {
        expect(stats.chartRight, 'الرسم يسار البطاقات').toBeLessThanOrEqual(stats.cardsLeft);
    }

    // اللقطة كاملة بعد المرور على الصفحة كلها، فيظهر كل عنوان وبطاقة بحركة ظهوره (data-reveal) وتبلغ الأرقام نهايتها
    for (let top = 0; top < (await page.evaluate(() => document.documentElement.scrollHeight)); top += 500) {
        await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), top);
        await page.waitForTimeout(250);
    }
    await page.waitForTimeout(1500);
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${SHOTS}/director-2026-11-03-home-${width}.png`, fullPage: true });
});
