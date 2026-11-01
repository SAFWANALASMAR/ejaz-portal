// الحركة في الواجهة (التحسين البصري — docs/plans/visual-refresh.md) بلا مكتبات، وكلها بالمفتاح --motion في visual.css: صفر
// لمن طلب تقليل الحركة أو لمن أطفأها، فلا عدّ ولا إمالة ولا تموّج ولا ظهور بالكلمة، والمحتوى ظاهر كما هو.
// - [data-reveal-group]: يضع data-reveal على أبنائه بتأخير متدرج، فيظهرون تباعاً بآلية home.js القائمة.
// - [data-count-to]: عدّ تصاعدي حتى الرقم المعروض (من اللوحة) حين يظهر.
// - [data-words]: ظهور النص كلمة كلمة.
// - .tilt: إمالة خفيفة بلمعة مع حركة الفأرة وحدها. و.btn-shine: تموّج عند الضغط.
// - [data-scroll-progress] و[data-back-to-top]: شريط تقدّم التمرير وزر العودة للأعلى.
// - .skeleton: هيكل التحميل يزول حين تُحمَّل صورته.
// - main[data-entering]: انتقال ناعم بعد كل تنقّل سريع.

import { onPage } from './page';

const motionOff = () => Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--motion')) === 0;

let firstPage = true;

onPage((signal) => {
    const off = motionOff();
    document.documentElement.toggleAttribute('data-motion-off', off);
    // ما يُخفى حتى يكمله السكربت (صور .skeleton) يُخفى بهذا الوسم وحده، فبلا JavaScript يظهر كما هو
    document.documentElement.setAttribute('data-motion-ready', '');

    // الظهور المتدرج لأبناء المجموعة، قبل أن يراقبها home.js
    document.querySelectorAll('[data-reveal-group]').forEach((group) => {
        [...group.children].forEach((child, index) => {
            child.setAttribute('data-reveal', '');
            child.style.setProperty('--reveal-delay', `${Math.min(index, 12) * 70}ms`);
        });
    });

    if (!firstPage && !off) {
        const main = document.getElementById('main');
        main?.setAttribute('data-entering', '');
        // حركة الصفحة نفسها لا حركة عنصر داخلها تنتهي قبلها
        const entered = (event) => {
            if (event.target === main) {
                main.removeAttribute('data-entering');
                main.removeEventListener('animationend', entered);
            }
        };
        main?.addEventListener('animationend', entered, { signal });
    }

    firstPage = false;

    skeletons(signal);
    scrollWatch(signal);
    loops(signal);

    if (off) {
        return;
    }

    words();
    counters(signal);
    tilts(signal);
});

function skeletons(signal) {
    document.querySelectorAll('.skeleton').forEach((frame) => {
        const image = frame.querySelector('img');

        if (!image || (image.complete && image.naturalWidth > 0)) {
            frame.setAttribute('data-loaded', '');

            return;
        }

        const done = () => frame.setAttribute('data-loaded', '');
        image.addEventListener('load', done, { once: true, signal });
        image.addEventListener('error', done, { once: true, signal });
    });
}

function scrollWatch(signal) {
    const progress = document.querySelector('[data-scroll-progress]');
    const top = document.querySelector('[data-back-to-top]');
    let frame = null;

    const update = () => {
        frame = null;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const ratio = max > 0 ? Math.min(1, window.scrollY / max) : 0;
        progress?.style.setProperty('--scroll', ratio.toFixed(4));
        top?.toggleAttribute('data-visible', window.scrollY > window.innerHeight * 0.8);
    };

    window.addEventListener('scroll', () => (frame ??= requestAnimationFrame(update)), { passive: true, signal });
    update();
}

function words() {
    document.querySelectorAll('[data-words]:not([data-words-ready])').forEach((element) => {
        // قارئ الشاشة يقرأ العنوان جملةً واحدة لا كلمات مجزأة
        element.setAttribute('aria-label', element.textContent.replace(/\s+/g, ' ').trim());
        let index = 0;
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
        const nodes = [];

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach((node) => {
            const fragment = document.createDocumentFragment();

            node.textContent.split(/(\s+)/).forEach((part) => {
                if (part.trim() === '') {
                    fragment.append(part);

                    return;
                }

                const word = document.createElement('span');
                word.setAttribute('data-word', '');
                word.setAttribute('aria-hidden', 'true');
                word.style.setProperty('--i', String(index++));
                word.textContent = part;
                fragment.append(word);
            });

            node.replaceWith(fragment);
        });

        element.setAttribute('data-words-ready', '');
    });
}

