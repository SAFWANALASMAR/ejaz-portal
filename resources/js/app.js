// تفاعل الواجهة العامة بلا مكتبات: قائمة الجوال، والقوائم المنسدلة، وإظهار كلمة المرور.
// كل زر قائمة له data-toggle وaria-controls يشير إلى العنصر الذي يفتحه.
// والمستمعات المفوّضة على document مرة واحدة، وما يمسح عناصر الصفحة في onPage (page.js) يُعاد بعد كل تنقّل سريع.

import { onLeave, onPage, start } from './page';
import './competition-entry';
import './live-countdown';
import './gallery';
import './competition-exam';
import './student-competition';
import './competition-page';
import './assistant';
import './password-rules';
// قبل home.js: يضع data-reveal على أبناء [data-reveal-group] قبل أن يراقبها
import './motion';
import './home';
import './carousel';
import './back-nav';
import './search-palette';
import './library-progress';
import './pdf-reader';
import './profile-reveal';
import './image-fit';
import './contact-form';
import './file-input';
import './social-filters';

function setOpen(toggle, open) {
    const target = document.getElementById(toggle.getAttribute('aria-controls'));

    if (!target) {
        return;
    }

    toggle.setAttribute('aria-expanded', String(open));
    target.hidden = !open;
}

function closeAll(except = null) {
    document.querySelectorAll('[data-toggle="dropdown"][aria-expanded="true"]').forEach((toggle) => {
        if (toggle !== except && !toggle.parentElement.contains(except)) {
            setOpen(toggle, false);
        }
    });
}

// مشغّل يوتيوب في صفحة عنصر المكتبة لا يُحمَّل إلا بضغط الزائر، ومن هذا المصدر وحده
const VIDEO_EMBED = 'https://www.youtube-nocookie.com/embed/';

function playVideo(link) {
    const frame = document.createElement('iframe');

    frame.src = link.dataset.videoEmbed;
    frame.title = link.dataset.videoTitle;
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.allowFullscreen = true;
    frame.referrerPolicy = 'strict-origin-when-cross-origin';
    frame.className = 'block aspect-video w-full';
    frame.videoLink = link;
    link.replaceWith(frame);
    frame.focus();
}

// والرابط يعود مكان المشغّل قبل مغادرة الصفحة، فلا يحفظه Livewire في نسخة الرجوع فيعمل المقطع وحده (autoplay) عند الرجوع
onLeave(() => {
    document.querySelectorAll('iframe').forEach((frame) => frame.videoLink && frame.replaceWith(frame.videoLink));
});

// نسخ رابط الخبر، ويُعلَن النسخ على الزر نفسه (aria-live). والزر مخفي حتى يتاح النسخ في المتصفح
function copyLink(button) {
    navigator.clipboard.writeText(button.dataset.copyLink).then(() => {
        button.dataset.label ??= button.textContent;
        button.textContent = button.dataset.copied;
        clearTimeout(Number(button.dataset.timer));
        button.dataset.timer = String(
            setTimeout(() => {
                button.textContent = button.dataset.label;
            }, 2000),
        );
    });
}

onPage(() => {
    if (navigator.clipboard) {
        document.querySelectorAll('[data-copy-link]').forEach((button) => {
            button.hidden = false;
        });
    }
});

function togglePassword(button) {
    const input = document.getElementById(button.dataset.passwordToggle);

    if (!input) {
        return;
    }

    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(visible));
    button.querySelector('[data-icon-show]').hidden = visible;
    button.querySelector('[data-icon-hide]').hidden = !visible;
}

