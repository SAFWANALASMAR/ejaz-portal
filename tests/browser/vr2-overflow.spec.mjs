import { test } from '@playwright/test';
test('overflow', async ({ page }) => {
    await page.goto(process.env.VIEW_PATH ?? '/', { waitUntil: 'networkidle' });
    console.log(JSON.stringify(await page.evaluate(() => {
        const res = [];
        const all = [...document.body.children, ...document.querySelector('main').children];
        for (const el of all) {
            const prev = el.style.display; el.style.display = 'none';
            res.push(el.tagName + '.' + String(el.className).slice(0, 40) + '#' + el.id + '=' + document.documentElement.scrollWidth);
            el.style.display = prev;
        }
        return { sw: document.documentElement.scrollWidth, res };
    })));
});
