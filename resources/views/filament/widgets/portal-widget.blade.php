{{-- «بوابتي»: قدرات كل فئة بنصوص المتطلبات، والرابط لما يملك صاحب الحساب فتحه (App\Support\Portals) --}}
<x-filament-widgets::widget>
    @foreach ($portals as $portal)
        <x-filament::section :heading="__('portals.'.$portal['key'].'.title')" :description="__('portals.description')">
            <ul style="display: grid; gap: 0.75rem;">
                @foreach ($portal['items'] as $item)
                    <li>
                        @if ($item['url'])
                            <x-filament::link :href="$item['url']" icon="heroicon-o-arrow-left" icon-position="after">
                                {{ __('portals.'.$portal['key'].'.items.'.$item['key']) }}
                            </x-filament::link>
                        @else
                            <span>{{ __('portals.'.$portal['key'].'.items.'.$item['key']) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endforeach
</x-filament-widgets::widget>