document.addEventListener('click', (event) => {
    // كل ضغطة خارج أزرار الفتح تطوي القوائم المنسدلة أولاً، ومنها ما يعود قبل آخر المستمع (زر كلمة المرور والنسخ والمقطع)،
    // لأن تركيز الضغط بالمؤشر لا يطويها (مستمع focusin أدناه — docs/decisions/2026-11-03-mobile-menu.md)
    if (!event.target.closest('[data-toggle]')) {
        closeAll();
    }

    const passwordToggle = event.target.closest('[data-password-toggle]');

    if (passwordToggle) {
        togglePassword(passwordToggle);

        return;
    }

    const copy = event.target.closest('[data-copy-link]');

    if (copy && navigator.clipboard) {
        copyLink(copy);

        return;
    }

    const video = event.target.closest('[data-video-embed]');

    // والضغط مع Ctrl أو Cmd يفتح المقطع في يوتيوب كما يريد الزائر
    if (video && video.dataset.videoEmbed.startsWith(VIDEO_EMBED) && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
        event.preventDefault();
        playVideo(video);

        return;
    }

    const toggle = event.target.closest('[data-toggle]');

    if (toggle) {
        // فتحها المرور قبل لحظة ثم ضُغط الزر: تبقى مفتوحة ولا يغلقها الضغط نفسه
        const hoverOpened = toggle.dataset.hoverOpened && Date.now() - Number(toggle.dataset.hoverOpened) < 600;
        const open = hoverOpened || toggle.getAttribute('aria-expanded') !== 'true';
        delete toggle.dataset.hoverOpened;

        if (toggle.dataset.toggle === 'dropdown') {
            closeAll(toggle);
        }

        setOpen(toggle, open);

        // فُتحت بلوحة المفاتيح (Enter أو Space — ضغط بلا مؤشر): التركيز إلى أول عناصرها
        if (open && event.detail === 0 && toggle.dataset.toggle === 'dropdown') {
            dropdownLinks(toggle)[0]?.focus();
        }

        return;
    }
});

// روابط القائمة المنسدلة لزرها
function dropdownLinks(toggle) {
    return [...(document.getElementById(toggle.getAttribute('aria-controls'))?.querySelectorAll('a[href]') ?? [])];
}

// قوائم الرأس المنسدلة تفتح بمرور الفأرة (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٣): بمهلة قصيرة للفتح وأطول للإغلاق
// فلا تومض عند عبور المؤشر فوقها. والفأرة وحدها: اللمس يبقى بالضغط، ولوحة المفاتيح بالأسهم وEnter وSpace وEscape
const HOVER_OPEN = 120;
const HOVER_CLOSE = 280;

function hoverItem(event) {
    if (event.pointerType !== 'mouse') {
        return null;
    }

    const item = event.target.closest?.('[data-nav-dropdown]');

    return item && !item.contains(event.relatedTarget) ? item : null;
}

document.addEventListener('pointerover', (event) => {
    const item = hoverItem(event);

    if (!item) {
        return;
    }

    const toggle = item.querySelector('[data-toggle="dropdown"]');
    clearTimeout(item.hoverTimer);
    item.hoverTimer = setTimeout(() => {
        if (toggle.getAttribute('aria-expanded') !== 'true') {
            closeAll(toggle);
            setOpen(toggle, true);
            toggle.dataset.hoverOpened = String(Date.now());
        }
    }, HOVER_OPEN);
});

document.addEventListener('pointerout', (event) => {
    const item = hoverItem(event);

    if (!item) {
        return;
    }

    const toggle = item.querySelector('[data-toggle="dropdown"]');
    clearTimeout(item.hoverTimer);
    item.hoverTimer = setTimeout(() => {
        // والتركيز داخلها بلوحة المفاتيح (:focus-visible) يبقيها مفتوحة، لا تركيز الضغط بالفأرة
        if (!item.querySelector(':focus-visible')) {
            setOpen(toggle, false);
        }
    }, HOVER_CLOSE);
});

// الأسهم في القائمة المنسدلة: السهم السفلي على الزر يفتحها ويركّز أول عنصر، وداخلها السهمان وHome وEnd بين عناصرها
document.addEventListener('keydown', (event) => {
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
        return;
    }

    const toggle = event.target.closest?.('[data-toggle="dropdown"]');

    if (toggle) {
        if (event.key === 'Home' || event.key === 'End') {
            return;
        }

        event.preventDefault();
        closeAll(toggle);
        setOpen(toggle, true);
        const links = dropdownLinks(toggle);
        (event.key === 'ArrowUp' ? links.at(-1) : links[0])?.focus();

        return;
    }

    const menu = event.target.closest?.('[data-dropdown-menu]');

    if (!menu) {
        return;
    }

    const links = [...menu.querySelectorAll('a[href]')];
    const index = links.indexOf(event.target);
    const next = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: links.length - 1 }[event.key];
    event.preventDefault();
    links[(next + links.length) % links.length]?.focus();
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    const open = document.querySelector('[data-toggle][aria-expanded="true"]');

    if (open) {
        setOpen(open, false);
        open.focus();
    }
});

