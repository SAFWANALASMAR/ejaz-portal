// تفاعل الواجهة العامة بلا مكتبات: قائمة الجوال، والقوائم المنسدلة، وإظهار كلمة المرور.
// كل زر قائمة له data-toggle وaria-controls يشير إلى العنصر الذي يفتحه.

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
    link.replaceWith(frame);
    frame.focus();
}

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

// ظل الرأس عند التمرير
const header = document.querySelector('[data-site-header]');

if (header) {
    const onScroll = () => header.toggleAttribute('data-scrolled', window.scrollY > 4);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}
