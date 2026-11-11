// يرسم صورة المشاركة الافتراضية 1200×630 من template.html (docs/decisions/2026-11-11-fix-site-audit-nov11.md، البند ٦).
// من جذر المستودع، والموقع يعمل والأصول مبنية (npm run build):
//   docker compose run --rm --no-deps browser node scripts/share-image/render.mjs
// والاسم من lang/ar/site.php، والرمز والمخطوطة البيضاوان من public/images/brand، والخط Zain من ملف خطوط البناء.
import { readFileSync } from 'node:fs';
import net from 'node:net';
import { chromium } from '@playwright/test';

// localhost في حاوية المتصفح محوَّل إلى حاوية التطبيق، كما في tests/browser/global-setup.mjs
const port = Number(process.env.APP_PORT ?? 8000);
const proxy = net.createServer((client) => {
    const upstream = net.connect(Number(process.env.APP_UPSTREAM_PORT ?? 8000), process.env.APP_HOST ?? 'app');
    client.pipe(upstream);
    upstream.pipe(client);
    client.on('error', () => upstream.destroy());
    upstream.on('error', () => client.destroy());
});
await new Promise((resolve) => proxy.listen(port, '127.0.0.1', resolve));
const base = `http://localhost:${port}`;
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const fonts = Object.values(manifest).find((entry) => /fonts-[^/]+\.css$/.test(entry.file));
const name = readFileSync('lang/ar/site.php', 'utf8').match(/'name' => '([^']+)'/)[1];

const html = readFileSync('scripts/share-image/template.html', 'utf8')
    .replace('{{MARK}}', `${base}/images/brand/logo-mark-white.png`)
    .replace('{{CALLIGRAPHY}}', `${base}/images/brand/logo-calligraphy-white.png`)
    .replace('{{NAME}}', name)
    .replace('</head>', `<link rel="stylesheet" href="${base}/build/${fonts.file}"></head>`);

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
await page.goto(`${base}/robots.txt`);
await page.setContent(html, { waitUntil: 'networkidle' });
await page.evaluate(() => document.fonts.ready);
await page.locator('[data-share-card]').screenshot({ path: 'public/images/brand/share-default.png' });
await browser.close();
proxy.close();
console.log('public/images/brand/share-default.png');
