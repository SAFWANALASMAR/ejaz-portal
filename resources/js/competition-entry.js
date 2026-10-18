// نموذج المشاركة في المسابقة: الحفظ التلقائي للمسودة، وتأكيد الإرسال، ورفع المقاطع مجزأة قابلة
// للاستئناف (routes/competitions.php). وكل فحص هنا للراحة فقط، فالخادم يعيده كله.
// ويُهيّأ مع كل صفحة (page.js)، ومغادرتها توقف الحفظ المؤجل والرفع والاستعلام عن الحالة كما كان التحميل الكامل
// يوقفها، والرفع يُستأنف من حيث انقطع عند العودة.

import { onPage } from './page';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// الرفع الجاري: مغادرة الصفحة تقطعه، فيُسأل المستخدم قبلها في التنقّل السريع وفي التحميل الكامل (beforeunload).
// والحارس يزول باكتمال الرفع أو توقفه
const uploads = new Map();

// والمتصفح يعرض رسالته هو، والقيمة غير الفارغة يحتاجها كروم أندرويد القديم ليعرضها
function holdPage(event) {
    const [upload] = uploads.values();

    event.preventDefault();
    event.returnValue = upload?.message ?? 'upload';
}

function guardUpload(controller, message) {
    if (uploads.size === 0) {
        window.addEventListener('beforeunload', holdPage);
    }

    uploads.set(controller, { message, url: window.location.href, state: window.history.state });
}

function releaseUpload(controller) {
    uploads.delete(controller);

    if (uploads.size === 0) {
        window.removeEventListener('beforeunload', holdPage);
    }
}

// المغادرة المؤكدة توقف الرفع كله (يُستأنف بإعادة اختيار الملف)، فلا يُسأل ثانية في تحميل كامل بعدها
function mayLeave(upload) {
    if (!window.confirm(upload.message)) {
        return false;
    }

    [...uploads.keys()].forEach((controller) => {
        controller.abort();
        releaseUpload(controller);
    });

    return true;
}

const samePage = (url, upload) => {
    const page = new URL(upload.url);

    return url.pathname + url.search === page.pathname + page.search;
};

// الروابط والتنقّل من الشيفرة. ورابط # في الصفحة نفسها لا يبدّلها، أما الرابط إليها بلا # فيجلبها من جديد. ويسبق
// مستمع page.js لأن هذه الوحدة تُستورد قبل start() في app.js، فالتنقّل الممنوع لا يبدأ شريط التقدّم
document.addEventListener('livewire:navigate', (event) => {
    const [upload] = uploads.values();
    const { url, history } = event.detail ?? {};

    if (!upload) {
        return;
    }

    // زرا الرجوع والتقدّم مرّا بمستمع popstate أدناه، فما يصل منهما هنا إلى الصفحة نفسها. وقد يجلبها Livewire من جديد
    // أو يضع نسخة قديمة منها (مدخل من مستند سابق بعد تحديث الصفحة)، فيُمنع ويبقى الرفع
    if (history) {
        if (url && samePage(url, upload)) {
            event.preventDefault();
        }

        return;
    }

    if (url?.hash && samePage(url, upload)) {
        return;
    }

    if (!mayLeave(upload)) {
        event.preventDefault();
    }
});

// زرا الرجوع والتقدّم يغيّران العنوان قبل أن يعلم بهما Livewire: يُسأل المستخدم قبله (مستمع capture على window
// يسبق مستمعه)، والبقاء يعيد عنوان الصفحة ويوقف الحدث عنه، فتبقى حالته ونسخه المحفوظة على الصفحة الباقية. وبعد زر
// التقدّم تبقى الصفحة المرفوضة قبلها في السجل (docs/decisions.md)
window.addEventListener('popstate', (event) => {
    const [upload] = uploads.values();

    if (!upload || samePage(window.location, upload) || mayLeave(upload)) {
        return;
    }

    event.stopImmediatePropagation();
    window.history.pushState(upload.state, '', upload.url);
}, { capture: true });

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...(options.headers ?? {}) },
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        const first = body.errors ? Object.values(body.errors)[0]?.[0] : null;
        throw new Error(first ?? body.message ?? String(response.status));
    }

    return response.status === 204 ? null : response.json();
}

// الجزء إلى مسار التطبيق برمز الحماية، أو مباشرة إلى التخزين السحابي برابطه الموقّع وحده، فلا
// يُرسل رمز الحماية ولا الكعكات إلى نطاق آخر
async function putChunk(url, chunk, signal) {
    if (new URL(url, location.href).origin === location.origin) {
        return request(url, { method: 'PUT', body: chunk, headers: { 'Content-Type': 'application/octet-stream' }, signal });
    }

    const response = await fetch(url, { method: 'PUT', body: chunk, credentials: 'omit', signal });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return null;
}

// الحفظ التلقائي بعد توقف قصير عن الكتابة
function autosave(form, signal) {
    const status = form.querySelector('[data-autosave-status]');
    let timer = null;
    let submitted = false;
    let inflight = null;

    const save = async () => {
        if (submitted) {
            return;
        }

        status.textContent = status.dataset.saving;
        const data = new FormData(form);
        inflight = new AbortController();

        try {
            await request(form.dataset.autosave, { method: 'POST', body: data, signal: inflight.signal });
            status.textContent = status.dataset.saved;
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            // رسالة الخادم إن كانت (الإغلاق مثلاً)، وإلا الرسالة العامة
            status.textContent = /^\d+$/.test(error.message) ? status.dataset.failed : error.message;
        }
    };

    const schedule = (event) => {
        if (event.target.matches('[data-video-input]')) {
            return;
        }

        clearTimeout(timer);
        timer = setTimeout(save, 2500);
    };

    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);

    // الحفظ أو الإرسال يحمل الحقول نفسها: لا حفظ تلقائي بعده، وإلا وصل إلى مشاركة أُرسلت فرُفض (403)
    // والطلب الجاري يُلغى، فلا يصل بعد الحفظ الصريح بحقول أقدم
    form.addEventListener('submit', () => {
        submitted = true;
        clearTimeout(timer);
        inflight?.abort();
    });

    // الرجوع إلى الصفحة من ذاكرة المتصفح يعيد الحفظ التلقائي (والخادم يرفض حفظ مشاركة أُرسلت)
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            submitted = false;
        }
    }, { signal });

    signal.addEventListener('abort', () => clearTimeout(timer));
}

