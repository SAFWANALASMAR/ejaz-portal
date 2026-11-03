{{--
    صفحة الوثيقة (Final-Governance «صفحة الوثيقة»، المرحلة ج — البند P7): مدمجة مع بياناتها، بطاقة البيانات الملونة (التصنيف وتاريخ
    الإصدار والإصدار والحجم وزرا التنزيل والفتح) في جهة البداية، والعرض المدمج بشريطه الداكن في جهة النهاية على الحاسوب. ومتصفحات
    الجوال لا تعرض PDF داخل الصفحة، فعليها الزران وحدهما. ووثيقة الرابط المباشر بزر يفتحه وحده. (تُخزَّن نصاً — GovernanceController)
--}}
@php
    use App\Support\Dates;
    use App\Support\FileSize;

    $size = FileSize::display($document->file_size);
    $rows = array_filter([
        ['icon' => 'folder', 'label' => __('governance.fields.category'), 'value' => $document->category->getLabel()],
        $document->issued_at ? ['icon' => 'calendar', 'label' => __('governance.fields.issued_at'), 'value' => Dates::display($document->issued_at)] : null,
        filled($document->version) ? ['icon' => 'tag', 'label' => __('governance.fields.version'), 'value' => $document->version] : null,
        $size ? ['icon' => 'document-text', 'label' => __('ui.inner.size'), 'value' => $size] : null,
    ]);
@endphp

<x-page-hero :title="$document->title" :summary="$document->category->getLabel()" />

<section class="py-8 sm:py-10">
    <div @class(['container-page grid gap-5 lg:items-start', 'lg:grid-cols-[1fr_2.2fr]' => $document->file !== null])>
        <aside class="tone-card flex flex-col gap-4 p-6" data-tone="soft" data-document-meta>
            <h2 class="text-xl leading-snug font-black">{{ $document->title }}</h2>
            <dl class="flex flex-col gap-3 text-sm">
                @foreach ($rows as $row)
                    <div class="grid grid-cols-[auto_1fr] items-center gap-x-3 border-b border-white/15 pb-3 last:border-0 last:pb-0">
                        <dt class="contents"><x-icon-tile :icon="$row['icon']" size="sm" class="row-span-2" /><span class="tone-card-muted">{{ $row['label'] }}</span></dt>
                        <dd class="font-black">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>

            @if (filled($document->description))
                <p class="tone-card-muted leading-7">{{ $document->description }}</p>
            @endif

            <div class="flex flex-wrap gap-2.5">
                {{-- وثيقة الرابط المباشر: زر يفتحه في تبويب جديد، بلا تنزيل ولا عرض مدمج --}}
                @if ($document->file === null)
                    @if ($document->isLink())
                        <a href="{{ $document->url }}" target="_blank" rel="noopener noreferrer" class="tone-pill">
                            {{ __('governance.document.open_link') }}
                            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                        </a>
                    @endif
                @elseif ($document->isViewOnly())
                    {{-- «للاطلاع فقط» (البند ٢٢): لا زر تنزيل ولا فتح للملف، والقراءة في القارئ بجانبه --}}
                    <p class="inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/15 px-3.5 py-1.5 text-sm font-extrabold" data-view-only-badge>
                        {{ \App\Support\Icon::svg('eye', 'size-4', ['aria-hidden' => 'true']) }}{{ __('governance.view_only.badge') }}
                    </p>
                @else
                    <a href="{{ route('governance.download', $document->slug) }}" class="tone-pill">
                        {{ \App\Support\Icon::svg('arrow-down-tray', 'size-4', ['aria-hidden' => 'true']) }}
                        {{ __('governance.document.download') }}
                        @if ($size)
                            <span class="font-normal">({{ $size }})</span>
                        @endif
                    </a>
                    <a href="{{ route('governance.file', $document->slug) }}" target="_blank" rel="noopener" class="tone-pill">
                        {{ __('governance.document.open') }}
                        <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                    </a>
                @endif
            </div>
        </aside>

        @if ($document->isViewOnly())
            {{-- قارئ المكتبة نفسه «للقراءة فقط» على كل العروض: يرسم الصفحات في <canvas> بلا شريط عارض المتصفح ولا تنزيل ولا طباعة
                 (resources/js/pdf-reader.js). يصعّب الحفظ ولا يمنعه منعاً تاماً --}}
            <div class="min-w-0 [&>[data-pdf-reader]]:mt-0" data-document-reader>
                @include('library.reader', ['url' => route('governance.file', $document->slug), 'title' => $document->title, 'texts' => 'governance.view_only.reader'])
            </div>
        @elseif ($document->file !== null)
            <div class="concept-panel hidden overflow-hidden lg:block" data-document-viewer>
                <p class="flex items-center gap-2.5 bg-primary px-4 py-3 text-sm font-bold text-white">
                    <x-icon-tile icon="document-text" variant="glass" size="xs" />
                    {{ $document->title }}
                </p>
                <iframe src="{{ route('governance.file', $document->slug) }}" title="{{ __('governance.document.viewer', ['title' => $document->title]) }}"
                    loading="lazy" class="block h-[80vh] w-full bg-[var(--blue-50)]"></iframe>
            </div>
            <p class="text-sm text-muted lg:hidden">{{ __('governance.document.mobile_hint') }}</p>
        @endif
    </div>
</section>
