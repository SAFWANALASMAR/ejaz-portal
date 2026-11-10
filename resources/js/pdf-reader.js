// قارئ كتب المكتبة «للقراءة فقط — بلا تحميل» (resources/views/library/reader.blade.php): يرسم صفحة واحدة في <canvas>
// بـ pdf.js، بلا شريط عارض المتصفح ولا زري التنزيل والطباعة. ومكتبة pdf.js تُحمَّل عند وجود القارئ وحده (تقسيم Vite)،
// وعاملها من أصول البناء على الموقع نفسه (worker-src 'self' — SecurityHeaders). والملف برمز لمرة واحدة وترويسة القارئ، ومخلوط
// بمفتاح يُفك هنا، فلا يأخذه برنامج تحميل بالرابط. ولا يمنع تصوير الشاشة ولا من يفك الشيفرة بأدوات المطور
// (docs/decisions.md — 30 أكتوبر 2026، وdocs/decisions/2026-11-10-feat-reader-download-protection.md).

import workerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url';
import { onPage } from './page';
import { progressReporter, resumePoint } from './library-progress';

// نسب التكبير من ملاءمة العرض (1)
const ZOOMS = [0.5, 0.75, 1, 1.25, 1.5, 2, 3];
// حد مساحة اللوحة بالبكسل: سفاري على الجوال يرفض ما فوق نحو 16.7 مليون
const MAX_PIXELS = 16_000_000;
// مسافة السحب الأفقي على الجوال لتقليب الصفحة
const SWIPE = 50;

let pdfjs = null;

async function loadPdfjs() {
    // النسخة legacy لمتصفحات الجوال الأقدم
    pdfjs ??= import('pdfjs-dist/legacy/build/pdf.min.mjs').then((module) => {
        module.GlobalWorkerOptions.workerSrc = workerUrl;

        return module;
    });

    return pdfjs;
}

// رمز القارئ لمرة واحدة ومفتاح فك الخلط (docs/decisions/2026-11-10-feat-reader-download-protection.md): من الصفحة أول مرة، ثم
// يُحذفان منها فلا تحملهما نسخة زر الرجوع، وإلا رمز جديد بطلب POST صغير بلا إعادة تحميل الصفحة
async function readerTicket(root, signal) {
    const { pdfTicket: ticket, pdfKey: key } = root.dataset;
    delete root.dataset.pdfTicket;
    delete root.dataset.pdfKey;

    if (ticket && key) {
        return { ticket, key };
    }

    const response = await fetch(root.dataset.pdfTicketUrl, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        signal,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    });

    if (!response.ok) {
        throw new Error(`ticket ${response.status}`);
    }

    return response.json();
}

// الملف من الخادم مخلوطاً بالمفتاح (XOR دوري، ReaderFileResponse)، بطلب واحد لكل فتح. ورمز منتهٍ أو مستعمل (404) يُستبدل مرة واحدة
async function fetchPdf(root, signal, retry = true) {
    const { ticket, key } = await readerTicket(root, signal);
    const response = await fetch(`${root.dataset.pdfReader}?t=${encodeURIComponent(ticket)}`, {
        credentials: 'same-origin',
        cache: 'no-store',
        signal,
        headers: { 'X-Ejaz-Reader': '1' },
    });

    if (response.status === 404 && retry) {
        return fetchPdf(root, signal, false);
    }

    if (!response.ok) {
        throw new Error(`file ${response.status}`);
    }

    const bytes = new Uint8Array(await response.arrayBuffer());
    const secret = Uint8Array.from(atob(key), (char) => char.charCodeAt(0));

    // أربعة بايتات في كل خطوة (المفتاح 32 بايتاً، مضاعف لأربعة)، ثم ما بقي بايتاً بايتاً
    const words = Math.floor(bytes.length / 4);
    const view = new Uint32Array(bytes.buffer, 0, words);
    const secretWords = new Uint32Array(secret.buffer);

    for (let i = 0; i < words; i++) {
        view[i] ^= secretWords[i % secretWords.length];
    }

    for (let i = words * 4; i < bytes.length; i++) {
        bytes[i] ^= secret[i % secret.length];
    }

    return bytes;
}

