<?php

namespace App\Filament\Pages;

use App\Actions\Settings\UpdateHomeContent;
use App\Actions\Settings\UpdateSiteSettings;
use App\Enums\SiteIcon;
use App\Filament\Concerns\ReadableByOverseers;
use App\Models\Setting;
use App\Models\User;
use App\Support\HomeContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * «محتوى الرئيسية» (ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026): نصوص كلمة خادم الحرمين الشريفين، وكلمة رئيس مجلس
 * الإدارة، والمبررات، والقيم الأربع، وصورها. الأصل من الخطة الاستراتيجية (HomeContent)، وما يُحفظ هنا يحل محله، والفارغ
 * يعيده. والصور تُستبدل بأعلى جودة متى وصلت. الصلاحية: إعدادات الموقع (settings.update)، ويطّلع المشرف العام.
 *
 * @property-read Schema $form
 */
class HomeContentPage extends Page
{
    use ReadableByOverseers;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 89;

    protected static ?string $slug = 'home-content';

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static function canWrite(): bool
    {
        return auth()->user()?->can('update', Setting::class) ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.home_content.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.groups.settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('settings.home_content.title');
    }

    public function mount(): void
    {
        $stored = Setting::group(HomeContent::KEY);
        $data = [];

        // النص المحفوظ، وإلا الأصل، فيرى المحرر ما يُعرض ويعدّله
        foreach (HomeContent::TEXTS as $section => $keys) {
            foreach ($keys as $key) {
                $data[$section][$key] = $stored[$section][$key] ?? __('home.sections.'.$section.'.'.$key);
            }
        }

        foreach (array_keys(HomeContent::IMAGES) as $section) {
            $data[$section]['image'] = $stored[$section]['image'] ?? null;
            $data[$section]['image_alt'] = $stored[$section]['image_alt'] ?? __('home.sections.'.$section.'.image_alt');
        }

        foreach (array_keys(HomeContent::BRAND) as $key) {
            $data['brand'][$key] = $stored['brand'][$key] ?? null;
        }

        foreach (HomeContent::HERO_SLIDES as $key) {
            $data['hero_slides'][$key] = $stored['hero_slides'][$key] ?? null;
        }

        foreach (range(0, HomeContent::VALUES - 1) as $index) {
            $item = (array) ($stored['values']['items'][$index] ?? []);
            $data['values']['items'][] = [
                'title' => $item['title'] ?? __('home.sections.values.items.'.$index.'.title'),
                'text' => $item['text'] ?? __('home.sections.values.items.'.$index.'.text'),
                'image' => $item['image'] ?? null,
                'icon' => $item['icon'] ?? HomeContent::VALUE_ICONS[$index],
            ];
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => ! static::canWrite())
            ->components([
                // صور شرائح البطل بترتيبها (أمر التصور النهائي، النسخة 63 — البند H1). فارغة تعني بديلها: صورة البطل وصورتا المقطعين
                Section::make(__('settings.home_content.hero_slides'))->description(__('settings.home_content.hero_slides_hint'))->columns(2)->schema(
                    array_map(fn (string $key): FileUpload => self::image('hero_slides.'.$key, 'hero')->label(__('settings.home_content.hero_slide_'.$key)), HomeContent::HERO_SLIDES),
                ),
                Section::make(__('home.sections.king.heading'))->columns(2)->schema([
                    Textarea::make('king.text')->label(__('settings.home_content.text'))->rows(4)->maxLength(6000)->columnSpanFull(),
                    TextInput::make('king.signature_title')->label(__('settings.home_content.signature_title'))->maxLength(300),
                    TextInput::make('king.signature_name')->label(__('settings.home_content.signature_name'))->maxLength(300),
                    self::image('king'),
                    TextInput::make('king.image_alt')->label(__('settings.home_content.image_alt'))->maxLength(300),
                    TextInput::make('king.heading')->label(__('settings.home_content.heading'))->maxLength(300),
                ]),
                Section::make(__('home.sections.chairman.heading'))->columns(2)->schema([
                    Textarea::make('chairman.excerpt')->label(__('settings.home_content.excerpt'))->rows(3)->maxLength(6000)->columnSpanFull(),
                    Textarea::make('chairman.full')->label(__('settings.home_content.full'))->helperText(__('settings.home_content.full_hint'))
                        ->rows(10)->maxLength(6000)->columnSpanFull(),
                    TextInput::make('chairman.name')->label(__('settings.home_content.name'))->maxLength(300),
                    TextInput::make('chairman.title')->label(__('settings.home_content.person_title'))->maxLength(300),
                    self::image('chairman'),
                    TextInput::make('chairman.image_alt')->label(__('settings.home_content.image_alt'))->maxLength(300),
                    TextInput::make('chairman.heading')->label(__('settings.home_content.heading'))->maxLength(300),
                ]),
                Section::make(__('home.sections.rationale.heading'))->columns(2)->schema([
                    TextInput::make('rationale.heading')->label(__('settings.home_content.heading'))->maxLength(300),
                    Textarea::make('rationale.text')->label(__('settings.home_content.text'))->rows(4)->maxLength(6000)->columnSpanFull(),
                    TextInput::make('rationale.lead')->label(__('settings.home_content.lead'))->maxLength(300),
                    TextInput::make('rationale.outcome')->label(__('settings.home_content.outcome'))->maxLength(300),
                    self::image('rationale'),
                    TextInput::make('rationale.image_alt')->label(__('settings.home_content.image_alt'))->helperText(__('settings.home_content.decorative_hint'))->maxLength(300),
                ]),
                Section::make(__('home.sections.values.heading'))->schema([
                    TextInput::make('values.heading')->label(__('settings.home_content.heading'))->maxLength(300),
                    // أربع قيم ثابتة العدد كما في الخطة، يُعدَّل نصها وصورتها
                    Repeater::make('values.items')->label(__('settings.home_content.values'))
                        ->addable(false)->deletable(false)->reorderable(false)
                        ->columns(2)
                        ->schema([
                            TextInput::make('title')->label(__('settings.home_content.value_title'))->maxLength(80),
                            Textarea::make('text')->label(__('settings.home_content.value_text'))->rows(2)->maxLength(300),
                            Select::make('icon')->label(__('ui.admin.value_icon'))->options(SiteIcon::class)->required(),
                            self::image('image', 'values')->columnSpanFull(),
                        ]),
                ]),
                // شرائح الهوية تحت البطل والبطاقة الجانبية في «خدماتنا» (مرجع المدير، 30 أكتوبر 2026). فارغة تعني: الرؤية والرسالة من
                // صفحة «عن الجمعية» المنشورة، والبطاقة بلا نص
                Section::make(__('ui.admin.identity'))->description(__('settings.home_content.identity_hint_final'))->columns(2)->schema([
                    Textarea::make('identity.vision')->label(__('ui.identity.vision'))->rows(3)->maxLength(6000),
                    Textarea::make('identity.mission')->label(__('ui.identity.mission'))->rows(3)->maxLength(6000),
                    Textarea::make('identity.goals')->label(__('settings.home_content.goals'))->rows(3)->maxLength(6000),
                    Textarea::make('identity.principles')->label(__('settings.home_content.principles'))->rows(3)->maxLength(6000),
                    TextInput::make('identity.side_title')->label(__('ui.admin.side_title'))->maxLength(300),
                    TextInput::make('identity.side_text')->label(__('ui.admin.side_text'))->maxLength(300),
                ]),
                // عناوين أقسام الرئيسية الظاهرة للزائر (أمر التصور النهائي §٢ب: تُدار من اللوحة لا من القوالب)
                Section::make(__('settings.home_content.headings'))->description(__('settings.home_content.headings_hint'))->columns(3)->schema(
                    array_map(fn (string $key): TextInput => TextInput::make('headings.'.$key)->label(__('settings.home_content.headings_fields.'.$key))->maxLength(300), HomeContent::TEXTS['headings']),
                ),
                // النصوص الثابتة في الواجهة (مراجعة المشرف، 26 أكتوبر 2026)
                Section::make(__('settings.home_content.interface'))->columns(2)->schema([
                    TextInput::make('tickers.news')->label(__('settings.home_content.tickers_news'))->maxLength(300),
                    TextInput::make('tickers.announcements')->label(__('settings.home_content.tickers_announcements'))->maxLength(300),
                    TextInput::make('channels.title')->label(__('settings.home_content.channels_title'))->maxLength(300),
                    // وسم «مثال تجريبي» في اللوحة وحدها ما دام النص الذي كتبه فريق البوابة لم يُستبدل (HomeContent::SAMPLE)
                    TextInput::make('channels.intro')->label(__('settings.home_content.channels_intro'))
                        ->helperText(fn (): ?string => HomeContent::isSample('channels', 'intro') ? __('settings.home_content.sample_hint') : null)->maxLength(300),
                    TextInput::make('announcements.title')->label(__('settings.home_content.announcements_title'))->maxLength(300),
                    TextInput::make('announcements.intro')->label(__('settings.home_content.announcements_intro'))
                        ->helperText(fn (): ?string => HomeContent::isSample('announcements', 'intro') ? __('settings.home_content.sample_hint') : null)->maxLength(300),
                ]),
                Section::make(__('settings.home_content.footer'))->columns(2)->schema([
                    Textarea::make('footer.about')->label(__('settings.home_content.footer_about'))->rows(2)->maxLength(300)->columnSpanFull(),
                    TextInput::make('footer.supervision')->label(__('settings.home_content.footer_supervision'))->maxLength(300),
                    TextInput::make('footer.license')->label(__('settings.home_content.footer_license'))->maxLength(300),
                    TextInput::make('footer.quick_links')->label(__('settings.home_content.footer_quick_links'))->maxLength(300),
                    TextInput::make('footer.contact')->label(__('settings.home_content.footer_contact'))->maxLength(300),
                    TextInput::make('footer.channels')->label(__('settings.home_content.footer_channels'))->maxLength(300),
                    TextInput::make('footer.rights')->label(__('settings.home_content.footer_rights'))->maxLength(300),
                    TextInput::make('footer.hours')->label(__('settings.home_content.footer_hours'))->maxLength(300),
                    TextInput::make('footer.accounts')->label(__('settings.home_content.footer_accounts'))->maxLength(300),
                ]),
                // مربع الترخيص في الصف الأول من الرأس (أمر التصور النهائي، النسخة 63 — البند R2)
                Section::make(__('settings.home_content.header'))->columns(2)->schema([
                    TextInput::make('header.licensed_by')->label(__('settings.home_content.header_licensed_by'))->maxLength(300),
                    TextInput::make('header.license_number')->label(__('settings.home_content.header_license_number'))->maxLength(300),
                ]),
                // ملفات الشعار والمخطوطة (HomeContent::BRAND): PNG أو WebP بخلفية شفافة، وفارغة تعني الأصل
                Section::make(__('settings.home_content.brand'))->description(__('settings.home_content.brand_hint'))->columns(2)->schema(
                    array_map(fn (string $key): FileUpload => self::brandImage($key), array_keys(HomeContent::BRAND)),
                ),
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

    public function save(UpdateHomeContent $action): void
    {
        $this->authorizeWrite();

        /** @var User $user */
        $user = auth()->user();

        try {
            $action->handle($user, $this->form->getState());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.$key => $messages])->all(),
            );
        }

        Notification::make()->success()->title(__('settings.saved'))->send();
    }

