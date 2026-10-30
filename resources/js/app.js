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
import './home';

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
        const open = toggle.getAttribute('aria-expanded') !== 'true';

        if (toggle.dataset.toggle === 'dropdown') {
            closeAll(toggle);
        }

        setOpen(toggle, open);

        return;
    }

    closeAll();
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

// القائمة المنسدلة تُغلق متى خرج منها التركيز بلوحة المفاتيح، فلا تبقى مفتوحة تغطي ما بعدها
document.addEventListener('focusin', (event) => closeAll(event.target));

// القائمة المفتوحة تُغلق قبل مغادرة الصفحة، فلا تعود مفتوحة بزر الرجوع
onLeave(() => {
    document.querySelectorAll('[data-toggle][aria-expanded="true"]').forEach((toggle) => setOpen(toggle, false));
});

onPage((signal) => {
    // ظل الرأس عند التمرير
    const header = document.querySelector('[data-site-header]');

    if (!header) {
        return;
    }

    const onScroll = () => header.toggleAttribute('data-scrolled', window.scrollY > 4);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true, signal });

    // قائمة الرأس صف واحد أو زر القائمة: إن لم تتسع عناصرها بهامش مريح (قائمة أطول من القياس، أو شارة البث)
    // ظهر زر القائمة بدلها، فلا تنكسر عبارة ولا تتداخل مع الشعار وزر الدخول. والقائمة تُدار من اللوحة.
    // والرأس عنصر جديد في كل صفحة، والعنصر النشط فيه (aria-current) من الخادم
    const mainNav = header.querySelector('[data-main-nav]');

    if (!mainNav) {
        return;
    }

    const list = mainNav.querySelector('ul');
    const fit = () => {
        header.removeAttribute('data-nav-overflow');
        header.toggleAttribute('data-nav-overflow', mainNav.offsetWidth > 0 && list.offsetWidth + 24 > mainNav.clientWidth);
    };

    const observer = new ResizeObserver(fit);
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

start();
