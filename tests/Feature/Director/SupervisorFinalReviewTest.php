<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Actions\Settings\UpdateSiteSettings;
use App\Actions\Users\ResetPasswordByAdmin;
use App\Enums\AccountCardAudience;
use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Enums\SiteIcon;
use App\Filament\Pages\HomeContentPage;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\ContentPages\Pages\EditContentPage;
use App\Models\AccountCard;
use App\Models\Committee;
use App\Models\Meeting;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\AccountNavigation;
use App\Support\HomeContent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Fakes\FakeSmsGateway;

/*
| المراجعة النهائية للمشرف على #66 وملف المطابقة وقرارات صفوان (26 أكتوبر 2026): اختبار لكل بند من «لازم قبل الدمج»
| والمنخفضة. واختبار المحاولات الفعلية في RoleMatrixTest (البند 11).
*/

beforeEach(function () {
    FakeSmsGateway::swap();
    Notification::fake();
});

/**
 * محتوى الرئيسية بأصله، وما يُغيَّر منه (UpdateHomeContent يشترط القيم الأربع).
 *
 * @param  array<string, mixed>  $content
 */
function saveHomeContent(User $actor, array $content): void
{
    app(UpdateHomeContent::class)->handle($actor, ['values' => ['items' => array_fill(0, HomeContent::VALUES, [])], ...$content]);
}

// ١
it('hides the old home sections once and puts the saved values back when rolled back', function () {
    Setting::put('home', ['show_stats' => true, 'show_partners' => false, 'hero_title' => 'نص محفوظ']);
    $migration = require database_path('migrations/2026_10_26_091000_hide_legacy_home_sections_once.php');

    $migration->up();
    expect(Setting::group('home'))->toMatchArray(['show_stats' => false, 'show_partners' => false, 'show_hero' => false, 'hero_title' => 'نص محفوظ']);

    $migration->down();
    $home = Setting::group('home');
    expect($home)->toMatchArray(['show_stats' => true, 'show_partners' => false, 'hero_title' => 'نص محفوظ'])
        ->and($home)->not->toHaveKey('show_hero')
        ->and(Setting::query()->where('key', 'home_legacy_before')->exists())->toBeFalse();
});

// ٢، وقرار صفوان (29 أكتوبر 2026): لا وسم «مثال تجريبي» للعامة قبل الإطلاق، ويبقى في اللوحة وحدها حتى تراجعه الجمعية
it('marks the texts written by the portal team in the admin panel only, never to the public', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    Setting::put('contact', ['social' => ['x' => 'https://x.com/ejazsa2025']]);

    // «قنوات التواصل» خرجت من الرئيسية بالنسخة 63 (الحسابات في التذييل)، ومقدمتها باقية في اللوحة
    expect($this->get('/')->getContent())->not->toContain('مثال تجريبي');
    $this->get('/media/announcements')->assertOk()->assertSee(__('home.sections.announcements.intro'))->assertDontSee('مثال تجريبي');

    // واللوحة تدل المسؤول عليه: التلميح على الحقل، وحالته في الإعدادات
    $this->actingAs($editor);
    Livewire::test(HomeContentPage::class)->assertSee(__('settings.home_content.sample_hint'));
    expect(HomeContent::isSample('channels', 'intro'))->toBeTrue();

    saveHomeContent($editor, ['channels' => ['intro' => 'نص الجمعية المعتمد'], 'announcements' => ['intro' => 'مقدمة الجمعية']]);

    expect(HomeContent::text('channels', 'intro'))->toBe('نص الجمعية المعتمد')
        ->and(HomeContent::isSample('channels', 'intro'))->toBeFalse();
    $this->get('/media/announcements')->assertSee('مقدمة الجمعية');
    // وبعد استبدال النصين يزول الوسم من اللوحة أيضاً (مراجعة المشرف على #102)
    Livewire::test(HomeContentPage::class)->assertDontSee(__('settings.home_content.sample_hint'));

    // والصفحة الموسومة بلا وسم للعامة، ووسمها حقل فيها في اللوحة
    $page = Page::factory()->create(['slug' => 'sample-intro', 'is_sample' => true, 'is_published' => true]);
    $this->get('/pages/sample-intro')->assertOk()->assertDontSee('مثال تجريبي');
    Livewire::test(EditContentPage::class, ['record' => $page->getRouteKey()])->assertFormSet(['is_sample' => true])->assertSee(__('pages.fields.is_sample'));

    // وصفحات الاستفسارات والتوظيف والتطوع والتقارير في ملف الاستيراد بفقرة موسومة، لا بعناوينها وحدها
    $pages = collect(json_decode((string) file_get_contents(base_path('docs/legacy-import/legacy-site.json')), true)['pages'])->keyBy('slug');

    foreach (['inquiries', 'jobs', 'volunteering', 'reports'] as $slug) {
        expect($pages[$slug]['is_sample'] ?? false)->toBeTrue($slug)
            ->and($pages[$slug]['blocks'])->toHaveCount(1)
            ->and($pages[$slug]['blocks'][0]['data']['body'])->not->toContain('【');
    }
});