function setupReader(root, signal) {
    const stage = root.querySelector('[data-pdf-stage]');
    const canvas = root.querySelector('[data-pdf-canvas]');
    const status = root.querySelector('[data-pdf-status]');
    const pageInput = root.querySelector('[data-pdf-page]');
    const total = root.querySelector('[data-pdf-total]');
    const zoomLabel = root.querySelector('[data-pdf-zoom]');
    const buttons = {
        prev: root.querySelector('[data-pdf-prev]'),
        next: root.querySelector('[data-pdf-next]'),
        zoomIn: root.querySelector('[data-pdf-zoom-in]'),
        zoomOut: root.querySelector('[data-pdf-zoom-out]'),
        zoomReset: root.querySelector('[data-pdf-zoom-reset]'),
    };
    const listen = (target, type, handler, options = {}) => target.addEventListener(type, handler, { ...options, signal });

    let doc = null;
    let task = null;
    let current = 1;
    let zoom = ZOOMS.indexOf(1);
    let renderTask = null;
    let generation = 0;
    // «قراءاتي»: كل صفحة رُسمت لمسجّل الدخول (library-progress.js)، والأولى تُبلَّغ فوراً
    const progress = progressReporter(root.dataset.progress, signal);
    let opened = false;

    // نسخة الصفحة المحفوظة لزر الرجوع (التنقّل السريع) تحمل حالة القارئ السابق: تُعاد إلى التحميل قبل أي تفاعل
    root.dataset.state = 'loading';
    delete root.dataset.pages;
    status.textContent = root.dataset.loading;
    pageInput.disabled = true;
    Object.values(buttons).forEach((button) => {
        button.disabled = true;
    });

    // لا قائمة سياقية (حفظ الصورة باسم) ولا سحب للصورة من القارئ
    listen(root, 'contextmenu', (event) => event.preventDefault());
    listen(root, 'dragstart', (event) => event.preventDefault());

    // العنوان في سمته، فلا يُستبدل ما في نصه من :page أو :total. والقيم بدالة، فلا يُفسَّر $& أو $' في العنوان نمطاً (المشرف على #101، ٣)
    const label = (page) => root.dataset.pageLabel
        .replace(':page', () => String(page))
        .replace(':total', () => String(doc.numPages))
        .replace(':title', () => root.dataset.title);

    const fail = () => {
        root.dataset.state = 'error';
        status.textContent = root.dataset.error;
    };

    async function render() {
        if (!doc) {
            return;
        }

        // رسم أحدث (نقر متتابع أو تغيّر العرض) يُلغي ما قبله بعد انتظار الصفحة، فلا يرسم اثنان على اللوحة معاً
        const run = ++generation;
        let page;

        try {
            page = await doc.getPage(current);
        } catch {
            if (!signal.aborted) {
                fail();
            }

            return;
        }

        if (signal.aborted || run !== generation) {
            return;
        }

        renderTask?.cancel();

        // ملاءمة عرض المساحة، ثم التكبير. والرسم بكثافة الشاشة (حتى الضعف) فيبقى النص حاداً، دون حد مساحة اللوحة
        const style = getComputedStyle(stage);
        const width = stage.clientWidth - parseFloat(style.paddingInlineStart) - parseFloat(style.paddingInlineEnd);
        const base = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: (Math.max(width, 100) / base.width) * ZOOMS[zoom] });
        let ratio = Math.min(window.devicePixelRatio || 1, 2);

        if (viewport.width * viewport.height * ratio * ratio > MAX_PIXELS) {
            ratio = Math.sqrt(MAX_PIXELS / (viewport.width * viewport.height));
        }

        canvas.width = Math.floor(viewport.width * ratio);
        canvas.height = Math.floor(viewport.height * ratio);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;

        renderTask = page.render({
            canvas,
            viewport,
            transform: ratio === 1 ? null : [ratio, 0, 0, ratio, 0, 0],
        });

        try {
            await renderTask.promise;
        } catch (error) {
            if (error?.name !== 'RenderingCancelledException') {
                fail();
            }

            return;
        }

        canvas.setAttribute('aria-label', label(current));
        status.textContent = label(current);
        root.dataset.state = 'ready';

        if (progress) {
            progress.page(current);

            if (!opened) {
                opened = true;
                progress.open();
            }
        }
    }

    const update = () => {
        pageInput.value = String(current);
        buttons.prev.disabled = current <= 1;
        buttons.next.disabled = current >= doc.numPages;
        buttons.zoomOut.disabled = zoom === 0;
        buttons.zoomIn.disabled = zoom === ZOOMS.length - 1;
        zoomLabel.textContent = `${Math.round(ZOOMS[zoom] * 100)}%`;
    };

    const go = (page) => {
        if (!doc) {
            return;
        }

        const target = Math.min(Math.max(1, Math.round(Number(page)) || current), doc.numPages);

        if (target !== current) {
            current = target;
            stage.scrollTo({ top: 0 });
            render();
        }

        update();
    };

    const setZoom = (index) => {
        if (!doc) {
            return;
        }

        zoom = Math.min(Math.max(0, index), ZOOMS.length - 1);
        update();
        render();
    };

    listen(buttons.prev, 'click', () => go(current - 1));
    listen(buttons.next, 'click', () => go(current + 1));
    listen(buttons.zoomIn, 'click', () => setZoom(zoom + 1));
    listen(buttons.zoomOut, 'click', () => setZoom(zoom - 1));
    listen(buttons.zoomReset, 'click', () => setZoom(ZOOMS.indexOf(1)));
    listen(pageInput, 'change', () => go(pageInput.value));
    listen(pageInput, 'keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            go(pageInput.value);
        }
    });

    // الموقع من اليمين: السهم الأيسر للتالية كمعرض الصور. والصفحة المكبّرة تتمرر بالسهمين، فلا تُقلَّب
    listen(stage, 'keydown', (event) => {
        if (stage.scrollWidth > stage.clientWidth) {
            return;
        }

        if (event.key === 'ArrowLeft') {
            go(current + 1);
        } else if (event.key === 'ArrowRight') {
            go(current - 1);
        }
    });

    // السحب الأفقي على الجوال يقلّب الصفحة ما لم تكن مكبّرة تتمرر أفقياً. السحب إلى اليمين للتالية كتقليب الكتاب العربي
    let touch = null;
    listen(stage, 'touchstart', (event) => {
        touch = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
    }, { passive: true });
    listen(stage, 'touchend', (event) => {
        if (!touch || !doc || stage.scrollWidth > stage.clientWidth) {
            return;
        }

        const dx = event.changedTouches[0].clientX - touch.x;
        const dy = event.changedTouches[0].clientY - touch.y;
        touch = null;

        if (Math.abs(dx) > SWIPE && Math.abs(dx) > Math.abs(dy) * 1.5) {
            go(dx > 0 ? current + 1 : current - 1);
        }
    }, { passive: true });

    // تغيّر عرض المساحة (تدوير الجوال، وتغيير حجم النافذة) يعيد الرسم بملاءمته
    let lastWidth = stage.clientWidth;
    let resizing = null;
    const observer = new ResizeObserver(() => {
        if (stage.clientWidth === lastWidth) {
            return;
        }

        lastWidth = stage.clientWidth;
        clearTimeout(resizing);
        resizing = setTimeout(render, 150);
    });
    observer.observe(stage);

    signal.addEventListener('abort', () => {
        observer.disconnect();
        clearTimeout(resizing);
        renderTask?.cancel();
        task?.destroy();
        doc = null;
    });

    // الملف المحمي (مسار رمز في الصفحة) بـ fetchPdf، والكتاب القابل للتنزيل في برنامج «قراءة كتاب» من مساره كما كان
    const source = root.dataset.pdfTicketUrl
        ? fetchPdf(root, signal).then((data) => ({ data }))
        : { url: root.dataset.pdfReader, disableRange: true, disableAutoFetch: true };

    Promise.all([loadPdfjs(), source])
        .then(([module, file]) => {
            if (signal.aborted) {
                return null;
            }

            // الملف كاملاً بطلب واحد (المسار بلا أجزاء ومحدود الطلبات)، وبلا WebAssembly ولا نماذج XFA، فلا تتسع السياسة.
            // والخطوط المضمّنة تُرسم أشكالاً من بيانات الملف (disableFontFace): تحميلها خطوطاً في المتصفح سقط في التجربة
            // إلى خط النظام فتفككت الحروف العربية، والقارئ يرسم في <canvas> وحده فلا يحتاج الخط في الصفحة
            task = module.getDocument({ ...file, useWasm: false, enableXfa: false, disableFontFace: true, isEvalSupported: false });

            return task.promise;
        })
        .then((loaded) => {
            if (!loaded || signal.aborted) {
                return;
            }

            doc = loaded;
            total.textContent = String(doc.numPages);
            root.dataset.pages = String(doc.numPages);
            pageInput.max = String(doc.numPages);
            // رابط «أكمل» من «قراءاتي» يبدأ من الصفحة المحفوظة
            current = Math.min(resumePoint() ?? 1, doc.numPages);
            Object.values(buttons).forEach((button) => {
                button.disabled = false;
            });
            pageInput.disabled = false;
            update();
            render();
        })
        .catch(() => {
            if (!signal.aborted) {
                fail();
            }
        });
}

onPage((signal) => document.querySelectorAll('[data-pdf-reader]').forEach((root) => setupReader(root, signal)));
