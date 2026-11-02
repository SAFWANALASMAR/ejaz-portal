{{-- مقطع نص: عنوان اختياري، ونص منسق بعد تنقيته (App\Support\RichText) --}}
<section class="scroll-mt-48 py-10 sm:py-12" @if ($anchor = \App\Support\ContentBlocks::anchor($data['heading'] ?? null)) id="{{ $anchor }}" @endif>
    <div class="container-page">
        <div class="max-w-4xl">
            @if (filled($data['heading'] ?? null))
                <x-section-heading class="mb-5">{{ $data['heading'] }}</x-section-heading>
            @endif

            {{-- سطر مريح للقراءة: نحو 68 حرفاً على الأكثر (ملاحظة صفوان ٣٠) --}}
            <div class="prose-ar measure text-lg">{{ \App\Support\RichText::toHtml(is_string($data['body'] ?? null) ? $data['body'] : null) }}</div>
        </div>
    </div>
</section>
