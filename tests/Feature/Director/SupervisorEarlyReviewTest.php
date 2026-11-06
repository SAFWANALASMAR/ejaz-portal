<?php

use App\Actions\Library\SaveLibraryItem;
use App\Actions\Settings\UpdateSiteSettings;
use App\Actions\Users\AnonymizeUser;
use App\Actions\Users\ChangeUserRoles;
use App\Actions\Users\ResetPasswordByAdmin;
use App\Actions\Users\UpdateUserAccount;
use App\Enums\LibrarySection;
use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Jobs\FetchYoutubeThumbnail;
use App\Models\AccountCard;
use App\Models\LibraryItem;
use App\Models\Meeting;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SuperAdminRoleChanged;
use App\Support\AccountSettings;
use App\Support\ContentCache;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeSmsGateway;

/*
| مراجعة المشرف المبكرة لملاحظات المدير التنفيذي (26 أكتوبر 2026): اختبار لكل بند. والبند الأول (استدعاء الحفظ والاختبار
| من المتصفح في الصفحات التقنية) في ReadOnlySuperAdminTest.
*/

beforeEach(function () {
    FakeSmsGateway::swap();
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function reviewRegistration(array $overrides = []): array
{
    return ['name' => 'عبدالله محمد', 'email' => fake()->unique()->safeEmail(), 'phone' => '05'.fake()->unique()->numerify('########'), 'password' => 'Long-Enough-Pass-2026',
        'password_confirmation' => 'Long-Enough-Pass-2026', 'privacy' => '1', ...$overrides];
}

// ٢
it('never leaves an account without a sign-in identifier, from the panel or from the profile', function () {
    $admin = userWithRole(RoleName::ExecutiveDirector);
    $staff = User::factory()->create(['username' => null, 'email' => 'staff@example.test', 'phone' => null]);

    expect(fn () => app(UpdateUserAccount::class)->handle($admin, $staff, ['name' => $staff->name, 'email' => null, 'phone' => null]))
        ->toThrow(ValidationException::class, __('users.identifier_required'));
    expect($staff->fresh()->email)->toBe('staff@example.test');

    $this->actingAs($staff)->put('/user/profile-information', ['name' => $staff->name, 'email' => '', 'phone' => '', 'current_password' => 'password'])
        ->assertSessionHasErrorsIn('updateProfileInformation', ['email' => __('account.profile.identifier_required')]);
    expect($staff->fresh()->email)->toBe('staff@example.test');

    // ومن له اسم مستخدم يُفرغ بريده وجواله
    $named = User::factory()->create(['username' => 'has.name', 'email' => 'named@example.test']);
    app(UpdateUserAccount::class)->handle($admin, $named, ['name' => $named->name, 'email' => null, 'phone' => null]);
    expect($named->fresh()->email)->toBeNull();
});

// ٣
it('makes the one-time password from characters that HTML leaves as they are', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);

    foreach (range(1, 20) as $i) {
        $user = User::factory()->create(['email' => null, 'email_verified_at' => null, 'username' => 'temp.'.$i]);
        $password = app(ResetPasswordByAdmin::class)->handle($admin, $user);

        expect($password)->toHaveLength(ResetPasswordByAdmin::LENGTH)
            ->and(e($password))->toBe($password)
            ->and(strpbrk($password, '<>&"\''))->toBeFalse();
    }
});

