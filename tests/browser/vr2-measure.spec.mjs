import { test } from '@playwright/test';
test('measure', async ({ page }) => {
    await page.setViewportSize({ width: Number(process.env.W ?? 1440), height: 900 }); await page.goto('/', { waitUntil: 'networkidle' });
    console.log(JSON.stringify(await page.evaluate(() => { const r = (s) => document.querySelector(s)?.getBoundingClientRect().width;
        const h = document.querySelector('[data-site-header]'); const n = h.querySelector('[data-main-nav]');
        h.removeAttribute('data-nav-overflow');
        return { list: n.querySelector('ul').offsetWidth, nav: n.clientWidth, brand: h.querySelector('a').offsetWidth, right: h.querySelector('.shrink-0.items-center.gap-2').offsetWidth, row: h.firstElementChild.clientWidth, pill: r(".login-pill"), search: r(".header-icon-button"), cal: r("[data-logo-calligraphy]"), kids: [...h.querySelector(".shrink-0.items-center.gap-2").children].map((e) => e.tagName + ":" + e.getBoundingClientRect().width) };
    })));
});