// ٣
it('shows the chairman opening and the first real paragraph of the introduction, verbatim', function () {
    $excerpt = HomeContent::paragraphs((string) HomeContent::resolved()['chairman']['excerpt']);
    $full = HomeContent::paragraphs((string) HomeContent::resolved()['chairman']['full']);

    expect($excerpt)->toHaveCount(2)
        ->and($excerpt)->toBe(array_slice($full, 0, 2))
        ->and($excerpt[1])->toStartWith('فإن القرآن الكريم كلام الله المُعجز');

    // والفقرة في شريحة رئيس الجمعية بالبطل (النسخة 63)، والكلمة كاملة تنتقل إلى «عن الجمعية» في المرحلة ج
    $this->get('/')->assertSee('ومصدر عزتها ورشدها.');
});

// ٤ و٥
it('builds the footer and the interface texts from the panel, with the national address map link', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    Setting::put('contact', ['email' => 'info@ejaz.sa', 'short_address' => 'RRRD2929', 'social' => ['x' => 'https://x.com/ejazsa2025']]);

    $html = (string) $this->get('/')->getContent();
    // لا سطر إشراف في الرأس ولا التذييل، والترخيص في مربعه بالرأس (أمر التصور النهائي، النسخة 63 — البند R2)
    expect($html)->toContain('data-site-footer')
        ->not->toContain(e(__('home.sections.footer.supervision')))
        ->toContain(e(__('home.sections.header.licensed_by')))
        ->toContain('RRRD2929')
        ->toContain('https://www.google.com/maps/search/?api=1&amp;query=RRRD2929')
        ->toContain('https://x.com/ejazsa2025');

    saveHomeContent($editor, [
        'footer' => ['about' => 'نبذة من اللوحة', 'rights' => 'حقوق من اللوحة'],
        'tickers' => ['news' => 'عنوان شريط من اللوحة'],
        'channels' => ['title' => 'قنواتنا من اللوحة'],
        'announcements' => ['title' => 'إعلانات من اللوحة'],
    ]);

    // والنبذة خرجت من التذييل بمربعاته الثلاثة (أمر التصور النهائي، النسخة 63)، ويبقى حقلها في اللوحة
    // وعنوان الأخبار يظهر بخبر منشور
    NewsArticle::factory()->create();
    expect((string) $this->get('/')->getContent())->toContain('حقوق من اللوحة')->toContain('عنوان شريط من اللوحة');
    $this->get('/media/announcements')->assertSee('إعلانات من اللوحة')->assertDontSee('نبذة من اللوحة');

    // والنصوص في صفحة «محتوى الرئيسية»
    $this->actingAs($editor);
    Livewire::test(HomeContentPage::class)->assertOk()
        ->assertFormSet(['footer.about' => 'نبذة من اللوحة', 'tickers.news' => 'عنوان شريط من اللوحة', 'footer.license' => __('home.sections.footer.license')]);
});

