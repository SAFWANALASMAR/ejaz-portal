{{-- رأس رئيسية اللوحة (الدفعة ٢ من طلب لوحة الإدارة، App\Filament\Pages\Dashboard): التحية والأدوار وتاريخ اليوم، والحالة الفارغة --}}
<div class="ej-dash" data-ej-dashboard>
    <header class="ej-dash-hero" data-ej-dashboard-hero data-ej-user-name="{{ $name }}">
        <span class="ej-dash-hero-icon" data-ej-hero-icon aria-hidden="true">{{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedSparkles, size: \Filament\Support\Enums\IconSize::Large) }}</span>

        <div class="ej-dash-hero-text">
            <p class="ej-dash-hero-eyebrow">{{ __('admin.dashboard.eyebrow') }} · <time>{{ $today }}</time></p>
            <h1 class="ej-dash-hero-greeting" data-ej-greeting>{{ __('admin.dashboard.greeting', ['name' => $name]) }}</h1>
            <p class="ej-dash-hero-description" data-ej-hero-description>{{ __('admin.dashboard.description') }}</p>

            @if ($roles !== [])
                <ul class="ej-dash-hero-roles" aria-label="{{ __('admin.dashboard.roles') }}">
                    @foreach ($roles as $role)
                        <li>{{ $role }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <img src="{{ asset('images/brand/logo-mark.png') }}" alt="" class="ej-dash-hero-mark" aria-hidden="true" width="495" height="505">
    </header>

    @if ($empty)
        {{-- لا عنصر في الرئيسية: أول مجموعات القائمة التي يملكها الحساب أزراراً (ملاحظة المقيّم على #168) --}}
        <x-admin.empty-state framed data-ej-dashboard-empty :heading="__('admin.dashboard.empty_heading')" :body="__('admin.dashboard.empty_body')" :links="$shortcuts" />
    @endif
</div>
