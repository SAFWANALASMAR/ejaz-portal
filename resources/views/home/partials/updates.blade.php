{{--
    «مستجدات الجمعية» (مرجع المدير التنفيذي): بطاقات صغيرة لأحدث المنشور في البرامج والمسابقات والمكتبة والحوكمة، بأيقونة
    نوعه في مربع متدرج وعنوانه وتاريخه، تظهر تباعاً عند التمرير. من البيانات وحدها (HomeShowcase::updates)، ويختفي القسم بلا
    منشور. و«المزيد» إلى المركز الإعلامي.

    $updates: list<array{kind, icon, title, url, date}>.
--}}
@php($tones = ['program' => 'green', 'competition' => 'navy', 'library' => 'sky', 'document' => 'blue', 'news' => 'violet'])

<section class="section-tint py-14 lg:py-16" aria-labelledby="home-updates">
    <div class="container-page">
        <div class="flex items-center justify-between gap-4" data-reveal>
            <h2 id="home-updates" class="flex items-center gap-3 text-2xl font-bold text-primary-dark">
                <x-icon-tile icon="calendar-days" tone="blue" size="sm" />
                {{ __('ui.updates.title') }}
            </h2>
            @if (Route::has('media.index'))
                <a href="{{ route('media.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                    {{ __('ui.updates.more') }}
                    {{ \App\Support\Icon::svg('arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                </a>
            @endif
        </div>

        <ul class="scroll-row mt-6" data-reveal-group>
            @foreach ($updates as $update)
                <li>
                    <a href="{{ $update['url'] }}" @navigate($update['url']) class="card-glow group flex h-full items-start gap-4 p-5">
                        <x-icon-tile :icon="$update['icon']" :tone="$tones[$update['kind']] ?? 'blue'" size="md" />
                        <span class="min-w-0">
                            <span class="text-xs font-semibold text-accent">{{ __('ui.updates.kinds.'.$update['kind']) }}</span>
                            <span class="mt-1 line-clamp-2 block font-bold leading-7 text-primary-dark group-hover:text-primary">{{ $update['title'] }}</span>
                            <time datetime="{{ $update['date']->toDateString() }}" class="mt-1 block text-sm text-muted">{{ \App\Support\Dates::display($update['date']) }}</time>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>
