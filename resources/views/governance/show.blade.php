{{--
    صفحة الوثيقة: بياناتها، وزرا الفتح والتنزيل، والعرض المدمج على الحاسوب. ومتصفحات الجوال
    لا تعرض PDF داخل الصفحة، فعليها الزران وحدهما. (تُخزَّن نصاً — GovernanceController)
--}}
@php
    use App\Support\Dates;
    use App\Support\FileSize;

    $size = FileSize::display($document->file_size);
@endphp

<x-page-hero :title="$document->title" :summary="$document->category->getLabel()" />

<section class="py-10 sm:py-14">
    <div class="container-page">
        <div class="flex flex-wrap items-center justify-between gap-6 rounded-2xl border border-line/70 bg-white p-6 shadow-xs">
            <dl class="grid gap-x-10 gap-y-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-muted">{{ __('governance.fields.category') }}</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $document->category->getLabel() }}</dd>
                </div>
                @if ($document->issued_at)
                    <div>
                        <dt class="text-muted">{{ __('governance.fields.issued_at') }}</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ Dates::display($document->issued_at) }}</dd>
                    </div>
                @endif
                @if (filled($document->version))
                    <div>
                        <dt class="text-muted">{{ __('governance.fields.version') }}</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ $document->version }}</dd>
                    </div>
                @endif
            </dl>

            <div class="flex flex-wrap gap-3">
                <x-button :href="route('governance.download', $document->slug)">
                    {{ __('governance.document.download') }}
                    {{-- بلا شفافية: الأبيض على الأزرق 5.9:1، وبشفافية 80% كان 4.4:1 دون حد WCAG --}}
                    @if ($size)
                        <span class="font-normal">({{ $size }})</span>
                    @endif
                </x-button>
                <x-button variant="outline" :href="route('governance.file', $document->slug)" target="_blank" rel="noopener">
                    {{ __('governance.document.open') }}
                    <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                </x-button>
            </div>
        </div>

        @if (filled($document->description))
            <p class="mt-6 max-w-4xl text-lg leading-8 text-ink/85">{{ $document->description }}</p>
        @endif

        <iframe src="{{ route('governance.file', $document->slug) }}" title="{{ __('governance.document.viewer', ['title' => $document->title]) }}"
            loading="lazy" class="mt-8 hidden h-[80vh] w-full rounded-2xl border border-line/70 bg-surface lg:block"></iframe>
        <p class="mt-6 text-sm text-muted lg:hidden">{{ __('governance.document.mobile_hint') }}</p>
    </div>
</section>