it('saves the full chairman speech from the panel with its paragraph breaks', function () {
    saveHomeContent(userWithRole(RoleName::ContentEditor), ['chairman' => ['full' => "فقرة أولى\nفقرة ثانية"]]);

    expect(HomeContent::paragraphs((string) HomeContent::resolved()['chairman']['full']))->toBe(['فقرة أولى', 'فقرة ثانية']);
});

// ٦
it('aligns the live About page with the strategic plan, and restores it when rolled back', function () {
    $old = [
        ['type' => 'text', 'data' => ['heading' => 'من نحن', 'body' => '<p>نص من نحن</p>']],
        ['type' => 'text', 'data' => ['heading' => 'الرؤية', 'body' => '<p>رؤية قديمة</p>']],
        ['type' => 'text', 'data' => ['heading' => 'التوجه الاستراتيجي', 'body' => '<p>【ناقص: محتوى التوجه الاستراتيجي】</p>']],
        ['type' => 'cards', 'data' => ['heading' => 'قيم الجمعية', 'items' => [['icon' => null, 'title' => 'قيمة قديمة', 'text' => 'وصف قديم', 'picture' => 'pages/value.png']]]],
    ];
    // منشورة قبل منع النشر بالعلامة (Publishable)، فتُنشر من القاعدة كما كانت على رابط العرض
    $page = Page::factory()->unpublished()->create(['slug' => 'about', 'blocks' => $old]);
    DB::table('pages')->where('id', $page->id)->update(['is_published' => true]);
    $migration = require database_path('migrations/2026_10_26_094000_align_about_page_with_strategic_plan.php');

    $migration->up();
    $blocks = collect($page->fresh()->blocks)->keyBy(fn (array $block) => $block['data']['heading'] ?? '');

    expect($blocks->keys()->all())->toBe(['من نحن', 'الرؤية', 'قيم الجمعية', 'الرسالة', 'أهداف الجمعية'])
        ->and($blocks['من نحن']['data']['body'])->toBe('<p>نص من نحن</p>')
        ->and($blocks['الرؤية']['data']['body'])->toBe('<p>ريادة تعليمية في ضوء براهين الإيمان العلمية.</p>')
        ->and($blocks['الرسالة']['data']['body'])->toContain('الإسهام في تنمية وتطوير المنظومة التعليمية والبحثية لبراهين الإيمان العلمية')
        ->and(array_column($blocks['أهداف الجمعية']['data']['items'], 'title'))->toHaveCount(5)->toContain('تعزيز القيم والانتماء الوطني.')
        ->and(array_column($blocks['قيم الجمعية']['data']['items'], 'title'))->toBe(['الإحسان والمهنية', 'الابتكار', 'التمكين', 'التكامل'])
        // صورة البطاقة القائمة باقية
        ->and($blocks['قيم الجمعية']['data']['items'][0]['picture'])->toBe('pages/value.png');

    $this->get('/about')->assertOk()->assertSee('ريادة تعليمية في ضوء براهين الإيمان العلمية.')->assertDontSee('التوجه الاستراتيجي');

    $migration->down();
    expect($page->fresh()->blocks)->toEqual($old);
});

// ٧
it('describes roles as granted by the two administrators, and self-demotion as of the administrator role', function () {
    expect(__('users.fields.roles_hint'))->toContain('المدير التنفيذي')->toContain('المسؤول التقني')
        ->and(__('users.cannot_demote_self'))->not->toContain('المشرف العام')->toContain('المدير التنفيذي');
});

