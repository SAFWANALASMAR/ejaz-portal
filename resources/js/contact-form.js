// نموذج «الاستفسارات والاقتراحات» بلا إعادة تحميل (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٣): كان الإرسال يعيد الصفحة
// فتومض بيضاء وتعود لأعلاها، ورسالة الاستلام لا تُرى. الآن يُرسل الطلب نفسه (بختم الوقت والفخ ورمز الحماية كما هي — ContactRequest)
// بـ fetch، فإن نجح حلّت لوحة «تم تسجيل طلبك» برقم الطلب مكان النموذج، ونُقل إليها التركيز وظهرت في الشاشة (role="status").
// والأخطاء تحت حقولها وفي تنبيه أعلى النموذج. وبلا سكربت يبقى الإرسال العادي بالتحويل ولوحة النجاح نفسها.

function fieldError(form, name, message) {
    const input = form.querySelector(`[name="${CSS.escape(name)}"]`);

    if (!input) {
        return;
    }

    const id = `${input.id}-error`;
    let note = document.getElementById(id);

    if (!note) {
        note = document.createElement('p');
        note.id = id;
        note.className = 'text-sm font-medium text-red-700';
        note.dataset.asyncError = '';
        // في غلاف الحقل (x-field) تحت حقله، كخطأ الخادم
        form.querySelector(`label[for="${CSS.escape(input.id)}"]`)?.parentElement?.append(note);
    }

    note.textContent = message;
    input.setAttribute('aria-invalid', 'true');
    const described = new Set((input.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean));
    described.add(id);
    input.setAttribute('aria-describedby', [...described].join(' '));
}

function clearErrors(form) {
    form.querySelectorAll('[data-async-error]').forEach((note) => note.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach((input) => input.removeAttribute('aria-invalid'));
    const summary = form.querySelector('[data-form-errors]');

    if (summary) {
        summary.hidden = true;
        summary.textContent = '';
    }
}

function showSummary(form, message) {
    const summary = form.querySelector('[data-form-errors]');

    if (summary) {
        summary.textContent = message;
        summary.hidden = false;
    }
}

document.addEventListener('submit', async (event) => {
    const form = event.target.closest?.('form[data-async-form]');

    if (!form || event.defaultPrevented || !window.fetch) {
        return;
    }

    event.preventDefault();

    const button = form.querySelector('button[type="submit"], button:not([type])');

    if (form.dataset.busy) {
        return;
    }

    form.dataset.busy = '1';
    form.setAttribute('aria-busy', 'true');
    const label = button?.innerHTML;

    if (button) {
        button.disabled = true;
        button.textContent = form.dataset.sending;
    }

    clearErrors(form);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));

        if (response.status === 201 && data.reference) {
            const panel = form.parentElement.querySelector('[data-contact-success]');
            panel.querySelector('[data-contact-success-text]').textContent = data.message;
            panel.querySelector('[data-contact-reference]').textContent = data.reference;
            form.hidden = true;
            panel.hidden = false;
            panel.scrollIntoView({ block: 'center', behavior: 'smooth' });
            panel.focus({ preventScroll: true });

            return;
        }

        if (response.status === 422 && data.errors) {
            Object.entries(data.errors).forEach(([name, messages]) => fieldError(form, name, messages[0]));
            showSummary(form, data.message ?? Object.values(data.errors)[0]?.[0] ?? form.dataset.failed);
        } else {
            // انتهت الجلسة (419) أو الحد (429) أو غيرهما: الرسالة من الخادم إن وُجدت
            showSummary(form, data.message && response.status < 500 ? data.message : form.dataset.failed);
        }

        (form.querySelector('[aria-invalid="true"]') ?? form.querySelector('[data-form-errors]'))?.focus?.();
        form.querySelector('[data-form-errors]')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    } catch {
        showSummary(form, form.dataset.failed);
    } finally {
        delete form.dataset.busy;
        form.removeAttribute('aria-busy');

        if (button) {
            button.disabled = false;
            button.innerHTML = label;
        }
    }
});
