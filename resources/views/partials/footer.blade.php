{{-- التذييل: التعريف، وروابط سريعة، وبيانات التواصل من إعدادات الموقع --}}
@php
    $menu = $siteMenu ?? collect();
    $contact = $siteContact ?? [];
@endphp

<footer class="mt-16 bg-primary text-white">
    <div class="bg-pattern">
        <div class="container-page grid gap-10 py-12 md:grid-cols-3">
            <div class="space-y-3">
                <p class="text-lg font-bold">{{ __('site.name') }}</p>
                <p class="text-sm leading-7 text-white/80">{{ __('site.tagline') }}</p>
                <p class="text-sm text-gold">{{ __('site.footer.supervision') }}</p>
            </div>

            <nav aria-label="{{ __('site.footer.quick_links') }}">
                <p class="mb-3 font-semibold text-gold">{{ __('site.footer.quick_links') }}</p>
                <ul class="grid grid-cols-2 gap-2 text-sm">
                    @foreach ($menu as $item)
                        <li><a href="{{ $item->href() }}" class="text-white/85 hover:text-white hover:underline">{{ $item->label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <div class="space-y-2 text-sm">
                <p class="mb-3 font-semibold text-gold">{{ __('site.footer.contact') }}</p>
                @if (! empty($contact['email']))
                    <p><a href="mailto:{{ $contact['email'] }}" class="text-white/85 hover:text-white" dir="ltr">{{ $contact['email'] }}</a></p>
                @endif
                @if (! empty($contact['phone']))
                    <p><a href="tel:{{ $contact['phone'] }}" class="text-white/85 hover:text-white" dir="ltr">{{ $contact['phone'] }}</a></p>
                @endif
                @if (! empty($contact['address']))
                    <p class="leading-7 text-white/85">{{ $contact['address'] }}</p>
                @endif
                @if (! empty($contact['social']))
                    <ul class="flex flex-wrap gap-3 pt-2">
                        @foreach ($contact['social'] as $network => $url)
                            <li><a href="{{ $url }}" target="_blank" rel="noopener" class="text-white/85 hover:text-white">{{ __('settings.social.'.$network) }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="container-page flex flex-col gap-2 py-4 text-xs text-white/70 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ now()->year }} {{ __('site.name') }} — {{ __('site.footer.rights') }}</p>
            @if (Route::has('privacy'))
                <a href="{{ route('privacy') }}" class="hover:text-white">{{ __('site.footer.privacy') }}</a>
            @endif
        </div>
    </div>
</footer>
