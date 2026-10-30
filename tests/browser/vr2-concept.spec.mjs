// لقطات التصور النهائي (docs/design/final-concept) على العروض الثلاثة، للمقارنة في وصف الطلب (محلي، لا يُحفظ في git)
import fs from 'node:fs';
import { test } from '@playwright/test';

const source = 'docs/design/final-concept';
const target = 'storage/vr2-concept';
const blobs = { '0c77': 'hero.jpg', '8245': 'side.jpg', '5ecc': 'news1.jpg', '1a30': 'news2.jpg', c870: 'news3.jpg', ba2d: 'logo-mark-white.png', ca09: 'logo-calligraphy-white.png', c145: 'logo-mark.png', d7ff: 'logo-calligraphy.png' };
for (const logo of ['logo-mark-white.png', 'logo-calligraphy-white.png', 'logo-mark.png', 'logo-calligraphy.png']) {
    fs.copyFileSync('public/images/brand/' + logo, target + '/' + logo);
}

test.beforeAll(() => {
    for (const file of fs.readdirSync(source)) {
        if (file.endsWith('.jpg')) {
            fs.copyFileSync(`${source}/${file}`, `${target}/${file}`);
        }
        if (file.endsWith('.dc.html')) {
            const html = fs.readFileSync(`${source}/${file}`, 'utf8').replace(/\/_blob\/([0-9a-f]{4})[0-9a-f]+/g, (match, prefix) => blobs[prefix] ?? match);
            fs.writeFileSync(`${target}/${file}`, html);
        }
    }
});

const files = fs.readdirSync(source).filter((file) => file.endsWith('.dc.html') && (!process.env.CONCEPT || file.includes(process.env.CONCEPT)));

for (const file of files) {
    test(`concept ${file}`, async ({ page }, info) => {
        await page.goto(`file:///work/${target}/${file}`);
        await page.waitForSelector('body[data-dc-ready]');
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.waitForTimeout(800);
        const width = info.project.use.viewport.width;
        await page.screenshot({ path: `docs/design/screenshots/visual-refresh-2/concept-${file.replace('.dc.html', '')}-${width}.png`, fullPage: true });
    });
}
