// مسابقة الطلاب (docs/plans/student-competition.md): عداد الاختبار، وصفوف الفئة المختارة في التسجيل.
// للراحة وحدها: الخادم يرفض ما بعد الموعد ويسلّم المحاولة بنفسه (SubmitAttempt وejaz:close-expired-attempts)،
// ويفحص الصف بصفوف الفئة (RegisterStudent).

import { onPage, rerun } from './page';

const format = (seconds) => {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
};

// الوقت الباقي من الخادم (لا من ساعة الجهاز) يُعدّ تنازلياً، ويُعلَن في الدقائق الخمس الأخيرة. وعند انتهائه يُرسل
// نموذج الصفحة بالتسليم الآلي، فتُحفظ إجابات الوحدة الحالية ثم تُسلَّم المحاولة. والإرسال بعد تأخير عشوائي حتى
// data-exam-auto-spread ثانية، داخل هامش الشبكة: من بدؤوا في آخر النافذة يشتركون في موعد واحد، فلا يصل
// تسليمهم كله في الثانية نفسها (مراجعة 26 أكتوبر، ن٢). والصفحة المفتوحة بعد انتهاء الوقت ترسل فوراً، فلا يتراكم
// التأخير، والإرسال باليد يلغي المؤجل
function timer(element, signal) {
    const total = Number(element.dataset.examTimer);
    const spread = total > 0 ? Number(element.dataset.examAutoSpread || 0) : 0;
    const output = element.querySelector('[data-exam-timer-value]');
    const alert = element.querySelector('[data-exam-timer-alert]');
    const form = document.querySelector('form[data-exam-autosubmit]');
    const started = performance.now();
    let warned = false;
    let sent = false;
    let delay;

    const tick = () => {
        const left = Math.max(0, Math.round(total - (performance.now() - started) / 1000));

        if (output) {
            output.textContent = format(left);
        }

        if (left <= 300 && !warned) {
            warned = true;
            element.dataset.warning = 'true';

            if (alert) {
                alert.textContent = alert.dataset.message ?? '';
            }
        }

        if (left === 0 && !sent && form) {
            sent = true;
            delay = window.setTimeout(() => {
                const go = form.querySelector('[data-exam-auto-go]');

                if (go) {
                    go.disabled = false;
                    go.value = 'submit';
                }

                form.querySelector('input[name="auto"]')?.setAttribute('value', '1');
                form.submit();
            }, Math.random() * spread * 1000);
        }
    };

    tick();
    const interval = window.setInterval(tick, 1000);
    // ضغطة منعها حارس الضغطة المزدوجة لا تلغي التسليم الآلي المؤجل (مراجعة #211). والحارس مسجّل قبل العداد
    form?.addEventListener('submit', (event) => {
        if (!event.defaultPrevented) {
            window.clearTimeout(delay);
        }
    }, { signal });
    signal.addEventListener('abort', () => {
        window.clearInterval(interval);
        window.clearTimeout(delay);
    });
}

// قائمة الصف تُظهر صفوف الفئة المختارة وحدها، وتُخفى إن لم تكن لها صفوف
function grades(form, signal) {
    const category = form.querySelector('select[name="category_id"]');
    const grade = form.querySelector('select[name="grade"]');

    if (!category || !grade) {
        return;
    }

    const wrapper = grade.closest('[data-grade-field]') ?? grade.parentElement;

    const update = () => {
        let any = false;

        grade.querySelectorAll('optgroup').forEach((group) => {
            const match = group.dataset.category === category.value;
            group.hidden = !match;
            group.disabled = !match;
            any ||= match;
        });

        if (grade.selectedOptions[0]?.parentElement?.disabled) {
            grade.value = '';
        }

        grade.required = any;
        wrapper.hidden = !any;
    };

    category.addEventListener('change', update, { signal });
    update();
}

