{{--
    «الجمعية بالأرقام» (الصورة 01، والمرجع الأسلوبي — docs/plans/visual-refresh.md): شريط داكن بلون الهوية، في كل خانة
    أيقونة في مربع ثم الرقم بعدّ تصاعدي حين يظهر (data-count-to — motion.js) ثم الوصف. أرقام معتمدة من لوحة الإدارة فقط،
    ولا يظهر الشريط بدونها (التصحيحات ٢). والرقم مكتوب كاملاً في الصفحة، فيبقى صحيحاً بلا سكربت ولمن طلب تقليل الحركة.
--}}
@php
    $columns = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-2 lg:grid-cols-3', 4 => 'grid-cols-2 lg:grid-cols-4'][count($stats)] ?? 'grid-cols-2 lg:grid-cols-4';
@endphp

<section class="section-dark py-12 lg:py-16" aria-labelledby="home-stats">
    <div class="bg-pattern-light absolute inset-0 -z-10" aria-hidden="true"></div>

    <div class="container-page">
        <h2 id="home-stats" class="sr-only">{{ __('home.stats.title') }}</h2>

        <dl class="grid gap-4 {{ $columns }}" data-reveal-group>
            @foreach ($stats as $stat)
                <div class="card-dark flex flex-col items-center gap-2 px-4 py-8 text-center">
                    <dt class="order-2 text-sm text-white/80">{{ $stat['label'] }}</dt>
                    <dd class="order-1 flex flex-col items-center gap-3">
                        @if ($stat['icon'])
                            <span class="icon-tile icon-tile-dark size-14" aria-hidden="true">{{ \App\Support\Icon::svg($stat['icon'], 'size-7') }}</span>
                        @endif
                        <span class="text-4xl font-bold text-white tabular-nums sm:text-5xl"
                            data-count-to="{{ $stat['value'] }}" data-count-suffix="{{ $stat['suffix'] }}">{{ number_format($stat['value']) }}{{ $stat['suffix'] }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
