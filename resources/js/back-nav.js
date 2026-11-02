// شريط العودة (x-back-nav) في كل صفحة: يعود بتاريخ المتصفح (history.back) متى كانت الصفحة السابقة في البوابة نفسها، وإلا
// فرابطه: الصفحة الأم من جدول App\Support\BackNavigation، ثم الرئيسية لما لا أم له. ورابطه بلا wire:navigate، فلا يسبق
// التنقّلُ السريع الرجوعَ. والضغط مع Ctrl أو Cmd أو Shift يبقى للرابط نفسه كما يريد الزائر. وEnter على الرابط يطلق click كذلك.
//
// السابقة في البوابة: جاء الزائر منها بتحميل كامل (document.referrer من الأصل نفسه)، أو بتنقّل سريع إلى الأمام. ويُعدّ
// التنقّل السريع إلى الأمام وحده: livewire:navigate بلا history. والرجوع بزر المتصفح (history) يُنقص العدّاد، فلا يبقى بعد
// العودة إلى أول صفحة ما يوهم بسابقة في البوابة فيخرج history.back من الموقع (مراجعة المشرف على #111، المنخفضة 2). وزر
// التقدّم لا يُميَّز من الرجوع في حدث Livewire، فيُنقصه أيضاً: الخطأ في جهة الرابط (الرئيسية) لا في جهة الخروج من البوابة.

let forwardVisits = 0;

document.addEventListener('livewire:navigate', (event) => {
    if (event.detail?.history) {
        forwardVisits = Math.max(0, forwardVisits - 1);
    } else {
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

        // والمرجع الصفحة نفسها (إرسال نموذج ثم تحويل إليها) لا يُحسب، وإلا عاد الرجوع إلى الصفحة نفسها
        const referrer = document.referrer !== '' ? new URL(document.referrer) : null;
        const sameOrigin = referrer !== null && referrer.origin === window.location.origin && referrer.pathname !== window.location.pathname;

        if (window.history.length > 1 && (forwardVisits > 0 || sameOrigin)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.history.back();
        }
    },
    true,
);
