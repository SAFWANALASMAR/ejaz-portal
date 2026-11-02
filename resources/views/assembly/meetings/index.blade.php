{{--
    الاجتماعات في منطقة الأعضاء: القادمة ثم السابقة، بطاقات الصورة 05 (الجوال) في شبكة.
--}}
<x-layouts.app :title="__('assembly.meetings.title')" noindex>
    <x-page-hero :title="__('assembly.meetings.title')" />

    <section class="band-sky py-12 sm:py-16">
        <div class="container-page">
            <h2 class="text-xl font-bold text-ink">{{ __('assembly.meetings.upcoming') }}</h2>

            @if ($upcoming->isEmpty())
                <x-empty-state icon="calendar-days" :message="__('assembly.meetings.empty')" class="mt-6" />
            @else
                <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($upcoming as $meeting)
                        <li>@include('assembly.meetings.card', ['meeting' => $meeting])</li>
                    @endforeach
                </ul>
            @endif

            <h2 class="mt-14 text-xl font-bold text-ink">{{ __('assembly.meetings.past') }}</h2>

            @if ($past->isEmpty())
                <x-empty-state icon="calendar-days" :message="__('assembly.meetings.past_empty')" class="mt-6" />
            @else
                <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($past as $meeting)
                        <li>@include('assembly.meetings.card', ['meeting' => $meeting])</li>
                    @endforeach
                </ul>

                {{ $past->links('partials.pagination') }}
            @endif
        </div>
    </section>
</x-layouts.app>