// القائمة المنسدلة تُغلق متى خرج منها التركيز بلوحة المفاتيح، فلا تبقى مفتوحة تغطي ما بعدها. وتركيز الضغط بالمؤشر (لا
// :focus-visible) يُترك للضغط نفسه (مستمع click أعلاه): إغلاقها عند التركيز يزيح أزرار قائمة الجوال قبل أن يصل الضغط إلى
// الزر التالي، فتُطوى الاثنتان ولا تُفتح التي ضُغطت (docs/decisions/2026-11-03-mobile-menu.md)
document.addEventListener('focusin', (event) => {
    if (event.target.matches?.(':focus-visible')) {
        closeAll(event.target);
    }
});

// القائمة المفتوحة تُغلق قبل مغادرة الصفحة، فلا تعود مفتوحة بزر الرجوع
onLeave(() => {
    document.querySelectorAll('[data-toggle][aria-expanded="true"]').forEach((toggle) => setOpen(toggle, false));
});

onPage((signal) => {
    // الرأس ثابت عند التمرير بمقاساته نفسها، فلا سكربت للتمرير (ترتيب صفوان، 2 نوفمبر 2026 — يلغي البند ٢٥)
    const header = document.querySelector('[data-site-header]');

    if (!header) {
        return;
    }

    // قائمة الرأس صف ثانٍ من كبسولات متساوية (القائمة «ج» — أمر التصور النهائي، النسخة 63). فإن لم يتسع اسم عنصر في كبسولته
    // (عناصر أكثر من القياس أو أسماء أطول أو تكبير المتصفح، والقائمة تُدار من اللوحة) صغر خطها وحشوها (data-nav-overflow —
    // concept.css)، ولا تُخفى ولا يحل محلها زر القائمة على الحاسوب (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٣).
    // والرأس عنصر جديد في كل صفحة، والعنصر النشط فيه (aria-current) من الخادم
    const mainNav = header.querySelector('[data-main-nav]');

    if (!mainNav) {
        return;
    }

    // وشارة البث في آخر الصف بنصها متى اتسع، وإلا فنقطة نابضة (data-compact)، ثم الخط الأصغر إن لم يتسع حتى بها
    const liveBadge = mainNav.querySelector('[data-live-badge]');
    const clipped = () => [...mainNav.querySelectorAll('.nav-capsule > .truncate')].some((label) => label.scrollWidth > label.clientWidth + 1);
    const fit = () => {
        header.removeAttribute('data-nav-overflow');
        liveBadge?.removeAttribute('data-compact');
        let overflow = clipped();
        if (overflow && liveBadge) {
            liveBadge.setAttribute('data-compact', '');
            overflow = clipped();
        }
        header.toggleAttribute('data-nav-overflow', mainNav.offsetWidth > 0 && overflow);
    };

    // تغيّر العرض وحده يغيّر الاتساع، فلا قياس مع غيره (إزاحة التخطيط — #116)
    let width = 0;
    const observer = new ResizeObserver(([entry]) => {
        if (Math.round(entry.contentRect.width) !== width) {
            width = Math.round(entry.contentRect.width);
            fit();
        }
    });
    observer.observe(header);
    signal.addEventListener('abort', () => observer.disconnect());
    document.fonts?.ready.then(() => signal.aborted || fit());
});

// نموذج بـ data-confirm يُسأل المستخدم قبل إرساله (إلغاء تسجيل مثلاً). بلا سكربت مضمّن، فتسمح
// به سياسة أمان المحتوى
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// مرساة الرابط (#about-vision وأخواتها في القائمة المنسدلة «عن الجمعية» — إضافة توجيه المدير، 3 نوفمبر 2026، البند ٢٨): بعد التنقّل
// السريع يُمرَّر إلى مقطعها، وإزاحة الرأس الثابت من scroll-padding-top على الصفحة وحدها (كانت معها scroll-mt-48 على المقطع فتتضاعف
// الإزاحة — رابط العرض، 5 نوفمبر 2026). والمرساة المشوّهة (/about#%) لا ترمي URIError توقف التهيئة: تُتجاهل (مراجعة المشرف على #157)
function anchorTarget(hash) {
    if (hash.length <= 1) {
        return null;
    }

    try {
        return document.getElementById(decodeURIComponent(hash.slice(1)));
    } catch {
        return null;
    }
}

