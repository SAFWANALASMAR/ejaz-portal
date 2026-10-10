// معرض الصور: كل [data-gallery] شبكة صور وعارض <dialog>. يفتح العارض على الصورة المضغوطة، وينتقل
// بالزرين وبالأسهم (في الاتجاه من اليمين: السهم الأيسر للتالية)، وEsc يغلقه (سلوك <dialog> نفسه).

function setupGallery(root) {
    const dialog = root.querySelector('[data-gallery-dialog]');
    const image = dialog?.querySelector('[data-gallery-image]');

    if (!dialog || !image) {
        return;
    }

    const items = [...root.querySelectorAll('[data-gallery-open] img')].map((img) => ({ src: img.src, alt: img.alt }));
    const caption = dialog.querySelector('[data-gallery-caption]');
    const counter = dialog.querySelector('[data-gallery-counter]');
    let current = 0;

    const show = (index) => {
        current = (index + items.length) % items.length;
        image.src = items[current].src;
        image.alt = items[current].alt;
        caption.textContent = items[current].alt;
        counter.textContent = dialog.dataset.counter.replace(':current', current + 1).replace(':total', items.length);
    };

    root.querySelectorAll('[data-gallery-open]').forEach((button) => {
        button.addEventListener('click', () => {
            show(Number(button.dataset.galleryOpen));
            dialog.showModal();
        });
    });

    dialog.querySelector('[data-gallery-close]')?.addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-gallery-next]')?.addEventListener('click', () => show(current + 1));
    dialog.querySelector('[data-gallery-prev]')?.addEventListener('click', () => show(current - 1));

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            show(current + 1);
        } else if (event.key === 'ArrowRight') {
            show(current - 1);
        }
    });

    // الضغط على الخلفية خارج الصورة يغلق العارض
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog || event.target.tagName === 'FIGURE') {
            dialog.close();
        }
    });
}

document.querySelectorAll('[data-gallery]').forEach(setupGallery);
