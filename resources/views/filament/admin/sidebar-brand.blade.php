{{-- شعار الهوية الأبيض في أعلى الشريط الجانبي الداكن: الرمز ثم المخطوطة الأكبر (سمة اللوحة، docs/decisions/2026-10-30-admin-theme.md) --}}
{{-- واسم الرابط عليه: في الشريط المطوي (والجوال قبل فتحه) تختفي المخطوطة ويبقى الرمز بلا نص بديل (فحص الوصول 0.15، axe: link-name) --}}
{{-- ومنذ الدفعة ١ من طلب لوحة الإدارة (4 نوفمبر 2026) الهوية في الشريط العلوي على الحاسوب، فهذا للقائمة المنسدلة على الجوال وحدها،
     ومعه زر إغلاقها: كانت تغطي زر الإغلاق في الشريط العلوي فلا تُغلق إلا بالنقر خارجها (shell.css) --}}
<div class="ej-sidebar-brand-row">
    <a href="{{ filament()->getUrl() }}" class="ej-sidebar-brand" aria-label="{{ __('admin.theme.logo_alt') }}">
        <img src="{{ asset('images/brand/logo-mark-white.png') }}" alt="" class="ej-sidebar-brand-mark" width="495" height="505">
        <img src="{{ asset('images/brand/logo-calligraphy-white.png') }}" alt="{{ __('admin.theme.logo_alt') }}" class="ej-sidebar-brand-calligraphy" width="1219" height="185">
    </a>
    <button type="button" class="ej-sidebar-close" data-ej-sidebar-close x-data="{}" x-on:click="$store.sidebar.close()" aria-controls="fi-main-sidebar"
        aria-label="{{ __('filament-panels::layout.actions.sidebar.collapse.label') }}">
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedXMark, size: \Filament\Support\Enums\IconSize::Medium) }}
    </button>
</div>
