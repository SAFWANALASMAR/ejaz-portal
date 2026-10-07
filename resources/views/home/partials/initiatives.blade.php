{{-- مقطع المبادرات (الصورة 07 — أحدث البرامج والمبادرات): العنوان يميناً و«عرض الكل» يساراً، ثم البطاقات --}}
<section class="bg-sand py-12 lg:py-16" aria-labelledby="home-initiatives">
    <div class="container-page">
        <div class="flex items-end justify-between gap-4">
            <x-section-heading id="home-initiatives">{{ __('content.initiatives.title') }}</x-section-heading>
            <a href="{{ route('initiatives.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                {{ __('site.view_all') }}
                {{ svg('heroicon-o-arrow-left', 'size-4') }}
            </a>
        </div>

        <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($initiatives as $initiative)
                <li>@include('content.initiatives.card', ['initiative' => $initiative])</li>
            @endforeach
        </ul>
    </div>
</section>
