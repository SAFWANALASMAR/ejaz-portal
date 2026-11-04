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
            calligraphyOffCentre: calligraphy && round((calligraphy.left + calligraphy.right) / 2 - (edge.left + edge.right) / 2),
            assistant: box('[data-header-assistant]'),
            licenceToActions: licence && round(licence.left - header.querySelector('.header-actions').getBoundingClientRect().right),
            // حافتا محتوى صف القائمة (تقييم لقطات التوجيه، البند ٥): الصف الأول في الحاوية نفسها
            navContent: (() => {
                const row = header.querySelector('.header-nav-row');
                const { left, right } = row.getBoundingClientRect();
                const style = getComputedStyle(row);

                return { left: round(left + parseFloat(style.paddingLeft)), right: round(right - parseFloat(style.paddingRight)) };
            })(),
        };
    });
}

// قبل كل لقطة صفحة كاملة (تقييم لقطات التوجيه، البند ١): المرور على الصفحة كلها على خطوات، فيظهر كل عنوان وبطاقة بحركة ظهوره
// (data-reveal) وتبلغ الأرقام نهايتها، ثم انتظار ثانية ونصف لحركات الصعود (rise-*)، ثم العودة إلى الأعلى
async function settleForShot(page) {
    for (let top = 0; top < (await page.evaluate(() => document.documentElement.scrollHeight)); top += 400) {
        await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), top);
        await page.waitForTimeout(250);
    }
    await page.waitForTimeout(1500);
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.waitForTimeout(1500);
}

async function headerShot(page, width, name) {
    const bottom = await page.evaluate(() => document.querySelector('[data-site-header]').getBoundingClientRect().bottom);
    await page.screenshot({ path: `${SHOTS}/director-2026-11-03-${name}.png`, clip: { x: 0, y: 0, width, height: Math.ceil(bottom) + 60 } });
}

