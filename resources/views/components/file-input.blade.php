{{--
    حقل رفع ملف بنص عربي (طلب المشرف، 4 نوفمبر 2026): المتصفح يكتب «Choose File» و«No file chosen» بلغته لا بلغة الصفحة.
    فالحقل الأصلي باقٍ بخصائصه وسلوكه كلها (الاسم والقبول والإلزام والوصف والتركيز وقارئ الشاشة وما يعلّقه به JavaScript)،
    شفافاً فوق زر ونص من lang/ar. واسم الملف المختار يكتبه resources/js/file-input.js، بلا Alpine (لا unsafe-eval في الواجهة العامة).
    والخصائص كلها للحقل، وbox أصناف الغلاف. وvariant: soft (فاتح) أو solid (أزرق بنص أبيض).
--}}
@props(['variant' => 'soft', 'box' => ''])

<span data-file-input @class([
    'relative flex min-h-11 w-full items-center gap-3 rounded-xl border border-line bg-white px-3 py-2',
    'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand has-[:focus-visible]:ring-offset-1',
    // أثناء رفع المقطع يعطّل competition-entry.js الحقل
    'has-[:disabled]:opacity-60',
    $box,
])>
    <span aria-hidden="true" @class([
        'shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold',
        'bg-primary-soft text-primary' => $variant !== 'solid',
        'bg-primary text-white' => $variant === 'solid',
    ])>{{ __('site.file_input.choose') }}</span>
    <span data-file-name data-empty="{{ __('site.file_input.none') }}" aria-hidden="true" class="min-w-0 truncate text-sm text-muted">{{ __('site.file_input.none') }}</span>
    {{-- title بالعربية: وإلا أظهر المتصفح «No file chosen» تلميحاً عند المرور فوق الحقل الشفاف --}}
    <input type="file" title="{{ __('site.file_input.choose') }}" {{ $attributes->class('absolute inset-0 size-full cursor-pointer opacity-0') }}>
</span>
