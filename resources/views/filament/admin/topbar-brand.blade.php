{{-- هوية الموقع في الشريط العلوي للوحة (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ١): رمز الشعار ثم المخطوطة بالأبيض على تدرج
     الترويسة الداكن، كرأس الموقع العام. رابط إلى رئيسية اللوحة باسم الجمعية، فلا يبقى في ترتيب التركيز بلا اسم (axe: link-name).
     وعلى الجوال الضيق تصغر المخطوطة ولا تختفي (shell.css) --}}
<a href="{{ filament()->getUrl() }}" class="ej-topbar-brand" data-ej-topbar-brand aria-label="{{ __('admin.theme.logo_alt') }}">
    <img src="{{ asset('images/brand/logo-mark-white.png') }}" alt="" class="ej-topbar-brand-mark" data-ej-brand-mark width="495" height="505">
    <span class="ej-topbar-brand-rule" aria-hidden="true"></span>
    <img src="{{ asset('images/brand/logo-calligraphy-white.png') }}" alt="" class="ej-topbar-brand-calligraphy" data-ej-brand-calligraphy width="1219" height="185">
</a>
