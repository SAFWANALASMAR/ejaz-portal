// صفحة مسابقة الطلاب (fix/brahin-director-notes): الأقسام المطوية ودليل الدخول شرائح. تحسين فوق ترميز يعمل بلا
// سكربت: details/summary تُفتح بنفسها، والخطوات قائمة كاملة. والموقع من اليمين وحده، فالسهم الأيسر للتالية.
// وتقليل الحركة في app.css. ويُهيّأ مع كل صفحة (page.js)، ومستمعاته بإشارتها فتزول معها.

import { onPage } from './page';

function setupCollapsibles(group, signal) {
    const cards = [...group.querySelectorAll('[data-collapsible]')];
    const toggle = group.querySelector('[data-collapsible-all]');
    const label = toggle?.querySelector('[data-collapsible-all-label]');

    const sync = () => {
        cards.forEach((card) => card.querySelector('summary')?.setAttribute('aria-expanded', String(card.open)));

        if (toggle) {
            const all = cards.every((card) => card.open);
            toggle.setAttribute('aria-expanded', String(all));
            label.textContent = all ? toggle.dataset.hide : toggle.dataset.show;
        }
    };

    cards.forEach((card) => card.addEventListener('toggle', sync, { signal }));

    if (toggle) {
        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            const open = !cards.every((card) => card.open);
            cards.forEach((card) => {
                card.open = open;
            });
            sync();
        }, { signal });
    }

    sync();
}

function setupSlides(root, signal) {
    const slides = [...root.querySelectorAll('[data-slide]')];
    const controls = root.querySelector('[data-slides-controls]');

    if (slides.length < 2 || !controls) {
        return;
    }

    const track = root.querySelector('[data-slides-track]');
    const previous = root.querySelector('[data-slide-previous]');
    const next = root.querySelector('[data-slide-next]');
    const dots = [...root.querySelectorAll('[data-slide-dot]')];
    const status = root.querySelector('[data-slides-status]');
    let current = 0;

    const show = (index, announce = true) => {
        current = Math.max(0, Math.min(slides.length - 1, index));
        slides.forEach((slide, position) => {
            slide.hidden = position !== current;
        });
        dots.forEach((dot, position) => (position === current ? dot.setAttribute('aria-current', 'step') : dot.removeAttribute('aria-current')));
        previous.disabled = current === 0;
        next.disabled = current === slides.length - 1;

        if (announce && status) {
            status.textContent = slides[current].getAttribute('aria-label');
        }
    };

    controls.hidden = false;
    root.dataset.slidesReady = '';
    previous.addEventListener('click', () => show(current - 1), { signal });
    next.addEventListener('click', () => show(current + 1), { signal });
    dots.forEach((dot, position) => dot.addEventListener('click', () => show(position), { signal }));

    track.addEventListener('keydown', (event) => {
        const step = { ArrowLeft: 1, ArrowRight: -1 }[event.key];

        if (step !== undefined) {
            event.preventDefault();
            show(current + step);
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            show(event.key === 'Home' ? 0 : slides.length - 1);
        }
    }, { signal });

    // السحب على الجوال: إلى اليسار للتالية، وإلى اليمين للسابقة، والحركة الرأسية تمرير للصفحة
    let start = null;
    track.addEventListener('pointerdown', (event) => {
        start = { x: event.clientX, y: event.clientY };
    }, { signal });
    track.addEventListener('pointerup', (event) => {
        if (start === null) {
            return;
        }

        const dx = event.clientX - start.x;
        const dy = event.clientY - start.y;
        start = null;

        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
            show(current + (dx < 0 ? 1 : -1));
        }
    }, { signal });
    track.addEventListener('pointercancel', () => {
        start = null;
    }, { signal });

    show(0, false);
}

onPage((signal) => {
    document.querySelectorAll('[data-collapsible-group]').forEach((group) => setupCollapsibles(group, signal));
    document.querySelectorAll('[data-slides]').forEach((root) => setupSlides(root, signal));
});
