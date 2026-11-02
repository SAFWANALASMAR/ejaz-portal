// «قراءاتي» (docs/decisions/2026-11-01-my-reading.md): بلاغات تقدّم صاحب الحساب في المكتبة إلى الخادم. لمسجّل الدخول وحده ما لم يختر «لا تحفظ قراءاتي»
// (meta[name=signed-in] في القالب العام، فصفحة العنصر المخزنة نصاً واحدة للجميع). تُجمع ثم تُرسل كل عشرين ثانية على الأكثر،
// وعند مغادرة الصفحة أو إخفائها (keepalive)، بحد الخادم ورمز CSRF. وما يرفضه الخادم (حد أو تحقق) يُترك ولا يُعاد.
// - الكتاب في قارئ الصفحة (pdf-reader.js): كل صفحة رُسمت.
// - المقطع والصوت في مشغّل الصفحة: ثواني التشغيل الفعلي بالساعة، والموضع، والمدة.
// - مقطع يوتيوب والكتاب في عارض المتصفح: علامة «فتحته» وحدها.
// ورابط «أكمل» من «قراءاتي» يحمل ?resume= بالصفحة أو الثانية، فيبدأ القارئ أو المشغّل منها.

import { onPage } from './page';

const FLUSH_MS = 20_000;
// حدود البلاغ الواحد في الخادم (RecordLibraryProgress)
const MAX_PAGES = 50;
const MAX_SECONDS = 120;
// فجوة أطول من هذا بين تحديثين للمشغّل (تعليق التحميل، أو تبويب نائم) لا تُحسب استماعاً
const MAX_TICK = 2;

// لمسجّل الدخول الذي لم يختر «لا تحفظ قراءاتي» (meta[name=reading-tracking] في الرأس غير المخزن لكل طلب)
const tracking = () => document.querySelector('meta[name="signed-in"]') !== null && document.querySelector('meta[name="reading-tracking"][content="off"]') === null;

function post(url, body, keepalive = false) {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(body),
    }).catch(() => null);
}

// الموضع المحفوظ من رابط «أكمل»: عدد صحيح موجب، وإلا لا شيء
export function resumePoint() {
    const value = Number(new URLSearchParams(window.location.search).get('resume'));

    return Number.isInteger(value) && value > 0 ? value : null;
}

// مُبلّغ لعنصر واحد في الصفحة، أو null لزائر. ويتوقف بإشارة الصفحة (التنقّل السريع) بعد بلاغ أخير
export function progressReporter(url, signal) {
    if (!url || !tracking()) {
        return null;
    }

    const pages = new Set();
    let page = null;
    let seconds = 0;
    let position = null;
    let duration = null;
    let dirty = false;
    let sending = false;

    // البلاغ الأخير عند المغادرة (keepalive) لا ينتظر سابقه، والخادم يعالج بلاغات الحساب واحداً بعد واحد
    const send = (keepalive = false) => {
        if (!dirty || (sending && !keepalive)) {
            return;
        }

        const body = {};
        const batch = [...pages].slice(0, MAX_PAGES);
        const whole = Math.min(Math.floor(seconds), MAX_SECONDS);

        if (batch.length > 0) {
            body.pages = batch;
        }

        if (page !== null) {
            body.page = page;
        }

        if (whole > 0) {
            body.seconds = whole;
        }

        if (position !== null) {
            body.position = position;
        }

        if (duration !== null) {
            body.duration = duration;
        }

        batch.forEach((number) => pages.delete(number));
        seconds -= whole;
        dirty = pages.size > 0 || seconds >= 1;
        sending = true;
        post(url, body, keepalive)
            .then(async (response) => {
                if (!response?.ok || batch.length === 0) {
                    return;
                }

                // الخادم يقبل صفحة لكل ثانيتين: ما لم يُقبل يعود إلى البلاغ التالي، فلا يضيع على من يقلّب بسرعة
                const accepted = new Set((await response.json().catch(() => ({}))).pages ?? []);
                const left = batch.filter((number) => !accepted.has(number));

                if (left.length > 0 && !signal.aborted) {
                    left.forEach((number) => pages.add(number));
                    dirty = true;
                }
            })
            .finally(() => {
                sending = false;
            });
    };

    const timer = setInterval(send, FLUSH_MS);
    const hidden = () => {
        if (document.visibilityState === 'hidden') {
            send(true);
        }
    };

    document.addEventListener('visibilitychange', hidden, { signal });
    window.addEventListener('pagehide', () => send(true), { signal });
    signal.addEventListener('abort', () => {
        clearInterval(timer);
        send(true);
    });

    return {
        // البلاغ الأول فوراً: ينشئ الصف ويبدأ ساعة الخادم، فلا يُرفض ما بعده لأنه أطول من الوقت المنقضي
        open() {
            dirty = true;
            send();
        },
        page(number) {
            pages.add(number);
            page = number;
            dirty = true;
        },
        media(delta, current, total) {
            seconds += delta;

            if (Number.isFinite(total) && total >= 1) {
                duration = Math.floor(total);
            }

            if (Number.isFinite(current) && current >= 0) {
                position = Math.floor(duration === null ? current : Math.min(current, duration));
            }

            dirty = true;
        },
    };
}

function setupMedia(element, signal) {
    const reporter = progressReporter(element.dataset.progress, signal);

    if (!reporter) {
        return;
    }

    const listen = (type, handler) => element.addEventListener(type, handler, { signal });
    const resume = resumePoint();
    let started = false;
    let last = null;

    const tick = () => {
        if (last === null) {
            return;
        }

        const now = performance.now();
        const delta = Math.min((now - last) / 1000, MAX_TICK);
        last = now;
        reporter.media(Math.max(0, delta), element.currentTime, element.duration);
    };

    listen('loadedmetadata', () => {
        if (resume !== null && resume < element.duration) {
            element.currentTime = resume;
        }
    });
    listen('play', () => {
        if (!started) {
            started = true;
            reporter.open();
        }

        last = performance.now();
    });
    listen('timeupdate', () => {
        if (!element.paused) {
            tick();
        }
    });
    ['pause', 'ended'].forEach((type) => listen(type, () => {
        tick();
        last = null;
    }));
    // والقفز قبل أول تشغيل (ومنه القفز إلى ?resume=) ليس نشاطاً يُبلَّغ
    listen('seeked', () => {
        if (started) {
            reporter.media(0, element.currentTime, element.duration);
        }
    });
}

onPage((signal) => {
    document.querySelectorAll('[data-library-media][data-progress]').forEach((element) => setupMedia(element, signal));

    // الكتاب في عارض المتصفح: لا يُعرف ما قُرئ منه، فعلامة «فتحته» متى ظهر العارض. وعلى الجوال لا عارض (مخفي)، فلا علامة
    if (tracking()) {
        document.querySelectorAll('[data-progress-open]').forEach((element) => {
            if (element.offsetParent !== null) {
                post(element.dataset.progressOpen, {});
            }
        });
    }
});

// مقطع يوتيوب: علامة «فتحته» عند ضغطه (قبل أن يحل المشغّل محل الرابط — app.js)، فلا تُقرأ مدته بلا سكربت يوتيوب
document.addEventListener('click', (event) => {
    const video = event.target.closest('[data-video-embed][data-progress]');

    if (video && tracking()) {
        post(video.dataset.progress, {});
    }
}, { capture: true });
