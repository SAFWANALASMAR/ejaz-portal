{{--
    «الدورات والبرامج» (الصورة 06 — الحاسوب): رأس في المنتصف، ثم بطاقات المحور الثلاث، ثم تبويبات كبسولية بالأنواع (النشط أخضر)،
    ثم شبكة بطاقات بثلاثة أعمدة على الحاسوب، وعمودين على اللوحي، وعمود على الجوال. والعنوان
    الفرعي والوصف من الجمعية (التصحيحات ٣).
--}}
<x-layouts.app :title="__('programs.title')">
    <x-page-hero :title="__('programs.title')" />

    <section class="mesh-light py-12 sm:py-16">
        <div class="container-page">
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

            <nav aria-label="{{ __('programs.tabs.label') }}" class="mx-auto mt-8 max-w-full overflow-x-auto">
                <ul class="mx-auto flex w-max gap-2 rounded-full border border-line/70 bg-white p-1.5 shadow-xs">
                    <li>
                        <a href="{{ route('programs.index') }}" wire:navigate @if (! $active) aria-current="page" @endif
                            class="block rounded-full px-5 py-2 text-sm font-semibold whitespace-nowrap text-muted transition hover:text-primary aria-[current=page]:bg-primary aria-[current=page]:text-white">
                            {{ __('programs.tabs.all') }}
                        </a>
                    </li>
                    @foreach ($types as $type)
                        <li>
                            <a href="{{ route('programs.index', ['type' => $type->slug]) }}" wire:navigate @if ($active?->is($type)) aria-current="page" @endif
                                class="block rounded-full px-5 py-2 text-sm font-semibold whitespace-nowrap text-muted transition hover:text-primary aria-[current=page]:bg-primary aria-[current=page]:text-white">
                                {{ $type->name }}
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