// مدة المقطع من بياناته في المتصفح، قبل الرفع
function readDuration(file) {
    return new Promise((resolve) => {
        const video = document.createElement('video');
        const url = URL.createObjectURL(file);
        video.preload = 'metadata';
        video.onloadedmetadata = () => {
            URL.revokeObjectURL(url);
            resolve(Number.isFinite(video.duration) ? video.duration : null);
        };
        video.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };
        video.src = url;
    });
}

function storageKey(box) {
    return 'competition-video:' + box.dataset.videoUpload;
}

function remember(box, value) {
    try {
        value ? sessionStorage.setItem(storageKey(box), JSON.stringify(value)) : sessionStorage.removeItem(storageKey(box));
    } catch {
        // التخزين غير متاح: يبدأ الرفع من أوله عند الانقطاع
    }
}

function recall(box) {
    try {
        return JSON.parse(sessionStorage.getItem(storageKey(box)) ?? 'null');
    } catch {
        return null;
    }
}

function videoUpload(box, signal) {
    const input = box.querySelector('[data-video-input]');
    const progress = box.querySelector('[data-video-progress]');
    const status = box.querySelector('[data-video-status]');
    const messages = JSON.parse(box.dataset.messages);
    const maxBytes = Number(box.dataset.maxBytes);
    const maxSeconds = Number(box.dataset.maxSeconds);

    if (!input) {
        return;
    }

    const url = (template, video) => template.replace('__VIDEO__', encodeURIComponent(video));
    let polling = null;

    signal.addEventListener('abort', () => clearTimeout(polling));

    const upload = async (file, video, chunkSize, urls, total, transfer) => {
        const indexes = Object.keys(urls).map(Number);
        let done = total - indexes.length;
        progress.hidden = false;

        for (const index of indexes) {
            const chunk = file.slice(index * chunkSize, Math.min(file.size, (index + 1) * chunkSize));
            await putChunk(urls[index], chunk, transfer);
            done++;
            progress.value = Math.round((done / total) * 100);
            status.textContent = messages.progress.replace(':percent', progress.value);
        }

        const result = await request(url(box.dataset.completeUrl, video), { method: 'POST', signal: transfer });
        status.textContent = result.label;
        remember(box, null);
        poll(video);
    };

    const poll = async (video) => {
        const result = await request(url(box.dataset.statusUrl, video), { signal }).catch(() => null);

        if (!result) {
            return;
        }

        status.textContent = result.reason ? `${result.label} — ${result.reason}` : result.label;

        if (result.status === 'processing') {
            polling = setTimeout(() => poll(video), 3000);
        }
    };

    input.addEventListener('change', async () => {
        const file = input.files[0];

        if (!file) {
            return;
        }

        if (file.type !== 'video/mp4') {
            status.textContent = messages.wrong_type;
            return;
        }

        if (file.size > maxBytes) {
            status.textContent = messages.too_large;
            return;
        }

        const duration = await readDuration(file);

        if (duration !== null && duration > maxSeconds) {
            status.textContent = messages.too_long;
            return;
        }

        // غادر المستخدم الصفحة أثناء قراءة المقطع: لا رفع من صفحة لم تعد ظاهرة
        if (signal.aborted) {
            return;
        }

        input.disabled = true;

        // يتوقف بمغادرة الصفحة، أو بتأكيد المغادرة أثناءه
        const controller = new AbortController();
        const transfer = controller.signal;
        const stop = () => controller.abort();
        signal.addEventListener('abort', stop, { once: true });
        guardUpload(controller, messages.leave);

        try {
            const previous = recall(box);

            // الملف نفسه بعد انقطاع: يكمل الأجزاء الناقصة فقط
            if (previous && previous.name === file.name && previous.size === file.size && previous.modified === file.lastModified) {
                const state = await request(url(box.dataset.statusUrl, previous.video), { signal: transfer }).catch(() => null);

                if (state && state.status === 'uploading') {
                    const total = Math.ceil(file.size / state.chunk_size);
                    await upload(file, previous.video, state.chunk_size, state.urls, total, transfer);
                    return;
                }
            }

            const start = await request(box.dataset.videoUpload, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ size: file.size, type: file.type, duration }),
                signal: transfer,
            });

            remember(box, { video: start.video, name: file.name, size: file.size, modified: file.lastModified });
            await upload(file, start.video, start.chunk_size, start.urls, Object.keys(start.urls).length, transfer);
        } catch (error) {
            // مغادرة الصفحة أثناء الرفع: يُستأنف عند العودة
            if (error.name === 'AbortError') {
                return;
            }

            status.textContent = `${messages.retry} (${error.message})`;
        } finally {
            signal.removeEventListener('abort', stop);
            releaseUpload(controller);
            input.disabled = false;
        }
    });
}

onPage((signal) => {
    document.querySelectorAll('form[data-autosave]').forEach((form) => autosave(form, signal));
    document.querySelectorAll('[data-video-upload]').forEach((box) => videoUpload(box, signal));
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-confirm]');

    if (button && !window.confirm(button.dataset.confirm)) {
        event.preventDefault();
    }
});