// ٤
it('lets nobody reserve an email by registering it unverified: the verified owner takes it', function () {
    $squatter = User::factory()->create(['username' => 'squatter', 'email' => 'owner@example.test', 'email_verified_at' => null]);

    $this->post('/register', reviewRegistration(['phone' => '0512340001', 'email' => 'owner@example.test']))->assertSessionHasNoErrors();
    $owner = User::where('phone', '+966512340001')->sole();
    $this->post('/logout');

    // بريد يحمله حسابان غير موثقين لا يُدخل به، ويدخل كلٌّ بجواله أو باسم مستخدمه
    $this->post('/login', ['login' => 'owner@example.test', 'password' => 'Long-Enough-Pass-2026']);
    $this->assertGuest();
    $this->post('/login', ['login' => '0512340001', 'password' => 'Long-Enough-Pass-2026']);
    $this->assertAuthenticatedAs($owner);
    $this->post('/logout');

    // التوثيق يأخذ البريد لصاحبه، ويفقده الحساب الآخر
    expect($owner->markEmailAsVerified())->toBeTrue()
        ->and($squatter->fresh()->email)->toBeNull()
        ->and(User::findByLoginIdentifier('owner@example.test')?->is($owner))->toBeTrue();

    // وبريد موثق لا يسجّل به غيره، ولا يوثّقه حساب آخر
    $this->post('/register', reviewRegistration(['email' => 'owner@example.test']))->assertSessionHasErrors('email');
    $late = User::factory()->create(['email' => 'owner@example.test', 'email_verified_at' => null]);
    expect($late->markEmailAsVerified())->toBeFalse();

    // ورابط الاستعادة للموثق، ولو حمل غيره البريد
    Password::sendResetLink(['email' => 'owner@example.test']);
    Notification::assertSentTo($owner, ResetPasswordNotification::class);
    Notification::assertNotSentTo($late, ResetPasswordNotification::class);
});

// ٥
it('never deletes the accounts registered while verification was off, once it is turned on', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $old = User::factory()->create(['email_verified_at' => null, 'created_at' => now()->subDays(30), 'last_login_at' => now()->subDays(30)]);
    $old->forceFill(['self_registered_at' => now()->subDays(30)])->save();
    $old->assignRole(RoleName::Member->value);

    app(UpdateSiteSettings::class)->handle($admin, [AccountSettings::KEY => ['verification' => true, 'show_deferred_portals' => false]]);
    $enabledAt = AccountSettings::verificationEnabledAt();
    expect($enabledAt)->not->toBeNull();

    $this->artisan('ejaz:prune-unverified-accounts')->assertSuccessful();
    expect(User::find($old->id))->not->toBeNull();

    // وحفظ آخر والتوثيق مفعّل لا يقدّم لحظة التفعيل، وإطفاؤه يمسحها
    $this->travel(2)->days();
    app(UpdateSiteSettings::class)->handle($admin, [AccountSettings::KEY => ['verification' => true, 'show_deferred_portals' => true]]);
    expect(AccountSettings::verificationEnabledAt()?->equalTo($enabledAt))->toBeTrue();

    app(UpdateSiteSettings::class)->handle($admin, [AccountSettings::KEY => ['verification' => false, 'show_deferred_portals' => true]]);
    expect(AccountSettings::verificationEnabledAt())->toBeNull();
});

// ٦
it('turns the old home sections off once on a site whose settings were saved before', function () {
    Setting::put('home', ['show_stats' => true, 'show_shortcuts' => true, 'show_partners' => true, 'show_latest' => true, 'hero_title' => 'نص محفوظ']);

    (require database_path('migrations/2026_10_26_091000_hide_legacy_home_sections_once.php'))->up();

    expect(Setting::group('home'))->toMatchArray(['show_stats' => false, 'show_shortcuts' => false, 'show_partners' => false,
        'show_hero' => false, 'show_latest' => true, 'hero_title' => 'نص محفوظ']);
});

// ٧
it('locks an account after ten failures across all of its identifiers', function () {
    $user = User::factory()->create(['username' => 'many.ids', 'email' => 'many@example.test', 'phone' => '+966512345670',
        'phone_verified_at' => now(), 'password' => Hash::make('Long-Enough-Pass-2026')]);

    foreach (range(1, 10) as $i) {
        $this->post('/login', ['login' => ['many.ids', 'many@example.test', '0512345670'][$i % 3], 'password' => 'wrong-'.$i], ['REMOTE_ADDR' => '10.0.'.$i.'.1']);
    }

    // المعرّفات الثلاثة لم يبلغ أيٌّ منها عشراً، والحساب بلغها
    $this->post('/login', ['login' => 'many.ids', 'password' => 'Long-Enough-Pass-2026'], ['REMOTE_ADDR' => '10.1.0.1']);
    $this->assertGuest();
    expect($user->fresh()->last_login_at)->toBeNull();
});

