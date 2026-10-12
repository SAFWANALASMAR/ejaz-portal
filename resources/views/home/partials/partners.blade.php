{{--
    مقطع الشركاء والرعاة (ملاحظة المدير التنفيذي): شعاراتهم بترتيبهم في اللوحة. والمجموعتان بعنوانيهما
    متى وُجد الراعي (BuildHomePage يجلب كلاً منهما باستعلامه).
--}}
@php
    $groups = array_filter(['partners' => $partners, 'sponsors' => $sponsors], fn ($group) => $group->isNotEmpty());
    $grouped = $sponsors->isNotEmpty();
@endphp

<section class="bg-white py-12 lg:py-16" aria-labelledby="home-partners">
    <div class="container-page">
        <x-section-header id="home-partners" :title="__('home.partners.title')" :href="route('media.partners')" />

        @foreach ($groups as $key => $group)
            @if ($grouped)
                <h3 class="mt-8 text-lg font-bold text-primary">{{ __('home.partners.'.$key) }}</h3>
            @endif
            <ul @class(['grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6', 'mt-4' => $grouped, 'mt-8' => ! $grouped])>
                @foreach ($group as $partner)
                    <li>@include('media.partner', ['partner' => $partner])</li>
                @endforeach
            </ul>
        @endforeach
    </div>
</section>