// ── ١. الرأس ──
for (const width of [1024, 1280, 1366, 1440, 1920]) {
    // البند ١٤ من الإضافة (أُلغي التوسيط في البند ١٢، واعتُمدت «الكتلة اليمنى»): الرمز والمخطوطة الممدودة ومربع الترخيص كتلة يميناً
    // مزاحة قليلاً عن الحافة، و«المساعد الذكي» والبحث والدخول يساراً مزاحة كذلك، وما بينهما فارغ
    test(`header at ${width}px: the mark, calligraphy and licence are one block inset from the right, the assistant, search and login inset from the left`, async ({ page }, info) => {
        test.skip(info.project.name !== 'desktop', 'العروض في الاختبار نفسه');
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);
        const layout = await measureHeader(page);
        console.log(`header ${width}px:`, JSON.stringify({ ...layout, boxes: undefined, assistant: undefined }));

        expect(layout.noHorizontalScroll).toBe(true);
        // الرمز مزاح قليلاً عن أقصى اليمين (كان 20 بكسل)، والصف الأول في حاوية صف القائمة: حافتاه حافتا محتواها (تقييم اللقطات، البند ٥)
        expect(layout.markFromEdge).toBeGreaterThanOrEqual(24);
        if (width >= 1280) {
            expect(Math.abs(layout.boxes.mark.right - layout.navContent.right), 'حافة الرمز حافة صف القائمة').toBeLessThanOrEqual(2);
            expect(Math.abs(layout.boxes.login.left - layout.navContent.left), 'حافة الدخول حافة صف القائمة').toBeLessThanOrEqual(2);
        }
        // المخطوطة بعده مباشرة: فراغ صغير غير ملاصق
        expect(layout.markToCalligraphy).toBeGreaterThanOrEqual(8);
        expect(layout.markToCalligraphy).toBeLessThanOrEqual(24);
        // مربع الترخيص قريب جداً من المخطوطة، وبمحاذاتها عمودياً
        expect(layout.calligraphyToLicence).toBeGreaterThanOrEqual(4);
        expect(layout.calligraphyToLicence).toBeLessThanOrEqual(16);
        expect(Math.abs(layout.verticalOffset)).toBeLessThanOrEqual(4);
        // الدخول مزاح قليلاً عن أقصى اليسار، والبحث بجانبه، و«المساعد الذكي» بجانب البحث
        expect(layout.loginFromEdge).toBeGreaterThanOrEqual(24);
        const { search, login } = layout.boxes;
        expect(search.left - login.right).toBeGreaterThanOrEqual(0);
        expect(search.left - login.right).toBeLessThanOrEqual(16);
        expect(layout.assistant, '«المساعد الذكي» ظاهر').not.toBeNull();
        expect(layout.assistant.left - search.right).toBeGreaterThanOrEqual(0);
        expect(layout.assistant.left - search.right).toBeLessThanOrEqual(16);
        // والمنطقة قبل الأزرار فارغة، أوسع من كل فراغ داخل الكتلة، والصف لا ينكسر
        expect(layout.licenceToActions).toBeGreaterThanOrEqual(20);
        expect(layout.licenceToActions).toBeGreaterThan(Math.max(layout.markToCalligraphy, layout.calligraphyToLicence));
        // المخطوطة ممدودة بأكبر ما يتسع (البندان ٤ و١٤)، والصف لا يزيد على 1320 فهي على 1440 و1920 بحجمها على 1320. وعلى 1024 تصغر
        // ملاصقة للرمز، ومربع الترخيص ظاهر مصغّراً (قرار صفوان، 4 نوفمبر 2026، البند ٤ من تقييم اللقطات)
        expect(layout.calligraphyHeight).toBeGreaterThan({ 1024: 60, 1280: 90, 1366: 100, 1440: 100, 1920: 100 }[width]);
        expect(layout.boxes.licence, 'مربع الترخيص ظاهر').not.toBeNull();
        expect(layout.calligraphyHeight).toBeLessThanOrEqual(120);

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

// ── ١ب. صف القائمة (إضافة التوجيه، البند ٣): العناصر السبعة وأيقوناتها ظاهرة دائماً على الحاسوب واللوحي الأفقي، ولا زر ☰،
//    وبتكبير المتصفح 125% (عرض الشاشة ÷ 1.25 بكثافة 1.25). والاسم لا يُقص على هذه العروض
async function expectFullMenu(page, label) {
    const menu = await page.evaluate(() => {
        const nav = document.querySelector('[data-main-nav]');
        const vw = document.documentElement.clientWidth;
        const capsules = [...nav.querySelectorAll('.nav-capsules > li > .nav-capsule')];

        return {
            navShown: getComputedStyle(nav).display !== 'none' && nav.getBoundingClientRect().height > 0,
            burgerShown: getComputedStyle(document.querySelector('[data-toggle="mobile-menu"]')).display !== 'none',
            noHorizontalScroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
            items: capsules.map((capsule) => {
                const box = capsule.getBoundingClientRect();
                const icon = capsule.querySelector('[data-icon-tile]').getBoundingClientRect();
                const text = capsule.querySelector('.truncate');

                return {
                    inside: box.left >= -0.5 && box.right <= vw + 0.5 && box.width > 0,
                    icon: Math.round(icon.width),
                    clipped: text.scrollWidth > text.clientWidth + 1,
                    font: parseFloat(getComputedStyle(capsule).fontSize),
                };
            }),
        };
    });
    console.log(`menu ${label}:`, JSON.stringify({ ...menu, items: menu.items.map((item) => `${item.icon}/${item.font}${item.clipped ? '/clipped' : ''}`) }));
    expect(menu.navShown, 'صف القائمة ظاهر').toBe(true);
    expect(menu.burgerShown, 'لا زر ☰').toBe(false);
    expect(menu.noHorizontalScroll).toBe(true);
    expect(menu.items).toHaveLength(7);
    for (const item of menu.items) {
        expect(item.inside, 'العنصر داخل الشاشة').toBe(true);
        expect(item.icon, 'الأيقونة ظاهرة').toBeGreaterThanOrEqual(15);
        expect(item.clipped, 'الاسم كاملاً').toBe(false);
        expect(item.font, 'خط مقروء').toBeGreaterThanOrEqual(11);
    }
}

for (const width of [1024, 1280, 1366, 1440]) {
    test(`menu at ${width}px: the seven items and their icons are shown, with no menu button`, async ({ page }, info) => {
        test.skip(info.project.name !== 'desktop', 'العروض في الاختبار نفسه');
        await page.setViewportSize({ width, height: 800 });
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);
        await expectFullMenu(page, `${width}`);
        // لقطات الرأس من اختبار الصف الأول
    });
}

for (const screen of [1280, 1366, 1440]) {
    test(`menu at ${screen}px with the browser zoomed to 125%: the seven items and their icons are shown`, async ({ browser }, info) => {
        test.skip(info.project.name !== 'desktop', 'العروض في الاختبار نفسه');
        const width = Math.round(screen / 1.25);
        const context = await browser.newContext({ baseURL: info.project.use.baseURL, locale: 'ar-SA', viewport: { width, height: 640 }, deviceScaleFactor: 1.25 });
        const page = await context.newPage();
        await page.goto('/');
        await page.evaluate(() => document.fonts.ready);
        await expectFullMenu(page, `${screen}@125%`);
        // مربع الترخيص ظاهر مصغّراً والمخطوطة ملاصقة للرمز لا في الوسط (قرار صفوان، البند ٤ من تقييم اللقطات)
        const layout = await measureHeader(page);
        expect(layout.boxes.licence, 'مربع الترخيص ظاهر').not.toBeNull();
        expect(layout.markToCalligraphy).toBeLessThanOrEqual(24);
        expect(layout.calligraphyToLicence).toBeLessThanOrEqual(16);
        expect(layout.licenceToActions).toBeGreaterThanOrEqual(12);
        if (screen === 1280) {
            await headerShot(page, width, 'header-1280-zoom125');
        }
        await context.close();
    });
}

test('menu on the portrait tablet and the phone keeps the menu button', async ({ page }, info) => {
    test.skip(info.project.name === 'desktop', 'اللوحي العمودي والجوال');
    await page.goto('/');
    await expect(page.locator('[data-toggle="mobile-menu"]')).toBeVisible();
    await expect(page.locator('[data-main-nav]')).toBeHidden();
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

    // الاتجاه الفعلي (إضافة التوجيه، البند ١٧، ويحل محل البند ٤): «خدماتنا» من اليسار إلى اليمين، ويوتيوب من اليمين إلى اليسار،
    // والمكتبة من اليسار إلى اليمين. موضع المسار على الشاشة قبل ثانيتين وبعدهما، وإشارة الفرق هي الاتجاه
    const trackLeft = (selector) => page.locator(selector).evaluate((element) => element.getBoundingClientRect().left);
    const direction = async (selector) => {
        await page.locator(selector).evaluate((element) => element.closest('.marquee').scrollIntoView({ block: 'center', behavior: 'instant' }));
        await page.waitForTimeout(600);
        const before = await trackLeft(selector);
        await page.waitForTimeout(2000);

        return (await trackLeft(selector)) - before;
    };
    const services = await direction('#home-services-track .marquee-track');
    const videos = await direction('[data-media-strip="videos"] .marquee-track');
    const books = await direction('[data-media-strip="books"] .marquee-track');
    // والأبحاث تحت الكتب عكسها، من اليمين إلى اليسار (البند ٢٦)
    const research = await direction('[data-media-strip="research"] .marquee-track');
    console.log(`directions ${width}px: services ${services}, videos ${videos}, books ${books}, research ${research}`);
    expect(research, 'الأبحاث من اليمين إلى اليسار').toBeLessThan(-2);
    expect(services, '«خدماتنا» من اليسار إلى اليمين').toBeGreaterThan(2);
    expect(videos, 'يوتيوب من اليمين إلى اليسار').toBeLessThan(-2);
    expect(books, 'المكتبة من اليسار إلى اليمين').toBeGreaterThan(2);
    // شريطا «الكتب والمؤلفات» و«الأبحاث والدراسات» منفصلان (البند ٢٦)، والبحث بلا غلاف بأيقونة الكتاب نفسها (البند ٦)
    await expect(page.locator('#home-strip-books')).toHaveText('من مكتبة إعجاز: الكتب والمؤلفات');
    await expect(page.locator('#home-strip-research')).toHaveText('من مكتبة إعجاز: الأبحاث والدراسات');
    const icons = await page.evaluate(() => [...document.querySelectorAll('[data-media-strip="books"] .marquee-group:not([data-marquee-copy]) [data-thumb-placeholder] svg, [data-media-strip="research"] .marquee-group:not([data-marquee-copy]) [data-thumb-placeholder] svg')].map((svg) => svg.innerHTML));
    expect(icons.length).toBeGreaterThan(0);
    expect(new Set(icons).size, 'أيقونة واحدة للكتب والأبحاث').toBe(1);
    // بعرض المحتوى كاملاً: الشريطان بعرض واحد
    const stripWidths = await page.evaluate(() => ['videos', 'books', 'research'].map((kind) => Math.round(document.querySelector(`[data-media-strip="${kind}"]`).getBoundingClientRect().width)));
    expect(Math.max(...stripWidths) - Math.min(...stripWidths)).toBeLessThanOrEqual(2);

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
    await page.locator('#home-news-track').evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'instant' }));
    const newsTop = () => page.locator('#home-news-track .marquee-track').evaluate((track) => new DOMMatrixReadOnly(getComputedStyle(track).transform).m42);
    const newsBefore = await newsTop();
    await page.mouse.move(1, 1);
    await expect.poll(async () => (await newsTop()) - newsBefore, { message: 'الأخبار تصعد', timeout: 6000 }).toBeLessThan(-1);

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

    await settleForShot(page);
    await page.screenshot({ path: `${SHOTS}/director-2026-11-03-home-${width}.png`, fullPage: true });
});

