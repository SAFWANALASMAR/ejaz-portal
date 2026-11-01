// صورة البطل أصغر من مساحتها (صور الأرشيف المنقولة نحو 500 إلى 800 بكسل في بطل بعرض 1440): لا تُمدّ فتضبب، بل تُعرض كاملة
// بمقاسها (scale-down، لا تكبير) في جهة النهاية فوق نسخة منها مموّهة تملأ الخلفية (concept.css: data-fit-small)، وكذلك مصغّرات
// المعرض في مربعاتها (data-fit-frame). والصورة الكبيرة تبقى مغطية مساحتها.
// ملاحظة صفوان، 1 نوفمبر 2026 (docs/decisions/2026-11-01-visual-refresh-2-followups.md).
import { onPage } from './page';

function check(img) {
    if (!img.naturalWidth || img.clientWidth < 2) {
        return;
    }

    const small = img.naturalWidth < img.clientWidth * 0.95 || img.naturalHeight < img.clientHeight * 0.95;
    img.toggleAttribute('data-fit-small', small);

    if (small) {
        img.parentElement?.style.setProperty('--fit-backdrop', `url("${(img.currentSrc || img.src).replace(/"/g, '%22')}")`);
    }
}

onPage((signal) => {
    const images = [...document.querySelectorAll('img[data-fit-adaptive]')];

    images.forEach((img) => {
        if (img.complete) {
            check(img);
        } else {
            img.addEventListener('load', () => check(img), { once: true, signal });
        }
    });

    let timer = null;
    window.addEventListener('resize', () => {
        clearTimeout(timer);
        timer = setTimeout(() => images.forEach(check), 150);
    }, { signal });
});
