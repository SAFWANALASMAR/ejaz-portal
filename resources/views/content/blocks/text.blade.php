{{-- مقطع نص: عنوان اختياري، ونص منسق بعد تنقيته (App\Support\RichText) --}}
<section class="py-10 sm:py-12">
    <div class="container-page">
        <div class="max-w-4xl">
            @if (filled($data['heading'] ?? null))
                <x-section-heading class="mb-5">{{ $data['heading'] }}</x-section-heading>
            @endif

            <div class="prose-ar text-lg">{{ \App\Support\RichText::toHtml(is_string($data['body'] ?? null) ? $data['body'] : null) }}</div>
        </div>
    </div>
</section>
