<?php

namespace App\Filament\Pages;

use App\Actions\Settings\UpdateSiteSettings;
use App\Enums\SiteIcon;
use App\Models\Setting;
use App\Models\User;
use App\Rules\SafeUrl;
use App\Support\PortalStats;
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
 * إعدادات الموقع: التواصل، والتواصل الاجتماعي، ومقاطع الرئيسية.
 * الصلاحية: settings.update (المشرف العام ومحرر المحتوى).
 *
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    public const SOCIAL = ['x', 'instagram', 'youtube', 'linkedin', 'snapchat', 'tiktok', 'facebook', 'whatsapp'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
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
            'home' => [
                'show_shortcuts' => true,
                'show_latest' => true,
                'show_initiatives' => true,
                'show_stats' => true,
                'show_partners' => true,
                ...Setting::group('home'),
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->tabs([
                    Tab::make(__('settings.tabs.contact'))->schema([
                        TextInput::make('contact.email')->label(__('settings.contact.email'))->email()->maxLength(255),
                        TextInput::make('contact.phone')->label(__('settings.contact.phone'))->tel()->maxLength(20),
                        Textarea::make('contact.address')->label(__('settings.contact.address'))->rows(2)->maxLength(300),
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
                        ]),
                        Section::make(__('settings.home.sections'))->columns(3)->schema([
                            Toggle::make('home.show_shortcuts')->label(__('settings.home.show_shortcuts')),
                            Toggle::make('home.show_latest')->label(__('settings.home.show_latest')),
                            Toggle::make('home.show_initiatives')->label(__('settings.home.show_initiatives')),
                            Toggle::make('home.show_stats')->label(__('settings.home.show_stats')),
                            Toggle::make('home.show_partners')->label(__('settings.home.show_partners')),
                        ]),
                        Section::make(__('settings.home.stats'))->description(__('settings.home.stats_hint'))->schema([
                            Repeater::make('home.stats')->hiddenLabel()->maxItems(4)->columns(5)->defaultItems(0)->schema([
                                TextInput::make('label')->label(__('settings.home.stat_label'))->required()->maxLength(60),
                                // رقم معتمد يُدخل يدوياً، أو رقم من بيانات البوابة الفعلية (PortalStats)
                                Select::make('source')->label(__('settings.home.stat_source'))
                                    ->options(fn (): array => collect(PortalStats::PUBLIC)->mapWithKeys(fn (string $key): array => [$key => __('settings.home.stat_sources.'.$key)])->all())
                                    ->placeholder(__('settings.home.stat_manual'))
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
                    ]),
                ]),
        ]);
    }

    public function save(UpdateSiteSettings $action): void
    {
        /** @var User $user */
        $user = auth()->user();

        /** @var array{contact: array<string, mixed>, home: array<string, mixed>} $state */
        $state = $this->form->getState();

        $state['contact']['social'] = array_filter($state['contact']['social'] ?? []);
        $state['home']['stats'] = array_values($state['home']['stats'] ?? []);

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