// وضع التركيز (decisions/2026-11-06-exam-focus-mode.md): كل زر في صفحة السؤال يُرسل النموذج كاملاً، فيظهر فوراً أن الضغطة وصلت
// (الزر المضغوط بدائرة تحميل والبقية باهتة، وإعلان لقارئ الشاشة)، ولا ترسل ضغطة ثانية طلباً ثانياً. ولا يُعطَّل الزر نفسه: قيمته
// (go) تُرسل معه. وإن لم يرد الخادم في 45 ثانية (لا 15: في ذروة الفتح تضاعف الضغطة الثانية الطلبات — مراجعة #211) يعود النموذج كما كان فتُعاد المحاولة. والإرسال الآلي (form.submit) لا يمر هنا
function pending(form, signal) {
    const saving = form.querySelector('[data-exam-saving]');
    let release;

    const reset = () => {
        form.removeAttribute('data-exam-pending');
        form.querySelectorAll('[data-exam-pressed]').forEach((button) => {
            button.removeAttribute('data-exam-pressed');
            button.removeAttribute('aria-busy');
        });

        if (saving) {
            saving.textContent = '';
        }
    };

    form.addEventListener(
        'submit',
        (event) => {
            if (form.hasAttribute('data-exam-pending')) {
                event.preventDefault();

                return;
            }

            form.setAttribute('data-exam-pending', '');
            event.submitter?.setAttribute('data-exam-pressed', '');
            event.submitter?.setAttribute('aria-busy', 'true');

            if (saving) {
                saving.textContent = saving.dataset.message ?? '';
            }

            release = window.setTimeout(reset, 45000);
        },
        { signal },
    );
    // تعذّر الحفظ في التنقّل داخل الصفحة (navigate): يعود النموذج كما كان فتُعاد المحاولة باليد
    form.addEventListener(
        'exam:release',
        () => {
            window.clearTimeout(release);
            reset();
        },
        { signal },
    );
    signal.addEventListener('abort', () => window.clearTimeout(release));
}

// ملء الشاشة (ملاحظات المدير التنفيذي، 7 نوفمبر 2026 — decisions/2026-11-07-exam-fullscreen.md): زر في شريط الاختبار يظهر حيث يدعمه
// المتصفح وحده (لا الآيفون)، ويتبدل اسمه وأيقونته «تصغير الشاشة» داخلها، ومع Esc أيضاً (fullscreenchange). وتبقى الشاشة ممتلئة
// بين الأسئلة لأن التنقّل فيها داخل الصفحة نفسها (navigate أدناه): المتصفح يخرج منها عند كل تحميل صفحة
function fullscreen(button, signal) {
    if (!document.fullscreenEnabled) {
        return;
    }

    const label = button.querySelector('[data-exam-fullscreen-label]');
    const sync = () => {
        const on = document.fullscreenElement !== null;

        button.dataset.state = on ? 'on' : 'off';

        if (label) {
            label.textContent = on ? button.dataset.exit : button.dataset.enter;
        }
    };

    button.hidden = false;
    sync();
    button.addEventListener(
        'click',
        () => {
            const request = document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen({ navigationUI: 'hide' });

            request.catch(() => {});
        },
        { signal },
    );
    document.addEventListener('fullscreenchange', sync, { signal });
}

// التنقّل بين صفحات الاختبار داخل الصفحة نفسها (decisions/2026-11-07-exam-fullscreen.md): النموذج يُرسل كما هو (الحفظ في الخادم
// وتحويله، SaveAttemptAnswers) لكن بطلب من السكربت، ثم توضع منطقة الاختبار من الصفحة التي حوّل إليها مكان الحالية، فلا يخرج
// المتصفح من ملء الشاشة. وما ليس صفحة اختبار (بعد التسليم أو انتهاء الوقت) يُفتح تحميلاً كاملاً. وأي تعذر (انقطاع، أو انتهاء
// الجلسة، أو خطأ في الخادم) يعود إلى الإرسال العادي بالزر نفسه، فلا تضيع إجابة. والعنوان يُستبدل في السجل لا يُضاف: زر الرجوع
// يخرج من الاختبار كما قبله، ولا يعيد صفحة قديمة بإجاباتها
// طلب التنقّل الجاري: طلب جديد يلغيه، فلا يحل رد أقدم مكان صفحة أحدث (مراجعة الشيفرة على طلب الدمج 213)
let inflight = null;

