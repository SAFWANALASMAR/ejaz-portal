// تفاعل الواجهة العامة بلا مكتبات: قائمة الجوال والقوائم المنسدلة.
// كل زر له data-toggle وaria-controls يشير إلى العنصر الذي يفتحه.

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

document.addEventListener('click', (event) => {
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
