// الرئيسية (ملاحظات المدير التنفيذي، 25 أكتوبر 2026) بلا مكتبات:
// - الظهور عند التمرير: [data-reveal] يظهر تباعاً حين يدخل الشاشة (--reveal-delay). وبلا سكربت أو لمن طلب تقليل الحركة
//   يبقى ظاهراً كما هو (app.css: html[data-reveal-ready] وحده يخفيه قبل ظهوره).
// - الشريطان المتحركان: زر الإيقاف [data-ticker-toggle] يوقف شريطه ويشغّله، ويُعلن حاله (aria-pressed واسمه).
// - كلمة رئيس المجلس كاملة في <dialog>، وبلا سكربت تبقى في <details> تحتها.

import { onLeave, onPage } from './page';

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

onPage((signal) => {
    const items = document.querySelectorAll('[data-reveal]');

    if (items.length === 0 || reducedMotion() || !('IntersectionObserver' in window)) {
        return;
    }

    document.documentElement.setAttribute('data-reveal-ready', '');

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.setAttribute('data-revealed', '');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.15 },
    );

    items.forEach((item) => observer.observe(item));

    // الطباعة تُظهر كل شيء ولو لم يُمرَّر إليه
    window.addEventListener('beforeprint', () => items.forEach((item) => item.setAttribute('data-revealed', '')), { signal });

    signal.addEventListener('abort', () => {
        observer.disconnect();
        document.documentElement.removeAttribute('data-reveal-ready');
    });
});

function toggleTicker(button) {
    const ticker = document.getElementById(button.dataset.tickerToggle);

    if (!ticker) {
        return;
    }

    const paused = !ticker.hasAttribute('data-paused');
    ticker.toggleAttribute('data-paused', paused);
    button.setAttribute('aria-pressed', String(paused));
    button.setAttribute('aria-label', paused ? button.dataset.labelPlay : button.dataset.labelPause);
    button.querySelector('[data-icon-pause]').toggleAttribute('hidden', paused);
    button.querySelector('[data-icon-play]').toggleAttribute('hidden', !paused);
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-ticker-toggle]');

    if (toggle) {
        toggleTicker(toggle);

        return;
    }

    // «اقرأ الكلمة كاملة»: النافذة بدل فتح <details>
    const opener = event.target.closest('[data-dialog-open]');
    const dialog = opener ? document.getElementById(opener.dataset.dialogOpen) : null;

    if (dialog && typeof dialog.showModal === 'function') {
        event.preventDefault();
        dialog.showModal();

        return;
    }

    const closer = event.target.closest('[data-dialog-close]');

    if (closer) {
        closer.closest('dialog')?.close();

        return;
    }

    // النقر خارج محتوى النافذة يغلقها
    if (event.target instanceof HTMLDialogElement && event.target.id === 'chairman-word') {
        event.target.close();
    }
});

// النسخ المكررة في كل شريط متحرك (data-marquee-copy — x-marquee): مخفية عن قارئ الشاشة بـ aria-hidden في القالب، وروابطها وأزرارها
// خارج ترتيب لوحة المفاتيح هنا، وتبقى تُضغط بالفأرة واللمس. كان inert يجعل نصف ما يظهر من الشريط في أي لحظة لا يفتح شيئاً
// (ملاحظة المدير التنفيذي على الإطلاق التجريبي، 12 نوفمبر 2026 — docs/decisions/2026-11-12-fix-marquee-copy-clickable.md)
onPage(() => {
    document.querySelectorAll('[data-marquee-copy]').forEach((copy) => {
        copy.querySelectorAll('a, button, input, select, textarea, summary, [tabindex]').forEach((element) => element.setAttribute('tabindex', '-1'));
    });
});

