// ملاحظة المدير التنفيذي ٢٩ (2 نوفمبر 2026): الرأس والتذييل بلونهما الداكن، وما بينهما أخف بتدرج «أخف، وهذا أخف منه»
// (docs/decisions/2026-11-02-director-29-lighter-mid.md). اللقطات قبل وبعد في docs/design/screenshots/director-29 (SHOT_PREFIX).
import { expect, test } from '@playwright/test';

test.skip(({ viewport }) => viewport.width === 820, 'الحاسوب والجوال يكفيان');

const prefix = process.env.SHOT_PREFIX ?? 'after';
const pages = [
    { name: 'home', path: '/' },
    { name: 'about', path: '/about' },
    { name: 'contact', path: '/contact' },
];

async function backgrounds(page) {
    return page.evaluate(() => {
        const bg = (el) => (el ? getComputedStyle(el).backgroundImage + ' | ' + getComputedStyle(el).backgroundColor : null);
        return {
            header: bg(document.querySelector('[data-site-header]')),
            footer: bg(document.querySelector('footer')),
            hero: bg(document.querySelector('.concept-hero')),
            scrim: bg(document.querySelector('.concept-hero-scrim')),
            cards: [...document.querySelectorAll('.tone-card')].slice(0, 4).map((card) => bg(card)),
        };
    });
}

for (const { name, path } of pages) {
    test(`${name}: لقطة ${prefix} وقياس الألوان`, async ({ page }, info) => {
        await page.goto(path);
        await page.evaluate(() => document.fonts.ready);
        console.log(name, info.project.name, JSON.stringify(await backgrounds(page), null, 1));
        await page.screenshot({ path: `docs/design/screenshots/director-29/${name}-${prefix}-${page.viewportSize().width}.png`, fullPage: true });
        if (name === 'home') {
            // البطل، وبطاقات الهوية، و«خدماتنا» كلٌّ في لقطته
            for (const [part, selector] of [['hero', '.concept-hero'], ['identity', '#home-identity'], ['services', 'section[aria-labelledby="home-shortcuts"]']]) {
                const section = page.locator(selector).first();
                await section.scrollIntoViewIfNeeded();
                await page.waitForTimeout(800);
                await section.screenshot({ path: `docs/design/screenshots/director-29/home-${part}-${prefix}-${page.viewportSize().width}.png` });
            }
        }
    });
}

// كل عنصر في المحتوى بين الرأس والتذييل (وما قبله وما بعده): لا خلفية ولا محطة تدرج بلون الرأس والتذييل #142E45 و#1F4565،
// ولو بشفافية. ويُقرأ اللون من الخلفية المحسوبة بصيغها الثلاث (rgb وrgba وcolor(srgb …)).
const chromeShades = ['20,46,69', '31,69,101'];
const contentPages = ['/', '/about', '/contact', '/login', '/services', '/media', '/governance', '/programs', '/library', '/competitions'];

async function chromeShadeOffenders(page) {
    return page.evaluate((shades) => {
        const found = [];
        const channels = (value) => {
            const colours = [];
            for (const match of value.matchAll(/rgba?\((\d+),\s*(\d+),\s*(\d+)/g)) colours.push(`${match[1]},${match[2]},${match[3]}`);
            for (const match of value.matchAll(/color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)/g)) {
                colours.push([match[1], match[2], match[3]].map((c) => Math.round(Number(c) * 255)).join(','));
            }
            return colours;
        };
        for (const element of document.querySelectorAll('#main, #main *')) {
            for (const pseudo of [null, '::before', '::after']) {
                const style = getComputedStyle(element, pseudo);
                if (pseudo && style.content === 'none') continue;
                const hit = channels(`${style.backgroundColor} ${style.backgroundImage}`).find((c) => shades.includes(c));
                if (hit) found.push(`${element.tagName.toLowerCase()}.${[...element.classList].join('.')}${pseudo ?? ''} → ${hit}`);
            }
        }
        return [...new Set(found)];
    }, chromeShades);
}

test('لا خلفية بلون الرأس والتذييل بينهما', async ({ page }) => {
    test.skip(page.viewportSize().width !== 1440, 'على الحاسوب');
    test.setTimeout(300_000);
    const offenders = {};
    for (const path of contentPages) {
        await page.goto(path);
        const found = await chromeShadeOffenders(page);
        if (found.length) offenders[path] = found;
    }
    expect(offenders).toEqual({});
});

// الرأس والتذييل بلونهما الداكن، والبطل والبطاقة الداكنة بالأزرق المتوسط إلى أخف منه (#2E6898 ← #3476AC)، ولا تمرير أفقي
for (const { name, path } of pages) {
    test(`${name}: الإطار داكن وما بينه أخف`, async ({ page }) => {
        await page.goto(path);
        const colours = await backgrounds(page);
        expect(colours.header).toContain('linear-gradient(rgb(23, 52, 79) 0%, rgb(31, 69, 101) 100%)');
        expect(colours.footer).toContain('rgb(31, 69, 101)');
        const hero = `${colours.hero} ${colours.scrim ?? ''}`;
        expect(hero).toContain('46, 104, 152');
        expect(hero).not.toMatch(/31, 69, 101|20, 46, 69/);
        const dark = colours.cards.find((card) => card.includes('rgb(46, 104, 152)'));
        expect(dark).toContain('linear-gradient(135deg, rgb(46, 104, 152), rgb(49, 113, 165) 55%, rgb(52, 118, 172))');
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
    });
}
