<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\LeavePanelWithFullPageLoad;
use App\Http\Middleware\RejectMalformedAdminRecord;
use App\Http\Middleware\RequirePasswordUpgrade;
use App\Http\Middleware\SecurityHeaders;
use Filament\Actions\Exports\ExportColumn;
use Filament\FontProviders\LocalFontProvider;
use Filament\Forms\Components\FileUpload;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\View\View;

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

        // خلية فارغة في صف قابل للنقر تصير رابطاً بلا نص (axe: link-name)، فلكل عمود نصي علامة الفراغ نفسها
        // المستعملة في الجداول، ويغيّرها العمود متى شاء
        TextColumn::configureUsing(fn (TextColumn $column) => $column->placeholder('—'));

        // كل عمود تصدير محمي من حقن الصيغ ولو نُسي له safeCell (كان «المسؤول» في تصدير الطلبات بلا حماية — الاختبار
        // الأمني 0.14.0). وsafeCell في الأعمدة يتخطى الفراغات قبل علامة الصيغة أيضاً
        ExportColumn::configureUsing(fn (ExportColumn $column) => $column->preventFormulaInjection());
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
                // الأزرق وحده (أمر التصور النهائي، البند G1): حالة «ناجح» والمعلومة بدرجتين من الأزرق بدل أخضر Filament
                // الافتراضي. و«مرفوض» (danger) يبقى أحمر، وهو أحد الاستثناءات الثلاثة
                'success' => Color::hex('#1F5F8F'),
                'info' => Color::hex('#3E8ACA'),
                // وحالات الانتظار والتنبيه (warning) بأزرق سماوي بدل كهرماني Filament (قرار المدير عبر صفوان، 1 نوفمبر 2026)
                'warning' => Color::hex('#1D8FD1'),
            ])
            // الخط يُخدَم من خادم البوابة نفسه (Vite) لا من مزود خارجي
            ->font('Zain', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): Htmlable => Vite::fonts())
            // سمة اللوحة على التصور النهائي 13–15: الشريط الداكن ومربعات الأيقونات ورؤوس الصفحات
            // (docs/decisions/2026-10-30-admin-theme.md)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn (): View => view('filament.admin.sidebar-brand'))
            // الهوية في الشريط العلوي (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ١): بعد زر طيّ القائمة، بدل شعار Filament الأفقي
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_BEFORE, fn (): View => view('filament.admin.topbar-brand'))
            // زر «المساعد الذكي» بجانب البحث العام يفتح المساعد (ملاحظات صفوان على #166)
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): View => view('filament.admin.topbar-assistant'))
            // صورة الحساب تُولَّد محلياً، لا من مزود خارجي
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // التنقّل بين صفحات اللوحة بلا إعادة تحميل كاملة، وما خارجها يُحمَّل تحميلاً كاملاً (decisions.md)
            ->spa()
            ->spaUrlExceptions(fn (): array => self::urlsOutsidePanel($panel->getPath()))
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // عنصر الحساب (مرحباً وتسجيل الخروج) صار تحية رأس الرئيسية (App\Filament\Pages\Dashboard)، والخروج في قائمة الحساب
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
                LeavePanelWithFullPageLoad::class,
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
                // كلمة مرور قائمة لا تطابق السياسة: تُغيَّر من «الأمان» قبل اللوحة
                RequirePasswordUpgrade::class,
                // بعد الدخول، فلا يُعرف شكل الروابط من 404 قبله
                RejectMalformedAdminRecord::class,
            ], isPersistent: true);
    }

    /**
     * أنماط كل رابط خارج /admin: الموقع العام بقالبه وأصوله، والدخول والخروج والتحقق الثنائي، وتنزيل الملفات
     * والتصدير والطباعة (روابط موقّعة أو ردود ملفات لا صفحات). تُبنى من أول مقطع في كل مسار مسجّل، فالمسار
     * الجديد خارج اللوحة يُستثنى بلا تعديل هنا. وتشمل الرابط المطلق (route() وurl()) والنسبي، وبالاستعلام
     * وبالجزء #.
     *
     * @return list<string>
     */
    public static function urlsOutsidePanel(string $panelPath): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(fn (RoutingRoute $route): string => Str::before(ltrim($route->uri(), '/'), '/'))
            ->reject(fn (string $segment): bool => $segment === $panelPath || str_starts_with($segment, '{'))
            ->unique()
            ->flatMap(fn (string $segment): array => collect($segment === '' ? [url('/'), url('/').'/', '/'] : [url($segment), '/'.$segment])
                ->flatMap(fn (string $prefix): array => [$prefix, $prefix.'?*', $prefix.'#*', ...($segment === '' ? [] : [$prefix.'/*'])])
                ->all())
            ->values()
            ->all();
    }
}
