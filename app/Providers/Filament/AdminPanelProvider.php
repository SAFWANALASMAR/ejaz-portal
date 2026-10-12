<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\SecurityHeaders;
use Filament\FontProviders\LocalFontProvider;
use Filament\Forms\Components\FileUpload;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * لوحة الإدارة على /admin.
 *
 * لا صفحة دخول خاصة باللوحة: الدخول موحد عبر /login (Fortify) ومعه التحقق الثنائي،
 * حتى لا يوجد طريق دخول ثانٍ يتجاوزه. انظر docs/decisions.md.
 */
class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // كل حقل رفع لا يقبل إلا ملفاً رُفع فعلاً أو كان محفوظاً من قبل، فلا يُستبدل
        // مساره بمسار ملف آخر (مهم خاصة للأقراص الخاصة لاحقاً)
        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->preventFilePathTampering());
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(fn (): string => __('admin.brand'))
            ->brandLogo(fn (): string => asset('images/brand/logo-horizontal.svg'))
            ->darkModeBrandLogo(fn (): string => asset('images/brand/logo-horizontal-white.svg'))
            ->brandLogoHeight('2.75rem')
            ->colors([
                // الأزرق الرسمي بدرجته المقروءة للنص الأبيض (دليل الهوية — docs/design-system.md)
                'primary' => Color::hex('#2E6898'),
            ])
            // الخط يُخدَم من خادم البوابة نفسه (Vite) لا من مزود خارجي
            ->font('Zain', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): Htmlable => Vite::fonts())
            // صورة الحساب تُولَّد محلياً، لا من مزود خارجي
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationItems([
                NavigationItem::make(fn (): string => __('admin.view_site'))
                    ->url('/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-globe-alt')
                    ->sort(100),
            ])
            // رؤوس الحماية وإيقاف الحساب كما في مجموعة web، فمسارات اللوحة لا تمر بها
            ->middleware([
                SecurityHeaders::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                EnsureAccountIsActive::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureTwoFactorIsEnabled::class,
                AdminIdleTimeout::class,
            ], isPersistent: true);
    }
}
