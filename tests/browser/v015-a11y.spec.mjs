// فحص الوصول (WCAG 2.1 AA) لصفحات الإصدار 0.15.0 الجديدة والمعدّلة (docs/reviews/2026-11-02-v0.15-a11y.md): axe-core على كل
// صفحة بحالاتها (المرور والتركيز)، ثم فحوص مكتوبة لما لا يراه axe: التركيز الظاهر وترتيبه، والقوائم المنسدلة بلوحة المفاتيح،
// والدوّارات والأشرطة المتحركة وأزرار إيقافها، وتقليل الحركة، والعناوين، والأسماء.
// التشغيل (الأصول مبنية، وبيانات المعاينة المحلية — tests/browser/README.md «فحص الوصول»):
//   docker compose run --rm --no-deps -e VITE_HOST=none -e DEMO_CREDENTIALS="$creds" browser \
//     npx playwright test --config=tests/browser/playwright.config.mjs v015-a11y.spec.mjs
// الصفحات التي تحتاج محتوى (عنصر مكتبة، وثيقة، مسابقة) تُتخطى إن لم يوجد: A11Y_BOOK وA11Y_VIDEO وA11Y_AUDIO وA11Y_DOCUMENT وA11Y_COMPETITION.
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { existsSync, writeFileSync } from 'node:fs';
import { AUTH_STATE } from './global-setup.mjs';

const BOOK = process.env.A11Y_BOOK;
const VIDEO = process.env.A11Y_VIDEO;
const AUDIO = process.env.A11Y_AUDIO;
const DOCUMENT = process.env.A11Y_DOCUMENT;
const COMPETITION = process.env.A11Y_COMPETITION;

const guestPages = [
    '/', '/about', '/library', '/library/videos', '/library/audio', '/library/books', '/library/research',
    BOOK && `/library/books/${BOOK}`, VIDEO && `/library/videos/${VIDEO}`, AUDIO && `/library/audio/${AUDIO}`,
    '/governance', DOCUMENT && `/governance/${DOCUMENT}`,
    '/competitions', COMPETITION && `/competitions/${COMPETITION}`, COMPETITION && `/competitions/${COMPETITION}/guide`,
    COMPETITION && `/competitions/${COMPETITION}/register`,
    '/contact', '/media', '/media/news', '/media/announcements', '/media/partners', '/programs', '/services', '/achievements',
].filter(Boolean);

const accountPages = ['/account', '/account/my-reading', '/account/competitions'];

async function open(page, path) {
    const response = await page.goto(path);
    test.skip([403, 404].includes(response?.status()), `${path} غير منشورة أو لغير صاحب الصلاحية`);
    await page.evaluate(() => document.fonts.ready);
    // مقاطع الرئيسية تظهر بالتمرير (home.js): تُظهر كلها قبل الفحص
    await page.evaluate(() => document.querySelectorAll('[data-reveal]').forEach((element) => element.setAttribute('data-revealed', '')));
    // القارئ يرسم صفحته بعد التحميل فيتغير طول الصفحة: يُنتظر حتى يجهز، فلا تُقاس العناصر قبل استقرارها
    await page.waitForFunction(() => [...document.querySelectorAll('[data-pdf-reader]')].every((reader) => reader.dataset.state !== 'loading'), null, { timeout: 30_000 }).catch(() => null);
    await page.waitForTimeout(800);
}

