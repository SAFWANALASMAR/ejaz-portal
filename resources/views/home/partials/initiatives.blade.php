{{-- مقطع المبادرات (الصورة 07 — أحدث البرامج والمبادرات): العنوان يميناً و«عرض الكل» يساراً، ثم البطاقات --}}
<section class="bg-surface py-12 lg:py-16" aria-labelledby="home-initiatives">
    <div class="container-page">
        <x-section-header id="home-initiatives" :title="__('content.initiatives.title')" :href="route('initiatives.index')" />

        <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($initiatives as $initiative)
                <li>@include('content.initiatives.card', ['initiative' => $initiative])</li>
            @endforeach
        </ul>
    </div>
</section>
