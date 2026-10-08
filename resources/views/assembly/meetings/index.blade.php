{{--
    الاجتماعات في منطقة الأعضاء: القادمة ثم السابقة، بطاقات الصورة 05 (الجوال) في شبكة.
--}}
<x-layouts.app :title="__('assembly.meetings.title')" noindex>
    <section class="bg-sand/60 py-12 sm:py-16">
        <div class="container-page">
            <x-section-heading>{{ __('assembly.meetings.upcoming') }}</x-section-heading>

            @if ($upcoming->isEmpty())
                <p class="mt-6 text-muted">{{ __('assembly.meetings.empty') }}</p>
            @else
                <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($upcoming as $meeting)
                        <li>@include('assembly.meetings.card', ['meeting' => $meeting])</li>
                    @endforeach
                </ul>
            @endif

            <x-section-heading class="mt-14">{{ __('assembly.meetings.past') }}</x-section-heading>

            @if ($past->isEmpty())
                <p class="mt-6 text-muted">{{ __('assembly.meetings.past_empty') }}</p>
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
