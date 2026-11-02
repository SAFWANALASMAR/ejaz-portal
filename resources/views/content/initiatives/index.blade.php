{{-- قائمة المبادرات المنشورة بترتيبها في اللوحة (تُخزَّن نصاً — InitiativeController) --}}
<x-page-hero :title="__('content.initiatives.title')" />
<x-page-sections />

<section class="py-12 sm:py-16">
    <div class="container-page">
        @if ($initiatives->isEmpty())
            <x-empty-state icon="light-bulb" :message="__('content.initiatives.empty')" />
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($initiatives as $initiative)
                    <li>@include('content.initiatives.card', ['initiative' => $initiative])</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
