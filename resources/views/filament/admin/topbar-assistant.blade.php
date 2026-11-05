{{-- زر «المساعد الذكي» بجانب البحث العام (ملاحظات صفوان على #166): يفتح المساعد الذكي في الموقع بتبويب جديد
     (resources/js/assistant.js يفتح نافذته على #assistant). وهو مطفأ من «إعدادات المساعد» يبقى ظاهراً معطّلاً «قيد التفعيل»
     (قرار صفوان، 4 نوفمبر 2026) --}}
@if (\App\Models\AssistantSettings::current()->is_enabled)
    <a href="{{ url('/') }}#assistant" target="_blank" rel="noopener" class="ej-topbar-assistant" data-ej-topbar-assistant
        aria-label="{{ __('admin.topbar.ai_search') }}"
        x-data="{}" x-tooltip="{ content: @js(__('admin.topbar.ai_search')), theme: $store.theme }">
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedSparkles, size: \Filament\Support\Enums\IconSize::Medium) }}
    </a>
@else
    <span class="ej-topbar-assistant cursor-not-allowed opacity-60" data-ej-topbar-assistant data-assistant-soon role="img"
        aria-label="{{ __('assistant.header.soon') }}"
        x-data="{}" x-tooltip="{ content: @js(__('assistant.header.soon')), theme: $store.theme }">
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedSparkles, size: \Filament\Support\Enums\IconSize::Medium) }}
    </span>
@endif