// الشريط المتحرك متصل بلا فراغ (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ١١): النسختان تتحركان بمقدار نسخة، فإن كانت النسخة
// أضيق من الشريط ظهر فراغ قبل أن تدور. فتُكرَّر عناصر كل نسخة حتى تملأ عرض الشريط، والمكرر مخفي عن قارئ الشاشة ولوحة المفاتيح
// (aria-hidden وtabindex=-1، ويبقى يُضغط)، وتطول مدة الدورة بقدره فتبقى السرعة واحدة. ويُعاد عند تغيّر عرض الشريط
function fillMarquee(marquee) {
    const groups = [...marquee.querySelectorAll(':scope > .marquee-track > .marquee-group')];

    // المكرر السابق يُحذف أولاً، ولو صار الشريط شبكة ثابتة (display: contents على الحاسوب) أو اختفى، فلا تبقى بطاقات مكررة فيها
    groups.forEach((group) => group.querySelectorAll('[data-marquee-fill]').forEach((clone) => clone.remove()));
    const base = Number(marquee.dataset.baseDuration || Number.parseFloat(marquee.style.getPropertyValue('--marquee-duration')) || 40);
    marquee.dataset.baseDuration = String(base);
    marquee.style.setProperty('--marquee-duration', `${base}s`);

    if (groups.length === 0 || getComputedStyle(marquee).display === 'contents' || marquee.clientWidth === 0) {
        return;
    }

    const originals = [...groups[0].children];
    const single = groups[0].scrollWidth;
    let copies = 1;

    while (single > 0 && groups[0].scrollWidth < marquee.clientWidth && copies < 8) {
        groups.forEach((group) => {
            originals.forEach((item) => {
                const clone = item.cloneNode(true);
                clone.setAttribute('data-marquee-fill', '');
                // مخفي عن قارئ الشاشة ولوحة المفاتيح، ويبقى يُضغط بالفأرة واللمس (inert كان يجعل أغلب البلاطات الظاهرة لا تفتح شيئاً)
                clone.setAttribute('aria-hidden', 'true');
                [clone, ...clone.querySelectorAll('a, button, [tabindex]')].forEach((element) => element.matches('a, button, [tabindex]') && element.setAttribute('tabindex', '-1'));
                clone.querySelectorAll('[id]').forEach((element) => element.removeAttribute('id'));
                group.append(clone);
            });
        });
        copies++;
    }

    marquee.style.setProperty('--marquee-duration', `${base * copies}s`);
}

onPage((signal) => {
    const marquees = document.querySelectorAll('[data-marquee][data-direction="horizontal"]');

    if (marquees.length === 0) {
        return;
    }

    const widths = new WeakMap();
    const observer = new ResizeObserver((entries) => {
        entries.forEach(({ target, contentRect }) => {
            const width = Math.round(contentRect.width);

            if (widths.get(target) !== width) {
                widths.set(target, width);
                fillMarquee(target);
            }
        });
    });

    marquees.forEach((marquee) => observer.observe(marquee));
    signal.addEventListener('abort', () => observer.disconnect());

    // والشريط الذي يصير شبكة (display: contents) لا صندوق له يراقَب: تغيّر عرض النافذة يعيد فحصه
    let timer = null;
    window.addEventListener(
        'resize',
        () => {
            clearTimeout(timer);
            timer = setTimeout(() => marquees.forEach(fillMarquee), 200);
        },
        { signal, passive: true },
    );
});

// التركيز بلوحة المفاتيح في شريط متحرك (فحص الوصول 0.15): الإيقاف وحده يُبقي الشريط عند موضعه، فقد تقف البطاقة المركّزة خارج
// صندوقه فلا تُرى. فيعود الشريط إلى أوله (data-focus-reveal — concept.css) وتُمرَّر البطاقة إلى داخله، ثم يعود من أوله حين يخرج
// التركيز. والفأرة واللمس لا يغيّران شيئاً (:focus-visible وحده)
const keyboardFocus = (element) => {
    try {
        return element.matches(':focus-visible');
    } catch {
        return false;
    }
};

document.addEventListener('focusin', (event) => {
    const marquee = event.target.closest?.('[data-marquee]');

    if (!marquee || !keyboardFocus(event.target)) {
        return;
    }

    marquee.setAttribute('data-focus-reveal', '');
    requestAnimationFrame(() => event.target.scrollIntoView({ block: 'nearest', inline: 'nearest' }));
});

document.addEventListener('focusout', (event) => {
    const marquee = event.target.closest?.('[data-marquee][data-focus-reveal]');

    if (marquee && !marquee.contains(event.relatedTarget)) {
        marquee.removeAttribute('data-focus-reveal');

        // والقائمة التي يمررها المستخدم بيده (العمودي مع تقليل الحركة) تبقى حيث تركها
        if (getComputedStyle(marquee).overflowY !== 'auto') {
            marquee.scrollLeft = 0;
            marquee.scrollTop = 0;
        }
    }
});

// قبل أن يحفظ Livewire نسخة الصفحة لزر الرجوع (wire:navigate): التنقّل من بطاقة مركّزة لا يطلق focusout، فلا تبقى العلامة فيها
onLeave(() => document.querySelectorAll('[data-focus-reveal]').forEach((marquee) => marquee.removeAttribute('data-focus-reveal')));
