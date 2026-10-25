{{--
    شريط الإعلانات المتحرك (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): صور الإعلانات بعناوينها، وصورة إعلان المتجر تفتح متجر
    الجمعية برابطه من الإعدادات (/store). يتوقف بالزر وبالمرور والتركيز، وبلا حركة لمن طلب تقليلها (app.css — .ticker).
    الإعلانات من اللوحة (App\Models\Announcement).
--}}
<section class="bg-surface py-12 lg:py-16" aria-labelledby="home-announcements">
    <div class="container-page">
        <div class="flex items-end justify-between gap-4">
            <x-section-heading id="home-announcements">{{ __('home.ticker.announcements') }}</x-section-heading>
            <div class="flex items-center gap-3">
                <button type="button" data-ticker-toggle="home-announcements-ticker" aria-pressed="false" data-label-pause="{{ __('home.ticker.pause') }}" data-label-play="{{ __('home.ticker.play') }}"
                    aria-label="{{ __('home.ticker.pause') }}" class="inline-flex rounded-full border border-line bg-white p-2 text-muted hover:text-ink">
                    {{ svg('heroicon-o-pause', 'size-5', ['aria-hidden' => 'true', 'data-icon-pause' => true]) }}
                    {{ svg('heroicon-o-play', 'size-5', ['aria-hidden' => 'true', 'data-icon-play' => true, 'hidden' => true]) }}
                </button>
                <a href="{{ route('media.announcements') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                    {{ __('home.ticker.all_announcements') }}
                    {{ svg('heroicon-o-arrow-left', 'size-4', ['aria-hidden' => 'true']) }}
                </a>
            </div>
        </div>
    </div>

    <div id="home-announcements-ticker" class="ticker mt-8" data-ticker style="--ticker-duration: {{ max(35, $announcements->count() * 10) }}s">
        <div class="ticker-track">
            @foreach ([false, true] as $copy)
                <ul class="ticker-group gap-5 px-2.5" @if ($copy) aria-hidden="true" data-ticker-copy @endif>
                    @foreach ($announcements as $announcement)
                        <li class="w-72 shrink-0 sm:w-80">
                            @include('media.announcements.card', ['announcement' => $announcement, 'hidden' => $copy])
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>
</section>
