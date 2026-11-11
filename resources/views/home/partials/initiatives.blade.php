{{-- مقطع المبادرات (الصورة 07 — أحدث البرامج والمبادرات): العنوان يميناً و«عرض الكل» يساراً، ثم البطاقات --}}
<section class="home-band py-12 lg:py-16" data-band="2" aria-labelledby="home-initiatives">
    <div class="container-page">
        <x-section-header id="home-initiatives" :title="__('content.initiatives.title')" :href="route('initiatives.index')" />

        {{-- مُظهر من الإعدادات بلا مبادرة منشورة: بحالته الفارغة (قرار صفوان، 11 نوفمبر 2026) --}}
        @if ($initiatives->isEmpty())
            <x-empty-state icon="sparkles" :message="__('home.empty.initiatives')" class="mt-8" data-home-empty="initiatives" />
        @else
        <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
            @foreach ($initiatives as $initiative)
                <li>@include('content.initiatives.card', ['initiative' => $initiative])</li>
            @endforeach
        </ul>
        @endif
    </div>
</section>
