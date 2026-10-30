{{--
    «الجمعية بالأرقام» (نمط ٥، والتصور المعتمد: شريط الأرقام الداكن): قسم داكن بزجاجية، في كل خانة شارة رقم (x-stat-badge)
    بأيقونة زجاجية ورقم كبير بالأزرق الفاتح يعدّ تصاعدياً حين يظهر، ثم الوصف. أرقام معتمدة من لوحة الإدارة فقط، ولا يظهر
    الشريط بدونها (التصحيحات ٢). والرقم مكتوب كاملاً في الصفحة، فيبقى صحيحاً بلا سكربت ولمن طلب تقليل الحركة.
--}}
@php
    $columns = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-2 lg:grid-cols-3', 4 => 'grid-cols-2 lg:grid-cols-4'][count($stats)] ?? 'grid-cols-2 lg:grid-cols-4';
@endphp

<section class="section-dark py-14 lg:py-20" aria-labelledby="home-stats">
    <div class="bg-pattern-light absolute inset-0 -z-10" aria-hidden="true"></div>

    <div class="container-page">
        <h2 id="home-stats" class="sr-only">{{ __('home.stats.title') }}</h2>

        <ul class="grid gap-4 {{ $columns }}" data-reveal-group>
            @foreach ($stats as $stat)
                <li class="glass rounded-3xl px-4 py-8">
                    <x-stat-badge :value="$stat['value']" :label="$stat['label']" :suffix="$stat['suffix']" :icon="$stat['icon']" />
                </li>
            @endforeach
        </ul>
    </div>
</section>
