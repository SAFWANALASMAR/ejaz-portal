{{-- المستندات ذات الصلاحية: لكل نوع عدد المنتهي والقريب من الانتهاء، وأقربها انتهاءً (RegistersOverviewWidget).
     والعنوان بمربع أيقونة، والصندوق بتدرج أزرق فاتح (ej-registers-box — البندان N2 وG2 من تدقيق القبول) --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('registers.widget.heading')" icon="heroicon-o-document-check" class="ej-registers-box">
        @if ($data['counts'] === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('registers.widget.none') }}</p>
        @else
            <div class="grid gap-6 lg:grid-cols-2">
                <ul class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($data['counts'] as $row)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <span class="text-gray-950 dark:text-white">{{ $row['label'] }}</span>
                            <span class="flex gap-2">
                                <x-filament::badge color="danger">{{ __('registers.widget.expired') }}: {{ number_format($row['expired']) }}</x-filament::badge>
                                <x-filament::badge color="warning">{{ __('registers.widget.expiring') }}: {{ number_format($row['expiring']) }}</x-filament::badge>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <div>
                    <p class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">{{ __('registers.widget.nearest') }}</p>
                    <ul class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($data['nearest'] as $entry)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <a href="{{ \App\Filament\Resources\RegisterEntries\RegisterEntryResource::getUrl('view', ['record' => $entry]) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $entry->title }}</a>
                                <span class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                    <span>{{ $entry->expires_on?->format('Y-m-d') }}</span>
                                    <x-filament::badge :color="$entry->status()->getColor()">{{ $entry->status()->getLabel() }}</x-filament::badge>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
