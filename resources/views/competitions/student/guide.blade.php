{{--
    دليل الدخول إلى الاختبار: صفحة عامة من نصوص المسابقة في اللوحة (page_content)، بأقسامها الأربعة. والخطوات
    شرائح (x-competitions.guide-slides)، وبقية الأقسام نقاط. ورابط حالة المحاولة في صفحة المسابقة لكل مرحلة.
--}}
@php
    use App\Support\CompetitionPage;
@endphp

<x-layouts.app :title="__('competitions.student.page.guide_title').' — '.$competition->title">
    <x-page-hero :title="__('competitions.student.page.guide_title')" />

    <section class="py-10 sm:py-14">
        <div class="container-page max-w-3xl space-y-10">
            <p class="text-lg font-semibold text-ink">{{ $competition->title }}</p>

            @foreach (CompetitionPage::GUIDE as $key)
                @php($items = $competition->pageList($key))
                @if ($items !== [])
                    <div>
                        <x-section-heading>{{ __('competitions.student.page.'.$key) }}</x-section-heading>
                        @if ($key === 'guide_steps')
                            <x-competitions.guide-slides :steps="$items" class="mt-4" />
                        @else
                            <ul class="mt-4 list-disc space-y-2 ps-6 leading-8 text-ink">
                                @foreach ($items as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            @endforeach

            <x-button :href="route('competitions.show', $competition->slug)" variant="outline" arrow>{{ $competition->title }}</x-button>
        </div>
    </section>
</x-layouts.app>
