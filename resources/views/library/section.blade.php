{{--
    قسم من المكتبة: رأس القسم، ثم تبويبات الأقسام الكبسولية (الصورة 06)، ثم البحث والتصفية
    بالتصنيف، ثم شبكة البطاقات بترقيم. والصفحة الأولى بلا تصفية تُخزَّن (LibraryController).
--}}
@php
    use App\Enums\LibrarySection;

    $activeTag = $filters['tag'] ?? null;
    $search = isset($filters['q']) ? ['q' => $filters['q']] : [];
@endphp

<x-page-hero :title="$section->getLabel()" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        <nav aria-label="{{ __('library.list.sections') }}">
            <ul class="flex flex-wrap gap-2">
                @foreach (LibrarySection::cases() as $tab)
                    <li>
                        <a href="{{ route('library.section', $tab->value) }}" wire:navigate @if ($tab === $section) aria-current="page" @endif
                            class="inline-flex rounded-full border border-line bg-white px-5 py-2 text-sm font-semibold text-ink transition hover:border-primary hover:text-primary aria-[current=page]:border-primary aria-[current=page]:bg-primary aria-[current=page]:text-white">
                            {{ $tab->getLabel() }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <form method="GET" action="{{ route('library.section', $section->value) }}" role="search" aria-label="{{ __('library.filters.label') }}"
            class="mt-8 flex flex-col gap-3 rounded-2xl border border-line/70 bg-surface p-4 sm:flex-row sm:items-end">
            <label class="block flex-1">
                <span class="text-sm font-medium text-ink">{{ __('library.filters.q') }}</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('library.filters.q_placeholder') }}"
                    class="mt-1 w-full rounded-xl border border-muted/70 bg-white px-4 py-2.5 focus:border-primary focus:outline-none">
            </label>
            @if ($activeTag !== null)
                <input type="hidden" name="tag" value="{{ $activeTag }}">
            @endif
            <x-button>{{ __('library.filters.submit') }}</x-button>
        </form>

        @if ($tags !== [])
            <div class="mt-5">
                <h2 id="tags" class="text-sm font-medium text-ink">{{ __('library.filters.tags') }}</h2>
                <ul class="mt-2 flex flex-wrap gap-2" aria-labelledby="tags">
                    @foreach ($tags as $tag)
                        <li>
                            <a href="{{ route('library.section', [$section->value, 'tag' => $tag, ...$search]) }}" wire:navigate @if ($activeTag === $tag) aria-current="true" @endif
                                class="inline-flex rounded-full border border-line bg-white px-3 py-1 text-sm text-ink transition hover:border-accent aria-[current=true]:border-accent aria-[current=true]:bg-accent/15 aria-[current=true]:font-semibold">
                                {{ $tag }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($filters !== [])
            <p class="mt-4"><a href="{{ route('library.section', $section->value) }}" wire:navigate class="text-sm text-primary underline decoration-accent underline-offset-4">{{ __('library.filters.reset') }}</a></p>
        @endif

        <h2 class="sr-only">{{ __('library.list.items') }}</h2>

        @if ($items->isEmpty())
            {{-- قسم بلا عناصر منشورة بعد (كالكتب والأبحاث من الموقع السابق) بحالة «قريباً» --}}
            <p class="mt-10 text-muted">{{ $filters === [] ? __('library.list.soon') : __('library.list.no_results') }}</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group>
                @foreach ($items as $item)
                    <li>@include('library.card', ['item' => $item])</li>
                @endforeach
            </ul>

            {{ $items->links('partials.pagination') }}
        @endif
    </div>
</section>