// ٨
it('draws the rationale with the calligraphy file, and replaces the logo files from the panel', function () {
    Storage::fake('public');
    // مقطع المبررات خرج من الرئيسية بالنسخة 63، وقالبه باقٍ لصفحة «عن الجمعية» (المرحلة ج)
    $html = view('home.partials.rationale', ['rationale' => HomeContent::resolved()['rationale']])->render();

    expect($html)->toContain('data-rationale-calligraphy')
        ->toContain('images/brand/logo-calligraphy-white.png')
        ->not->toContain('text-[clamp(4.5rem');

    Storage::disk('public')->put('settings/new-mark.png', imageBytes('png', 40, 40));
    saveHomeContent(userWithRole(RoleName::ContentEditor), ['brand' => ['mark' => 'settings/new-mark.png']]);

    // الشعار الملون في بطاقة الدخول (الرأس والتذييل بالأبيض — التصور النهائي)
    expect(HomeContent::brand('mark'))->toBe(Storage::disk('public')->url('settings/new-mark.png'))
        ->and((string) $this->get('/login')->getContent())->toContain(Storage::disk('public')->url('settings/new-mark.png'));

    // ولا مسار خارج مجلد الإعدادات
    expect(fn () => saveHomeContent(userWithRole(RoleName::TechnicalAdmin), ['brand' => ['mark' => '../.env']]))->toThrow(ValidationException::class);
});

// ٩
it('shows the Teams button for board and committee meetings too, to those who see them', function () {
    $board = Meeting::factory()->online()->create(['body' => MeetingBody::Board, 'starts_at' => now()->addHours(2), 'online_join_url' => 'https://teams.microsoft.com/l/meetup-join/board']);
    $committee = Committee::factory()->create(['name' => 'لجنة البرامج']);
    Meeting::factory()->online()->create(['body' => MeetingBody::Committee, 'committee_id' => $committee->id, 'starts_at' => now()->addHour(), 'online_join_url' => 'https://teams.microsoft.com/l/meetup-join/committee']);

    // المشرف العام يرى كل الاجتماعات اطلاعاً: أقربها اجتماع اللجنة
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get('/account')->assertOk()
        ->assertSee('https://teams.microsoft.com/l/meetup-join/committee', false)
        ->assertSee('لجنة البرامج');

    // وعضو المجلس بلا عضوية اللجنة يرى اجتماع المجلس وحده (والبوابات المؤجلة ظاهرة)
    showDeferredPortals();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get('/account')->assertOk()
        ->assertSee($board->online_join_url, false)
        ->assertDontSee('https://teams.microsoft.com/l/meetup-join/committee', false);
});

// ١٠
it('shows a dashboard card to the chosen roles only', function () {
    AccountCard::factory()->create(['title' => 'بطاقة للسكرتير', 'url' => '/programs', 'icon' => SiteIcon::Link, 'audience' => AccountCardAudience::Roles, 'roles' => [RoleName::Secretary->value]]);

    expect(AccountNavigation::cards(userWithRole(RoleName::Secretary))->pluck('title'))->toContain('بطاقة للسكرتير')
        ->and(AccountNavigation::cards(userWithRole(RoleName::Member))->pluck('title'))->not->toContain('بطاقة للسكرتير');
});

// ١٢
it('keeps the email of an older account without a username from being taken at sign-up', function () {
    $legacy = User::factory()->create(['username' => null, 'email' => 'old.member@example.test', 'email_verified_at' => null, 'password' => Hash::make('Long-Enough-Pass-2026')]);

    $this->post('/register', ['name' => 'شخص آخر', 'username' => 'someone.else', 'email' => 'old.member@example.test',
        'password' => 'Long-Enough-Pass-2026', 'password_confirmation' => 'Long-Enough-Pass-2026', 'privacy' => '1'])
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'old.member@example.test')->count())->toBe(1);

    $this->post('/login', ['login' => 'old.member@example.test', 'password' => 'Long-Enough-Pass-2026']);
    $this->assertAuthenticatedAs($legacy);
});

