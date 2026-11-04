{{-- «بوابتي»: قدرات كل فئة بنصوص المتطلبات، والرابط لما يملك صاحب الحساب فتحه (App\Support\Portals).
     وكل قدرة بطاقة بأيقونة شاشتها ودائرة سهم (الدفعة ٢ من طلب لوحة الإدارة) --}}
<x-filament-widgets::widget>
    @foreach ($portals as $portal)
        <x-filament::section :heading="__('portals.'.$portal['key'].'.title')" :description="__('portals.description')" icon="heroicon-o-key" class="ej-portal" data-ej-portal>
            <ul class="ej-portal-grid">
                @foreach ($portal['items'] as $item)
                    <li>
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" class="ej-portal-card" data-ej-portal-item>
                                <span class="ej-portal-icon" data-ej-portal-icon aria-hidden="true">{{ \Filament\Support\generate_icon_html($item['icon'], size: \Filament\Support\Enums\IconSize::Large) }}</span>
                                <span class="ej-portal-label">{{ __('portals.'.$portal['key'].'.items.'.$item['key']) }}</span>
                                <span class="ej-portal-arrow" aria-hidden="true">{{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowLeft, size: \Filament\Support\Enums\IconSize::Small) }}</span>
                            </a>
                        @else
                            <div class="ej-portal-card ej-portal-card-static" data-ej-portal-item>
                                <span class="ej-portal-icon" data-ej-portal-icon aria-hidden="true">{{ \Filament\Support\generate_icon_html($item['icon'], size: \Filament\Support\Enums\IconSize::Large) }}</span>
                                <span class="ej-portal-label">{{ __('portals.'.$portal['key'].'.items.'.$item['key']) }}</span>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endforeach
</x-filament-widgets::widget>
