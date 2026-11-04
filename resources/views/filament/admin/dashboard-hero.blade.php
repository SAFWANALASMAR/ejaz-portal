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
        <section class="ej-dash-empty" data-ej-dashboard-empty>
            <svg class="ej-dash-empty-art" viewBox="0 0 160 120" aria-hidden="true">
                <path d="M80 10l11 27 27 11-27 11-11 27-11-27-27-11 27-11z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" opacity=".35"/>
                <rect x="58" y="26" width="44" height="44" rx="6" transform="rotate(45 80 48)" fill="none" stroke="currentColor" stroke-width="2" opacity=".55"/>
                <circle cx="80" cy="48" r="9" fill="currentColor" opacity=".18"/>
                <path d="M30 104h100" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".3"/>
                <path d="M52 112h56" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".18"/>
            </svg>
            <h2 class="ej-dash-empty-heading">{{ __('admin.dashboard.empty_heading') }}</h2>
            <p class="ej-dash-empty-body">{{ __('admin.dashboard.empty_body') }}</p>
        </section>
    @endif
</div>