// المنخفضة
it('refuses the one-time password from the panel to the overseers accounts', function (RoleName $role) {
    $record = userWithRole($role, ['email_verified_at' => null]);

    expect(userWithRole(RoleName::TechnicalAdmin)->can('resetPassword', $record))->toBeFalse()
        ->and(fn () => app(ResetPasswordByAdmin::class)->handle(userWithRole(RoleName::ExecutiveDirector), $record))->toThrow(AuthorizationException::class);
})->with([RoleName::SuperAdmin, RoleName::ExecutiveDirector, RoleName::TechnicalAdmin]);

it('refuses to create an admin with an email held by an unverified account, without taking it', function () {
    $holder = User::factory()->create(['email' => 'held@example.test', 'email_verified_at' => null]);

    $this->artisan('ejaz:create-admin')
        ->expectsQuestion('البريد الإلكتروني', 'held@example.test')
        ->assertFailed();

    expect($holder->fresh()->email)->toBe('held@example.test')
        ->and(User::where('email', 'held@example.test')->count())->toBe(1);
});

it('refuses temporary uploads on the settings pages to the super admin', function () {
    $file = [['name' => 'a.png', 'size' => 1024, 'type' => 'image/png']];

    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    Livewire::test(SiteSettings::class)->call('_startUpload', 'data.home.hero_image', $file, false)->assertForbidden();
    Livewire::test(HomeContentPage::class)->call('_startUpload', 'data.king.image', $file, false)->assertForbidden();

    $this->actingAs(userWithRole(RoleName::ContentEditor));
    Livewire::test(HomeContentPage::class)->call('_startUpload', 'data.king.image', $file, false)->assertDispatched('upload:generatedSignedUrl');
});

it('keeps the settings save for those who may write it', function () {
    expect(fn () => app(UpdateSiteSettings::class)->handle(userWithRole(RoleName::SuperAdmin), ['contact' => ['email' => 'x@example.test']]))
        ->toThrow(AuthorizationException::class);
});

// البند 8: البيانات الرسمية للجمعية (26 أكتوبر 2026)
it('writes the official data into the existing contact settings, keeps what the editor entered, and rolls back', function () {
    $before = ['phone' => '+966 12 000 0000', 'address' => 'مكة المكرمة - برج الساعة (أبراج البيت المقابلة للمسجد الحرام) - الدور التاسع بجوار المصلى',
        'social' => ['facebook' => 'https://facebook.com/profile.php?id=61595399341851', 'x' => 'https://x.com/edited']];
    Setting::put('contact', $before);
    $migration = require database_path('migrations/2026_10_26_096000_apply_official_contact_data.php');

    $migration->up();
    expect(Setting::group('contact'))->toMatchArray([
        'email' => 'info@ejaz.sa',
        // ما أدخله المحرر يبقى
        'phone' => '+966 12 000 0000',
        'whatsapp' => '+966550556758',
        'address' => 'مكة المكرمة — برج الساعة، الدور التاسع، بجوار مصلى الرجال',
        'short_address' => 'MALA4207',
    ])->and(Setting::group('contact')['social'])->toMatchArray(['facebook' => 'https://facebook.com/ejazsa2025', 'x' => 'https://x.com/edited', 'telegram' => 'https://t.me/ejazsa2025']);

    $migration->down();
    expect(Setting::group('contact'))->toEqual($before);
});

it('shows the WhatsApp button, the license and the national address map in the footer and on the contact page', function () {
    Setting::put('contact', ['email' => 'info@ejaz.sa', 'whatsapp' => '+966550556758', 'short_address' => 'MALA4207',
        'address' => 'مكة المكرمة — برج الساعة، الدور التاسع، بجوار مصلى الرجال']);

    foreach (['/', '/contact'] as $path) {
        $html = (string) $this->get($path)->assertOk()->getContent();

        expect($html)->toContain('href="https://wa.me/966550556758"')
            ->toContain(e(__('contact.info.whatsapp')))
            ->toContain('https://www.google.com/maps/search/?api=1&amp;query=MALA4207')
            ->toContain('بجوار مصلى الرجال');
    }

    // والترخيص في صفحة التواصل، وخرج سطره من التذييل إلى مربع الترخيص في الرأس (النسخة 63)
    $this->get('/contact')->assertSee('رقم الترخيص: 1000723700');
});