// ── ٣. ما كان يتحرك يبقى يتحرك (إضافة التوجيه، البند ١١): البطل، ودوّارات البطاقات الخمس (والبطاقات نفسها صف ثابت لا يتحرك)،
//    والإعلانات، والأخبار تصعد، وشريطا يوتيوب والمكتبة، والشركاء. والشريط القليل يتكرر فيتحرك أيضاً
async function expectRotates(page, selector, timeout) {
    const root = page.locator(selector).first();
    // بالتمرير من الصفحة لا من Playwright: العنصر المتحرك لا «يستقر» فينتظره scrollIntoViewIfNeeded بلا نهاية
    await root.evaluate((element) => element.scrollIntoView({ block: 'center', behavior: 'instant' }));
    const active = () => root.evaluate((element) => [...element.querySelectorAll('[data-carousel-item]')].findIndex((item) => item.hasAttribute('data-active')));
    const first = await active();
    await expect.poll(active, { message: `${selector} يتبدل`, timeout }).not.toBe(first);
}

async function expectMoves(page, selector) {
    const track = page.locator(`${selector} .marquee-track`).first();
    await track.evaluate((element) => element.closest('.marquee').scrollIntoView({ block: 'center', behavior: 'instant' }));
    await page.waitForTimeout(400);
    expect(await track.evaluate((element) => getComputedStyle(element).animationName), `${selector} متحرك`).not.toBe('none');
    const position = () => track.evaluate((element) => {
        const box = element.getBoundingClientRect();

        return Math.round((box.left + box.top) * 10) / 10;
    });
    const before = await position();
    await page.waitForTimeout(1500);
    expect(Math.abs((await position()) - before), `${selector} يتحرك فعلاً`).toBeGreaterThan(1);
}

