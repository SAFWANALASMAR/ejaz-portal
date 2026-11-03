{{--
    «منشوراتنا» (طلب صفوان، 3 نوفمبر 2026): منشورات الجمعية وأحداثها على منصات التواصل، بتصفية بالمنصة والنوع، ثم شبكة البطاقات
    بترقيم. والصفحة الأولى بلا تصفية تُخزَّن (SocialPostController).
--}}
@php
    $platformLink = fn (?\App\Enums\SocialPlatform $option): string => route('media.social', array_filter(['platform' => $option?->value, 'kind' => $kind?->value]));
    $kindLink = fn (?\App\Enums\SocialPostKind $option): string => route('media.social', array_filter(['platform' => $platform?->value, 'kind' => $option?->value]));
    $pill = 'inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold transition';
    $on = 'bg-primary text-white';
    $off = 'border border-line bg-white text-ink hover:border-primary hover:text-primary';
@endphp

<x-page-hero :title="__('social_posts.title')" :summary="__('social_posts.list.intro')" />
<x-page-sections />

<section class="py-10 sm:py-14">
    <div class="container-page">
        <nav aria-label="{{ __('social_posts.list.filter_platform') }}" data-social-filter="platform">
            <ul class="flex flex-wrap gap-2">
                @foreach ([null, ...\App\Enums\SocialPlatform::cases()] as $option)
                    <li>
                        <a href="{{ $platformLink($option) }}" wire:navigate @if ($option === $platform) aria-current="page" @endif @class([$pill, $on => $option === $platform, $off => $option !== $platform])>
                            @if ($option)
                                <x-social-icon :network="$option->value" class="size-4" />
                            @endif
                            {{ $option?->getLabel() ?? __('social_posts.list.all') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <nav aria-label="{{ __('social_posts.list.filter_kind') }}" class="mt-3" data-social-filter="kind">
            <ul class="flex flex-wrap gap-2">
                @foreach ([null, ...\App\Enums\SocialPostKind::cases()] as $option)
                    <li>
                        <a href="{{ $kindLink($option) }}" wire:navigate @if ($option === $kind) aria-current="page" @endif @class([$pill, $on => $option === $kind, $off => $option !== $kind])>
                            {{ $option?->getLabel() ?? __('social_posts.list.all') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

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
