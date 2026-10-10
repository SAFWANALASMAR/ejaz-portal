{{--
    الحوكمة (الصورة 05): رأس القسم، ثم بلاطات التصنيفات، ثم البحث والتصفية، ثم قائمة الوثائق،
    لكل وثيقة أيقونة PDF وعنوان وبياناتها. والصفحة الأولى بلا تصفية تُخزَّن (GovernanceController).
--}}
@php
    use App\Enums\DocumentCategory;
    use App\Support\Dates;

    $active = $filters['category'] ?? null;
@endphp

<x-page-hero :title="__('governance.title')" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        <h2 class="sr-only">{{ __('governance.fields.category') }}</h2>
        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach (DocumentCategory::cases() as $category)
                <li>
                    <a href="{{ route('governance.index', ['category' => $category->value]) }}"
                        @if ($active === $category->value) aria-current="true" @endif
                        class="flex h-full flex-col items-center gap-3 rounded-2xl border border-line/70 bg-white p-5 text-center shadow-xs transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md aria-[current=true]:border-gold aria-[current=true]:ring-1 aria-[current=true]:ring-gold">
                        <span class="text-gold" aria-hidden="true">{{ svg('heroicon-o-'.$category->icon(), 'size-8') }}</span>
                        <span class="font-semibold text-ink">{{ $category->getLabel() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <form method="GET" action="{{ route('governance.index') }}" role="search" aria-label="{{ __('governance.filters.label') }}"
            class="mt-10 grid gap-3 rounded-2xl border border-line/70 bg-sand p-4 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('governance.filters.q') }}</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('governance.filters.q_placeholder') }}"
                    class="mt-1 w-full rounded-xl border border-line bg-white px-4 py-2.5 focus:border-primary focus:outline-none">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('governance.filters.category') }}</span>
                <select name="category" class="mt-1 w-full rounded-xl border border-line bg-white px-4 py-2.5 focus:border-primary focus:outline-none">
                    <option value="">{{ __('governance.filters.all_categories') }}</option>
                    @foreach (DocumentCategory::cases() as $category)
                        <option value="{{ $category->value }}" @selected($active === $category->value)>{{ $category->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('governance.filters.year') }}</span>
                <select name="year" class="mt-1 w-full rounded-xl border border-line bg-white px-4 py-2.5 focus:border-primary focus:outline-none">
                    <option value="">{{ __('governance.filters.all_years') }}</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected((int) ($filters['year'] ?? 0) === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </label>
            <x-button>{{ __('governance.filters.submit') }}</x-button>
        </form>

        @if ($filters !== [])
            <p class="mt-4"><a href="{{ route('governance.index') }}" class="text-sm text-primary underline decoration-gold underline-offset-4">{{ __('governance.filters.reset') }}</a></p>
        @endif

        <h2 class="sr-only">{{ __('governance.list.title') }}</h2>

        @if ($documents->isEmpty())
            <p class="mt-10 text-muted">{{ $filters === [] ? __('governance.list.empty') : __('governance.list.no_results') }}</p>
        @else
            <ul class="mt-8 divide-y divide-line/70 overflow-hidden rounded-2xl border border-line/70 bg-white shadow-xs">
                @foreach ($documents as $document)
                    <li>
                        <a href="{{ route('governance.show', $document->slug) }}" class="flex items-start gap-4 p-4 transition hover:bg-sand/60 sm:p-5">
                            <span class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-primary-soft text-primary" aria-hidden="true">
                                {{ svg('heroicon-o-document-text', 'size-6') }}
                                <span class="text-[10px] font-bold">{{ __('governance.list.pdf') }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-ink">{{ $document->title }}</span>
                                <span class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                                    <span>{{ $document->category->getLabel() }}</span>
                                    @if ($document->issued_at)
                                        <span>{{ Dates::display($document->issued_at) }}</span>
                                    @endif
                                    @if (filled($document->version))
                                        <span>{{ __('governance.fields.version') }}: {{ $document->version }}</span>
                                    @endif
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{ $documents->links('partials.pagination') }}
        @endif
    </div>
</section>