test('home: everything that moved keeps moving, and the five cards are one row that does not move', async ({ page }, info) => {
    test.setTimeout(180_000);
    await page.goto('/');
    await page.mouse.move(1, 1);

    await expectRotates(page, '[data-hero-slider]', 10_000);

    // البطاقات الخمس: لا تتحرك هي نفسها، ودوّاراتها (الرؤية والرسالة، والقيم، والمبادرات) تتبدل
    const cards = page.locator('#home-identity');
    await cards.evaluate((element) => element.scrollIntoView({ block: 'center', behavior: 'instant' }));
    const boxes = () => cards.evaluate((section) => [...section.querySelectorAll('[data-identity-card]')]
        .filter((card) => !card.closest('[data-marquee-copy]'))
        .map((card) => Math.round(card.getBoundingClientRect().left)));
    if (info.project.name === 'desktop') {
        const before = await boxes();
        await page.waitForTimeout(1500);
        expect(await boxes(), 'البطاقات لا تتحرك').toEqual(before);
    }
    await expectRotates(page, '[data-identity-slider]', 9_000);
    // على الجوال واللوحي البطاقات الأربع في شريط يتحرك كما في main: يُوقف بزره فتبقى البطاقة في الشاشة، والدوّار يتوقف خارجها
    if (info.project.name !== 'desktop') {
        await expectMoves(page, '#home-identity');
        await page.locator('[data-ticker-toggle="home-cards-track"]').click();
        // المؤشر يبقى حيث ضُغط، فيقف فوق دوّار آخر بعد التمرير فيوقفه (التوقف بالمرور)
        await page.mouse.move(1, 1);
    }
    await expectRotates(page, '[data-values-slider]', 7_000);
    await expectRotates(page, '[data-initiatives-slider]', 7_000);

    await expectRotates(page, '.home-announcements', 10_000);
    await expectMoves(page, '#home-news-track');
    await expectMoves(page, '[data-media-strip="videos"]');
    await expectMoves(page, '[data-media-strip="books"]');
    await expectMoves(page, '[aria-labelledby="home-partners"]');
});

// ── ٤. «المساعد الذكي» في الصف الأول (إضافة التوجيه، البند ١٣): يفتح المساعد القائم ويعمل دليله بلا مزوّد. ويلزمه المساعد مفعّلاً
//    من «إعدادات المساعد» في البيئة المحلية أو رابط العرض
test('header: the assistant button opens the existing assistant', async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'الزر من 1024');
    for (const width of [1280, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        const button = page.locator('.header-top [data-header-assistant]');
        await expect(button).toBeVisible();
        await expect(button).toHaveAccessibleName('المساعد الذكي');
        await expect(page.locator('#assistant-panel')).toBeHidden();

        await button.click();
        await expect(page.locator('#assistant-panel')).toBeVisible();
        await expect(button).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#assistant-question')).toBeFocused();

        await page.locator('[data-assistant-close]').click();
        await expect(page.locator('#assistant-panel')).toBeHidden();
        await expect(button).toBeFocused();
    }
});

// ── ٥. رأس كل صفحة داخلية «فاتح بصورة واحدة» (البند ٢٧، ويحل محل ذوبان الصورة في البند ١٥)، بلقطة لكل قسم على 1440 و390
const SECTION_PAGES = {
    media: '/media',
    governance: '/governance',
    services: '/services',
    programs: '/programs',
    competitions: '/competitions',
    initiatives: '/initiatives',
    contact: '/contact',
    achievements: '/achievements',
    library: '/library',
    about: '/about',
};