// ٨
it('ends the sessions of the account on their next request after a one-time password, whatever the session store', function () {
    $user = User::factory()->create(['email' => null, 'email_verified_at' => null, 'username' => 'session.owner']);
    $oldHash = $user->getAuthPassword();

    app(ResetPasswordByAdmin::class)->handle(userWithRole(RoleName::TechnicalAdmin), $user);

    $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])->get('/account')->assertRedirect('/login');
});

// ٩
it('grants a role from the command only to an active, verified, not anonymized account, and alerts the overseers', function () {
    $watcher = userWithRole(RoleName::TechnicalAdmin);
    $inactive = User::factory()->create(['email' => 'inactive@example.test', 'is_active' => false]);

    $this->artisan('ejaz:create-admin', ['--role' => 'executive_director'])
        ->expectsQuestion('البريد الإلكتروني', 'inactive@example.test')
        ->assertFailed();
    expect($inactive->fresh()->hasRole(RoleName::ExecutiveDirector->value))->toBeFalse();

    $owner = User::factory()->create(['email' => 'owner.admin@example.test']);
    $this->artisan('ejaz:create-admin', ['--role' => 'executive_director'])
        ->expectsQuestion('البريد الإلكتروني', 'owner.admin@example.test')
        ->expectsConfirmation('للبريد حساب قائم باسم «'.$owner->name.'». أيُمنح دور '.RoleName::ExecutiveDirector->getLabel().'؟', 'yes')
        ->assertSuccessful();

    Notification::assertSentTo($watcher, SuperAdminRoleChanged::class, fn (SuperAdminRoleChanged $mail): bool => $mail->user->is($owner) && $mail->by === null);
});

// ١٠
it('alerts the overseers for every oversight role that changed, not the first one alone', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $user = userWithRole(RoleName::Member);

    app(ChangeUserRoles::class)->handle($admin, $user, [RoleName::SuperAdmin->value, RoleName::ExecutiveDirector->value]);

    Notification::assertSentTo($admin, SuperAdminRoleChanged::class, fn (SuperAdminRoleChanged $mail): bool => $mail->role === RoleName::SuperAdmin);
    Notification::assertSentTo($admin, SuperAdminRoleChanged::class, fn (SuperAdminRoleChanged $mail): bool => $mail->role === RoleName::ExecutiveDirector);
});

// ١١
it('moves a book with its PDF to the audio section by a YouTube link, and removes the file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('library/book.pdf', pdfContents());
    $book = LibraryItem::factory()->create(['section' => LibrarySection::Books, 'file' => 'library/book.pdf', 'video_url' => null]);

    app(SaveLibraryItem::class)->handle(userWithRole(RoleName::ContentEditor), $book, ['section' => LibrarySection::Audio->value, 'video_url' => 'https://youtu.be/abcdefghijk']);

    expect($book->fresh())->section->toBe(LibrarySection::Audio)->file->toBeNull()
        ->and(Storage::disk('local')->exists('library/book.pdf'))->toBeFalse();
});

// ١٢
it('reshapes only the default media items, and puts them back when rolled back', function () {
    $this->seed(MenuSeeder::class);
    $migration = require database_path('migrations/2026_10_25_150000_reshape_media_and_library_menu.php');
    $migration->down();

    $media = MenuItem::whereNull('parent_id')->where('url', '/media')->sole();
    expect(MenuItem::where('parent_id', $media->id)->pluck('url')->all())->toContain('/library', '/library/videos')
        ->and(MenuItem::whereNull('parent_id')->where('url', '/library')->exists())->toBeFalse();

    // ومكتبة الصور كما كانت في الموقع قبل الترحيل، وعنصر عدّل المحرر عنوانه يبقى كما هو
    MenuItem::query()->firstOrCreate(['parent_id' => $media->id, 'url' => '/media/albums'], ['label' => 'مكتبة الصور', 'is_visible' => true, 'sort' => 2]);
    MenuItem::where('parent_id', $media->id)->where('url', '/library/videos')->update(['label' => 'مقاطعنا']);
    $migration->up();

    expect(MenuItem::where('parent_id', $media->id)->where('url', '/library/videos')->value('label'))->toBe('مقاطعنا')
        ->and(MenuItem::where('parent_id', $media->id)->where('url', '/library')->exists())->toBeFalse()
        ->and(MenuItem::where('parent_id', $media->id)->where('url', '/media/albums')->value('is_visible'))->toBeFalse()
        ->and(MenuItem::whereNull('parent_id')->where('url', '/library')->exists())->toBeTrue();
});

