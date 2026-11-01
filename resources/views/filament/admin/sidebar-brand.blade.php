{{-- شعار الهوية الأبيض في أعلى الشريط الجانبي الداكن: الرمز ثم المخطوطة الأكبر (سمة اللوحة، docs/decisions/2026-10-30-admin-theme.md) --}}
<a href="{{ filament()->getUrl() }}" class="ej-sidebar-brand">
    <img src="{{ asset('images/brand/logo-mark-white.png') }}" alt="" class="ej-sidebar-brand-mark" width="495" height="505">
    <img src="{{ asset('images/brand/logo-calligraphy-white.png') }}" alt="{{ __('admin.theme.logo_alt') }}" class="ej-sidebar-brand-calligraphy" width="1219" height="185">
</a>
