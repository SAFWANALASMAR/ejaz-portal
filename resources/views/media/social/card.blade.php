{{--
    بطاقة منشور تواصل («منشوراتنا»): الصورة إن رُفعت، وإلا شعار المنصة على خلفية فاتحة، ثم المنصة والنوع والتاريخ، ثم مقتطف
    النص، ثم «عرض على المنصة» في تبويب جديد. لا تضمين ولا سكربت من المنصة.
--}}
@php
    use App\Enums\SocialPostKind;
    use App\Support\Dates;

    $href = $post->href();
@endphp

<article class="tone-card flex h-full flex-col overflow-hidden" data-social-post>
    @if ($post->image)
        <span class="skeleton block overflow-hidden"><img src="{{ Storage::disk('public')->url($post->image) }}" alt="" loading="lazy" class="aspect-[16/10] w-full object-cover"></span>
    @else
        <span class="tone-card-media flex aspect-[16/10] w-full items-center justify-center" aria-hidden="true">
            <x-social-icon :network="$post->platform->value" class="size-14" />
        </span>
    @endif
    <div class="flex flex-1 flex-col p-5">
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span class="inline-flex items-center gap-1.5 font-bold">
                <x-social-icon :network="$post->platform->value" class="size-4" />
                {{ $post->platform->getLabel() }}
            </span>
            @if ($post->kind === SocialPostKind::Event)
                <span class="tone-chip">{{ $post->kind->getLabel() }}</span>
            @endif
            <time datetime="{{ $post->posted_on->toDateString() }}" class="tone-card-muted">{{ Dates::display($post->posted_on) }}</time>
        </p>
        <p class="mt-2 line-clamp-5 leading-7 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($post->body, 400) }}</p>
        @if ($href !== null)
            <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" data-social-post-link
                class="more-link mt-auto self-start pt-4">
                {{ __('social_posts.list.view') }}
                <span class="sr-only">{{ __('social_posts.list.view_label', ['platform' => $post->platform->getLabel()]) }} ({{ __('site.opens_in_new_tab') }})</span>
            </a>
        @endif
    </div>
</article>
