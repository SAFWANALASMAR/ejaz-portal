// الصور تُعرض واضحة في كل موضع (ملاحظة صفوان، 1 نوفمبر 2026 — docs/decisions/2026-11-01-visual-refresh-2-followups.md): لا صورة
// أكبر من مقاسها الطبيعي فتضبب (بهامش 10% لا يُرى)، ولكل صورة أبعاد أو نسبة تحجز مكانها فلا تزيح الصفحة عند تحميلها. على العروض الثلاثة.
// الصفحات بالصور: المعرض والأخبار والرئيسية والمركز الإعلامي، وصفحتا ألبوم وخبر إن وُجدا (ALBUM_SLUG وNEWS_SLUG).
import { expect, test } from '@playwright/test';

const pages = ['/', '/media', '/media/news', '/media/albums'];
if (process.env.ALBUM_SLUG) pages.push(`/media/albums/${process.env.ALBUM_SLUG}`);
if (process.env.NEWS_SLUG) pages.push(`/media/news/${process.env.NEWS_SLUG}`);

async function loadAllImages(page) {
    await page.evaluate(async () => {
        document.querySelectorAll('img[loading="lazy"]').forEach((img) => { img.loading = 'eager'; });
        for (let y = 0; y < document.body.scrollHeight; y += 400) {
            window.scrollTo(0, y);
            await new Promise((resolve) => setTimeout(resolve, 60));
        }
        window.scrollTo(0, 0);
        await Promise.all([...document.images].map((img) => (img.complete ? null : new Promise((resolve) => { img.onload = img.onerror = resolve; }))));
    });
}

for (const url of pages) {
    test(`images on ${url}: reserved box, never upscaled, no layout shift`, async ({ page }) => {
        await page.addInitScript(() => {
            window.__cls = 0;
            // الإزاحة التي سببها صورة في المحتوى وحدها: مصدرها صورة أو غلاف صورة مباشر خارج الرأس. وإزاحة الرأس عند تحميل الخط
            // وحقن الأنماط في بيئة التطوير (Vite) خارجها
            const fromImage = (node) => node?.nodeType === 1 && !node.closest('[data-site-header]')
                && (node.tagName === 'IMG' || (node.children.length <= 2 && node.querySelector(':scope > img, :scope > span > img') !== null));
            new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    if (!entry.hadRecentInput && (entry.sources ?? []).some((source) => fromImage(source.node))) window.__cls += entry.value;
                }
            }).observe({ type: 'layout-shift', buffered: true });
        });
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto(url);
        await loadAllImages(page);

        const problems = await page.evaluate(() => {
            const out = [];
            for (const img of document.images) {
                const box = img.getBoundingClientRect();
                const src = img.currentSrc || img.src;
                if (box.width < 2 || box.height < 2 || !img.naturalWidth || /\.svg(\?|$)/.test(src) || img.closest('dialog')) continue;

                const style = getComputedStyle(img);
                if (!(img.hasAttribute('width') && img.hasAttribute('height')) && style.aspectRatio === 'auto') {
                    out.push(`no box: ${src}`);
                }

                const fit = Math.min(box.width / img.naturalWidth, box.height / img.naturalHeight);
                const scale = style.objectFit === 'scale-down' ? Math.min(1, fit)
                    : style.objectFit === 'contain' ? fit
                    : Math.max(box.width / img.naturalWidth, box.height / img.naturalHeight);
                if (scale > 1.1) {
                    out.push(`upscaled x${scale.toFixed(2)}: ${src} (${Math.round(box.width)}x${Math.round(box.height)} from ${img.naturalWidth}x${img.naturalHeight})`);
                }
            }
            return out;
        });

        expect(problems).toEqual([]);
        expect(await page.evaluate(() => window.__cls), 'إزاحة التخطيط من الصور').toBeLessThan(0.05);
    });
}
