{{--
    معاينة نسخة من صفحة ثابتة قبل نشرها (PagePreviewController): شريط ينبّه أنها مسودة، ثم رأس الصفحة وأقسامها الظاهرة كما ستُنشر.
    وقوائم الصفحة نفسها (البرامج والأخبار وغيرها) لا تتغير بالمحرر، فتبقى في صفحتها. لا تُفهرس ولا تُخزَّن.
--}}
<x-layouts.app :title="$content['title']" :description="$content['summary']" noindex :canonical="false">
    <div data-page-preview-banner role="status" class="border-b border-brand-light bg-accent-light">
        <div class="mx-auto flex max-w-[82.5rem] flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-primary-dark sm:px-7">
            <span>{{ __('page_editor.preview.banner', ['page' => __('page_editor.pages.'.$key), 'status' => $revision->status->getLabel()]) }}</span>
            <span class="font-normal">{{ __('page_editor.preview.hint', ['minutes' => \App\Models\PageRevision::PREVIEW_MINUTES]) }}</span>
        </div>
    </div>

    <x-page-hero :title="$content['title']" :summary="$content['summary']" :image="$image" />

    <div data-page-sections>
        <x-content-blocks :blocks="$content['blocks']" :stack="$key === 'about'" />
    </div>
</x-layouts.app>
