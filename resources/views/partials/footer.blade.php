{{--
    التذييل (docs/design/ui-reference.md — التذييل): شريط أخضر داكن بعرض الصفحة،
    فيه روابط الصفحات وبيانات التواصل وحسابات التواصل الاجتماعي وحقوق النشر بنص فاتح.
    البيانات من إعدادات الموقع، وما لم يُدخل لا يظهر.
--}}
@php
    $menu = $siteMenu ?? collect();
    $contact = $siteContact ?? [];
    // فحص عند العرض أيضاً، فلا يُعرض رابط غير آمن ولو وصل إلى القاعدة دون تحقق
    $social = array_filter($contact['social'] ?? [], fn ($url) => is_string($url) && \App\Rules\SafeUrl::isSafe($url, allowInternal: false));
    $hasContact = filled($contact['email'] ?? null) || filled($contact['phone'] ?? null) || filled($contact['address'] ?? null) || $social !== [];
@endphp

<footer class="relative overflow-hidden bg-primary-dark text-white">
    <img src="{{ asset('images/decor/palms-line.svg') }}" alt="" aria-hidden="true"
        class="pointer-events-none absolute bottom-0 end-0 w-[420px] max-w-[70%] opacity-25">

    <div class="container-page relative grid gap-10 py-14 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr]">
        <div class="space-y-4">
            <x-brand light />
            <p class="max-w-sm text-sm leading-7 text-white/75">{{ __('site.tagline') }}</p>
            <p class="text-sm text-accent-light">{{ __('site.footer.supervision') }}</p>
            <p class="text-xs text-white/70">{{ __('site.license') }}</p>
        </div>

        <nav aria-label="{{ __('site.footer.quick_links') }}">
            <p class="mb-4 font-semibold text-accent-light">{{ __('site.footer.quick_links') }}</p>
            <ul class="grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                {{-- العنصر الذي يشير إلى صفحة غير منشورة يبقى في الرأس لعناصره الفرعية، ولا رابط له هنا (SiteChromeComposer) --}}
                @foreach ($menu->where('linkable', true) as $item)
                    <li><a href="{{ $item->href() }}" @if ($item->opensInNewTab) target="_blank" rel="noopener" @endif @if ($item->navigates()) wire:navigate @endif class="text-white/80 hover:text-white">{{ $item->label }}</a></li>
                @endforeach
            </ul>
        </nav>

        @if ($hasContact)
        <div class="space-y-3 text-sm">
            <p class="mb-4 font-semibold text-accent-light">{{ __('site.footer.contact') }}</p>
            @if (! empty($contact['email']))
                <p class="flex items-center gap-2">
                    {{ svg('heroicon-o-envelope', 'size-4 shrink-0 text-accent-light') }}
                    <a href="mailto:{{ $contact['email'] }}" class="text-white/80 hover:text-white" dir="ltr">{{ $contact['email'] }}</a>
                </p>
            @endif
            @if (! empty($contact['phone']))
                <p class="flex items-center gap-2">
                    {{ svg('heroicon-o-phone', 'size-4 shrink-0 text-accent-light') }}
                    <a href="tel:{{ $contact['phone'] }}" class="text-white/80 hover:text-white" dir="ltr">{{ $contact['phone'] }}</a>
                </p>
            @endif
            @if (! empty($contact['address']))
                <p class="flex items-start gap-2">
                    {{ svg('heroicon-o-map-pin', 'mt-1 size-4 shrink-0 text-accent-light') }}
                    <span class="leading-7 text-white/80">{{ $contact['address'] }}</span>
                </p>
            @endif
            @if ($social !== [])
                <ul class="flex flex-wrap gap-2 pt-2">
                    @foreach ($social as $network => $url)
                        <li>
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                                class="inline-flex rounded-full border border-white/25 px-3 py-1 text-white/85 hover:border-accent-light hover:text-white">
                                {{ __('settings.social.'.$network) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @endif
    </div>

    <div class="relative border-t border-white/10">
        <div class="container-page flex flex-col gap-2 py-5 text-xs text-white/65 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ now()->year }} {{ __('site.name') }} — {{ __('site.footer.rights') }}</p>
            @if (\App\Models\Page::isPublishedSlug(\App\Models\Page::PRIVACY))
                <a href="{{ route('privacy') }}" wire:navigate class="hover:text-white">{{ __('site.footer.privacy') }}</a>
            @endif
        </div>
    </div>
</footer>