async function axe(page, include) {
    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).exclude('.cf-turnstile');
    if (include) builder = builder.include(include);
    const { violations } = await builder.analyze();

    return violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.map((n) => `${n.target.join(' ')} — ${n.failureSummary?.split('\n')[1] ?? ''}`).slice(0, 4).join(' | ')}`);
}

test.describe('axe: v0.15 guest pages', () => {
    for (const path of guestPages) {
        test(path, async ({ page }) => {
            await open(page, path);
            expect(await axe(page), path).toEqual([]);
        });
    }
});

test.describe('axe: v0.15 account pages', () => {
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS');
    test.use({ storageState: AUTH_STATE });

    for (const path of accountPages) {
        test(path, async ({ page }) => {
            await open(page, path);
            expect(await axe(page), path).toEqual([]);
        });
    }
});

// ── التركيز الظاهر بتباين 3:1 (WCAG 2.4.7 و1.4.11) ──────────────────────────────────────────────────────────────────────
// لكل محطة Tab: لقطتان لحيّز العنصر وحوله، بمؤشره ثم بلا إطاره وظله (والتركيز باقٍ)، والحركة كلها مجمّدة. والمؤشر ظاهر إن تغيّر في الحلقة حول
// العنصر (وداخل حافته) ما يعادل خطاً بعرض بكسل على محيطه كله، بتباين 3:1 على الأقل بين اللونين قبل التركيز وبعده. فيكشف ما لا
// يكشفه فحص outline في CSS: الإطار الذي يقصّه أب بـ overflow:hidden (الأشرطة المتحركة)، أو الذي يذوب في خلفية بلونه.
const FREEZE = '*, *::before, *::after { animation-play-state: paused !important; transition: none !important; caret-color: transparent !important; } html { scroll-behavior: auto !important; }';

// تُقارن اللقطتان في صفحة فارغة بلا سياسة أمان المحتوى
async function ringMeasure(scratch, focused, unfocused, box) {
    return scratch.evaluate(
        async ({ focused, unfocused, box }) => {
            const load = (b64) =>
                new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.width;
                        canvas.height = img.height;
                        const context = canvas.getContext('2d');
                        context.drawImage(img, 0, 0);
                        resolve(context.getImageData(0, 0, img.width, img.height));
                    };
                    img.src = `data:image/png;base64,${b64}`;
                });
            const [a, b] = await Promise.all([load(focused), load(unfocused)]);
            const scale = a.width / box.clipWidth;
            const lum = (d, i) => {
                const c = [d[i], d[i + 1], d[i + 2]].map((v) => {
                    v /= 255;
                    return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
                });
                return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
            };
            let strong = 0;
            for (let y = 0; y < Math.min(a.height, b.height); y++) {
                for (let x = 0; x < Math.min(a.width, b.width); x++) {
                    // إحداثيات CSS نسبةً إلى العنصر: الحلقة من 4 بكسل داخل حافته إلى 6 خارجها
                    const cx = x / scale - box.padX;
                    const cy = y / scale - box.padY;
                    const outside = Math.max(-cx, cx - box.width, -cy, cy - box.height);
                    if (outside > 6 || outside < -4) continue;
                    const i = (y * a.width + x) * 4;
                    const l1 = lum(a.data, i);
                    const l2 = lum(b.data, i);
                    if ((Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05) >= 3) strong++;
                }
            }
            return { strong: strong / scale / scale, perimeter: 2 * (box.width + box.height) };
        },
        { focused, unfocused, box },
    );
}

async function walkFocus(page, scratch, stops = 70) {
    await page.addStyleTag({ content: FREEZE });
    await page.mouse.move(0, 0);
    const problems = [];
    const seen = new Set();

    for (let i = 0; i < stops; i++) {
        await page.keyboard.press('Tab');
        const info = await page.evaluate(async () => {
            const el = document.activeElement;
            if (!el || el === document.body) return null;
            // كل عنصر يُعلَّم أول مرة، فعودة التركيز إلى عنصر سبق دورة كاملة (أو فخ)
            const repeated = el.hasAttribute('data-a11y-seen');
            el.setAttribute('data-a11y-seen', '');
            // فوري: التمرير الناعم في الصفحة (scroll-behavior) يقيس الموضع في منتصف الحركة
            el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
            // إطاران قبل القياس: ما يتغير بالتركيز (focus-within) وبالتمرير يستقر أولاً
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            const rect = el.getBoundingClientRect();
            const label = (el.getAttribute('aria-label') || el.textContent || el.getAttribute('title') || '').replace(/\s+/g, ' ').trim().slice(0, 40);
            const classes = typeof el.className === 'string' ? el.className.split(/\s+/).filter(Boolean).slice(0, 2).join('.') : '';
            return {
                key: `${el.tagName.toLowerCase()}${el.id ? `#${el.id}` : ''}${classes ? `.${classes}` : ''} «${label}»`,
                visible: el.checkVisibility({ visibilityProperty: true, opacityProperty: true }) && rect.width > 0 && rect.height > 0,
                hiddenAncestor: el.closest('[aria-hidden="true"], [inert]') !== null,
                repeated,
                box: { x: rect.x, y: rect.y, width: rect.width, height: rect.height },
            };
        });
        if (!info) {
            // التركيز خرج من الصفحة (إلى شريط المتصفح) بعد آخر عنصر
            if (process.env.A11Y_SHOTS) console.log(`${page.url()}: walk ended on body after ${seen.size} stops`);
            break;
        }
        const id = seen.size;
        if (info.repeated) {
            if (process.env.A11Y_SHOTS) console.log(`${page.url()}: walk repeated ${info.key} after ${seen.size} stops`);
            break;
        }
        seen.add(id);

        if (info.hiddenAncestor) problems.push(`focus inside aria-hidden/inert: ${info.key}`);
        // رابط التخطي لا يظهر إلا مركّزاً، فظهوره نفسه هو المؤشر
        // وعارض PDF في المتصفح (iframe) ينقل التركيز إلى داخله ويرسم مؤشره بنفسه
        // وزر إيقاف الشريط: إطاره ملاصق لخلفيته الفاتحة (6:1، FocusAndDotContrastTest)، وما تحته بطاقات تتحرك فلا يُقاس عليه
        if (info.key.startsWith('a.sr-only') || info.key.startsWith('iframe') || info.key.startsWith('button.marquee-toggle')) continue;
        if (!info.visible) {
            problems.push(`focus on an invisible element: ${info.key}`);
        } else {
            const pad = 8;
            const viewport = page.viewportSize();
            const clip = { x: Math.max(0, info.box.x - pad), y: Math.max(0, info.box.y - pad) };
            clip.width = Math.min(viewport.width - clip.x, info.box.x + info.box.width + pad - clip.x);
            clip.height = Math.min(viewport.height - clip.y, info.box.y + info.box.height + pad - clip.y);
            if (clip.width > 4 && clip.height > 4) {
                const focused = (await page.screenshot({ clip })).toString('base64');
                // بلا مؤشر: يُخفى إطار العنصر وظله وحدهما والتركيز باقٍ، فلا يتغير ما حوله (focus-within في البطاقة والشريط)
                // ولا ينتقل التركيز، فالـ Tab التالي يكمل من هنا
                await page.evaluate(() => {
                    const el = document.activeElement;
                    el.dataset.a11yStyle = el.getAttribute('style') ?? '';
                    el.style.setProperty('outline', 'none', 'important');
                    el.style.setProperty('box-shadow', 'none', 'important');
                });
                const unfocused = (await page.screenshot({ clip })).toString('base64');
                await page.evaluate(() => {
                    const el = document.activeElement;
                    el.setAttribute('style', el.dataset.a11yStyle);
                    delete el.dataset.a11yStyle;
                });
                const m = await ringMeasure(scratch, focused, unfocused, {
                    padX: info.box.x - clip.x, padY: info.box.y - clip.y, width: info.box.width, height: info.box.height, clipWidth: clip.width,
                });
                if (m.strong < m.perimeter * 0.9) {
                    // للمراجعة بالعين: A11Y_SHOTS مجلد تُحفظ فيه لقطتا كل مؤشر ضعيف (خارج git)
                    if (process.env.A11Y_SHOTS) {
                        const name = `${process.env.A11Y_SHOTS}/${new URL(page.url()).pathname.replace(/\W+/g, '_')}-${problems.length}-${info.key.replace(/[^\w]+/g, '_').slice(0, 40)}`;
                        writeFileSync(`${name}-on.png`, Buffer.from(focused, 'base64'));
                        writeFileSync(`${name}-off.png`, Buffer.from(unfocused, 'base64'));
                    }
                    problems.push(`weak or clipped focus indicator (${Math.round(m.strong)} of ${Math.round(m.perimeter)} px at 3:1): ${info.key}`);
                }
            }
        }
    }

    return { problems, count: seen.size };
}

const focusPages = [
    '/', '/library', '/library/books', BOOK && `/library/books/${BOOK}`, AUDIO && `/library/audio/${AUDIO}`, DOCUMENT && `/governance/${DOCUMENT}`,
    '/contact', '/competitions', COMPETITION && `/competitions/${COMPETITION}`, '/media/announcements',
].filter((path) => path && (!process.env.A11Y_ONLY || path === process.env.A11Y_ONLY));

test.describe('focus indicator visible at 3:1 on every Tab stop', () => {
    for (const path of focusPages) {
        test(path, async ({ page, context }) => {
            await open(page, path);
            const scratch = await context.newPage();
            const { problems, count } = await walkFocus(page, scratch, path === '/' ? 200 : 120);
            await scratch.close();
            expect(count).toBeGreaterThan(3);
            expect(problems, path).toEqual([]);
        });
    }
});

// لوحة الإدارة («ج» في القائمة الجانبية والرئيسية): بجلسة إدارية محفوظة في ADMIN_STATE كما في a11y.spec.mjs
const ADMIN_STATE = process.env.ADMIN_STATE;

test.describe('admin dashboard and sidebar', () => {
    test.skip(!ADMIN_STATE || !existsSync(ADMIN_STATE), 'يحتاج جلسة إدارية محفوظة: ADMIN_STATE');
    test.use({ storageState: ADMIN_STATE ?? AUTH_STATE });

    // ولوحة الإدارة وبوابة المجلس (الجمعية العمومية بخطها الزمني للإنجازات) لحساب يحمل أدوارهما
    for (const path of ['/admin', '/assembly']) {
        test(`axe and focus on ${path}`, async ({ page, context }) => {
            await open(page, path);
            expect(await axe(page), path).toEqual([]);
            // مؤشر تركيز أزرار Filament ظاهر باللقطة، لكنه ليس إطاراً ولا ظلاً فلا يعزله هذا القياس. وما سواه في فرع لوحة الإدارة
            // (fix/v0.15-admin-qa — docs/reviews/2026-11-02-v0.15-a11y.md «ما بقي»)
            if (path === '/admin') return;
            const scratch = await context.newPage();
            const { problems } = await walkFocus(page, scratch, 60);
            await scratch.close();
            expect(problems, path).toEqual([]);
        });
    }
});

test.describe('focus indicator on account pages', () => {
    test.skip(!existsSync(AUTH_STATE), 'يحتاج المستخدم التجريبي: DEMO_CREDENTIALS');
    test.use({ storageState: AUTH_STATE });

    for (const path of accountPages) {
        test(path, async ({ page, context }) => {
            await open(page, path);
            const scratch = await context.newPage();
            const { problems } = await walkFocus(page, scratch);
            await scratch.close();
            expect(problems, path).toEqual([]);
        });
    }
});

// ── تباين حالة المرور (1.4.3) ───────────────────────────────────────────────────────────────────────────────────────────
const hoverTargets = ['.nav-capsule', '.login-pill', '.more-link', '.hero-pill', '.tone-pill', '.footer-link-row', '.service-tile', '.media-tile',
    '[data-news-card]', '.carousel-arrow', '.concept-arrow', '.marquee-toggle', '.social-circle'];

test.describe('hover states keep text contrast', () => {
    for (const path of ['/', '/library', '/contact', DOCUMENT && `/governance/${DOCUMENT}`].filter(Boolean)) {
        test(path, async ({ page }) => {
            await open(page, path);
            await page.addStyleTag({ content: FREEZE });
            const problems = [];

            for (const selector of hoverTargets) {
                const elements = page.locator(`${selector}:visible`);
                const total = Math.min(await elements.count(), 3);
                for (let i = 0; i < total; i++) {
                    const element = elements.nth(i);
                    if (await element.evaluate((el) => el.closest('[aria-hidden="true"], [inert]') !== null)) continue;
                    await element.scrollIntoViewIfNeeded();
                    await element.hover({ force: true, timeout: 5000 }).catch(() => null);
                    await element.evaluate((el) => el.setAttribute('data-a11y-hover', ''));
                    const { violations } = await new AxeBuilder({ page }).include('[data-a11y-hover]').withRules(['color-contrast']).analyze();
                    await element.evaluate((el) => el.removeAttribute('data-a11y-hover'));
                    violations.forEach((v) => v.nodes.forEach((n) => problems.push(`${selector} hover: ${n.target.join(' ')} — ${n.failureSummary?.split('\n')[1] ?? ''}`)));
                }
            }

            expect(problems, path).toEqual([]);
        });
    }
});

// ── البنية: العناوين، والنص البديل، والأرقام المتحركة خارج المناطق الحية ───────────────────────────────────────────────────
test.describe('structure: one h1, no skipped heading level, alt on every image, no count-up announced', () => {
    for (const path of guestPages) {
        test(path, async ({ page }) => {
            await open(page, path);
            const report = await page.evaluate(() => {
                const shown = (el) => el.checkVisibility() || el.closest('.sr-only') !== null;
                const headings = [...document.querySelectorAll('main h1, main h2, main h3, main h4, main h5, main h6, main [role=heading]')]
                    .filter((h) => shown(h) && h.closest('[aria-hidden="true"], [inert]') === null)
                    .map((h) => ({ level: Number(h.getAttribute('aria-level') ?? h.tagName.slice(1)), text: h.textContent.replace(/\s+/g, ' ').trim().slice(0, 40) }));
                const skips = [];
                headings.forEach((h, i) => {
                    if (i > 0 && h.level > headings[i - 1].level + 1) skips.push(`h${headings[i - 1].level} «${headings[i - 1].text}» → h${h.level} «${h.text}»`);
                });
                return {
                    lang: document.documentElement.lang,
                    dir: document.documentElement.dir,
                    h1: document.querySelectorAll('h1').length,
                    skips,
                    noAlt: [...document.querySelectorAll('img:not([alt])')].map((img) => img.getAttribute('src')?.slice(-40)),
                    // عدّاد يتبدل نصه داخل منطقة حية يُقرأ رقماً رقماً. ومسار الدوّار «off» أثناء التبديل و«polite» متوقفاً، فكل aria-live يُعد
                    liveCounters: [...document.querySelectorAll('[data-count-to]')]
                        .filter((el) => el.closest('[aria-hidden="true"]') === null && el.closest('[aria-live], [role=status], [role=alert], [role=log]') !== null)
                        .map((el) => el.dataset.countTo),
                };
            });

            expect(report.lang).toBe('ar');
            expect(report.dir).toBe('rtl');
            expect(report.h1, 'exactly one h1').toBe(1);
            expect(report.skips, 'heading levels').toEqual([]);
            expect(report.noAlt, 'img without alt').toEqual([]);
            expect(report.liveCounters, 'count-up inside a live region').toEqual([]);
        });
    }
});

// ── الأشرطة المتحركة والدوّارات ─────────────────────────────────────────────────────────────────────────────────────────
test.describe('marquees and carousels', () => {
    test('marquee copies are hidden from assistive tech and keyboard yet clickable, and each pause button names and stops its strip', async ({ page }) => {
        await open(page, '/');
        expect(await page.locator('[data-marquee]').count()).toBeGreaterThan(0);

        // aria-hidden وروابطها خارج ترتيب لوحة المفاتيح، ولا inert فتبقى تُضغط بالفأرة واللمس (fix/marquee-copy-clickable)
        const copies = await page.evaluate(() => [...document.querySelectorAll('[data-marquee-copy]')].map((copy) => ({
            hidden: copy.getAttribute('aria-hidden') === 'true',
            inert: copy.hasAttribute('inert'),
            tabbable: [...copy.querySelectorAll('a, button, input, select, textarea, summary, [tabindex]')].filter((element) => element.tabIndex >= 0).length,
        })));
        copies.forEach((copy) => expect(copy).toEqual({ hidden: true, inert: false, tabbable: 0 }));

        for (const toggle of await page.locator('[data-ticker-toggle]').all()) {
            if (!(await toggle.isVisible())) continue;
            const id = await toggle.getAttribute('aria-controls');
            const label = await toggle.getAttribute('aria-label');
            expect(label).toBeTruthy();
            expect(await page.locator(`#${id}[role=region][aria-label]`).count(), `strip #${id} is a named region`).toBe(1);

            await toggle.focus();
            await page.keyboard.press('Enter');
            await expect(toggle).toHaveAttribute('aria-pressed', 'true');
            expect(await toggle.getAttribute('aria-label')).not.toBe(label);
            await page.mouse.move(0, 0);
            await toggle.blur();
            // متوقف فعلاً: موضع المسار لا يتغير في نصف ثانية (وحالته للتشخيص إن تحرك)
            const sample = () => page.locator(`#${id} .marquee-track`).evaluate((track) => {
                const style = getComputedStyle(track);
                return { transform: style.transform, state: `${style.animationName} ${style.animationPlayState} inview=${track.parentElement.hasAttribute('data-inview')} paused=${track.parentElement.hasAttribute('data-paused')}` };
            });
            // بعد خروج التركيز يعود الشريط من أوله (data-focus-reveal — home.js)، فيُنتظر استقراره قبل القياس
            await page.waitForTimeout(300);
            const before = await sample();
            await page.waitForTimeout(500);
            const after = await sample();
            expect(after.transform, `#${id} paused (${after.state})`).toBe(before.transform);
            await toggle.focus();
            await page.keyboard.press('Enter');
            await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        }
    });

    test('every carousel is a named region with labelled slides and dots, arrows move it, and hidden slides are inert', async ({ page }) => {
        await open(page, '/');
        // ما في نسخة الشريط الثانية (aria-hidden وtabindex=-1) خارج لوحة المفاتيح وقارئ الشاشة أصلاً
        const carousels = await page.locator('[data-carousel][data-carousel-ready]:not([inert] *, [aria-hidden="true"] *)').all();
        expect(carousels.length).toBeGreaterThan(0);

        for (const carousel of carousels) {
            const meta = await carousel.evaluate((root) => {
                const own = (selector) => [...root.querySelectorAll(selector)].filter((el) => el.closest('[data-carousel]') === root);
                return {
                    name: root.getAttribute('aria-label'),
                    role: root.getAttribute('aria-roledescription'),
                    slides: own('[data-carousel-item]').map((item) => ({ role: item.getAttribute('role'), label: item.getAttribute('aria-label'), inert: item.hasAttribute('inert'), active: item.hasAttribute('data-active') })),
                    dots: own('[data-carousel-dot]').map((dot) => dot.getAttribute('aria-label')),
                    current: own('[data-carousel-dot][aria-current="true"]').length,
                };
            });
            expect(meta.name).toBeTruthy();
            expect(meta.role).toBeTruthy();
            meta.slides.forEach((slide) => {
                expect(slide.role).toBe('group');
                expect(slide.label).toBeTruthy();
                expect(slide.inert).toBe(!slide.active);
            });
            meta.dots.forEach((label) => expect(label).toBeTruthy());
            if (meta.dots.length > 0) expect(meta.current).toBe(1);

            const toggle = carousel.locator('[data-carousel-toggle]');
            if (meta.slides.length > 1 && (await toggle.count()) > 0 && (await toggle.first().isVisible())) {
                const before = meta.slides.findIndex((slide) => slide.active);
                await toggle.first().focus();
                // التركيز يوقف التبديل التلقائي
                await expect(carousel).not.toHaveAttribute('data-running', '');
                await page.keyboard.press('ArrowLeft');
                const after = await carousel.evaluate((root) => [...root.querySelectorAll('[data-carousel-item]')].filter((el) => el.closest('[data-carousel]') === root).findIndex((el) => el.hasAttribute('data-active')));
                expect(after).toBe((before + 1) % meta.slides.length);
                await page.keyboard.press('ArrowRight');
            }
        }
    });

    test('reduced motion: the news strip stands still, the moving strips keep a visible pause button, and no carousel autoplays', async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await open(page, '/');

        // الأفقية تتحرك أبطأ مع تقليل الحركة بقرار المدير التنفيذي (1 نوفمبر، البند ١١)، فلكل منها زر إيقاف ظاهر (WCAG 2.2.2)
        const strips = await page.evaluate(() => [...document.querySelectorAll('[data-marquee]')].map((strip) => {
            const style = getComputedStyle(strip.querySelector('.marquee-track'));
            const moving = style.animationName !== 'none' && parseFloat(style.animationDuration) > 0.01;
            const toggle = document.querySelector(`[data-ticker-toggle="${strip.id}"]`);
            return { id: strip.id, direction: strip.dataset.direction, moving, pausable: toggle !== null && toggle.checkVisibility() };
        }));
        for (const strip of strips) {
            if (strip.direction === 'vertical') expect(strip.moving, strip.id).toBe(false);
            if (strip.moving) expect(strip.pausable, strip.id).toBe(true);
        }

        // بطاقات الهوية تتبدل بلا حركة بقرار المدير (data-autoplay-always) ولها زر إيقاف، والبقية لا تبدأ
        expect(await page.locator('[data-carousel][data-running]:not([data-autoplay-always])').count()).toBe(0);
    });
});

