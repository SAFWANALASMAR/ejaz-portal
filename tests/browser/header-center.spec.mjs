// توازن الصف الأول على الحاسوب (طلب صفوان، 3 نوفمبر 2026 — docs/decisions/2026-11-03-header-calligraphy-center.md):
// «وازن المخطوطة ورقم الترخيص؛ لازم يرى الناظر أنها في الوسط وأن كل شيء متناسق».
// - من 1280: المخطوطة في منتصف عرض الرأس، ومربع الترخيص في منتصف الفراغ بينها وبين البحث والدخول، وبمحاذاتها عمودياً.
// - الرمز في أقصى اليمين، والبحث والدخول في أقصى اليسار كما كانا. ودون 1280 الترتيب السابق، والجوال على 390 كما كان.
// اللقطات: docs/design/screenshots/header-center/<العرض>-<before|after>.png (SHOT_PREFIX، وafter افتراضياً).
// ولقطات «قبل» من main قبل التغيير: SHOT_PREFIX=before يلتقط ويقيس ولا يتحقق من التوازن.
import { readFileSync, writeFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

const SHOTS = 'docs/design/screenshots/header-center';
const PREFIX = process.env.SHOT_PREFIX ?? 'after';
const BEFORE = PREFIX === 'before';
// مواضع عناصر الرأس على الجوال قبل التغيير، والجوال لا يتغير
const PHONE_BASELINE = `${SHOTS}/390-before.json`;

test.beforeEach(async ({}, testInfo) => test.skip(testInfo.project.name !== 'desktop', 'العروض في الاختبار نفسه'));

function measure(page) {
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
        const headerBox = header.getBoundingClientRect();
        const mark = box('[data-brand-mark]');
        const calligraphy = box('[data-logo-calligraphy]');
        const licence = box('[data-header-licence]');
        const actions = box('.header-actions');
        const login = box('.header-top .login-pill');
        const search = box('[data-search-open]');
        const burger = box('[data-toggle="mobile-menu"]');
        const round = (value) => Math.round(value * 10) / 10;

        return {
            viewport: document.documentElement.clientWidth,
            noHorizontalScroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
            boxes: { mark, calligraphy, licence, actions, login, search, burger },
            // RTL: الرمز يميناً، ثم المخطوطة، ثم مربع الترخيص، ثم البحث والدخول يساراً
            calligraphyOffCentre: calligraphy && round((calligraphy.left + calligraphy.right) / 2 - (headerBox.left + headerBox.right) / 2),
            markToCalligraphy: mark && calligraphy && round(mark.left - calligraphy.right),
            calligraphyToLicence: calligraphy && licence && round(calligraphy.left - licence.right),
            licenceToActions: licence && actions && round(licence.left - actions.right),
            verticalOffset: calligraphy && licence && round((licence.top + licence.bottom) / 2 - (calligraphy.top + calligraphy.bottom) / 2),
            markFromEdge: mark && round(headerBox.right - mark.right),
            loginFromEdge: login && round(login.left - headerBox.left),
        };
    });
}

async function open(page, width) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);
    const layout = await measure(page);
    const bottom = await page.evaluate(() => document.querySelector('[data-site-header]').getBoundingClientRect().bottom);
    await page.screenshot({ path: `${SHOTS}/${width}-${PREFIX}.png`, clip: { x: 0, y: 0, width, height: Math.ceil(bottom) + 80 } });
    console.log(`${width}px ${PREFIX}:`, JSON.stringify({ ...layout, boxes: undefined }));

    return layout;
}

for (const width of [1280, 1440, 1920]) {
    test(`header at ${width}px: calligraphy centred, licence midway to search and login`, async ({ page }) => {
        const layout = await open(page, width);
        expect(layout.noHorizontalScroll).toBe(true);
        test.skip(BEFORE, 'لقطة «قبل» وقياسها فقط');

        // ١. المخطوطة في منتصف عرض الرأس
        expect(Math.abs(layout.calligraphyOffCentre)).toBeLessThanOrEqual(8);
        // ٢. الرمز في أقصى اليمين، والدخول في أقصى اليسار بهامش صغير، والبحث بجانبه
        expect(layout.markFromEdge).toBeGreaterThanOrEqual(8);
        expect(layout.markFromEdge).toBeLessThanOrEqual(24);
        expect(layout.loginFromEdge).toBeGreaterThanOrEqual(8);
        expect(layout.loginFromEdge).toBeLessThanOrEqual(24);
        const { search, login } = layout.boxes;
        expect(search.left - login.right).toBeGreaterThanOrEqual(0);
        expect(search.left - login.right).toBeLessThanOrEqual(16);
        // ٣. مربع الترخيص في منتصف الفراغ بين المخطوطة والبحث والدخول، أبعد عن المخطوطة من فاصل الأعمدة، وبمحاذاتها عمودياً
        expect(layout.calligraphyToLicence).toBeGreaterThan(12);
        expect(Math.abs(layout.calligraphyToLicence - layout.licenceToActions)).toBeLessThanOrEqual(8);
        expect(Math.abs(layout.verticalOffset)).toBeLessThanOrEqual(4);
    });
}

test('header below 1280px keeps the licence beside the calligraphy without overlapping', async ({ page }) => {
    const layout = await open(page, 1263);
    expect(layout.noHorizontalScroll).toBe(true);
    expect(layout.calligraphyToLicence).toBeGreaterThanOrEqual(4);
    expect(layout.calligraphyToLicence).toBeLessThanOrEqual(24);
    expect(layout.licenceToActions).toBeGreaterThanOrEqual(0);
    expect(layout.markToCalligraphy).toBeGreaterThan(0);
});

test('header on the phone at 390px is unchanged', async ({ page }) => {
    const layout = await open(page, 390);
    expect(layout.noHorizontalScroll).toBe(true);

    if (BEFORE) {
        writeFileSync(PHONE_BASELINE, `${JSON.stringify(layout.boxes, null, 4)}\n`);

        return;
    }

    const baseline = JSON.parse(readFileSync(PHONE_BASELINE, 'utf8'));
    for (const [name, box] of Object.entries(baseline)) {
        expect(layout.boxes[name] === null, `${name}: الظهور نفسه`).toBe(box === null);
        for (const side of box ? ['left', 'top', 'width', 'height'] : []) {
            expect(Math.abs(layout.boxes[name][side] - box[side]), `${name}.${side}`).toBeLessThanOrEqual(0.5);
        }
    }
});
