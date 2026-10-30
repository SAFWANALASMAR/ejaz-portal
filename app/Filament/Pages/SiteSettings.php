<?php

namespace App\Filament\Pages;

use App\Actions\Settings\UpdateSiteSettings;
use App\Enums\RoleName;
use App\Enums\SiteIcon;
use App\Filament\Concerns\ReadableByOverseers;
use App\Models\Setting;
use App\Models\User;
use App\Rules\PlainText;
use App\Rules\SafeUrl;
use App\Rules\SaudiMobile;
use App\Rules\StoreUrl;
use App\Rules\VideoUrl;
use App\Support\AccountSettings;
use App\Support\NationalAddress;
use App\Support\PortalStats;
use App\Support\Retention;
use App\Support\SaudiPhone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * إعدادات الموقع: التواصل، والتواصل الاجتماعي، ومقاطع الرئيسية، ومدة الاحتفاظ.
 * الصلاحية: settings.update (المشرف العام ومحرر المحتوى)، ومدة الاحتفاظ retention.update (المشرف العام).
 *
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    use ReadableByOverseers;

    public const SOCIAL = ['x', 'instagram', 'youtube', 'linkedin', 'snapchat', 'tiktok', 'telegram', 'facebook', 'whatsapp'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static function canWrite(): bool
    {
        return auth()->user()?->can('update', Setting::class) ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('settings.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'contact' => Setting::group('contact'),
            // افتراض المقاطع كما في BuildHomePage: مقاطع المدير التنفيذي ظاهرة، والسابقة (والإحصائيات) مخفية
            // والبطل وبطاقات «خدماتنا» ظاهرة دائماً ببنية مرجع المدير، فلا مفتاح لهما (decisions/2026-10-30-visual-refresh-2.md)
            'home' => [
                'show_video' => false,
                'show_latest' => true,
                'show_announcements' => true,
                'show_initiatives' => false,
                'show_stats' => false,
                'show_partners' => false,
                'show_videos' => false,
                'show_accounts' => true,
                ...Setting::group('home'),
            ],
            // رابط مخزّن لا يصح (من قبل قاعدة النطاق) لا يمنع حفظ بقية التبويبات: يظهر فارغاً فيعمل المعتمد
            'store' => [...Setting::group('store'), 'url' => StoreUrl::isAllowed(Setting::group('store')['url'] ?? null) ? Setting::group('store')['url'] : null],
            'donations' => [
                ...Setting::group('donations'),
                // المبالغ تُحفظ قائمة أرقام، والمكرر يعرضها صفوفاً
                'amounts' => array_map(fn (int $amount): array => ['amount' => $amount], array_map('intval', (array) (Setting::group('donations')['amounts'] ?? []))),
            ],
            'retention' => Setting::group('retention'),
            'accounts' => [
                'verification' => AccountSettings::verificationEnabled(),
                'show_deferred_portals' => AccountSettings::showsDeferredPortals(),
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => ! static::canWrite())
            ->components([
                Tabs::make()->tabs([
                    Tab::make(__('settings.tabs.contact'))->schema([
                        TextInput::make('contact.email')->label(__('settings.contact.email'))->email()->maxLength(255),
                        TextInput::make('contact.phone')->label(__('settings.contact.phone'))->tel()->maxLength(20),
                        // زر واتساب في التذييل وصفحة التواصل (WhatsApp::link)
                        TextInput::make('contact.whatsapp')->label(__('settings.contact.whatsapp'))
                            ->helperText(__('settings.contact.whatsapp_hint'))
                            ->tel()->maxLength(20)
                            ->rule(new SaudiMobile)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? (SaudiPhone::normalize($state) ?? $state) : null)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Textarea::make('contact.address')->label(__('settings.contact.address'))->rows(2)->maxLength(300),
                        // يولّد رابط الخريطة ما لم يُدخل رابطها صراحة (NationalAddress)
                        TextInput::make('contact.short_address')->label(__('settings.contact.short_address'))
                            ->helperText(__('settings.contact.short_address_hint'))
                            ->maxLength(12)
                            ->dehydrateStateUsing(fn (?string $state): ?string => NationalAddress::normalize($state) ?? (filled($state) ? $state : null))
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                if (filled($value) && NationalAddress::normalize((string) $value) === null) {
                                    $fail(__('settings.contact.short_address_invalid'));
                                }
                            })
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('contact.map_url')->label(__('settings.contact.map_url'))
                            ->helperText(__('settings.contact.map_url_hint'))
                            ->maxLength(1000)
                            ->rule(new SafeUrl(allowInternal: false)),
                    ]),
                    Tab::make(__('settings.tabs.social'))->schema([
                        Section::make()->description(__('settings.social.hint'))->columns(2)->schema(
                            array_map(fn (string $network) => TextInput::make('contact.social.'.$network)
                                ->label(__('settings.social.'.$network))
                                ->maxLength(500)
                                ->extraInputAttributes(['dir' => 'ltr'])
                                ->rule(new SafeUrl(allowInternal: false)), self::SOCIAL),
                        ),
                    ]),
                    Tab::make(__('settings.tabs.home'))->schema([
                        Section::make(__('settings.home.hero'))->columns(2)->schema([
                            TextInput::make('home.hero_title')->label(__('settings.home.hero_title'))->maxLength(120)->columnSpanFull(),
                            Textarea::make('home.hero_text')->label(__('settings.home.hero_text'))->rows(3)->maxLength(400)->columnSpanFull(),
                            TextInput::make('home.hero_button_label')->label(__('settings.home.hero_button_label'))->maxLength(40),
                            TextInput::make('home.hero_button_url')->label(__('settings.home.hero_button_url'))->maxLength(500)
                                ->extraInputAttributes(['dir' => 'ltr'])
                                ->rule(new SafeUrl),
                            // لا SVG من المستخدمين (docs/security.md)
                            FileUpload::make('home.hero_image')->label(__('settings.home.hero_image'))
                                ->helperText(__('settings.home.hero_image_hint'))
                                ->image()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(4096)
                                ->rules(['dimensions:max_width=4000,max_height=4000'])
                                // الصفحة بلا سجل، فيُسمح صراحة بمسار الصورة المحفوظة حالياً فقط
                                ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $file === (Setting::group('home')['hero_image'] ?? null))
                                ->disk('public')
                                ->directory(UpdateSiteSettings::IMAGE_DIRECTORY)
                                ->visibility('public')
                                ->columnSpanFull(),
                            TextInput::make('home.hero_image_alt')->label(__('settings.home.hero_image_alt'))
                                ->helperText(__('settings.home.hero_image_alt_hint'))
                                ->maxLength(300)
                                ->rule(new PlainText)
                                ->columnSpanFull(),
                            TextInput::make('home.video_url')->label(__('settings.home.video_url'))
                                ->helperText(__('settings.home.video_url_hint'))
                                ->maxLength(VideoUrl::MAX_LENGTH)
                                ->extraInputAttributes(['dir' => 'ltr'])
                                ->rule(new VideoUrl(youtubeOnly: true))
                                ->columnSpanFull(),
                        ]),
                        Section::make(__('settings.home.sections'))->description(__('settings.home.sections_hint'))->columns(3)->schema([
                            Toggle::make('home.show_latest')->label(__('settings.home.show_latest')),
                            Toggle::make('home.show_announcements')->label(__('settings.home.show_announcements')),
                            Toggle::make('home.show_accounts')->label(__('settings.home.show_accounts')),
                            Toggle::make('home.show_video')->label(__('settings.home.show_video')),
                            Toggle::make('home.show_initiatives')->label(__('settings.home.show_initiatives')),
                            Toggle::make('home.show_stats')->label(__('settings.home.show_stats')),
                            Toggle::make('home.show_partners')->label(__('settings.home.show_partners')),
                            Toggle::make('home.show_videos')->label(__('settings.home.show_videos')),
                        ]),
                        Section::make(__('settings.home.stats'))->description(__('settings.home.stats_hint'))->schema([
                            Repeater::make('home.stats')->hiddenLabel()->maxItems(4)->columns(5)->defaultItems(0)->schema([
                                TextInput::make('label')->label(__('settings.home.stat_label'))->required()->maxLength(60),
                                // رقم معتمد يُدخل يدوياً، أو رقم من بيانات البوابة الفعلية (PortalStats)
                                Select::make('source')->label(__('settings.home.stat_source'))
                                    ->options(fn (): array => collect(PortalStats::PUBLIC)->mapWithKeys(fn (string $key): array => [$key => __('settings.home.stat_sources.'.$key)])->all())
                                    ->placeholder(__('settings.home.stat_manual'))
                                    ->in(PortalStats::PUBLIC)
                                    ->live(),
                                TextInput::make('value')->label(__('settings.home.stat_value'))->integer()->minValue(0)->maxValue(100000000)
                                    ->required(fn (Get $get): bool => blank($get('source')))
                                    ->visible(fn (Get $get): bool => blank($get('source')))
                                    ->dehydrateStateUsing(fn ($state) => $state === null ? null : (int) $state),
                                TextInput::make('suffix')->label(__('settings.home.stat_suffix'))->maxLength(3),
                                Select::make('icon')->label(__('settings.home.stat_icon'))
                                    ->options(SiteIcon::class)
                                    ->required(),
                            ]),
                        ]),
                    ]),
                    // المتجر المعتمد على سلة (قرار صفوان): رابطه https من نطاق مسموح. وخيارات المتجر الداخلي
                    // والتبرعات مؤرشفة خلف PAYMENTS_ENABLED (docs/plans/store-salla.md)
                    Tab::make(__('settings.tabs.store'))->schema([
                        TextInput::make('store.url')->label(__('settings.store.url'))
                            ->helperText(__('settings.store.url_hint', ['default' => config('store.url')]))
                            ->maxLength(500)
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->rule(new StoreUrl),
                        Toggle::make('store.enabled')->label(__('settings.store.enabled'))->visible(fn (): bool => (bool) config('payments.enabled')),
                        Textarea::make('store.intro')->label(__('settings.store.intro'))->rows(3)->maxLength(400)->visible(fn (): bool => (bool) config('payments.enabled')),
                        Section::make(__('settings.store.products'))->description(__('settings.store.products_hint'))->visible(fn (): bool => (bool) config('payments.enabled'))->schema([
                            Repeater::make('store.products')->hiddenLabel()->maxItems(24)->columns(2)->defaultItems(0)->schema([
                                TextInput::make('name')->label(__('settings.store.product_name'))->required()->maxLength(100),
                                TextInput::make('url')->label(__('settings.store.product_url'))->required()->maxLength(500)
                                    ->extraInputAttributes(['dir' => 'ltr'])
                                    ->rule(new SafeUrl(allowInternal: false)),
                                Textarea::make('description')->label(__('settings.store.product_description'))->rows(2)->maxLength(300)->columnSpanFull(),
                            ]),
                        ]),
                    ]),
                    Tab::make(__('settings.tabs.donations'))->visible(fn (): bool => (bool) config('payments.enabled'))->schema([
                        Toggle::make('donations.enabled')->label(__('settings.donations.enabled')),
                        Textarea::make('donations.intro')->label(__('settings.donations.intro'))->rows(3)->maxLength(400),
                        Section::make(__('settings.donations.amounts'))->description(__('settings.donations.amounts_hint'))->schema([
                            Repeater::make('donations.amounts')->hiddenLabel()->maxItems(6)->defaultItems(0)->schema([
                                TextInput::make('amount')->label(__('settings.donations.amount'))->integer()->required()
                                    ->minValue((int) (config('payments.donation.min_halalas') / 100))
                                    ->maxValue((int) (config('payments.donation.max_halalas') / 100)),
                            ]),
                        ]),
                    ]),
                    // التوثيق والبوابات المؤجلة (accounts.update: المدير التنفيذي والمسؤول التقني)، ولا تُرسل من غيرهما.
                    // ويطّلع عليها المشرف العام
                    Tab::make(__('settings.tabs.accounts'))
                        ->visible(fn (): bool => (auth()->user()?->can('accounts.update') ?? false) || (auth()->user() instanceof User && RoleName::isOverseer(auth()->user())))
                        ->schema([
                            Section::make()->description(__('settings.accounts.hint'))->schema([
                                Toggle::make('accounts.verification')->label(__('settings.accounts.verification'))
                                    ->helperText(__('settings.accounts.verification_hint')),
                                Toggle::make('accounts.show_deferred_portals')->label(__('settings.accounts.show_deferred_portals'))
                                    ->helperText(__('settings.accounts.show_deferred_portals_hint')),
                            ]),
                        ]),
                    // تحذف بيانات شخصية آلياً، فللمسؤول التقني وحده (retention.update)، ولا تُرسل من غيره
                    Tab::make(__('settings.tabs.retention'))
                        ->visible(fn (): bool => auth()->user()?->can('retention.update') ?? false)
                        ->schema([
                            Section::make()->description(__('settings.retention.hint'))->schema([
                                TextInput::make('retention.days')->label(__('settings.retention.days'))
                                    ->helperText(__('settings.retention.days_hint'))
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(Retention::MAX_DAYS),
                            ]),
                        ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('site.save'))->submit('save')->keyBindings(['mod+s']),
                    ])->visible(fn (): bool => static::canWrite()),
                ]),
        ]);
    }

    public function save(UpdateSiteSettings $action): void
    {
        $this->authorizeWrite();

        /** @var User $user */
        $user = auth()->user();

        /** @var array{contact: array<string, mixed>, home: array<string, mixed>, store: array<string, mixed>, donations: array<string, mixed>, retention?: array<string, mixed>, accounts?: array<string, mixed>} $state */
        $state = $this->form->getState();

        // تبويب الحسابات يراه المشرف العام اطلاعاً، فلا يُرسل إلا ممن يملك تغييره
        if (! $user->can('accounts.update')) {
            unset($state['accounts']);
        }

        $state['contact']['social'] = array_filter($state['contact']['social'] ?? []);
        $state['home']['stats'] = array_values($state['home']['stats'] ?? []);
        if (config('payments.enabled')) {
            $state['store']['products'] = array_values($state['store']['products'] ?? []);
            $state['donations']['amounts'] = array_values(array_map(fn (array $row): int => (int) $row['amount'], $state['donations']['amounts'] ?? []));
        } else {
            // الحقول المؤرشفة مخفية فلا تعود في الحالة: تبقى قيمها المحفوظة كما هي، ويُحفظ رابط المتجر وحده
            $state['store'] = [...Setting::group('store'), 'url' => $state['store']['url'] ?? null];
            unset($state['donations']);
        }

        try {
            $action->handle($user, $state);
        } catch (ValidationException $exception) {
            // الصورة المرفوضة حُذفت، فيعود الحقل إلى الصورة المحفوظة ليُحفظ النموذج بعدها
            if (array_key_exists('home.hero_image', $exception->errors())) {
                $stored = Setting::group('home')['hero_image'] ?? null;
                $this->data['home']['hero_image'] = is_string($stored) ? [(string) Str::uuid() => $stored] : [];
            }

            // أخطاء الإجراء بمسارات الإعدادات، والحقول في النموذج تحت data
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.$key => $messages])->all(),
            );
        }

        Notification::make()->success()->title(__('settings.saved'))->send();
    }
}
