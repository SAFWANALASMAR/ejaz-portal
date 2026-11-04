// شريط تصفية «منشوراتنا» يُمرَّر أفقياً على الجوال (social-filters.css)، فالمنصة المختارة من آخره تبقى خارجه بعد التنقّل.
// فتُوسَّط الكبسولة النشطة داخل الشريط وحده، لا الصفحة (scrollIntoView يحرك الصفحة رأسياً). ملاحظة صفوان على طلب الدمج 165، 4 نوفمبر 2026.
import { onPage } from './page';

onPage(() => {
    document.querySelectorAll('[data-social-filter] .social-filter-track').forEach((track) => {
        const active = track.querySelector('[aria-current="page"]');

        if (!active || track.scrollWidth <= track.clientWidth) {
            return;
        }

        const box = track.getBoundingClientRect();
        const pill = active.getBoundingClientRect();

        if (pill.left >= box.left && pill.right <= box.right) {
            return;
        }

        track.scrollBy({ left: pill.left + pill.width / 2 - (box.left + box.width / 2), behavior: 'instant' });
    });
});
