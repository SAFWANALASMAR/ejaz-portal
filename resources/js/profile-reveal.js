// زر «إظهار البيانات» في ملخص «بياناتي» (account/partials/profile-summary): يبدّل بين القيمة المخفية والكاملة في المتصفح
// وحده، ويحدّث aria-expanded ونص الزر. بالتفويض على المستند، فيعمل بعد كل تنقّل سريع بلا تهيئة.

document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-reveal-toggle]');

    if (!button) {
        return;
    }

    const list = document.getElementById(button.getAttribute('aria-controls'));
    const reveal = button.getAttribute('aria-expanded') !== 'true';

    button.setAttribute('aria-expanded', String(reveal));
    button.querySelector('[data-reveal-label]').textContent = reveal ? button.dataset.labelHide : button.dataset.labelShow;

    if (list) {
        list.dataset.revealed = String(reveal);
        list.querySelectorAll('[data-masked]').forEach((element) => (element.hidden = reveal));
        list.querySelectorAll('[data-full]').forEach((element) => (element.hidden = !reveal));
    }
});