async function swapTo(request, fallback, failed) {
    inflight?.abort();
    const mine = new AbortController();
    inflight = mine;

    let response;
    let html;

    try {
        response = await request(mine.signal);
        html = response.ok ? await response.text() : '';
    } catch {
        // أُلغي لطلب أحدث: لا شيء (والطلب الأحدث يضع منطقة الاختبار من جديد بحارسها مفكوكاً). وانقطاع الشبكة: الإرسال العادي، فيرى الطالب صفحة المتصفح لانقطاعه كما قبل
        if (!mine.signal.aborted) {
            fallback();
        }

        return;
    }

    if (inflight !== mine) {
        return;
    }

    inflight = null;

    // الخروج من الاختبار (التسليم، أو انتهاء الوقت): عنوان صفحة المرحلة بلا تحويل، فتبقى رسالتها لتحميلها الكامل. ومن الأصل نفسه وحده
    const leave = response.headers.get('X-Exam-Location');

    if (response.status === 204 && leave) {
        const target = new URL(leave, window.location.href);

        // والحفظ تمّ: عنوان من أصل آخر (لا يرسله الخادم) يُستبدل بإعادة تحميل الصفحة الحالية، لا برسالة «تعذّر الحفظ»
        if (target.origin === window.location.origin) {
            window.location.assign(target.href);
        } else {
            window.location.reload();
        }

        return;
    }

    // انتهت الجلسة أو تغيّر رمز الحماية: الصفحة من جديد تحميلاً كاملاً، بلا إرسال ثانٍ بالرمز نفسه
    if (response.status === 419) {
        window.location.reload();

        return;
    }

    // ازدحام أو خطأ في الخادم (429 و5xx وغيرها): لا إعادة إرسال تلقائية تضاعف الحمل في الذروة (مراجعة الأمن على طلب الدمج 213)،
    // والإجابات باقية في الصفحة برسالة، ويعود الزر فيُعاد الحفظ باليد
    if (!response.ok) {
        failed();

        return;
    }

    const url = new URL(response.url, window.location.href);
    const page = new DOMParser().parseFromString(html, 'text/html');
    const next = page.querySelector('[data-exam-focus]');
    const current = document.querySelector('[data-exam-focus]');

    if (url.origin !== window.location.origin || !next || !current) {
        window.location.assign(url.href);

        return;
    }

    current.replaceWith(document.adoptNode(next));
    document.title = page.title;
    window.history.replaceState(window.history.state, '', url.href);
    window.scrollTo(0, 0);
    rerun();

    // التركيز إلى رقم الصفحة المقروء (أو عنوان المراجعة)، فيقرأ قارئ الشاشة الصفحة الجديدة من أولها
    const heading = document.querySelector('#exam-unit-label, [data-exam-focus] .exam-body h2');

    if (heading) {
        heading.tabIndex = -1;
        heading.focus({ preventScroll: true });
    }
}

function navigate(signal) {
    const focus = document.querySelector('[data-exam-focus]');

    if (!focus || !window.fetch || !window.DOMParser) {
        return;
    }

    focus.querySelectorAll('form[data-exam-form]').forEach((form) => {
        form.addEventListener(
            'submit',
            (event) => {
                // ضغطة ثانية منعها pending، أو إرسال النموذج من الشيفرة: لا شيء هنا. ومتصفح لا يعرف الزر المضغوط (سفاري قبل 15.4)
                // يرسل عادياً بقيمته، فلا يضيع «go» (مراجعة الأمن على طلب الدمج 213، م١)
                if (event.defaultPrevented || !event.submitter) {
                    return;
                }

                event.preventDefault();

                const data = new FormData(form);
                const submitter = event.submitter;

                if (submitter?.name) {
                    data.set(submitter.name, submitter.value);
                }

                // الإرسال العادي بقيمة الزر نفسها: حقل go المخفي للتسليم الآلي (معطّل حتى يُضبط)
                const fallback = () => {
                    const go = form.querySelector('[data-exam-auto-go]');

                    if (go && submitter?.name === 'go') {
                        go.value = submitter.value;
                        go.disabled = false;
                    }

                    form.submit();
                };

                const failedNote = form.querySelector('[data-exam-failed]');

                if (failedNote) {
                    failedNote.hidden = true;
                }

                swapTo(
                    (abort) => fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', signal: abort, headers: { Accept: 'text/html', 'X-Exam-Inline': '1' } }),
                    fallback,
                    () => {
                        form.dispatchEvent(new Event('exam:release'));

                        if (failedNote) {
                            failedNote.textContent = failedNote.dataset.message ?? '';
                            failedNote.hidden = false;
                        }
                    },
                );
            },
            { signal },
        );
    });

    focus.querySelectorAll('a[data-exam-link]').forEach((link) => {
        link.addEventListener(
            'click',
            (event) => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                event.preventDefault();

                // نقرة ثانية قبل الرد لا تطلب مرة أخرى
                if (focus.hasAttribute('data-exam-pending')) {
                    return;
                }

                focus.setAttribute('data-exam-pending', '');
                const full = () => window.location.assign(link.href);
                swapTo((abort) => fetch(link.href, { credentials: 'same-origin', signal: abort, headers: { Accept: 'text/html' } }), full, full);
            },
            { signal },
        );
    });
}

