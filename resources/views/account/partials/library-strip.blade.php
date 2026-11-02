{{--
    «جديد مكتبة إعجاز وقناتها» تحت الترحيب في لوحة المستفيد (Final-Dashboard — البند D1): شريط واحد متحرك (x-marquee) يتعاقب فيه
    مقطع من قناة الجمعية وغلاف كتاب أو بحث، بالبطاقات الملونة نفسها التي في شريطي الرئيسية. محتوى عام منشور، فيُخزَّن نصاً مع المحتوى
    العام (DashboardController). ولا تشغيل هنا: الضغط إلى صفحة المقطع أو الكتاب. ويختفي إن لم يكن في المكتبة شيء منهما.

    $videos وَ$books: Collection<LibraryItem> (BuildHomePage::libraryStrip).
--}}
@php
    $items = collect();
    for ($i = 0; $i < max($videos->count(), $books->count()); $i++) {
        foreach ([$videos->get($i), $books->get($i)] as $item) {
            if ($item !== null) {
                $items->push($item);
            }
        }
    }
    $video = 0;
@endphp

@if ($items->isNotEmpty())
    <section class="mx-auto max-w-[82.5rem] px-4 pt-5 sm:px-7" aria-labelledby="account-library-strip" data-account-library-strip>
        <div class="mb-2.5 flex items-center justify-between gap-4">
            <h2 id="account-library-strip" class="text-xl font-black text-primary-dark">{{ __('account.dashboard_final.library') }}</h2>
            <a href="{{ route('library.index') }}" wire:navigate class="more-link shrink-0">{{ __('account.dashboard_final.library_more') }}</a>
        </div>

        <x-marquee id="account-library-track" :label="__('account.dashboard_final.library')" :duration="max(30, $items->count() * 6)" class="media-marquee">
            @foreach ($items as $item)
                @php($isVideo = $item->section === \App\Enums\LibrarySection::Videos)
                <a href="{{ route('library.show', [$item->section->value, $item->slug]) }}" wire:navigate data-card class="tone-card media-tile group">
                    <span class="media-tile-thumb">
                        @if ($thumbnail = $item->thumbnailUrl())
                            <img src="{{ $thumbnail }}" alt="" loading="lazy" width="200" height="118" @class(['size-full transition duration-500 group-hover:scale-105', 'object-cover' => $isVideo, 'object-contain' => ! $isVideo])>
                        @else
                            {{-- بلا صورة أو غلاف: مربع بتدرج أزرق وأيقونة القسم (كشريطي الرئيسية) --}}
                            <span class="media-tile-placeholder" data-thumb-placeholder aria-hidden="true">
                                @unless ($isVideo)
                                    {{ \App\Support\Icon::svg($item->section->icon(), 'size-10') }}
                                @endunless
                            </span>
                        @endif
                        @if ($isVideo)
                            <x-video-number :number="++$video" />
                            <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                <span class="youtube-play">{{ \App\Support\Icon::solid('play', 'size-4') }}</span>
                            </span>
                        @endif
                    </span>
                    <span class="media-tile-caption">{{ $item->title }}</span>
                </a>
            @endforeach
        </x-marquee>
    </section>
@endif
