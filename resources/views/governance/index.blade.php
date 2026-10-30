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
@if (($filters['category'] ?? null) === \App\Enums\DocumentCategory::AnnualReports->value)
    <x-page-intro slug="reports" />
@endif

<section class="py-10 sm:py-14">
    <div class="container-page">
        <h2 class="sr-only">{{ __('governance.fields.category') }}</h2>
        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5" data-reveal-group>
            @foreach (DocumentCategory::cases() as $category)
                <li>
                    <a href="{{ route('governance.index', ['category' => $category->value]) }}" wire:navigate
                        @if ($active === $category->value) aria-current="true" @endif
                        class="card-lift tilt group flex h-full flex-col items-center gap-3 p-5 text-center aria-[current=true]:border-accent aria-[current=true]:ring-1 aria-[current=true]:ring-accent">
                        <x-icon-tile :icon="$category->icon()" :tone="['blue', 'violet', 'green', 'navy', 'sky'][$loop->index % 5]" size="md" />
                        <span class="font-semibold text-ink">{{ $category->getLabel() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <form method="GET" action="{{ route('governance.index') }}" role="search" aria-label="{{ __('governance.filters.label') }}"
            class="mt-10 grid gap-3 rounded-2xl border border-line/70 bg-surface p-4 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('governance.filters.q') }}</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('governance.filters.q_placeholder') }}"
                    class="mt-1 w-full rounded-xl border border-muted/70 bg-white px-4 py-2.5 focus:border-primary focus:outline-none">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('governance.filters.category') }}</span>
                <select name="category" class="mt-1 w-full rounded-xl border border-muted/70 bg-white px-4 py-2.5 focus:border-primary focus:outline-none">
                    <option value="">{{ __('governance.filters.all_categories') }}</option>
                    @foreach (DocumentCategory::cases() as $category)
                        <option value="{{ $category->value }}" @selected($active === $category->value)>{{ $category->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-ink">{{ __('governance.filters.year') }}</span>
                <select name="year" class="mt-1 w-full rounded-xl border border-muted/70 bg-white px-4 py-2.5 focus:border-primary focus:outline-none">
                    <option value="">{{ __('governance.filters.all_years') }}</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}" @selected((int) ($filters['year'] ?? 0) === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </label>
            <x-button>{{ __('governance.filters.submit') }}</x-button>
        </form>

        @if ($filters !== [])
            <p class="mt-4"><a href="{{ route('governance.index') }}" wire:navigate class="text-sm text-primary underline decoration-accent underline-offset-4">{{ __('governance.filters.reset') }}</a></p>
        @endif

        <h2 class="sr-only">{{ __('governance.list.title') }}</h2>

        @if ($documents->isEmpty())
            <x-empty-state icon="shield-check" :message="$filters === [] ? __('governance.list.empty') : __('governance.list.no_results')" class="mt-10" />
        @else
            <ul class="mt-8 grid gap-4 lg:grid-cols-2" data-reveal-group>
                @foreach ($documents as $document)
                    <li>
                        <x-file-card :title="$document->title" :href="route('governance.show', $document->slug)"
                            :download="route('governance.download', $document->slug)" :bytes="$document->file_size"
                            :type="__('governance.list.pdf')" class="h-full"
                            :meta="[
                                $document->category->getLabel(),
                                $document->issued_at ? Dates::display($document->issued_at) : null,
                                filled($document->version) ? __('governance.fields.version').': '.$document->version : null,
                            ]" />
                    </li>
                @endforeach
            </ul>

            {{ $documents->links('partials.pagination') }}
        @endif
    </div>
</section>