// ١٣
it('stores the built-in cards in Arabic whatever the locale at migration time', function () {
    expect(AccountCard::where('route', 'account.profile')->value('title'))->toBe(__('account.nav.profile', [], 'ar'))
        ->and((string) file_get_contents(database_path('migrations/2026_10_25_140000_create_account_cards_table.php')))->toContain("__(\$title, [], 'ar')");
});

// ١٤
it('fetches the YouTube thumbnail once on the server and serves it from the site, never from Google in the page', function () {
    config(['security.youtube_thumbnails' => true]);
    Storage::fake('public');
    // أعلى دقة أولاً ثم hqdefault (FetchYoutubeThumbnail::SIZES)
    Http::fake([
        'https://i.ytimg.com/vi/abcdefghijk/maxresdefault.jpg' => Http::response('', 404),
        'https://i.ytimg.com/vi/abcdefghijk/hqdefault.jpg' => Http::response(imageBytes('jpeg', 32, 18), 200, ['Content-Type' => 'image/jpeg']),
    ]);
    $editor = userWithRole(RoleName::ContentEditor);

    $item = app(SaveLibraryItem::class)->handle($editor, new LibraryItem, ['title' => 'مقطع', 'slug' => 'clip', 'section' => LibrarySection::Videos->value,
        'video_url' => 'https://youtu.be/abcdefghijk', 'is_published' => true]);

    $thumbnail = (string) $item->fresh()->thumbnail;
    expect(FetchYoutubeThumbnail::isFetchedFor($thumbnail, 'abcdefghijk'))->toBeTrue()
        ->and(Storage::disk('public')->exists($thumbnail))->toBeTrue();
    ContentCache::flush();
    expect($this->get('/library/videos')->getContent())->toContain(Storage::disk('public')->url($thumbnail))->not->toContain('i.ytimg.com');

    // رابط آخر: تُترك صورة المقطع السابق وتُحذف، ويُجلب غيرها
    Http::fake(['*' => Http::response('', 404)]);
    app(SaveLibraryItem::class)->handle($editor, $item->fresh(), ['video_url' => 'https://youtu.be/zyxwvutsrqp']);
    expect($item->fresh()->thumbnail)->toBeNull()
        ->and(Storage::disk('public')->exists($thumbnail))->toBeFalse();
});

// ١٥
it('shows the super admin the Teams button of the coming general assembly meeting', function () {
    $meeting = Meeting::factory()->online()->create(['body' => MeetingBody::GeneralAssembly, 'starts_at' => now()->addDay(), 'online_join_url' => 'https://teams.microsoft.com/l/meetup-join/demo']);

    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get('/account')->assertOk()
        ->assertSee(__('account.oversight.join_teams'))
        ->assertSee($meeting->online_join_url, false);
});

// ١٦
it('answers the Filament failed-rows download with 404 for everyone, the administrators included', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->get('/filament/imports/1/failed-rows/download')->assertNotFound();
});

// ١٧
it('takes no username at registration, so neither a reserved nor a retired username can be claimed', function () {
    // التسجيل بالجوال ولا اسم مستخدم للحساب الجديد (decisions/2026-11-05-phone-login.md): يُهمل الحقل إن أُرسل
    $this->post('/register', reviewRegistration(['username' => 'admin']))->assertSessionHasNoErrors();
    $this->post('/logout');
    expect(User::query()->whereNotNull('username')->exists())->toBeFalse();

    $gone = User::factory()->create(['username' => 'gone.user', 'deletion_requested_at' => now()]);
    app(AnonymizeUser::class)->handle(userWithRole(RoleName::TechnicalAdmin), $gone);

    expect($gone->fresh()->username)->toBeNull()
        ->and(DB::table('retired_usernames')->count())->toBe(1)
        ->and(DB::table('retired_usernames')->value('hash'))->not->toContain('gone');
    $this->post('/register', reviewRegistration(['username' => 'gone.user']))->assertSessionHasNoErrors();
    expect(User::query()->where('username', 'gone.user')->exists())->toBeFalse();
});