// ورابط مرساة في الصفحة نفسها (☰ على الجوال من «عن الجمعية» نفسها): يُغلق القائمة ويُمرَّر فوراً، لا تنقّل سريع إلى الخادم
// يُبقي القائمة مفتوحة ويؤخر النزول ثانية أو اثنتين (رابط العرض، 5 نوفمبر 2026). في مرحلة الالتقاط قبل مستمع wire:navigate على الرابط
document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href*="#"]');

    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') {
        return;
    }

    const url = new URL(link.href, window.location.href);

    if (url.origin !== window.location.origin || url.pathname !== window.location.pathname || url.search !== window.location.search) {
        return;
    }

    const target = anchorTarget(url.hash);

    if (!target) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    document.querySelectorAll('[data-toggle][aria-expanded="true"]').forEach((toggle) => setOpen(toggle, false));
    // حالة Livewire في التاريخ تبقى كما هي، فيعمل زر الرجوع
    window.history.pushState(window.history.state, '', url.hash);
    target.scrollIntoView({ block: 'start' });
}, true);

onPage(() => {
    anchorTarget(window.location.hash)?.scrollIntoView({ block: 'start' });
});

// الرأس يصغر عند التمرير ويعود كاملاً في أعلى الصفحة (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٣١ — يعيد التصغير الذي ألغاه
// صفوان في 2 نوفمبر). من 1024 وحده، والصف الثاني ظاهر في الحالتين. data-header-compact يصغّر الرمز والمخطوطة ومربع الترخيص والأزرار
// والحشو (concept.css)، ويعوَّض الفرق بهامش سفلي للرأس نفسه (--header-shrink) بالانتقال نفسه، فلا يتحرك المحتوى. والفرق يُقاس مرة
// بلا انتقال قبل الرسم، ويُعاد مع تغيّر العرض. وعتبتان متباعدتان (140 ثم 40 بكسل) فلا يرتجف عند الحد. ويُعاد مع كل صفحة (onPage)
onPage((signal) => {
    const header = document.querySelector('[data-site-header]');

    if (!header) {
        return;
    }

    const desktop = window.matchMedia('(min-width: 64rem)');
    const ON = 140;
    const OFF = 40;

    // الفرق بين الحالتين بلا انتقال (data-header-measuring)، في المهمة نفسها فلا يُرسم إطار بينهما
    const measure = () => {
        const compact = header.hasAttribute('data-header-compact');
        header.setAttribute('data-header-measuring', '');
        header.removeAttribute('data-header-compact');
        const full = header.getBoundingClientRect().height;
        header.setAttribute('data-header-compact', '');
        const small = header.getBoundingClientRect().height;
        header.toggleAttribute('data-header-compact', compact);
        header.style.setProperty('--header-shrink', `${Math.max(0, full - small)}px`);
        void header.offsetHeight;
        header.removeAttribute('data-header-measuring');
    };

    let frame = 0;
    const update = () => {
        frame = 0;
        const compact = header.hasAttribute('data-header-compact');
        const y = window.scrollY;

        if (!desktop.matches) {
            header.removeAttribute('data-header-compact');
        } else if (!compact && y > ON) {
            header.setAttribute('data-header-compact', '');
        } else if (compact && y < OFF) {
            header.removeAttribute('data-header-compact');
        }
    };

    const schedule = () => {
        frame ||= requestAnimationFrame(update);
    };

    measure();
    update();
    window.addEventListener('scroll', schedule, { passive: true, signal });

    let width = window.innerWidth;
    window.addEventListener('resize', () => {
        if (window.innerWidth !== width) {
            width = window.innerWidth;
            measure();
            schedule();
        }
    }, { passive: true, signal });
    document.fonts?.ready.then(() => signal.aborted || measure());
    signal.addEventListener('abort', () => frame && cancelAnimationFrame(frame));
});

// بعد تسجيل كل ما يُهيَّأ مع كل صفحة (onPage)، ومنه المرساة وتصغير الرأس أعلاه
start();
