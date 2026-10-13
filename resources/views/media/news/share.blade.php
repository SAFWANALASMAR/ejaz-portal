{{--
    مشاركة الخبر بروابط المواقع نفسها، بلا مكتبات ولا سكربتات خارجية، فلا يتصل المتصفح بها إلا عند
    الضغط. ونسخ الرابط في resources/js/app.js.
--}}
@php
    $url = route('media.news.show', $article->slug);

    $links = [
        'whatsapp' => 'https://wa.me/?text='.rawurlencode($article->title.' '.$url),
        'x' => 'https://x.com/intent/post?text='.rawurlencode($article->title).'&url='.rawurlencode($url),
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($url),
    ];
@endphp

<div class="flex flex-wrap items-center gap-2">
    <span class="text-sm font-medium text-ink">{{ __('news.article.share') }}</span>
    @foreach ($links as $network => $link)
        <a href="{{ $link }}" target="_blank" rel="noopener noreferrer"
            class="inline-flex rounded-full border border-line bg-white px-3 py-1 text-sm text-ink transition hover:border-primary hover:text-primary">
            {{ __('settings.social.'.$network) }}
            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
        </a>
    @endforeach
    <button type="button" hidden data-copy-link="{{ $url }}" data-copied="{{ __('news.article.link_copied') }}" aria-live="polite"
        class="inline-flex rounded-full border border-line bg-white px-3 py-1 text-sm text-ink transition hover:border-primary hover:text-primary">
        {{ __('news.article.copy_link') }}
    </button>
</div>