test('inner page heroes: light with dark text, the section image in a tilted white frame, one shot per section', async ({ page }, info) => {
    test.skip(info.project.name === 'tablet', 'لقطات 1440 و390');
    test.setTimeout(240_000);
    const width = widthOf[info.project.name];
    for (const [name, path] of Object.entries(SECTION_PAGES)) {
        await page.goto(path);
        await page.evaluate(() => document.fonts.ready);
        const hero = page.locator('[data-page-hero]');
        await expect(hero, name).toHaveClass(/page-hero-light/);
        const look = await hero.evaluate((section) => {
            const frame = section.querySelector('.page-hero-frame');
            const back = section.querySelector('.page-hero-back');
            const title = section.querySelector('h1');
            const calligraphy = section.querySelector('[data-page-calligraphy]');

            return {
                title: getComputedStyle(title).color,
                calligraphy: { filter: getComputedStyle(calligraphy).filter, colour: getComputedStyle(calligraphy).backgroundColor, mask: getComputedStyle(calligraphy).maskImage || getComputedStyle(calligraphy).webkitMaskImage, label: calligraphy.getAttribute('aria-label'), height: calligraphy.getBoundingClientRect().height },
                background: getComputedStyle(section).backgroundImage,
                frameBorder: getComputedStyle(frame).borderTopWidth,
                frameTransform: getComputedStyle(frame).transform,
                backShown: getComputedStyle(back).display !== 'none',
                frameBelowText: frame.getBoundingClientRect().top >= title.getBoundingClientRect().bottom,
            };
        });
        expect(look.title, `${name}: نص داكن`).toBe('rgb(31, 69, 101)');
        // المخطوطة بأزرق الهوية الداكن بلا ظل، قناعاً من ملف المخطوطة (قرار صفوان، البند ٣٢ — تقييم اللقطات، البند ٣)
        // بلا ظل (وحركة الصعود rise-3 تُبقي blur(0px) بعد انتهائها، وليس ظلاً)
        expect(look.calligraphy.filter, `${name}: بلا ظل`).not.toContain('drop-shadow');
        expect(look.calligraphy.colour, `${name}: أزرق الهوية`).toBe('rgb(31, 69, 101)');
        expect(look.calligraphy.mask).toContain('logo-calligraphy.png');
        expect(look.calligraphy.label).toBe('جمعية إعجاز للدراسات والبحوث العلمية');
        expect(look.calligraphy.height).toBeGreaterThan(20);
        expect(look.background, `${name}: خلفية فاتحة`).toContain('linear-gradient');
        expect(look.frameBorder).toBe('8px');
        expect(look.frameTransform, `${name}: مائلة`).not.toBe('none');
        if (info.project.name === 'mobile') {
            expect(look.backShown, 'لا بطاقة خلفية على الجوال').toBe(false);
            expect(look.frameBelowText, 'الصورة تحت النص على الجوال').toBe(true);
        } else {
            expect(look.backShown).toBe(true);
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), name).toBeLessThanOrEqual(0);
        // بعد اكتمال حركة الصعود (rise-*)، فلا يظهر الوصف والمخطوطة مموَّهين (البند ١)
        await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
        await page.waitForTimeout(1500);
        const box = await hero.boundingBox();
        await page.screenshot({ path: `${SHOTS}/director-2026-11-03-hero-${name}-${width}.png`, clip: { x: 0, y: 0, width, height: Math.ceil(box.y + box.height) } });
    }
});

// ── ٦. «عن الجمعية» (البنود ٢٠ و٢١ و٢٤ و٢٨): «من نحن» يميناً في بطاقة ناعمة والرؤية والرسالة اقتباسين يساراً، وشريطا الأهداف والقيم
//    باتجاهين متعاكسين، وكلمتا خادم الحرمين ورئيس المجلس بلا صورتين. يلزمها «عن الجمعية» منشورة بمقاطعها
test('about: «من نحن» on the right in a soft light card, the vision and mission on the left as quotations', async ({ page }, info) => {
    const width = widthOf[info.project.name];
    await page.goto('/about');
    await page.evaluate(() => document.fonts.ready);
    const who = page.locator('[data-about-who]');
    const quotes = page.locator('[data-about-quotes]');
    await expect(who).toBeVisible();
    await expect(quotes.locator('[data-about-quote]')).toHaveCount(2);

    const colours = await who.evaluate((card) => ({ text: getComputedStyle(card).color, background: getComputedStyle(card).backgroundImage }));
    expect(colours.text).toBe('rgb(20, 46, 69)');
    expect(colours.background).toContain('rgb(234, 244, 252)');
    expect(colours.background).toContain('rgb(198, 225, 247)');

    const [whoBox, quotesBox] = [await who.boundingBox(), await quotes.boundingBox()];
    if (info.project.name === 'desktop') {
        expect(whoBox.x, '«من نحن» يميناً').toBeGreaterThan(quotesBox.x);
    } else {
        expect(whoBox.y, '«من نحن» أولاً').toBeLessThan(quotesBox.y);
    }
    const quote = await quotes.locator('.about-quote-text').first().evaluate((el) => ({ size: parseFloat(getComputedStyle(el).fontSize), weight: Number(getComputedStyle(el).fontWeight), border: getComputedStyle(el.closest('blockquote')).borderTopWidth }));
    expect(quote.size).toBeGreaterThanOrEqual(21);
    expect(quote.weight).toBeGreaterThanOrEqual(700);
    expect(quote.border).toBe('0px');
    // الكلمتان بلا صورتين (البند ٢٤)
    expect(await page.locator('section[aria-labelledby="about-king"] img, section[aria-labelledby="about-chairman"] img').count()).toBe(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);

    if (info.project.name !== 'tablet') {
        await settleForShot(page);
        await page.screenshot({ path: `${SHOTS}/director-2026-11-03-about-${width}.png`, fullPage: true });
    }
});

