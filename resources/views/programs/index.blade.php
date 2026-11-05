{{--
    «الدورات والبرامج» (التصور النهائي — Final-Page، المرحلة ج): الرأس، ثم شريط أيقونات واحد بالأنواع القائمة في لوح فاتح عائم فوق حافته
    (ملاحظات المدير التنفيذي، 4 نوفمبر 2026، ويحل محل التبويبات وبطاقات المحور): كل نوع مربع بأيقونته واسمه كتصنيفات الحوكمة، يُنزل
    إلى مقطعه في الصفحة نفسها بلا صفحة جديدة، و«المسابقات» آخره إلى صفحتها. ثم «كيف تسجّل»، ثم مقطع لكل نوع بشبكة بطاقاته (ثلاثة أعمدة
    على الحاسوب، وعمودان على اللوحي، وعمود على الجوال) و«عرض الكل» متى زادت. وبنوع في الرابط (?type=) قائمته كاملة مرقّمة.
--}}
@php
    $anchor = fn (\App\Models\ProgramType $type): string => 'programs-'.$type->slug;
    $perSection = \App\Http\Controllers\ProgramController::PER_SECTION;
@endphp

<x-layouts.app :title="__('programs.title')">
    <x-page-hero :title="__('programs.title')" />

    <section class="pb-12 sm:pb-16">
        <div class="mx-auto max-w-[82.5rem] px-4 sm:px-7">
            <nav aria-label="{{ __('programs.hub.label') }}" class="tabs-bar relative z-10 -mt-[2.125rem] mb-8" data-programs-bar>
                <ul class="grid grid-cols-3 gap-2 sm:grid-cols-[repeat(auto-fit,minmax(8.5rem,1fr))]">
                    @foreach ($types as $type)
                        {{-- في الصفحة نفسها المرساة وحدها فيمرّر المتصفح إليها بلا تحميل، ومن قائمة نوع إلى مقطعه في الصفحة الأم --}}
                        <li class="tone-card">
                            <a href="{{ $active ? route('programs.index').'#'.$anchor($type) : '#'.$anchor($type) }}" data-programs-bar-item="{{ $type->slug }}"
                                @if ($active?->is($type)) aria-current="page" @endif
                                class="group flex h-full flex-col items-center gap-2 rounded-[1.25rem] p-3 text-center aria-[current=page]:ring-3 aria-[current=page]:ring-[var(--blue-300)] aria-[current=page]:ring-inset sm:p-4">
                                <x-icon-tile :icon="$type->icon()" size="sm" />
                                <span class="text-sm font-black sm:text-base">{{ $type->name }}</span>
                            </a>
                        </li>
                    @endforeach
                    <li class="tone-card">
                        <a href="{{ route('competitions.index') }}" @navigate(route('competitions.index')) data-programs-bar-item="competitions"
                            class="group flex h-full flex-col items-center gap-2 rounded-[1.25rem] p-3 text-center sm:p-4">
                            <x-icon-tile icon="trophy" size="sm" />
                            <span class="text-sm font-black sm:text-base">{{ __('programs.hub.competitions') }}</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="text-center">
                <x-missing :what="__('programs.intro_missing')" />
            </div>

            <x-programs.how-to class="mt-6" />

            {{-- وجهة «اختر برنامجاً» في الدليل: أول المقاطع، والشريط أعلى الصفحة --}}
            <div id="programs-list"></div>

            @if ($active)
                <div class="mt-10">
                    <x-section-header :title="$active->name" :icon="$active->icon()" :href="route('programs.index')" :link-label="__('programs.hub.all')" />
                </div>
                @if ($programs->isEmpty())
                    <x-empty-state :icon="$active->icon()" :message="__('programs.hub.empty')" class="mt-6" />
                @else
                    <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                        @foreach ($programs as $program)
                            <li>@include('programs.card', ['program' => $program])</li>
                        @endforeach
                    </ul>

                    {{ $programs->links('partials.pagination') }}
                @endif
            @else
                {{-- مقطع لكل نوع فيه برنامج منشور (الفارغ لا يظهر — ProgramController)، وبلا نوع حالة فارغة واحدة --}}
                @forelse ($sections as $section)
                    @php($id = $anchor($section['type']))
                    <section id="{{ $id }}" aria-labelledby="{{ $id }}-title" class="mt-12" data-programs-section="{{ $section['type']->slug }}">
                        <x-section-header id="{{ $id }}-title" :title="$section['type']->name" :icon="$section['type']->icon()" :link-context="$section['type']->name"
                            :href="$section['programs']->count() > $perSection ? route('programs.index', ['type' => $section['type']->slug]) : null" />
                        <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                            @foreach ($section['programs']->take($perSection) as $program)
                                <li>@include('programs.card', ['program' => $program])</li>
                            @endforeach
                        </ul>
                    </section>
                @empty
                    <x-empty-state icon="academic-cap" :message="__('programs.list.empty')" class="mt-10" />
                @endforelse
            @endif
        </div>
    </section>
    {{-- أقسام الصفحة من «محرر الصفحات» بعد قائمة البرامج، لأن الشريط يطفو على حافة الرأس --}}
    <x-page-sections />
</x-layouts.app>