function counters(signal) {
    const items = document.querySelectorAll('[data-count-to]');

    if (items.length === 0 || !('IntersectionObserver' in window)) {
        return;
    }

    const format = new Intl.NumberFormat('en-US');

    const run = (element) => {
        const target = Number(element.dataset.countTo);
        const suffix = element.dataset.countSuffix ?? '';
        const started = performance.now();
        const duration = 1600;

        const step = (now) => {
            const progress = Math.min(1, (now - started) / duration);
            // تباطؤ في آخره
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = format.format(Math.round(target * eased)) + suffix;

            if (progress < 1 && !signal.aborted) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
    };

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    run(entry.target);
                }
            });
        },
        { threshold: 0.4 },
    );

    // ما ظهر رقمه النهائي في الشاشة الأولى يبقى كما هو، فلا يقفز إلى الصفر ثم يعدّ، إلا ما طلب العدّ عند التحميل
    // (data-count-on-load، كعدّاد في البطل). وما خارجها يبدأ من الصفر وهو مخفي، فيعدّ حين يظهر بلا قفزة
    items.forEach((item) => {
        const box = item.getBoundingClientRect();
        const onScreen = box.top < window.innerHeight && box.bottom > 0;

        if (onScreen && 'countOnLoad' in item.dataset) {
            run(item);

            return;
        }

        if (onScreen) {
            return;
        }

        item.textContent = '0' + (item.dataset.countSuffix ?? '');
        observer.observe(item);
    });
    signal.addEventListener('abort', () => observer.disconnect());
}

function tilts(signal) {
    document.querySelectorAll('.tilt').forEach((card) => {
        card.addEventListener(
            'pointermove',
            (event) => {
                if (event.pointerType !== 'mouse') {
                    return;
                }

                const box = card.getBoundingClientRect();
                const x = (event.clientX - box.left) / box.width;
                const y = (event.clientY - box.top) / box.height;
                card.style.setProperty('--tilt-x', `${((0.5 - y) * 6).toFixed(2)}deg`);
                card.style.setProperty('--tilt-y', `${((x - 0.5) * 6).toFixed(2)}deg`);
                card.style.setProperty('--shine-x', `${(x * 100).toFixed(1)}%`);
                card.style.setProperty('--shine-y', `${(y * 100).toFixed(1)}%`);
                card.setAttribute('data-tilting', '');
            },
            { signal },
        );

        card.addEventListener(
            'pointerleave',
            () => {
                card.style.removeProperty('--tilt-x');
                card.style.removeProperty('--tilt-y');
                card.removeAttribute('data-tilting');
            },
            { signal },
        );
    });
}

// تموّج الضغط على الأزرار، وزر العودة للأعلى (مستمعان مفوّضان مرة واحدة)
document.addEventListener('click', (event) => {
    const top = event.target.closest('[data-back-to-top]');

    if (top) {
        window.scrollTo({ top: 0, behavior: motionOff() ? 'auto' : 'smooth' });
        document.getElementById('main')?.focus({ preventScroll: true });

        return;
    }

    const button = event.target.closest('.btn-shine');

    if (!button || motionOff()) {
        return;
    }

    const box = button.getBoundingClientRect();
    const size = Math.max(box.width, box.height);
    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    ripple.setAttribute('aria-hidden', 'true');
    ripple.style.width = ripple.style.height = `${size}px`;
    ripple.style.left = `${event.clientX - box.left - size / 2}px`;
    ripple.style.top = `${event.clientY - box.top - size / 2}px`;
    button.append(ripple);
    ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
});

// الحلقات المستمرة الخفيفة ([data-loop]: النقش الهندسي البطيء، والتوهج النابض، والبطاقة العائمة) لا تعمل إلا وهي في الشاشة
// (data-inview)، فلا تستهلك شيئاً خارجها. ولمن طلب تقليل الحركة لا تعمل أصلاً (visual.css).
function loops(signal) {
    const items = document.querySelectorAll('[data-loop]');

    if (items.length === 0 || !('IntersectionObserver' in window)) {
        items.forEach((item) => item.setAttribute('data-inview', ''));

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => entry.target.toggleAttribute('data-inview', entry.isIntersecting));
    });
    items.forEach((item) => observer.observe(item));
    signal.addEventListener('abort', () => observer.disconnect());
}