// عمود الأرقام على الحاسوب واللوحي: أعمدة بقدر ما يلزم (حتى خمسة) لتظهر الأرقام كلها بلا تمرير في ارتفاع الشاشة، كأربعين
// سؤالاً على حاسوب محمول 1366×768. والجوال شريط أفقي لا يمسه هذا (resources/css/exam-focus.css)
function fitColumns(list, signal) {
    const fit = () => {
        // الأرقام وحدها، و«المراجعة» صف كامل تحتها
        const numbers = list.querySelectorAll(':scope > li:not(.exam-units-review)');
        const review = list.querySelector(':scope > li.exam-units-review');
        const units = list.closest('[data-exam-units]');

        if (!units || numbers.length === 0 || !window.matchMedia('(min-width: 768px)').matches) {
            return;
        }

        const style = getComputedStyle(list);
        const gap = parseFloat(style.rowGap) || 0;
        const pitch = numbers[0].getBoundingClientRect().height + gap;
        const room =
            parseFloat(getComputedStyle(units).maxHeight) -
            (units.offsetHeight - list.clientHeight) -
            (parseFloat(style.paddingTop) || 0) -
            (parseFloat(style.paddingBottom) || 0) -
            (review ? review.getBoundingClientRect().height + gap : 0);
        const rows = Number.isFinite(room) && pitch > 0 ? Math.max(1, Math.floor(room / pitch)) : numbers.length;

        list.style.setProperty('--exam-cols', String(Math.min(5, Math.max(1, Math.ceil(numbers.length / rows)))));
    };

    fit();
    window.addEventListener('resize', fit, { signal });
}

// أرقام الصفحات: الحالي في وسط الشريط الأفقي (الجوال) أو العمود (الحاسوب واللوحي)، بتمرير قائمة الأرقام وحدها لا الصفحة، ومفتاح
// الألوان خارجها ثابت
function centerCurrent(nav) {
    const current = nav.querySelector('[aria-current="step"]');

    if (!current) {
        return;
    }

    const box = nav.getBoundingClientRect();
    const item = current.getBoundingClientRect();

    if (nav.scrollWidth > nav.clientWidth + 1) {
        nav.scrollLeft += item.left + item.width / 2 - (box.left + box.width / 2);
    } else if (nav.scrollHeight > nav.clientHeight + 1) {
        nav.scrollTop += item.top + item.height / 2 - (box.top + box.height / 2);
    }
}

onPage((signal) => {
    document.querySelectorAll('form[data-exam-form]').forEach((form) => pending(form, signal));
    document.querySelectorAll('[data-exam-timer]').forEach((element) => timer(element, signal));
    document.querySelectorAll('[data-exam-units-list]').forEach((list) => fitColumns(list, signal));
    document.querySelectorAll('[data-exam-units-list]').forEach(centerCurrent);
    document.querySelectorAll('[data-exam-fullscreen]').forEach((button) => fullscreen(button, signal));
    navigate(signal);
    document.querySelectorAll('form[data-student-register]').forEach((form) => grades(form, signal));
});