it('saves the WhatsApp number from the settings in the international form, and refuses a number that is not a Saudi mobile', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    Livewire::test(SiteSettings::class)->fillForm(['contact.whatsapp' => '0550556758'])->call('save')->assertHasNoFormErrors();
    expect(Setting::group('contact')['whatsapp'])->toBe('+966550556758');

    Livewire::test(SiteSettings::class)->fillForm(['contact.whatsapp' => '0123'])->call('save')->assertHasFormErrors(['contact.whatsapp']);
});

it('carries the official data in the import file', function () {
    $contact = json_decode((string) file_get_contents(base_path('docs/legacy-import/legacy-site.json')), true)['settings']['contact'];

    expect($contact)->toMatchArray(['email' => 'info@ejaz.sa', 'whatsapp' => '+966550556758', 'short_address' => 'MALA4207',
        'address' => 'مكة المكرمة — برج الساعة، الدور التاسع، بجوار مصلى الرجال'])
        ->and(collect($contact['social'])->except('whatsapp')->every(fn (string $url): bool => str_contains($url, 'ejazsa2025')))->toBeTrue();
});

// مراجعة المشرف الأخيرة على #66: الصفحة تعرض الأصل في حقوله، فحفظ حقل آخر لا يجعل النص المثال نصاً معتمداً
it('keeps the «مثال تجريبي» tag after saving the home content page for another field', function () {
    Setting::put('contact', ['social' => ['x' => 'https://x.com/ejazsa2025']]);
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    Livewire::test(HomeContentPage::class)
        ->assertFormSet(['channels.intro' => __('home.sections.channels.intro')])
        ->fillForm(['king.heading' => 'عنوان معدّل'])
        ->call('save')
        ->assertHasNoFormErrors();

    $stored = Setting::group(HomeContent::KEY);
    expect($stored['king']['heading'])->toBe('عنوان معدّل')
        ->and($stored['channels']['intro'] ?? null)->toBeNull()
        ->and($stored['announcements']['intro'] ?? null)->toBeNull()
        // والمقتطف بأصله لا يُخزَّن، فيتبع أي تحديث لأصله
        ->and($stored['chairman']['excerpt'] ?? null)->toBeNull()
        ->and(HomeContent::isSample('channels', 'intro'))->toBeTrue()
        ->and(HomeContent::isSample('announcements', 'intro'))->toBeTrue();

    // والوسم في اللوحة وحدها (قرار صفوان، 29 أكتوبر 2026)
    expect((string) $this->get('/')->getContent())->not->toContain('مثال تجريبي');
    $this->get('/media/announcements')->assertDontSee('مثال تجريبي');
});

it('keeps the footer texts in the wording the site had before', function () {
    expect(__('home.sections.footer.about'))->toBe(__('site.tagline'))
        ->and(__('home.sections.footer.supervision'))->toBe(__('site.footer.supervision'))
        ->and(__('home.sections.footer.license'))->toBe(__('site.license'));
});

it('keeps the transparency of an uploaded logo or calligraphy file', function () {
    Storage::fake('public');
    $image = imagecreatetruecolor(20, 10);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    ob_start();
    imagepng($image);
    Storage::disk('public')->put('settings/calligraphy.png', (string) ob_get_clean());

    saveHomeContent(userWithRole(RoleName::ContentEditor), ['brand' => ['calligraphy' => 'settings/calligraphy.png']]);

    $saved = imagecreatefromstring((string) Storage::disk('public')->get('settings/calligraphy.png'));
    expect($saved)->not->toBeFalse()
        ->and(imagecolorsforindex($saved, imagecolorat($saved, 5, 5))['alpha'])->toBe(127);
});
