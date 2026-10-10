{{--
    الأخبار: رأس القسم، ثم البحث والتصفية بالتصنيف، ثم شبكة البطاقات بترقيم. والصفحة الأولى بلا
    تصفية تُخزَّن (NewsController).
--}}
@php
    $activeCategory = $filters['category'] ?? null;
    $search = isset($filters['q']) ? ['q' => $filters['q']] : [];
@endphp

<x-page-hero :title="__('news.title')" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        <form method="GET" action="{{ route('media.news.index') }}" role="search" aria-label="{{ __('news.filters.label') }}"
            class="flex flex-col gap-3 rounded-2xl border border-line/70 bg-surface p-4 sm:flex-row sm:items-end">
            <label class="block flex-1">
                <span class="text-sm font-medium text-ink">{{ __('news.filters.q') }}</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('news.filters.q_placeholder') }}"
                    class="mt-1 w-full rounded-xl border border-line bg-white px-4 py-2.5 focus:border-primary focus:outline-none">
            </label>
            @if ($activeCategory !== null)
                <input type="hidden" name="category" value="{{ $activeCategory }}">
            @endif
            <x-button>{{ __('news.filters.submit') }}</x-button>
        </form>

        @if ($categories !== [])
            <div class="mt-5">
                <h2 id="categories" class="text-sm font-medium text-ink">{{ __('news.filters.categories') }}</h2>
                <ul class="mt-2 flex flex-wrap gap-2" aria-labelledby="categories">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ route('media.news.index', ['category' => $category, ...$search]) }}" @if ($activeCategory === $category) aria-current="true" @endif
                                class="inline-flex rounded-full border border-line bg-white px-3 py-1 text-sm text-ink transition hover:border-accent aria-[current=true]:border-accent aria-[current=true]:bg-accent/15 aria-[current=true]:font-semibold">
                                {{ $category }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($filters !== [])
            <p class="mt-4"><a href="{{ route('media.news.index') }}" class="text-sm text-primary underline decoration-accent underline-offset-4">{{ __('news.filters.reset') }}</a></p>
        @endif

        <h2 class="sr-only">{{ __('news.list.items') }}</h2>

        @if ($articles->isEmpty())
            <p class="mt-10 text-muted">{{ $filters === [] ? __('news.list.empty') : __('news.list.no_results') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <li>@include('media.news.card', ['article' => $article])</li>
                @endforeach
            </ul>

            {{ $articles->links('partials.pagination') }}
        @endif
    </div>
</section>
