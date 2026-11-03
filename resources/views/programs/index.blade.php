{{--
    «الدورات والبرامج» (التصور النهائي — Final-Page، المرحلة ج): الرأس، ثم التبويبات بالأنواع القائمة في لوح فاتح عائم فوق حافته
    (الكبسولات بيضاء والنشطة داكنة)، ثم بطاقات المحور ملونة، ثم «كيف تسجّل» بخطوات ملونة، ثم شبكة بطاقات ملونة بثلاثة أعمدة على الحاسوب، وعمودين على اللوحي، وعمود على الجوال. والعنوان
    الفرعي والوصف من الجمعية (التصحيحات ٣).
--}}
<x-layouts.app :title="__('programs.title')">
    <x-page-hero :title="__('programs.title')" />

    <section class="pb-12 sm:pb-16">
        <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
            <nav aria-label="{{ __('programs.tabs.label') }}" class="tabs-bar relative z-10 -mt-[2.125rem] mb-8 overflow-x-auto">
                <ul class="flex w-max gap-2">
                    <li>
                        <a href="{{ route('programs.index') }}" wire:navigate @if (! $active) aria-current="page" @endif
                            class="tab-pill">
                            {{ __('programs.tabs.all') }}
                        </a>
                    </li>
                    @foreach ($types as $type)
                        <li>
                            <a href="{{ route('programs.index', ['type' => $type->slug]) }}" wire:navigate @if ($active?->is($type)) aria-current="page" @endif
                                class="tab-pill">
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
                            <a href="{{ $entry['url'] }}" @navigate($entry['url']) class="tone-card group flex h-full items-center gap-4 p-5">
                                <x-icon-tile :icon="$entry['icon']" size="md" />
                                <span class="text-lg font-black">{{ $entry['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <x-programs.how-to class="mt-6" />

            {{-- وجهة «اختر برنامجاً» في الدليل: أول القائمة، والتبويبات بالأنواع في الشريط العائم أعلى الصفحة --}}
            <div id="programs-list" class="scroll-mt-28"></div>

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
    {{-- أقسام الصفحة من «محرر الصفحات» بعد قائمة البرامج، لأن شريط التبويبات يطفو على حافة الرأس --}}
    <x-page-sections />
</x-layouts.app>
