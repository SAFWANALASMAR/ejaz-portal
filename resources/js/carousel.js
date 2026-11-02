// الدوّارات بلا مكتبة (x-carousel وx-hero-slider — plans/2026-10-30-visual-refresh-2-directive.md): شريحة نشطة واحدة
// (data-active)، ونقاط بـ aria-current، ومؤشر «01 / 03»، وأسهم ولوحة المفاتيح. والتبديل التلقائي (data-autoplay):
// - لا يبدأ أصلاً لمن طلب تقليل الحركة (--motion = 0)، فيبدأ متوقفاً وزره «تشغيل».
// - يتوقف مؤقتاً بالمرور والتركيز، وخارج الشاشة (IntersectionObserver)، والتبويب المخفي (visibilitychange).
// - وزر الإيقاف (aria-pressed) يوقفه حتى يُضغط ثانية.
// وaria-live «off» أثناء التبديل التلقائي و«polite» وهو متوقف (نمط APG للدوّار).

import { onPage } from './page';

const motionOff = () => Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--motion')) === 0;
const pad = (number) => String(number).padStart(2, '0');

function setup(root, signal) {
    const items = [...root.querySelectorAll('[data-carousel-item]')].filter((item) => item.closest('[data-carousel]') === root);
    const dots = [...root.querySelectorAll('[data-carousel-dot]')].filter((dot) => dot.closest('[data-carousel]') === root);
    const track = root.querySelector('[data-carousel-track]');
    const status = root.querySelector('[data-carousel-status]');
    const toggle = root.querySelector('[data-carousel-toggle]');
    const interval = Number(root.dataset.interval) || 6000;
    const total = items.length;

    if (total === 0) {
        return;
    }

    let index = Math.max(0, items.findIndex((item) => item.hasAttribute('data-active')));
    let timer = null;
    // زر الإيقاف أو تقليل الحركة. وبطاقات الهوية تحت البطل تتبدل حتى مع تقليل الحركة (data-autoplay-always — ملاحظات المدير
    // التنفيذي، 1 نوفمبر 2026، البندان ٩ و١٠): تبديل فوري بلا انتقال ولا لمعة، ويوقفه زر الإيقاف والمرور والتركيز (WCAG 2.2.2)
    let paused = (motionOff() && !('autoplayAlways' in root.dataset)) || !('autoplay' in root.dataset);
    const card = root.closest('.home-card');
    let hovering = false;
    let visible = true;

    items.forEach((item, position) => {
        item.setAttribute('role', 'group');
        item.setAttribute('aria-roledescription', root.dataset.slideLabel ?? 'slide');
        item.setAttribute('aria-label', `${position + 1} ${root.dataset.ofLabel ?? '/'} ${total}`);
    });

    const running = () => !paused && !hovering && visible && !document.hidden && total > 1;

    const render = () => {
        // التركيز في شريحة تُخفى (inert) ينتقل إلى مسار الشرائح، فلا يضيع إلى الصفحة
        if (items.some((item, position) => position !== index && item.contains(document.activeElement))) {
            track?.focus({ preventScroll: true });
        }

        items.forEach((item, position) => {
            const active = position === index;
            item.toggleAttribute('data-active', active);
            item.toggleAttribute('inert', !active);
        });
        dots.forEach((dot, position) => (position === index ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
        if (status) {
            status.textContent = `${pad(index + 1)} / ${pad(total)}`;
        }
    };

    const schedule = () => {
        clearTimeout(timer);
        root.toggleAttribute('data-running', running());
        track?.setAttribute('aria-live', running() ? 'off' : 'polite');

        if (running()) {
            // يُعاد تشغيل شريط التقدّم مع كل شريحة
            root.style.setProperty('--slide-duration', `${interval}ms`);
            timer = setTimeout(() => go(index + 1), interval);
        }
    };

    const go = (next) => {
        index = (next + total) % total;
        render();
        schedule();

        // لمعة البطاقة عند التبدل (concept.css، وتقليل الحركة يطفئها): تُعاد من أولها مع كل شريحة
        if (card && !motionOff()) {
            card.removeAttribute('data-shine');
            void card.offsetWidth;
            card.setAttribute('data-shine', '');
        }
    };

    const setPaused = (value) => {
        paused = value;
        if (toggle) {
            toggle.setAttribute('aria-pressed', String(paused));
            toggle.setAttribute('aria-label', paused ? toggle.dataset.labelPlay : toggle.dataset.labelPause);
            toggle.querySelector('[data-icon-pause]')?.toggleAttribute('hidden', paused);
            toggle.querySelector('[data-icon-play]')?.toggleAttribute('hidden', !paused);
        }
        schedule();
    };

    root.querySelector('[data-carousel-prev]')?.addEventListener('click', () => go(index - 1), { signal });
    root.querySelector('[data-carousel-next]')?.addEventListener('click', () => go(index + 1), { signal });
    dots.forEach((dot, position) => dot.addEventListener('click', () => go(position), { signal }));
    toggle?.addEventListener('click', () => setPaused(!paused), { signal });

    // السهم الأيسر للتالي في الاتجاه من اليمين
    root.addEventListener(
        'keydown',
        (event) => {
            if (event.target.closest('input, textarea, select')) {
                return;
            }
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                go(index + 1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                go(index - 1);
            }
        },
        { signal },
    );

    // المرور والتركيز كلٌّ بعلامته: خروج الفأرة لا يعيد التبديل والتركيز ما زال داخل الدوّار
    const hold = (key, value) => () => {
        holds[key] = value;
        hovering = holds.pointer || holds.focus;
        schedule();
    };
    const holds = { pointer: false, focus: false };
    // وفي بطاقات الهوية البطاقة كلها (رابطها يغطيها وهو أخ للدوّار): المرور عليها وتركيز رابطها يوقفانه أيضاً
    const area = card ?? root;
    area.addEventListener('pointerenter', hold('pointer', true), { signal });
    area.addEventListener('pointerleave', hold('pointer', false), { signal });
    area.addEventListener('focusin', hold('focus', true), { signal });
    area.addEventListener(
        'focusout',
        (event) => {
            if (!area.contains(event.relatedTarget)) {
                hold('focus', false)();
            }
        },
        { signal },
    );
    document.addEventListener('visibilitychange', schedule, { signal });

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(([entry]) => {
            visible = entry.isIntersecting;
            schedule();
        });
        observer.observe(root);
        signal.addEventListener('abort', () => observer.disconnect());
    }

    signal.addEventListener('abort', () => clearTimeout(timer));

    root.setAttribute('data-carousel-ready', '');
    render();
    setPaused(paused);
}

onPage((signal) => {
    document.querySelectorAll('[data-carousel]').forEach((root) => setup(root, signal));
});
