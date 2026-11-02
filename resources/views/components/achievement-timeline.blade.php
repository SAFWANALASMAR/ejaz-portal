{{--
    الخط الزمني للإنجازات المنشورة (Final-Board — «إنجازاتنا»)، في الصفحة العامة وبوابة المجلس والجمعية العمومية: خط عمودي في جهة
    البداية بنقطة لكل إنجاز، ثم بطاقة (.tone-card) بمربع أيقونة، والتاريخ بأيقونة التقويم، والعنوان، والتفاصيل، ورابط المرفق الموقّع
    متى وُجد. وexcerpt: التفاصيل مقتطعة بثلاثة أسطر (البوابة)، وإلا كاملة بأسطرها (الصفحة العامة).
--}}
@props(['achievements', 'excerpt' => false])

@php
    use App\Support\Dates;
@endphp

<ol {{ $attributes->class(['relative flex flex-col gap-4 ps-6 before:absolute before:inset-y-2 before:start-2 before:w-0.5 before:rounded-full before:bg-[var(--concept-border)]']) }} data-achievements-timeline>
    @foreach ($achievements as $achievement)
        <li class="relative" data-achievement>
            <span class="absolute -start-[1.375rem] top-6 size-3.5 rounded-full border-2 border-white bg-primary shadow" aria-hidden="true"></span>
            <article class="tone-card flex items-start gap-3.5 p-4 sm:p-5">
                <x-icon-tile icon="star" size="sm" />
                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <p class="tone-card-muted flex items-center gap-1.5 text-sm font-extrabold">
                        {{ \App\Support\Icon::svg('calendar', 'size-4', ['aria-hidden' => 'true']) }}
                        <time datetime="{{ $achievement->achieved_on->toDateString() }}">{{ Dates::display($achievement->achieved_on) }}</time>
                    </p>
                    <h3 class="text-lg font-black break-words">{{ $achievement->title }}</h3>
                    <p @class(['tone-card-muted leading-7 break-words whitespace-pre-line', 'line-clamp-3' => $excerpt])>{{ $achievement->details }}</p>
                    @if ($url = $achievement->attachmentUrl())
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="tone-pill mt-1 self-start" data-achievement-attachment>
                            {{ \App\Support\Icon::svg($achievement->attachmentIsImage() ? 'photo' : 'arrow-down-tray', 'size-4', ['aria-hidden' => 'true']) }}
                            {{ __($achievement->attachmentIsImage() ? 'achievements.actions.open_image' : 'achievements.actions.download_file') }}
                            <span class="sr-only">({{ __('site.opens_in_new_tab') }})</span>
                        </a>
                    @endif
                </div>
            </article>
        </li>
    @endforeach
</ol>