test('about: the goals strip moves right to left and the values strip after it left to right', async ({ page }) => {
    await page.goto('/about');
    const strips = page.locator('[data-cards-block] .marquee');
    test.skip((await strips.count()) < 2, 'يلزمه مقطعا بطاقات (الأهداف والقيم)');
    const move = async (index) => {
        const strip = strips.nth(index);
        await strip.evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'instant' }));
        await page.mouse.move(1, 1);
        await page.waitForTimeout(600);
        const left = () => strip.evaluate((el) => el.querySelector('.marquee-track').getBoundingClientRect().left);
        const before = await left();
        await page.waitForTimeout(2000);

        return (await left()) - before;
    };
    expect(await move(0), 'الأهداف من اليمين إلى اليسار').toBeLessThan(-2);
    expect(await move(1), 'القيم من اليسار إلى اليمين').toBeGreaterThan(2);
});

// ── ٧. القائمة المنسدلة «عن الجمعية» (البند ٢٨): الخمسة بالترتيب، مراسٍ إلى مقاطع موجودة، على الحاسوب وفي قائمة ☰
test('menu: the «عن الجمعية» dropdown lists the five anchors in order, each reaching its section', async ({ page }, info) => {
    await page.goto('/');
    const labels = ['التوجه الاستراتيجي', 'من نحن', 'الرؤية', 'الرسالة', 'القيم'];
    let toggle;
    // اللوحي العمودي والجوال بقائمة ☰، والحاسوب بصف القائمة
    if (info.project.name !== 'desktop') {
        await page.locator('[data-toggle="mobile-menu"]').click();
        toggle = page.locator('#mobile-menu button[data-toggle="dropdown"]').first();
    } else {
        toggle = page.locator('[data-main-nav] [data-nav-dropdown] [data-toggle="dropdown"]').first();
    }
    await toggle.click();
    const menu = page.locator(`#${await toggle.getAttribute('aria-controls')}`);
    await expect(menu).toBeVisible();
    const items = await menu.locator('a').evaluateAll((links) => links.map((link) => [link.textContent.trim(), link.getAttribute('href')]));
    const anchors = items.filter(([, href]) => href.includes('/about#'));
    expect(anchors.map(([label]) => label)).toEqual(labels);

    // المرساة إلى مقطعها تحت الرأس الثابت
    await menu.locator('a[href*="/about#about-vision"]').click();
    await page.waitForURL(/\/about#about-vision$/);
    await page.waitForTimeout(1200);
    const reached = await page.evaluate(() => {
        const box = document.getElementById('about-vision').getBoundingClientRect();
        const header = document.querySelector('[data-site-header]').getBoundingClientRect().bottom;

        return { belowHeader: box.top >= header - 2, inView: box.top < window.innerHeight };
    });
    expect(reached).toEqual({ belowHeader: true, inView: true });
    for (const [, href] of anchors) {
        expect(await page.locator(`#${href.split('#')[1]}`).count(), href).toBe(1);
    }
});

// ── ٨. البطل «فاتح بصورة واحدة» (البندان ٢٢ و٢٣): أربع شرائح بالترتيب، لقطة لكل منها على 1440
test('hero: four light slides in order — the Mushaf, the Haram, the King with the national frame, the chairman — one shot each', async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'لقطات الشرائح على 1440');
    await page.goto('/');
    const slider = page.locator('[data-hero-slider]');
    const keys = await slider.locator('[data-hero-slide]').evaluateAll((slides) => slides.map((slide) => slide.dataset.heroSlide));
    expect(keys).toEqual(['about', 'haram', 'king', 'chairman']);
    await slider.locator('[data-carousel-toggle]').click();
    for (const [index, key] of keys.entries()) {
        await slider.locator('[data-carousel-dot]').nth(index).click();
        const slide = slider.locator(`[data-hero-slide="${key}"]`);
        await expect(slide).toHaveAttribute('data-active', '');
        await page.waitForTimeout(1500);
        const look = await slide.evaluate((el) => ({
            buttons: [...el.querySelectorAll('[data-hero-button]')].map((button) => button.dataset.heroButton),
            textClipped: (() => { const text = el.querySelector('[data-hero-text]'); return text ? text.scrollHeight > text.clientHeight + 1 : false; })(),
            titleLines: Math.round(el.querySelector('h1 span, h2 span').getBoundingClientRect().height / parseFloat(getComputedStyle(el.querySelector('h1 span, h2 span')).lineHeight)),
            dots: [...document.querySelectorAll('[data-hero-slider] [data-carousel-dot]')].map((dot) => { const bar = getComputedStyle(dot, '::before'); return { width: bar.width, height: bar.height, colour: bar.backgroundColor, button: getComputedStyle(dot).backgroundColor }; }),
            title: getComputedStyle(el.querySelector('h1, h2')).color,
            frame: getComputedStyle(el.querySelector('[data-hero-frame]')).borderTopWidth,
            shadow: getComputedStyle(el.querySelector('[data-hero-frame]')).boxShadow,
        }));
        expect(look.title).toBe('rgb(31, 69, 101)');
        expect(look.frame).toBe('8px');
        // أزرار الشريحتين الأوليين (البند ٦)، والاقتباس كاملاً (البند ٧)، واسم رئيس المجلس بسطر واحد (البند ٩)، والمؤشر أشرطة رفيعة (البند ١٢)
        expect(look.buttons, key).toEqual({ about: ['primary', 'secondary'], haram: ['primary'], king: [], chairman: ['primary'] }[key]);
        if (key === 'king') {
            expect(look.textClipped, 'الاقتباس كاملاً').toBe(false);
        }
        if (key === 'chairman') {
            expect(look.titleLines, 'الاسم سطر واحد').toBe(1);
        }
        for (const [position, dot] of look.dots.entries()) {
            expect(dot.button, 'لا كتلة رمادية').toBe('rgba(0, 0, 0, 0)');
            expect(dot.height).toBe('6px');
            expect(dot.width).toBe(position === index ? '52px' : '34px');
            expect(dot.colour).toBe(position === index ? 'rgb(31, 69, 101)' : 'rgba(31, 69, 101, 0.25)');
        }
        if (key === 'king') {
            expect(look.shadow, 'حلقة ذهبية وخضراء').toContain('rgb(201, 169, 97)');
            expect(look.shadow).toContain('rgba(14, 107, 71');
        }
        const box = await slider.boundingBox();
        await page.screenshot({ path: `${SHOTS}/director-2026-11-03-hero-slide-${index + 1}-${key}-1440.png`, clip: { x: 0, y: 0, width: 1440, height: Math.ceil(box.y + box.height) } });
    }
});

