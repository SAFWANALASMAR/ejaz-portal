{{-- مقطع نص: عنوان اختياري، ونص منسق بعد تنقيته (App\Support\RichText) --}}
{{-- والمرسى على العنوان لا على المقطع، فيقع العنوان تحت الرأس الثابت مباشرة لا حشو المقطع (المشرف على طلب الدمج 183) --}}
<section class="py-10 sm:py-12">
    <div class="container-page">
        <div class="max-w-4xl">
            @if (filled($data['heading'] ?? null))
                {{-- في متغير قبل المكوّن: Blade يقيّم تعبير خاصية المكوّن المجهول مرتين، فيأخذ anchorOnce المرسى في الأولى ويرد null في الثانية --}}
                @php($anchor = \App\Support\ContentBlocks::anchorOnce($data['heading']))
                {{-- والمرسى على غلاف ثابت لا على العنوان: العنوان يظهر منزاحاً 1.5rem (data-reveal) فتقيس قفزة المتصفح موضعه المنزاح --}}
                <div @if ($anchor) id="{{ $anchor }}" @endif>
                    <x-section-heading class="mb-5">{{ $data['heading'] }}</x-section-heading>
                </div>
            @endif

            {{-- سطر مريح للقراءة: نحو 68 حرفاً على الأكثر لفقرات النص وقوائمه وعناوينه وحدها، لا لحاويته، فلا يضيق جدول أو صورة (ملاحظة صفوان ٣٠) --}}
            <div class="prose-ar measure-flow text-lg">{{ \App\Support\RichText::toHtml(is_string($data['body'] ?? null) ? $data['body'] : null) }}</div>
        </div>
    </div>
</section>
