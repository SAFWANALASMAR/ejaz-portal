{{--
    «الدورات والبرامج» (الصورة 06 — الحاسوب): رأس في المنتصف، ثم بطاقات المحور الثلاث، ثم تبويبات كبسولية بالأنواع (النشط أخضر)،
    ثم شبكة بطاقات بثلاثة أعمدة على الحاسوب، وعمودين على اللوحي، وعمود على الجوال. والعنوان
    الفرعي والوصف من الجمعية (التصحيحات ٣).
--}}
<x-layouts.app :title="__('programs.title')">
    <section class="bg-surface/60 py-12 sm:py-16">
        <div class="container-page">
            <div class="text-center">
                <span class="mx-auto block h-1 w-12 rounded-full bg-accent" aria-hidden="true"></span>
                <h1 class="mt-3 text-3xl font-bold text-primary sm:text-4xl">{{ __('programs.title') }}</h1>
                <x-missing :what="__('programs.intro_missing')" class="mt-4" />
            </div>

            {{-- محور «الدورات والبرامج» (ملاحظة المدير التنفيذي): الدورات التدريبية، والمجالس العلمية، والمسابقات --}}
            <nav aria-label="{{ __('programs.hub.label') }}" class="mt-10">
                <ul class="grid gap-4 sm:grid-cols-3" data-reveal-group>
                    @foreach ($hub as $entry)
                        <li>
                            <a href="{{ $entry['url'] }}" @navigate($entry['url']) class="group flex h-full items-center gap-4 card-lift p-5 hover:border-brand hover:shadow-md">
                                <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary transition group-hover:bg-primary group-hover:text-white" aria-hidden="true">
                                    {{ \App\Support\Icon::svg($entry['icon'], 'size-6') }}
                                </span>
                                <span class="text-lg font-bold text-ink">{{ $entry['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <x-programs.how-to class="mt-10" />

            <nav id="programs-list" aria-label="{{ __('programs.tabs.label') }}" class="mx-auto mt-10 max-w-full scroll-mt-28 overflow-x-auto">
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
                <p class="mt-10 text-center text-muted">{{ __('programs.list.empty') }}</p>
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
