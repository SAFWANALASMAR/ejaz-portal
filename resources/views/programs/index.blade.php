{{--
    «الدورات والبرامج» (التصور النهائي — Final-Page): الرأس، ثم التبويبات بالأنواع شريطاً عائماً فوق حافته (النشط داكن)، ثم بطاقات المحور،
    ثم شبكة بطاقات بثلاثة أعمدة على الحاسوب، وعمودين على اللوحي، وعمود على الجوال. والعنوان
    الفرعي والوصف من الجمعية (التصحيحات ٣).
--}}
<x-layouts.app :title="__('programs.title')">
    <x-page-hero :title="__('programs.title')" />

    <section class="pb-12 sm:pb-16">
        <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
            <nav aria-label="{{ __('programs.tabs.label') }}" class="relative z-10 -mt-[2.125rem] mb-8 overflow-x-auto rounded-[1.375rem] bg-white p-2.5 shadow-[0_18px_40px_-22px_rgba(31,69,101,.45)] ring-1 ring-[var(--concept-border)]">
                <ul class="flex w-max gap-2">
                    <li>
                        <a href="{{ route('programs.index') }}" wire:navigate @if (! $active) aria-current="page" @endif
                            class="block rounded-full border border-[var(--concept-border)] px-5 py-2.5 text-[15px] font-extrabold whitespace-nowrap text-primary-dark transition hover:border-[var(--concept-border-hover)] aria-[current=page]:border-primary-dark aria-[current=page]:bg-primary-dark aria-[current=page]:text-white">
                            {{ __('programs.tabs.all') }}
                        </a>
                    </li>
                    @foreach ($types as $type)
                        <li>
                            <a href="{{ route('programs.index', ['type' => $type->slug]) }}" wire:navigate @if ($active?->is($type)) aria-current="page" @endif
                                class="block rounded-full border border-[var(--concept-border)] px-5 py-2.5 text-[15px] font-extrabold whitespace-nowrap text-primary-dark transition hover:border-[var(--concept-border-hover)] aria-[current=page]:border-primary-dark aria-[current=page]:bg-primary-dark aria-[current=page]:text-white">
                                {{ $type->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="text-center">
                <x-missing :what="__('programs.intro_missing')" />
            </div>

            {{-- محور «الدورات والبرامج» (ملاحظة المدير التنفيذي): الدورات التدريبية، والمجالس العلمية، والمسابقات --}}
            <nav aria-label="{{ __('programs.hub.label') }}" class="mt-10">
                <ul class="grid gap-4 sm:grid-cols-3" data-reveal-group>
                    @foreach ($hub as $entry)
                        <li>
                            <a href="{{ $entry['url'] }}" @navigate($entry['url']) class="group flex h-full items-center gap-4 card-lift p-5 hover:border-brand hover:shadow-md">
                                <x-icon-tile :icon="$entry['icon']" :index="$loop->index" size="md" />
                                <span class="text-lg font-bold text-ink">{{ $entry['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>



            @if ($programs->isEmpty())
                <x-empty-state icon="academic-cap" :message="__('programs.list.empty')" class="mt-10" />
            @else
                <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                    @foreach ($programs as $program)
                        <li>@include('programs.card', ['program' => $program])</li>
                    @endforeach
                </ul>

                {{ $programs->links('partials.pagination') }}
            @endif
        </div>
    </section>
</x-layouts.app>
