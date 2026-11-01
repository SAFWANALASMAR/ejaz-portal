{{-- الإعلانات: المنشور في مدة عرضه بترتيبه في اللوحة، بتصفية بنوعه (تُخزَّن نصاً لكل نوع — MediaController). والعنوان والمقدمة
     من «محتوى الرئيسية». ولا وسم «مثال تجريبي» للعامة (قرار صفوان، 29 أكتوبر 2026)، فيبقى في اللوحة وحدها --}}
<x-page-hero :title="\App\Support\HomeContent::text('announcements', 'title')" :summary="\App\Support\HomeContent::text('announcements', 'intro')" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        <nav aria-label="{{ __('announcements.list.filter') }}">
            <ul class="flex flex-wrap gap-2">
                @foreach ([null, ...\App\Enums\AnnouncementKind::cases()] as $option)
                    @php($active = $option === $kind)
                    <li>
                        <a href="{{ route('media.announcements', $option ? ['kind' => $option->value] : []) }}" wire:navigate @if ($active) aria-current="page" @endif
                            @class([
                                'inline-flex rounded-full px-5 py-2 text-sm font-semibold transition',
                                'bg-primary text-white' => $active,
                                'border border-line bg-white text-ink hover:border-primary hover:text-primary' => ! $active,
                            ])>{{ $option?->getLabel() ?? __('announcements.list.all') }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($announcements->isEmpty())
            <x-empty-state icon="bell" :message="__('announcements.list.empty')" class="mt-8" />
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($announcements as $announcement)
                    <li>@include('media.announcements.card', ['announcement' => $announcement])</li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
