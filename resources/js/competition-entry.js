// نموذج المشاركة في المسابقة: الحفظ التلقائي للمسودة، وتأكيد الإرسال، ورفع المقاطع مجزأة قابلة
// للاستئناف (routes/competitions.php). وكل فحص هنا للراحة فقط، فالخادم يعيده كله.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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
async function putChunk(url, chunk) {
    if (new URL(url, location.href).origin === location.origin) {
        return request(url, { method: 'PUT', body: chunk, headers: { 'Content-Type': 'application/octet-stream' } });
    }

    const response = await fetch(url, { method: 'PUT', body: chunk, credentials: 'omit' });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return null;
}

// الحفظ التلقائي بعد توقف قصير عن الكتابة
function autosave(form) {
    const status = form.querySelector('[data-autosave-status]');
    let timer = null;

    const save = async () => {
        status.textContent = status.dataset.saving;
        const data = new FormData(form);

        try {
            await request(form.dataset.autosave, { method: 'POST', body: data });
            status.textContent = status.dataset.saved;
        } catch (error) {
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

function videoUpload(box) {
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

    const upload = async (file, video, chunkSize, urls, total) => {
        const indexes = Object.keys(urls).map(Number);
        let done = total - indexes.length;
        progress.hidden = false;

        for (const index of indexes) {
            const chunk = file.slice(index * chunkSize, Math.min(file.size, (index + 1) * chunkSize));
            await putChunk(urls[index], chunk);
            done++;
            progress.value = Math.round((done / total) * 100);
            status.textContent = messages.progress.replace(':percent', progress.value);
        }

        const result = await request(url(box.dataset.completeUrl, video), { method: 'POST' });
        status.textContent = result.label;
        remember(box, null);
        poll(video);
    };

    const poll = async (video) => {
        const result = await request(url(box.dataset.statusUrl, video)).catch(() => null);

        if (!result) {
            return;
        }

        status.textContent = result.reason ? `${result.label} — ${result.reason}` : result.label;

        if (result.status === 'processing') {
            setTimeout(() => poll(video), 3000);
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

        input.disabled = true;

        try {
            const previous = recall(box);

            // الملف نفسه بعد انقطاع: يكمل الأجزاء الناقصة فقط
            if (previous && previous.name === file.name && previous.size === file.size && previous.modified === file.lastModified) {
                const state = await request(url(box.dataset.statusUrl, previous.video)).catch(() => null);

                if (state && state.status === 'uploading') {
                    const total = Math.ceil(file.size / state.chunk_size);
                    await upload(file, previous.video, state.chunk_size, state.urls, total);
                    return;
                }
            }

            const start = await request(box.dataset.videoUpload, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ size: file.size, type: file.type, duration }),
            });

            remember(box, { video: start.video, name: file.name, size: file.size, modified: file.lastModified });
            await upload(file, start.video, start.chunk_size, start.urls, Object.keys(start.urls).length);
        } catch (error) {
            status.textContent = `${messages.retry} (${error.message})`;
        } finally {
            input.disabled = false;
        }
    });
}

document.querySelectorAll('form[data-autosave]').forEach(autosave);
document.querySelectorAll('[data-video-upload]').forEach(videoUpload);

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-confirm]');

    if (button && !window.confirm(button.dataset.confirm)) {
        event.preventDefault();
    }
});
