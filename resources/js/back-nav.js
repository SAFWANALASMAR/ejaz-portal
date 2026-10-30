// شريط العودة (x-back-nav): الصفحة بلا أم في جدول App\Support\BackNavigation تعود بتاريخ المتصفح متى جاء الزائر من صفحة في
// البوابة نفسها، وإلا فرابطه (الرئيسية). يُلتقط الضغط قبل التنقّل السريع (wire:navigate)، فلا يفتح الرابط فوق الرجوع.
// والضغط مع Ctrl أو Cmd أو Shift يبقى للرابط نفسه كما يريد الزائر.

let internalVisits = 0;

// كل تنقّل سريع داخل البوابة يضيف صفحة إلى التاريخ
document.addEventListener('livewire:navigated', () => {
    internalVisits++;
});

window.addEventListener(
    'click',
    (event) => {
        const link = event.target.closest?.('[data-history-back]');

        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
            return;
        }

        const sameOrigin = document.referrer !== '' && new URL(document.referrer).origin === window.location.origin;

        if (window.history.length > 1 && (internalVisits > 1 || sameOrigin)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.history.back();
        }
    },
    true,
);
