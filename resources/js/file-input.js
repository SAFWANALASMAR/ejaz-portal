// اسم الملف المختار في حقل الرفع العربي (resources/views/components/file-input.blade.php). مستمع واحد على المستند، فيبقى
// بعد التنقل السريع (wire:navigate) بلا إعادة تهيئة. وإفراغ الحقل (رفع المقطع ثم مسحه) يعيد النص «لم يُختر ملف».
document.addEventListener('change', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.type !== 'file') {
        return;
    }

    const name = input.closest('[data-file-input]')?.querySelector('[data-file-name]');

    if (!name) {
        return;
    }

    const files = Array.from(input.files ?? []).map((file) => file.name);
    name.textContent = files.length > 0 ? files.join('، ') : name.dataset.empty;
});
