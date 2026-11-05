// شروط كلمة المرور تحت حقلها (components/password-rules.blade.php): تُعلَّم أثناء الكتابة، ومؤشر القوة نصاً في منطقة
// حية. عون للكتابة وحده، والقواعد في الخادم (PasswordPolicy). والنصوص كلها من سمات العنصر (lang/ar)، لا من هنا.

import { onPage } from './page';

const MIN_LENGTH = 12;

// أرقام عربية وفارسية إلى لاتينية، كتوحيدها في الخادم (Digits::latin)
const latinDigits = (value) => value.replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660)).replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06f0));

// أجزاء بيانات صاحب الحساب من حقول الصفحة نفسها: كلمات الاسم، وما قبل @، والأرقام التسعة الأخيرة من الجوال
function personalFragments() {
    const value = (id) => document.getElementById(id)?.value ?? '';
    const name = value('field-name').toLowerCase().split(/[\s\-_.]+/u);
    const email = value('field-email').toLowerCase().split('@')[0] ?? '';
    const phone = latinDigits(value('field-phone')).replace(/\D/g, '');

    return [...name, email, phone.length >= 9 ? phone.slice(-9) : ''].filter((fragment) => [...fragment].length >= 3);
}

function check(password) {
    const lower = latinDigits(password).toLowerCase();
    const hasLatin = /[A-Za-z]/.test(password);

    return {
        length: [...password].length >= MIN_LENGTH,
        letter: /\p{L}/u.test(password),
        mixed: !hasLatin || (/[A-Z]/.test(password) && /[a-z]/.test(password)),
        number: /\p{N}/u.test(password),
        // كقاعدة symbols() في لارافيل: فراغ أو رمز أو علامة ترقيم
        symbol: /[\p{Z}\p{S}\p{P}]/u.test(password),
        personal_item: password !== '' && !personalFragments().some((fragment) => lower.includes(fragment)),
    };
}

function update(box, input) {
    const password = input.value;
    const results = check(password);
    const met = Object.values(results).filter(Boolean).length;

    box.querySelectorAll('[data-rule]').forEach((item) => {
        const ok = password !== '' && results[item.dataset.rule];
        item.dataset.state = ok ? 'met' : 'unmet';
        item.querySelector('[data-mark]').textContent = ok ? '✓' : '•';
        item.querySelector('[data-state-text]').textContent = ok ? box.dataset.met : box.dataset.unmet;
    });

    // ضعيفة ما لم تتحقق الشروط كلها، ومقبولة عند تحققها، وقوية متى طالت أربعة أحرف فوق الحد
    const all = met === Object.keys(results).length;
    const level = password === '' ? 'empty' : !all ? 'weak' : [...password].length >= MIN_LENGTH + 4 ? 'strong' : 'fair';
    const widths = { empty: '0%', weak: '33%', fair: '66%', strong: '100%' };

    box.querySelector('[data-meter]').style.width = widths[level];
    const text = box.dataset.strengthLabel.replace(':level', box.dataset[`level${level[0].toUpperCase()}${level.slice(1)}`]);
    const strength = box.querySelector('[data-strength]');
    if (strength.textContent.trim() !== text) {
        strength.textContent = text;
    }
}

onPage((signal) => {
    document.querySelectorAll('[data-password-rules]').forEach((box) => {
        const input = document.getElementById(box.dataset.for);
        if (!input) {
            return;
        }

        // والعنوان وتنبيه التسريب معاً (المشرف على طلب الدمج 189، المنخفضة 3)، ولا يتكرر المعرّف إن أعيدت التهيئة
        const described = new Set((input.getAttribute('aria-describedby') ?? '').split(/s+/).filter(Boolean));
        described.add(`${box.dataset.for}-rules-heading`);
        described.add(`${box.dataset.for}-rules-leaked`);
        input.setAttribute('aria-describedby', [...described].join(' '));
        const refresh = () => update(box, input);
        input.addEventListener('input', refresh, { signal });
        ['field-name', 'field-email', 'field-phone'].forEach((id) => document.getElementById(id)?.addEventListener('input', refresh, { signal }));
        refresh();
    });
});
