{{--
    بطاقة إعلان: الصورة بعرض البطاقة وعنوانها. ما له رابط يفتحه (والمتجر وما خارج البوابة في تبويب جديد)، وما لا رابط له
    صورة وعنوان. $hidden للنسخة الثانية في الشريط المتحرك: خارج لوحة المفاتيح.
--}}
@php
    $href = $announcement->href();
    $external = $announcement->opensExternally();
    $classes = 'group flex h-full flex-col overflow-hidden card-lift';
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($external) target="_blank" rel="noopener noreferrer" @else @navigate($href) @endif
        @if ($hidden ?? false) tabindex="-1" @endif class="{{ $classes }} hover:-translate-y-0.5 hover:shadow-md">
@else
    <div class="{{ $classes }}">
@endif
        <img src="{{ Storage::disk('public')->url($announcement->image) }}" alt="" loading="lazy" width="400" height="300" class="aspect-[4/3] w-full bg-primary-soft object-cover">
        <span class="flex flex-1 items-start justify-between gap-3 p-4">
            <span class="font-bold leading-7 text-ink">{{ $announcement->title }}</span>
            @if ($href)
                <span class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-white transition group-hover:bg-secondary" aria-hidden="true">
                    {{ \App\Support\Icon::svg($external ? 'arrow-up-left' : 'arrow-left', 'size-4') }}
                </span>
            @endif
        </span>
        @if ($href && $external)
            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
        @endif
@if ($href)
    </a>
@else
    </div>
@endif
