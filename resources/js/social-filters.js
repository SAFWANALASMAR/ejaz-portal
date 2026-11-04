// شريط تصفية «منشوراتنا» يُمرَّر أفقياً على الجوال (social-filters.css)، فالمنصة المختارة من آخره تبقى خارجه بعد التنقّل.
// فتُوسَّط الكبسولة النشطة داخل الشريط وحده، لا الصفحة (scrollIntoView يحرك الصفحة رأسياً). ملاحظة صفوان على طلب الدمج 165، 4 نوفمبر 2026.
// و«لما نضغط على الأيقونة تتحرك» (صفوان، 16:06): عند الاختيار ينتقل لون النشطة إليها فوراً بانسياب، ويتمرر الشريط بنعومة
// ليتوسطها، وبعد التنقّل يبدأ الشريط الجديد من الموضع نفسه ويكمل، ويقفز مربع شعارها. والتصفية نفسها رابطها كما هو.
import { onLeave, onPage } from './page';

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// الاختيار الجاري: أي شريط، وموضع تمريره، والكبسولة النشطة قبله
let chosen = null;

function center(track, pill, behavior) {
    if (track.scrollWidth <= track.clientWidth) {
        return;
    }

    const box = track.getBoundingClientRect();
    const rect = pill.getBoundingClientRect();
    track.scrollBy({ left: rect.left + rect.width / 2 - (box.left + box.width / 2), behavior });
}

document.addEventListener('click', (event) => {
    const pill = event.target instanceof Element ? event.target.closest('[data-social-filter] [data-social-filter-pill]') : null;

    // النقر لفتح تبويب جديد لا يغيّر هذه الصفحة
    if (!pill || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const track = pill.closest('.social-filter-track');
    const previous = track.querySelector('[aria-current="page"]');
    chosen = { filter: pill.closest('[data-social-filter]').dataset.socialFilter, scroll: track.scrollLeft, previous, pill };

    if (previous !== pill) {
        previous?.removeAttribute('aria-current');
        pill.setAttribute('aria-current', 'page');
    }

    center(track, pill, reducedMotion() ? 'instant' : 'smooth');
});

// Livewire يحفظ نسخة الصفحة لزر الرجوع بعد هذا، فتعود إليها حالتها كما كانت
onLeave(() => {
    if (chosen && chosen.previous !== chosen.pill) {
        chosen.pill.removeAttribute('aria-current');
        chosen.previous?.setAttribute('aria-current', 'page');
    }
});

onPage(() => {
    const selection = chosen;
    chosen = null;

    document.querySelectorAll('[data-social-filter]').forEach((filter) => {
        const track = filter.querySelector('.social-filter-track');
        const active = track?.querySelector('[aria-current="page"]');

        if (!active) {
            return;
        }

        if (selection && selection.filter === filter.dataset.socialFilter) {
            // تبقى السمة حتى التنقّل التالي: الحركة تعمل مرة واحدة حين تُضاف
            active.setAttribute('data-just-selected', '');
            track.scrollLeft = selection.scroll;
            center(track, active, reducedMotion() ? 'instant' : 'smooth');

            return;
        }

        const box = track.getBoundingClientRect();
        const pill = active.getBoundingClientRect();

        if (pill.left >= box.left && pill.right <= box.right) {
            return;
        }

        center(track, active, 'instant');
    });
});
