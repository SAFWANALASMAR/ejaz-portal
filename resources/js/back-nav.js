// شريط العودة (x-back-nav): الصفحة بلا أم في جدول App\Support\BackNavigation تعود بتاريخ المتصفح متى كانت الصفحة السابقة
// في البوابة نفسها، وإلا فرابطه (الرئيسية). ورابطها بلا wire:navigate، فلا يسبق التنقّلُ السريع الرجوعَ. والضغط مع Ctrl أو
// Cmd أو Shift يبقى للرابط نفسه كما يريد الزائر. وEnter على الرابط يطلق click كذلك.
//
// السابقة في البوابة: جاء الزائر منها بتحميل كامل (document.referrer من الأصل نفسه)، أو بتنقّل سريع إلى الأمام. ويُعدّ
// التنقّل السريع إلى الأمام وحده: livewire:navigate بلا history (الرجوع والتقدّم بأزرار المتصفح لا يُحسبان).

let forwardVisits = 0;

document.addEventListener('livewire:navigate', (event) => {
    if (!event.detail?.history) {
        forwardVisits++;
    }
});

window.addEventListener(
    'click',
    (event) => {
        const link = event.target.closest?.('[data-history-back]');

        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
            return;
        }

        const sameOrigin = document.referrer !== '' && new URL(document.referrer).origin === window.location.origin;

        if (window.history.length > 1 && (forwardVisits > 0 || sameOrigin)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.history.back();
        }
    },
    true,
);
