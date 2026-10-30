{{--
    مقطع الشركاء والرعاة (ملاحظة المدير التنفيذي): شعاراتهم بترتيبهم في اللوحة. والمجموعتان بعنوانيهما متى وُجد الراعي
    (BuildHomePage يجلب كلاً منهما باستعلامه). وكل مجموعة شريط يتحرك بلا توقف (التحسين البصري — docs/plans/visual-refresh.md)
    بآلية شريط الأخبار (.ticker في app.css): يتوقف بالمرور والتركيز وبزر الإيقاف (WCAG 2.2.2)، والنسخة الثانية خاملة (inert)
    فلا يصلها قارئ الشاشة ولا لوحة المفاتيح. ولمن طلب تقليل الحركة: بلا حركة، ويُمرَّر باليد.
--}}
@php
    $groups = array_filter(['partners' => $partners, 'sponsors' => $sponsors], fn ($group) => $group->isNotEmpty());
    $grouped = $sponsors->isNotEmpty();
@endphp

<section class="bg-white py-12 lg:py-16" aria-labelledby="home-partners">
    <div class="container-page">
        <x-section-header id="home-partners" :title="__('home.partners.title')" :href="route('media.partners')" />

        @foreach ($groups as $key => $group)
            <div @class(['flex items-center justify-between gap-3', 'mt-8' => $grouped])>
                @if ($grouped)
                    <h3 class="text-lg font-bold text-primary">{{ __('home.partners.'.$key) }}</h3>
                @endif
                <button type="button" data-ticker-toggle="home-partners-{{ $key }}" aria-pressed="false" data-label-pause="{{ __('home.ticker.pause') }}" data-label-play="{{ __('home.ticker.play') }}"
                    aria-label="{{ __('home.ticker.pause') }}" class="ms-auto inline-flex shrink-0 rounded-full p-2 text-muted hover:bg-surface hover:text-ink">
                    {{ \App\Support\Icon::svg('pause', 'size-5', ['data-icon-pause' => true]) }}
                    {{ \App\Support\Icon::svg('play', 'size-5', ['data-icon-play' => true, 'hidden' => true]) }}
                </button>
            </div>

            <div id="home-partners-{{ $key }}" class="ticker mt-4" data-ticker style="--ticker-duration: {{ max(25, $group->count() * 6) }}s">
                <div class="ticker-track">
                    @foreach ([false, true] as $copy)
                        <ul class="ticker-group py-2" @if ($copy) inert aria-hidden="true" data-ticker-copy @endif>
                            @foreach ($group as $partner)
                                <li class="w-40 shrink-0 sm:w-48">@include('media.partner', ['partner' => $partner])</li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
