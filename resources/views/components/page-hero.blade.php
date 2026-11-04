{{--
    رأس الصفحات الداخلية (التصور النهائي — Final-About وFinal-Page وأخواتهما، ونمط الجوال على كل العروض منذ 31 أكتوبر): بارتفاع
    320 بكسل، الصورة بعرضه كله تحت الرأس مباشرة بتكبير بطيء، والتدرج يشتد نحو الأزرق الداكن في الأسفل وفي جهة النص. وفي أعلاه شريط العودة كبسولتين، ثم شارة القسم «✦ اسم القسم»، ثم
    العنوان، ثم شريط أزرق يمتد، ثم الوصف. والظهور من ضباب كلمة بعد كلمة، وكله يتوقف لمن طلب تقليل الحركة.

    الشارة: اسم القسم من اسم المسار (App\Support\PageSections)، وفي صفحة القسم نفسه اسم الجمعية المختصر. ويمرر القالب eyebrow.
    والصورة المؤقتة المحايدة حتى تصل صور الجمعية. وما بعد الوصف في الـ slot (أزرار أو شارات)، وبطاقة جانبية في
    جهة النهاية في الـ slot المسمى aside (بطاقة جوائز المسابقة)، تنزل تحت النص على الجوال. وcompact: أقصر بمسافات أقل (صفحة
    المسابقة، البند P3). والصورة صورة الصفحة متى مررتها (المرحلة ج)، وإلا الرسم المؤقت.
--}}
@props(['title', 'summary' => null, 'image' => null, 'images' => [], 'eyebrow' => null, 'icon' => null, 'compact' => false])

@php
    $section = \App\Support\PageSections::for(request()->route());
    $badge = $eyebrow ?? ($section['label'] === $title ? __('site.short_name') : $section['label']);
    $back = \App\Support\BackNavigation::shows(request()->route());
    // عنوان الصفحة الثابتة ووصفها المنشوران من «محرر الصفحات» يغلبان على نصوص القالب (EditablePages)، والشارة من عنوان القالب
    $editable = \App\Support\EditablePages::live(request()->route()?->getName());
    if ($editable !== []) {
        $title = $editable['title'];
        // وصف المحرر يغلب، إلا علامة «【ناقص】» فيه والقالب يمرر نصاً معتمداً (نص «تواصل معنا» — إضافة توجيه المدير، البند ٣٠)
        $summary = filled($summary) && str_contains((string) $editable['summary'], '【') ? $summary : $editable['summary'];
    }
    // صورة الصفحة المرفوعة من «محتوى الرئيسية» ← «صور رؤوس الصفحات» أولاً، ثم ما يمرره القالب، ثم نقش الصفحة المحايد (NeutralImage)
    $heroSrc = \App\Support\HomeContent::pageHeroUrl(request()->route()?->getName()) ?? $image ?? ($images[0] ?? null) ?? \App\Support\NeutralImage::forPage(request()->route()?->getName());
    // صور تتبدل في الإطار (رأس «مكتبة إعجاز» — البند ٢٥): أكثر من صورة وحدها، وإلا الصورة الواحدة ثابتة
    $rotating = count($images) > 1 ? array_values($images) : [];
@endphp

{{-- «فاتح بصورة واحدة» (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٢٧ — docs/decisions/2026-11-03-director-home.md، ويحل
     محل ذوبان الصورة في الأزرق في البند ١٥): خلفية فاتحة بدرجة لكل قسم (data-shade من اسم المسار)، ونقش الهوية باهت، ونص داكن
     (#1F4565)، وصورة القسم الحالية في جهة النهاية (اليسار) بإطار أبيض 8 بكسل مائلة نحو −4°، وخلفها بطاقة مائلة عكسها. وعلى الجوال
     الصورة تحت النص والبطاقة الخلفية مخفية. وما في aside (بطاقة جوائز المسابقة) تحت النص. والصورة زخرفة، فالعنوان يكفي --}}
@php
    $shade = (crc32(explode('.', (string) request()->route()?->getName())[0] ?: 'page') % 4) + 1;
@endphp

<section data-page-hero class="page-hero-light" data-shade="{{ $shade }}" @if ($compact) data-compact @endif>
    <span class="page-hero-light-pattern" aria-hidden="true"></span>

    <div class="page-hero-light-row relative mx-auto flex max-w-[82.5rem] flex-col gap-8 px-4 pt-5.5 pb-10 sm:px-7 lg:flex-row lg:items-center lg:justify-between lg:gap-12">
        <div class="flex min-w-0 flex-1 flex-col gap-3">
            @if ($back)
                <x-back-nav inline class="mb-1" />
            @endif
            <p data-hero-badge class="hero-chip rise-1">
                <span aria-hidden="true">✦</span>
                {{ $badge }}
            </p>
            <h1 class="rise-2 max-w-3xl text-[2.25rem] leading-[1.1] font-black text-balance sm:text-[3.25rem]">{{ $title }}</h1>
            <span class="title-bar" aria-hidden="true"></span>
            {{-- مخطوطة اسم الجمعية كبيرة تحت العنوان في كل صفحة داخلية (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ١٤)، بأزرق الهوية
                 الداكن #1F4565 بلا ظل على الخلفية الفاتحة (قرار صفوان، 4 نوفمبر 2026، البند ٣٢ — تقييم اللقطات، البند ٣): قناع من ملف المخطوطة
                 نفسه (mask-image) فوق لون العنوان، فلا ملف جديد، والملف من «محتوى الرئيسية» متى استُبدل --}}
            <span role="img" aria-label="{{ __('site.name') }}" data-page-calligraphy class="page-hero-calligraphy page-hero-calligraphy-mask rise-3"
                style="--calligraphy-mask: url('{{ \App\Support\HomeContent::brand('calligraphy') }}')"></span>

            {{-- فقرة لكل سطر فارغ في الوصف (نص «تواصل معنا» فقرتان) --}}
            @foreach (filled($summary) ? preg_split('/\n\s*\n/', trim((string) $summary)) : [] as $paragraph)
                <p class="page-hero-light-summary rise-3 max-w-[36rem] text-lg leading-[1.75] sm:text-[1.1875rem]" data-hero-summary>{{ $paragraph }}</p>
            @endforeach
            {{ $slot ?? '' }}

            {{-- البطاقة الجانبية (بطاقة جوائز المسابقة) تحت النص، والصورة في جهتها --}}
            @isset($aside)
                <div class="page-hero-aside">{{ $aside }}</div>
            @endisset
        </div>

        <div class="page-hero-visual" data-page-hero-visual aria-hidden="true">
            <span class="page-hero-back"></span>
            @if ($rotating !== [])
                {{-- الصور تتبدل بتلاشٍ ناعم بالدوّار القائم (carousel.js، data-carousel)، ولا تبدأ لمن طلب تقليل الحركة، ويوقفها المرور --}}
                <span class="page-hero-frame" data-carousel data-autoplay data-interval="5000" data-page-hero-rotator>
                    @foreach ($rotating as $index => $src)
                        <img src="{{ $src }}" alt="" width="1280" height="640" data-carousel-item data-page-hero-image @if ($index === 0) data-active @else loading="lazy" @endif>
                    @endforeach
                </span>
            @else
                <span class="page-hero-frame">
                    <img src="{{ $heroSrc }}" alt="" width="1280" height="640" data-page-hero-image>
                </span>
            @endif
        </div>
    </div>
</section>
