{{--
    «منشوراتنا» (طلب صفوان، 3 نوفمبر 2026): منشورات الجمعية وأحداثها على منصات التواصل، بتصفية بالمنصة والنوع، ثم شبكة البطاقات
    بترقيم. والصفحة الأولى بلا تصفية تُخزَّن (SocialPostController).
--}}
@php
    $platformLink = fn (?\App\Enums\SocialPlatform $option): string => route('media.social', array_filter(['platform' => $option?->value, 'kind' => $kind?->value]));
    $kindLink = fn (?\App\Enums\SocialPostKind $option): string => route('media.social', array_filter(['platform' => $platform?->value, 'kind' => $option?->value]));
@endphp

<x-page-hero :title="__('social_posts.title')" :summary="__('social_posts.list.intro')" />
<x-page-sections />

<section class="py-10 sm:py-14">
    <div class="container-page">
        {{-- شريط التصفية (ملاحظة صفوان على طلب الدمج 165): كبسولة لكل منصة بشعارها في مربع أيقونة صغير، وتحته النوع شريطاً أصغر بعنوانه.
             الأنماط في resources/css/social-filters.css --}}
        <div class="social-filters">
            <nav aria-label="{{ __('social_posts.list.filter_platform') }}" class="social-filter" data-social-filter="platform">
                <ul class="social-filter-track">
                    @foreach ([null, ...\App\Enums\SocialPlatform::cases()] as $option)
                        <li>
                            <a href="{{ $platformLink($option) }}" wire:navigate @if ($option === $platform) aria-current="page" @endif class="social-filter-pill" data-social-filter-pill>
                                @if ($option)
                                    <x-icon-tile size="xs" shade="light" class="social-filter-tile"><x-social-icon :network="$option->value" class="size-3.5" /></x-icon-tile>
                                @else
                                    <x-icon-tile icon="squares-2x2" size="xs" shade="light" class="social-filter-tile" />
                                @endif
                                <span>{{ $option?->getLabel() ?? __('social_posts.list.all') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-label="{{ __('social_posts.list.filter_kind') }}" class="social-filter social-filter-kind" data-social-filter="kind">
                <span class="social-filter-label" data-social-filter-label>{{ __('social_posts.fields.kind') }}</span>
                <ul class="social-filter-track">
                    @foreach ([null, ...\App\Enums\SocialPostKind::cases()] as $option)
                        <li>
                            <a href="{{ $kindLink($option) }}" wire:navigate @if ($option === $kind) aria-current="page" @endif class="social-filter-pill social-filter-pill-sm" data-social-filter-pill>
                                {{ $option?->getLabel() ?? __('social_posts.list.all') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>

        <h2 class="sr-only">{{ __('social_posts.list.items') }}</h2>

        @if ($posts->isEmpty())
            <x-empty-state icon="chat-bubble-left-right" :message="$filters === [] ? __('social_posts.list.empty') : __('social_posts.list.no_results')" class="mt-10" />
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($posts as $post)
                    <li>@include('media.social.card', ['post' => $post])</li>
                @endforeach
            </ul>

            {{ $posts->links('partials.pagination') }}
        @endif
    </div>
</section>
