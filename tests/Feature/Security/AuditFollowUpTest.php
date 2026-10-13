<?php

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Models\User;
use App\Notifications\TwoFactorFailuresWarning;
use App\Notifications\TwoFactorRotationRefused;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| الاختبارات المانعة لعودة ثغرات الفحص الأمني الشامل الثاني (docs/security/2026-10-12-security-audit.md).
*/

// M3: رمز التحقق الثنائي لا يُخمَّن بلا سقف، وكل رمز خاطئ يُسجَّل على الحساب
it('caps two-factor guesses per quarter hour beyond the per-minute limit, and logs each wrong code', function () {
    $editor = userWithRole(RoleName::ContentEditor, ['email' => 'editor@example.test']);
    $this->post('/login', ['login' => 'editor@example.test', 'password' => 'password'])->assertRedirect('/two-factor-challenge');

    foreach (range(1, 10) as $i) {
        $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect();

        if ($i % 5 === 0) {
            $this->travel(61)->seconds();
        }
    }

    $this->post('/two-factor-challenge', ['code' => '000000'])->assertTooManyRequests();
    $this->assertGuest();

    expect(Activity::query()->where('event', ActivityEvent::TwoFactorFailed->value)->where('subject_id', $editor->id)->whereNull('causer_id')->count())->toBe(10);
});

// F8: سر التحقق الثنائي المؤكد لا يُستبدل بطلب من الجلسة، فلا يُقفل صاحب الحساب. والإعداد الأول كما هو
it('refuses to rotate a confirmed two-factor secret, logs the attempt and tells the owner', function () {
    Notification::fake();
    $editor = userWithRole(RoleName::ContentEditor);
    $secret = $editor->two_factor_secret;

    $this->actingAs($editor)->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication', ['force' => true])->assertForbidden();

    expect($editor->fresh()->two_factor_secret)->toBe($secret)
        ->and($editor->fresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and(Activity::where('event', ActivityEvent::TwoFactorRotationRefused->value)->where('subject_id', $editor->id)->count())->toBe(1);
    Notification::assertSentTo($editor, TwoFactorRotationRefused::class);

    $member = User::factory()->create();
    $this->actingAs($member)->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication')->assertRedirect();

    expect($member->fresh()->two_factor_secret)->not->toBeNull();
});

// تتابع الرموز الخاطئة: تنبيه المشرف العام مرة واحدة لكل حساب في اليوم عند العتبة
it('alerts the super admin once a day when wrong two-factor codes pile up on one account', function () {
    Notification::fake();
    config(['security.two_factor_alert_threshold' => 3]);
    $admin = userWithRole(RoleName::SuperAdmin);
    $editor = userWithRole(RoleName::ContentEditor, ['email' => 'editor@example.test']);
    $this->post('/login', ['login' => 'editor@example.test', 'password' => 'password'])->assertRedirect('/two-factor-challenge');

    foreach (range(1, 2) as $i) {
        $this->post('/two-factor-challenge', ['code' => '000000']);
    }
    Notification::assertNothingSent();

    foreach (range(1, 2) as $i) {
        $this->post('/two-factor-challenge', ['code' => '000000']);
    }

    Notification::assertSentTo($admin, TwoFactorFailuresWarning::class, fn (TwoFactorFailuresWarning $warning): bool => $warning->account->is($editor) && $warning->failures === 3);
    Notification::assertSentTimes(TwoFactorFailuresWarning::class, 1);
    expect(Activity::where('event', ActivityEvent::TwoFactorFailuresAlerted->value)->where('subject_id', $editor->id)->count())->toBe(1);
});

// رفع ملفات اللوحة المؤقت بحد لكل حساب
it('rate-limits temporary panel uploads per account', function () {
    config(['security.uploads_per_minute' => 2]);
    // Livewire يحفظ الرفع المؤقت في هذا القرص أثناء الاختبار
    Storage::fake('tmp-for-tests');
    $editor = userWithRole(RoleName::ContentEditor);
    $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), absolute: false);
    $upload = fn () => $this->actingAs($editor)->post($url, ['files' => [UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')]]);

    $upload()->assertOk();
    $upload()->assertOk();
    $upload()->assertTooManyRequests();
});

// بدء الرفع يُحدّ أيضاً: منه يمر كل رفع، حتى المباشر إلى التخزين (s3) الذي لا يمر بمسار الرفع المحدود
it('rate-limits starting panel uploads, which every upload passes through, even straight to the storage', function () {
    config(['security.uploads_per_minute' => 2]);
    $this->actingAs(userWithRole(RoleName::ContentEditor));
    $page = Livewire::test(CreateDocument::class);
    $file = [['name' => 'a.pdf', 'size' => 1024, 'type' => 'application/pdf']];

    $page->call('_startUpload', 'data.file', $file, false)->assertDispatched('upload:generatedSignedUrl');
    $page->call('_startUpload', 'data.file', $file, false)->assertDispatched('upload:generatedSignedUrl');

    $page->call('_startUpload', 'data.file', $file, false)->assertStatus(429);
});

// مسار الرفع المحلي لصاحب جلسة وحده: الرابط الموقّع وحده لا يكفي
it('refuses a temporary upload without a signed-in account, even with a signed link', function () {
    Storage::fake('tmp-for-tests');
    $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), absolute: false);

    $this->post($url, ['files' => [UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')]])->assertRedirect('/login');
    expect(Storage::disk('tmp-for-tests')->allFiles())->toBe([]);
});

// الصفر يوقف التنبيه ويرفع حد الرفع، ولا يمنع شيئاً
it('turns the alert off and the upload limit off at zero', function () {
    Notification::fake();
    config(['security.two_factor_alert_threshold' => 0, 'security.uploads_per_minute' => 0, 'security.uploads_per_hour' => 0]);
    userWithRole(RoleName::SuperAdmin);
    userWithRole(RoleName::ContentEditor, ['email' => 'editor@example.test']);
    $this->post('/login', ['login' => 'editor@example.test', 'password' => 'password']);

    foreach (range(1, 4) as $i) {
        $this->post('/two-factor-challenge', ['code' => '000000']);
    }
    Notification::assertNothingSent();

    $this->actingAs(userWithRole(RoleName::ContentEditor));
    $page = Livewire::test(CreateDocument::class);

    foreach (range(1, 3) as $i) {
        $page->call('_startUpload', 'data.file', [['name' => 'a.pdf', 'size' => 1024, 'type' => 'application/pdf']], false)->assertDispatched('upload:generatedSignedUrl');
    }
});
