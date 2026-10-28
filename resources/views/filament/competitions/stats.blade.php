{{-- لوحة متابعة المسابقة: تتحدث كل دقيقة، والأرقام من CompetitionStats (مخزنة دقيقة). ومسابقة الطلاب بتسمياتها: محاولات الفروع --}}
@php($stats = $this->getStats())
@php($prefix = $this->getRecord()->student_mode ? 'student_' : '')

<x-filament-panels::page>
    <div wire:poll.60s class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach (['registered', 'drafts', 'submitted'] as $key)
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('competitions.stats.'.$prefix.$key) }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-950 dark:text-white">{{ number_format($stats[$key]) }}</p>
                </x-filament::section>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach (['categories', 'regions'] as $group)
                <x-filament::section :heading="__('competitions.stats.'.$prefix.'by_'.$group)">
                    @if ($stats[$group] === [])
                        <p class="text-sm text-gray-500">{{ __('competitions.stats.'.$prefix.'empty') }}</p>
                    @else
                        <ul class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($stats[$group] as $label => $count)
                                <li class="flex items-center justify-between py-2 text-sm">
                                    <span class="text-gray-950 dark:text-white">{{ $label }}</span>
                                    <span class="font-semibold text-gray-950 dark:text-white">{{ number_format($count) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        <p class="text-xs text-gray-500">{{ __('competitions.stats.refresh') }}</p>
    </div>
</x-filament-panels::page>
