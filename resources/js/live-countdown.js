// العدّاد التنازلي لصفحة البث: يحسب الباقي حتى الموعد (data-countdown بصيغة ISO) بوحدات من ملفات
// الترجمة (data-countdown-labels)، ويعيد تحميل الصفحة عند الصفر فيظهر المشغّل بلا تدخل.
// ويُهيّأ مع كل صفحة (page.js)، ومؤقتاته تتوقف عند مغادرتها فلا تعيد تحميل صفحة أخرى.

import { onPage } from './page';

onPage((signal) => {
    document.querySelectorAll('[data-countdown]').forEach((element) => {
        const target = Date.parse(element.dataset.countdown);
        const labels = JSON.parse(element.dataset.countdownLabels || '{}');

        if (Number.isNaN(target)) {
            return;
        }

        let timer = null;
        let reload = null;

        const render = () => {
            const remaining = Math.max(0, Math.floor((target - Date.now()) / 1000));
            const parts = [
                [Math.floor(remaining / 86400), labels.days],
                [Math.floor((remaining % 86400) / 3600), labels.hours],
                [Math.floor((remaining % 3600) / 60), labels.minutes],
                [remaining % 60, labels.seconds],
            ];

            // الأيام تظهر إن وُجدت، والباقي دائماً
            element.textContent = parts
                .filter(([value], index) => index > 0 || value > 0)
                .map(([value, label]) => `${value} ${label}`)
                .join(' · ');

            if (remaining === 0) {
                clearInterval(timer);
                reload = setTimeout(() => window.location.reload(), 1500);
            }
        };

        render();
        timer = setInterval(render, 1000);

        signal.addEventListener('abort', () => {
            clearInterval(timer);
            clearTimeout(reload);
        });
    });
});