    /**
     * ملف الشعار أو المخطوطة: PNG أو WebP لتبقى الخلفية شفافة، لا SVG (docs/security.md). وفارغ يعني الأصل.
     */
    private static function brandImage(string $key): FileUpload
    {
        return self::image('brand.'.$key, 'brand')
            ->label(__('settings.home_content.brand_'.$key))
            ->helperText(null)
            ->acceptedFileTypes(['image/png', 'image/webp'])
            ->maxSize(2048);
    }

    /**
     * صورة المقطع على القرص العام، لا SVG (docs/security.md). وفارغة تعني الأصل من الخطة الاستراتيجية.
     */
    private static function image(string $section, string $field = ''): FileUpload
    {
        $name = $field === '' ? $section.'.image' : $section;

        return FileUpload::make($name)
            ->label(__('settings.home_content.image'))
            ->helperText(__('settings.home_content.image_hint'))
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(6144)
            ->rules(['dimensions:max_width=5000,max_height=5000'])
            // الصفحة بلا سجل: يُسمح صراحة بالصور المحفوظة حالياً وحدها
            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => in_array($file, HomeContent::imagePaths(Setting::group(HomeContent::KEY)), true))
            ->disk('public')
            ->directory(UpdateSiteSettings::IMAGE_DIRECTORY)
            ->visibility('public');
    }
}
