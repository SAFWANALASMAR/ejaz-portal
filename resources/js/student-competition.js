// مسابقة الطلاب (docs/plans/student-competition.md): عداد الاختبار، وصفوف الفئة المختارة في التسجيل.
// للراحة وحدها: الخادم يرفض ما بعد الموعد ويسلّم المحاولة بنفسه (SubmitAttempt وejaz:close-expired-attempts)،
// ويفحص الصف بصفوف الفئة (RegisterStudent).

import { onPage } from './page';

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
    signal.addEventListener('abort', () => window.clearTimeout(release));
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
    document.querySelectorAll('[data-exam-units-list]').forEach(centerCurrent);
    document.querySelectorAll('form[data-student-register]').forEach((form) => grades(form, signal));
});