// تقييم لقطات التوجيه (البند ٨): نص كل شريحة لا يلامس شريط الشرائح على الجوال واللوحي والحاسوب. والبند ١٣: العائم مخفي على الحاسوب
// وزر الرأس يكفي، ويبقى العائم على الجوال
test('hero: no slide text touches the slide bar, and the floating assistant shows on phones only', async ({ page }, info) => {
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);
    const slider = page.locator('[data-hero-slider]');
    // بالنقر البرمجي: الرأس الثابت قد يغطي الشريط حين يمرّره المتصفح إلى أعلى الشاشة على الجوال
    await slider.locator('[data-carousel-toggle]').evaluate((button) => button.click());
    const count = await slider.locator('[data-hero-slide]').count();
    for (let index = 0; index < count; index++) {
        await slider.locator('[data-carousel-dot]').nth(index).evaluate((dot) => dot.click());
        await page.mouse.move(1, 1);
        await page.waitForTimeout(900);
        const gap = await page.evaluate((i) => {
            const caption = document.querySelectorAll('[data-hero-slide]')[i].querySelector('figcaption').getBoundingClientRect();

            return document.querySelector('[data-hero-progress]').getBoundingClientRect().top - caption.bottom;
        }, index);
        expect(gap, `الشريحة ${index + 1}: النص فوق الشريط`).toBeGreaterThanOrEqual(8);
    }

    if (await page.locator('[data-assistant-fab]').count()) {
        if (info.project.name === 'desktop') {
            await expect(page.locator('[data-assistant-fab]')).toBeHidden();
            await expect(page.locator('[data-header-assistant]')).toBeVisible();
        } else {
            await expect(page.locator('[data-assistant-fab]')).toBeVisible();
        }
    }
});

// ── ٩. «خدماتنا الإلكترونية» بالنمط «أ» (البند ٢٩): تسع بطاقات 3×3 بلا خانة فارغة، والبطاقة نفسها في «خدماتنا» بالرئيسية
test('services: nine style «أ» cards in a 3×3 grid, the same card in the home strip, with shots', async ({ page }, info) => {
    test.skip(info.project.name === 'tablet', 'لقطات 1440 و390');
    const width = widthOf[info.project.name];
    await page.goto('/services');
    const cards = page.locator('[data-service-grid] > li');
    await expect(cards).toHaveCount(9);
    const boxes = await cards.evaluateAll((items) => items.map((item) => {
        const box = item.getBoundingClientRect();

        return { top: Math.round(box.top), width: Math.round(box.width) };
    }));
    if (info.project.name === 'desktop') {
        expect(new Set(boxes.map((box) => box.top)).size, 'ثلاثة صفوف').toBe(3);
        expect(new Set(boxes.map((box) => box.width)).size, 'بلا خانة فارغة ولا امتداد').toBe(1);
    }
    const look = await page.locator('.service-card').first().evaluate((card) => ({
        color: getComputedStyle(card).color,
        tile: getComputedStyle(card.querySelector('.service-card-tile')).width,
        align: getComputedStyle(card).textAlign,
    }));
    expect(look).toEqual({ color: 'rgb(31, 69, 101)', tile: '64px', align: 'center' });
    await expect(page.locator('[data-store-card] a')).toHaveAttribute('target', '_blank');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
    await settleForShot(page);
    await page.screenshot({ path: `${SHOTS}/director-2026-11-03-services-${width}.png`, fullPage: true });

    await page.goto('/');
    const section = page.locator('section[aria-labelledby="home-shortcuts"]');
    await section.evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'instant' }));
    expect(await section.locator('.service-card').count()).toBeGreaterThan(2);
    await page.waitForTimeout(900);
    const box = await section.boundingBox();
    await page.screenshot({ path: `${SHOTS}/director-2026-11-03-home-services-${width}.png`, clip: { x: 0, y: Math.max(0, box.y), width, height: Math.ceil(box.height) } });
});

