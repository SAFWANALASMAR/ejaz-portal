// فحص بوابة عضو مجلس الإدارة قبل الإصدار 0.15.0 (docs/reviews/2026-11-03-v0.15-board-portal-qa.md):
// يزحف على كل صفحة يصلها عضو المجلس في /admin و/assembly و/account، على عرضي 1440 و390، ويتحقق في كل صفحة من:
// لا رد 4xx/5xx، والاتجاه من اليمين، وبلا تمرير أفقي، وبلا أخطاء في وحدة التحكم، وبلا نص إنجليزي ظاهر،
// وكل رابط داخلي فيها يفتح (لا رابط معطّل ولا رابط إلى صفحة محظورة عليه).
//
// للبيئة المحلية وحدها، والحسابات من v015-e2e.fixtures.php (رأس v015-e2e.spec.mjs)، والأصول مبنية بلا public/hot. ثم، بخادم
// بعدة عمال داخل حاوية التطبيق (خادم التطوير يرد طلباً واحداً في المرة):
//   docker compose exec -d -T -e PHP_CLI_SERVER_WORKERS=8 app php artisan serve --no-reload --host=0.0.0.0 --port=8170
//   docker compose run --rm --no-deps -e VITE_HOST=none -e APP_UPSTREAM_PORT=8170 browser npx playwright test --config=tests/browser/v015-board-qa.config.mjs
import { writeFileSync, mkdirSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { ACCOUNTS, checkStep, login, state, STATE_DIR, watch } from './v015-e2e.helpers.mjs';

const START = ['/admin', '/assembly', '/assembly/meetings', '/assembly/minutes', '/assembly/files', '/account'];
const SCOPE = /^\/(admin|assembly|account)(\/|$)/;
// مسارات لا تُزار: الخروج، والتنزيل والتصدير (ملفات لا صفحات)، وصفحات تعدّل الحالة
const SKIP = /(logout|\/download|\/export|\/preview-file|\/files\/[^/]+\/(open|view)$|\.pdf$|\.docx$|livewire)/;
// كلمات لاتينية مقبولة في واجهة عربية: أسماء منتجات ومختصرات
const ALLOWED_LATIN = /^(PDF|Excel|Word|YouTube|Teams|Microsoft|Zoom|WhatsApp|SMS|URL|QR|ID|CSV|XLSX|DOCX|JPG|PNG|MP4|MP3|Google|Salla|https?|www|ejaz|sa|test|com|org|Turnstile|Laravel|Filament|AM|PM|Gmail|Outlook|Instagram|LinkedIn|Facebook|TikTok|Snapchat|Telegram|X)$/i;

// المسار بنمطه: الأرقام والمعرّفات إلى {id}، فيُزار سجل واحد لكل شاشة
const pattern = (path) => path.replace(/\/[0-9a-f]{8}-[0-9a-f-]{27}/gi, '/{uuid}').replace(/\/\d+/g, '/{id}');

test.describe('board member portal', () => {
    test.skip(!ACCOUNTS?.board, 'accounts.json');

    test('login and save the session', async ({ page }) => {
        mkdirSync(STATE_DIR, { recursive: true });
        await login(page, ACCOUNTS.board);
        await expect(page).not.toHaveURL(/\/(login|two-factor-challenge)/);
        await page.context().storageState({ path: state('board-qa') });
    });

    test('every reachable page', async ({ browser, baseURL }, testInfo) => {
        test.setTimeout(1_800_000);
        const context = await browser.newContext({ ...testInfo.project.use, storageState: state('board-qa') });
        const page = await context.newPage();
        const watcher = watch(page, baseURL);
        const origin = new URL(baseURL).origin;
        const queue = [...START];
        const seenPatterns = new Set();
        const linkStatus = new Map();
        const report = [];

        while (queue.length) {
            const path = queue.shift();
            const key = pattern(path.split('?')[0]);
            if (seenPatterns.has(key)) continue;
            seenPatterns.add(key);

            // رابط موقّع يبدأ تنزيلاً (ملفات المجلس): فحص الرابط يكفي، فلا يُفتح صفحةً
            if (/[?&]signature=/.test(path)) continue;
            const response = await page.goto(path);
            const finalPath = new URL(page.url()).pathname;
            const entry = { path, status: response?.status(), finalPath, english: [], brokenLinks: [] };
            expect.soft(response?.status(), `status ${path}`).toBeLessThan(400);
            await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => {});
            // جداول اللوحة تُحمَّل بعد الصفحة: ينتظر صفوفها أو حالتها الفارغة قبل جمع روابط السجلات
            if (finalPath.startsWith('/admin/') && (await page.locator('.fi-ta').count())) {
                await page.locator('.fi-ta-row, .fi-ta-empty-state').first().waitFor({ timeout: 15_000 }).catch(() => {});
            }
            await checkStep(page, testInfo, watcher, key.replace(/[^a-z0-9]+/gi, '-'));

            // نص إنجليزي ظاهر: كلمات لاتينية في عُقد نصية مرئية خارج الحقول والشيفرة والبريد والروابط
            entry.english = await page.evaluate((allowedSource) => {
                const allowed = new RegExp(allowedSource, 'i');
                const found = new Set();
                const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
                while (walker.nextNode()) {
                    const node = walker.currentNode;
                    const element = node.parentElement;
                    if (!element || element.closest('script,style,code,pre,input,textarea,[dir=ltr],[aria-hidden=true],.sr-only,template,noscript')) continue;
                    const box = element.getBoundingClientRect();
                    const style = getComputedStyle(element);
                    if (box.width === 0 || box.height === 0 || style.visibility === 'hidden' || style.display === 'none') continue;
                    const text = node.textContent.replace(/\S+@\S+/g, '').replace(/https?:\/\/\S+/g, '');
                    const words = (text.match(/[A-Za-z][A-Za-z_\\-]{1,}/g) ?? []).filter((word) => !allowed.test(word));
                    if (words.length) found.add(node.textContent.trim().slice(0, 120));
                }
                return [...found];
            }, ALLOWED_LATIN.source);
            // التلميحات والعناوين البديلة الظاهرة للقارئ
            entry.english.push(...(await page.evaluate(() => [...document.querySelectorAll('[title],[aria-label],[placeholder]')]
                .flatMap((element) => ['title', 'aria-label', 'placeholder'].map((name) => element.getAttribute(name)))
                .filter((value) => value && /^[\x20-\x7E]+$/.test(value) && /[A-Za-z]{3,}/.test(value) && !/@|https?:/.test(value))
                .map((value) => `attr: ${value}`))));
            entry.english = [...new Set(entry.english)];
            expect.soft(entry.english, `english on ${path}`).toEqual([]);

            // الروابط الداخلية: كل رابط يُفحص مرة، والصفحات في النطاق تُضاف إلى الطابور
            const hrefs = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href));
            for (const href of new Set(hrefs)) {
                let url;
                try { url = new URL(href); } catch { continue; }
                if (url.origin !== origin || SKIP.test(url.pathname) || url.protocol !== 'http:') continue;
                const target = url.pathname + url.search;
                if (!linkStatus.has(target)) {
                    const result = await context.request.get(target, { maxRedirects: 0, failOnStatusCode: false }).catch(() => null);
                    linkStatus.set(target, result?.status() ?? 0);
                }
                const status = linkStatus.get(target);
                if (status >= 400 || status === 0) entry.brokenLinks.push(`${status} ${target}`);
                if (SCOPE.test(url.pathname) && !seenPatterns.has(pattern(url.pathname))) queue.push(target);
            }
            expect.soft(entry.brokenLinks, `broken links on ${path}`).toEqual([]);
            report.push(entry);
            console.log(`[${testInfo.project.name}] ${entry.status} ${path} -> ${entry.finalPath} english=${JSON.stringify(entry.english)} broken=${JSON.stringify(entry.brokenLinks)}`);
        }

        mkdirSync('storage/playwright', { recursive: true });
        writeFileSync(`storage/playwright/board-qa-${testInfo.project.name}.json`, JSON.stringify(report, null, 2));
        await context.close();
    });
});