// ── القائمة المنسدلة والقسم الحالي والانتقال والقارئ ────────────────────────────────────────────────────────────────────
test.describe('keyboard operability and names', () => {
    test('header dropdown: arrows move through its links and Escape returns focus to its button', async ({ page }) => {
        test.skip(page.viewportSize().width < 1280, 'القائمة الكاملة على الحاسوب');
        await open(page, '/');
        const toggle = page.locator('[data-main-nav] button[data-toggle="dropdown"]').first();
        test.skip((await toggle.count()) === 0, 'لا قائمة منسدلة');
        const menu = page.locator(`#${await toggle.getAttribute('aria-controls')}`);

        await toggle.focus();
        await page.keyboard.press('ArrowDown');
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(menu.locator('a').first()).toBeFocused();
        await page.keyboard.press('End');
        await expect(menu.locator('a').last()).toBeFocused();
        await page.keyboard.press('ArrowDown');
        await expect(menu.locator('a').first()).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(menu).toBeHidden();
        await expect(toggle).toBeFocused();
    });

    test('the current section is announced in the main menu', async ({ page }) => {
        test.skip(page.viewportSize().width < 1280, 'القائمة الكاملة على الحاسوب');
        await open(page, '/library');
        expect(await page.locator('[data-main-nav] [aria-current="page"], [data-main-nav] [data-current]').count()).toBeGreaterThan(0);
        // زر القائمة المنسدلة للقسم الحالي يُعلَن حالياً بالرابط داخلها
        for (const button of await page.locator('[data-main-nav] button[data-current]').all()) {
            const menu = page.locator(`#${await button.getAttribute('aria-controls')}`);
            expect(await menu.locator('[aria-current="page"]').count(), await button.textContent()).toBeGreaterThan(0);
        }
    });

    test('page transition overlay is a hidden, polite status with a spoken text', async ({ page }) => {
        await open(page, '/');
        const overlay = page.locator('[data-page-transition]');
        test.skip((await overlay.count()) === 0, 'بلا تنقّل سريع');
        await expect(overlay).toBeHidden();
        await expect(overlay).toHaveAttribute('role', 'status');
        expect((await overlay.locator('.sr-only').textContent()).trim()).not.toBe('');
    });

    test('the read-only reader: labelled controls, page field, arrows turn pages', async ({ page }) => {
        test.skip(!BOOK, 'A11Y_BOOK');
        await open(page, `/library/books/${BOOK}`);
        const reader = page.locator('[data-pdf-reader]');
        test.skip((await reader.count()) === 0, 'الكتاب ليس للقراءة فقط');
        await expect(reader).toHaveAttribute('data-state', 'ready', { timeout: 30_000 });

        for (const button of await reader.locator('button').all()) {
            await expect(button).toHaveAccessibleName(/.+/);
        }
        const input = reader.locator('[data-pdf-page]');
        await expect(input).toHaveAccessibleName(/.+/);

        await reader.locator('[data-pdf-stage]').focus();
        await page.keyboard.press('ArrowLeft');
        await expect(input).toHaveValue('2');
        await page.keyboard.press('ArrowRight');
        await expect(input).toHaveValue('1');
    });

    test('every «more» and «view all» link carries the name of its section', async ({ page }) => {
        await open(page, '/');
        // نسخ الشريط المتحرك مخفية عن قارئ الشاشة ولوحة المفاتيح (data-marquee-copy، ومنها شريط البطاقات منذ #224)، فلا تُعد
        const names = await page.locator('main a.more-link').evaluateAll((links) => links.filter((link) => !link.closest('[data-marquee-copy]')).map((link) => {
            // الاسم المحسوب تقريباً: النص كاملاً بما فيه المخفي بصرياً
            return link.getAttribute('aria-label') ?? link.textContent.replace(/\s+/g, ' ').trim();
        }));
        // الإعلانات والأخبار والمقاطع والكتب والشركاء، وما نصه يصف وجهته («كل المبادرات») باسمه
        expect(names.length).toBeGreaterThan(2);
        expect(new Set(names).size, `duplicate names: ${names.join(' | ')}`).toBe(names.length);
    });
});
