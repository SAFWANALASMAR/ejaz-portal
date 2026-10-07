{{--
    شريط الإحصائيات (الصورة 01): أعمدة بينها خطوط رفيعة، في كل عمود أيقونة ثم الرقم ثم الوصف.
    أرقام معتمدة من لوحة الإدارة فقط، ولا يظهر الشريط بدونها (التصحيحات ٢).
--}}
@php
    $columns = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-2 lg:grid-cols-3', 4 => 'grid-cols-2 lg:grid-cols-4'][count($stats)] ?? 'grid-cols-2 lg:grid-cols-4';
@endphp

<section class="border-b border-line/70 bg-white" aria-labelledby="home-stats">
    <div class="container-page">
        <h2 id="home-stats" class="sr-only">{{ __('home.stats.title') }}</h2>

        <dl class="grid gap-px bg-line/70 {{ $columns }}">
            @foreach ($stats as $stat)
                <div class="flex flex-col items-center gap-1 bg-white px-4 py-8 text-center">
                    <dt class="order-2 text-sm text-muted">{{ $stat['label'] }}</dt>
                    <dd class="order-1 flex flex-col items-center gap-2">
                        @if ($stat['icon'])
                            <span class="text-gold" aria-hidden="true">{{ svg('heroicon-o-'.$stat['icon'], 'size-7') }}</span>
                        @endif
                        <span class="text-3xl font-bold text-primary sm:text-4xl">{{ number_format($stat['value']) }}{{ $stat['suffix'] }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
