// الرئيسية (ملاحظات المدير التنفيذي، 25 أكتوبر 2026) بلا مكتبات:
// - الظهور عند التمرير: [data-reveal] يظهر تباعاً حين يدخل الشاشة (--reveal-delay). وبلا سكربت أو لمن طلب تقليل الحركة
//   يبقى ظاهراً كما هو (app.css: html[data-reveal-ready] وحده يخفيه قبل ظهوره).
// - الشريطان المتحركان: زر الإيقاف [data-ticker-toggle] يوقف شريطه ويشغّله، ويُعلن حاله (aria-pressed واسمه).
// - كلمة رئيس المجلس كاملة في <dialog>، وبلا سكربت تبقى في <details> تحتها.

import { onPage } from './page';

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