// ── ١٠. الرأس يصغر عند التمرير ويعود كاملاً في أعلى الصفحة (البند ٣١)، والصف الثاني ظاهر، ولا يتحرك المحتوى
test('header: shrinks on scroll and returns full at the top, the menu row kept, with no layout shift', async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'على 1440');
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);
    const header = page.locator('[data-site-header]');
    const state = () => page.evaluate(() => {
        const element = document.querySelector('[data-site-header]');
        const first = document.querySelector('main > *');

        return {
            compact: element.hasAttribute('data-header-compact'),
            calligraphy: element.querySelector('[data-logo-calligraphy]').getBoundingClientRect().height,
            mark: element.querySelector('[data-brand-mark]').getBoundingClientRect().height,
            capsules: [...element.querySelectorAll('[data-main-nav] .nav-capsule')].filter((capsule) => capsule.getBoundingClientRect().height > 0).length,
            firstOffset: Math.round(first.getBoundingClientRect().top + window.scrollY),
        };
    });

    const full = await state();
    expect(full.compact).toBe(false);
    expect(full.capsules).toBe(7);
    await header.screenshot({ path: `${SHOTS}/director-2026-11-03-header-compact-top-1440.png` });

    await page.evaluate(() => window.scrollTo({ top: 700, behavior: 'instant' }));
    await expect(header).toHaveAttribute('data-header-compact', '');
    await page.waitForTimeout(600);
    const small = await state();
    expect(small.calligraphy, 'المخطوطة أصغر').toBeLessThan(full.calligraphy);
    expect(small.mark, 'الرمز أصغر').toBeLessThan(full.mark);
    expect(small.capsules, 'العناصر السبعة ظاهرة').toBe(7);
    expect(small.firstOffset, 'لا يتحرك المحتوى').toBe(full.firstOffset);
    await page.screenshot({ path: `${SHOTS}/director-2026-11-03-header-compact-scrolled-1440.png`, clip: { x: 0, y: 0, width: 1440, height: 320 } });

    // عتبتان متباعدتان: بين 40 و140 تبقى الحالة كما هي، وفي الأعلى يعود كاملاً
    await page.evaluate(() => window.scrollTo({ top: 90, behavior: 'instant' }));
    await page.waitForTimeout(300);
    await expect(header).toHaveAttribute('data-header-compact', '');
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await expect(header).not.toHaveAttribute('data-header-compact', /.*/);
    await page.waitForTimeout(600);
    const back = await state();
    expect(Math.abs(back.calligraphy - full.calligraphy)).toBeLessThan(0.5);
    expect(back.firstOffset).toBe(full.firstOffset);
});

test('header: the shrink has no transition under reduced motion', async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'على 1440');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    const duration = await page.locator('.header-top [data-logo-calligraphy]').evaluate((img) => getComputedStyle(img).transitionDuration);
    expect(duration.split(',').every((value) => parseFloat(value) === 0)).toBe(true);
    await page.evaluate(() => window.scrollTo({ top: 700, behavior: 'instant' }));
    await expect(page.locator('[data-site-header]')).toHaveAttribute('data-header-compact', '');
});

// مراجعة المشرف على #157 (أمنية، متوسطة): مرساة مشوّهة (/about#%) كانت ترمي URIError من decodeURIComponent فتوقف التهيئة كلها.
// الآن تُتجاهل، وكل تهيئة في page.js وحدها. فلا خطأ في الصفحة، وما يُسجَّل بعدها يعمل: تصغير الرأس (آخر تهيئة في app.js) على
// الحاسوب، ودوّار البطل في الرئيسية بالمرساة نفسها
test('a malformed hash (/about#%) neither throws nor stops the later page setups', async ({ page }, info) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));

    await page.goto('/about#%');
    await page.evaluate(() => document.fonts.ready);
    await expect(page.locator('h1').first()).toBeVisible();

    if (info.project.name === 'desktop') {
        await page.evaluate(() => window.scrollTo({ top: 600, behavior: 'instant' }));
        await expect(page.locator('[data-site-header]')).toHaveAttribute('data-header-compact', '');
    }

    await page.goto('/#%');
    const slider = page.locator('[data-hero-slider]');
    const first = await slider.locator('[data-carousel-item][data-active]').getAttribute('data-hero-slide');
    await slider.locator('[data-carousel-dot]').nth(1).evaluate((dot) => dot.click());
    await expect(slider.locator('[data-carousel-item][data-active]')).not.toHaveAttribute('data-hero-slide', first);

    expect(errors, 'لا خطأ في الصفحة').toEqual([]);
});
