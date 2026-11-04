{{-- زر «البحث بالذكاء الاصطناعي» بجانب البحث العام (ملاحظات صفوان على #166): يفتح المساعد الذكي في الموقع بتبويب جديد
     (resources/js/assistant.js يفتح نافذته على #assistant)، ولا يظهر إن عُطّل المساعد من «إعدادات المساعد» --}}
@if (\App\Models\AssistantSettings::current()->is_enabled)
    <a href="{{ url('/') }}#assistant" target="_blank" rel="noopener" class="ej-topbar-assistant" data-ej-topbar-assistant
        aria-label="{{ __('admin.topbar.ai_search') }}"
        x-data="{}" x-tooltip="{ content: @js(__('admin.topbar.ai_search')), theme: $store.theme }">
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedSparkles, size: \Filament\Support\Enums\IconSize::Medium) }}
    </a>
@endif
