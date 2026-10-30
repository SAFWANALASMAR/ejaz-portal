// رأس الصفحات الداخلية متوازن (docs/decisions.md — 14 أكتوبر): حافة سفلية مستقيمة بارتفاع واحد عند الحافتين،
// وغطاء لوني موحّد لا تثقل به جهة، والعنوان الأبيض مقروء بتباين 4.5:1 فأكثر فوق أفتح نقطة من الخلفية.
// ولقطة الرأس على العرض الحالي في docs/design/screenshots/page-hero-<الصفحة>-<العرض>.png
import { expect, test } from '@playwright/test';

const pages = [
    { name: 'governance', path: '/governance' },
    { name: 'initiatives', path: '/initiatives' },
    { name: 'library', path: '/library' },
];

// أعلى سطوع نسبي (WCAG) في لقطة PNG، تُحسب في المتصفح نفسه فلا تلزم مكتبة صور
async function maxLuminance(page, png) {
    return page.evaluate(async (base64) => {
        const image = new Image();
        image.src = `data:image/png;base64,${base64}`;
        await image.decode();

        const canvas = document.createElement('canvas');
        canvas.width = image.width;
        canvas.height = image.height;
        const context = canvas.getContext('2d');
        context.drawImage(image, 0, 0);
        const { data } = context.getImageData(0, 0, image.width, image.height);

        const channel = (value) => {
            const c = value / 255;
            return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
        };
        let max = 0;
        for (let i = 0; i < data.length; i += 4) {
            max = Math.max(max, 0.2126 * channel(data[i]) + 0.7152 * channel(data[i + 1]) + 0.0722 * channel(data[i + 2]));
        }

        return max;
    }, png.toString('base64'));
}

for (const target of pages) {
    test(`${target.name} hero is balanced and readable`, async ({ page }) => {
        await page.goto(target.path);
        await page.evaluate(() => document.fonts.ready);
        // ظهور العنوان كلمة كلمة ينتهي قبل اللقطة والقياس (الحلقات المستمرة لا تنتهي فتُستثنى)
        await page.waitForFunction(() =>
            document.getAnimations().every((animation) => animation.playState !== 'running' || animation.effect?.getTiming().iterations === Infinity),
        );

        const hero = page.locator('[data-page-hero]');
        await expect(hero).toBeVisible();
        const box = await hero.boundingBox();
        const width = page.viewportSize().width;

        await page.screenshot({ path: `docs/design/screenshots/page-hero-${target.name}-${width}.png`, clip: { x: 0, y: 0, width, height: box.y + box.height + 48 } });

        // الحافة السفلية: الشريط نفسه تحت أول بكسل وآخر بكسل من العرض، على بعد بكسلين من أسفله
        const edges = await hero.evaluate((section) => {
            const { bottom } = section.getBoundingClientRect();
            const inHero = (x) => section.contains(document.elementFromPoint(x, bottom - 2));

            return { clipPath: getComputedStyle(section).clipPath, start: inHero(document.documentElement.clientWidth - 1), end: inHero(1) };
        });
        expect(edges.clipPath).toBe('none');
        expect(edges.start).toBe(true);
        expect(edges.end).toBe(true);

        // التباين: الخلفية وحدها خلف حروف العنوان (النص مخفي، والمربع مربع النص لا عرض العنصر كله)،
        // وأفتح بكسل فيها مقابل الأبيض
        const title = hero.locator('h1');
        const text = await title.evaluate((element) => {
            const range = document.createRange();
            range.selectNodeContents(element);
            const { x, y, width, height } = range.getBoundingClientRect();

            return { x, y: y + window.scrollY, width, height };
        });
        await title.evaluate((element) => (element.style.visibility = 'hidden'));
        const background = await page.screenshot({ clip: text });
        await title.evaluate((element) => (element.style.visibility = ''));

        const contrast = 1.05 / ((await maxLuminance(page, background)) + 0.05);
        expect(contrast, `title contrast ${contrast.toFixed(2)}:1`).toBeGreaterThanOrEqual(4.5);
    });
}
