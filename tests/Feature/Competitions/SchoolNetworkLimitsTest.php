<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Competition;
use App\Models\User;
use App\Support\SchoolNetworkLimits;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Notification;

/*
| حدود الدخول والتسجيل لكل عنوان أثناء نوافذ مسابقة الطلاب (SchoolNetworkLimits، مراجعة المطابقة والمراجعة النهائية):
| مدرسة كاملة خلف عنوان عام واحد، وجهاز معمل واحد يسجّل فصلاً. والحدود الأساسية تُخفض هنا (30 تسجيلاً للعنوان وللشبكة،
| و20 دخولاً خاطئاً) فيظهر الفرق بأربعين طالباً. والتسجيل يتسع ما دام مفتوحاً، والدخول في أيام الاختبار وحدها.
*/

beforeEach(function () {
    Notification::fake();
    config([
        'security.registration_per_ip_per_hour' => 30,
        'security.registration_per_network_per_day' => 30,
        'security.login_failures_per_subscriber' => 20,
    ]);
    SchoolNetworkLimits::forget();
});

function schoolRegistration(int $i): array
{
    return [
        'name' => 'طالب المدرسة '.$i,
        'email' => "school{$i}@example.test",
        'phone' => '05'.str_pad((string) (40000000 + $i), 8, '0', STR_PAD_LEFT),
        'password' => 'Long-Enough-Pass-2026',
        'password_confirmation' => 'Long-Enough-Pass-2026',
        'privacy' => '1',
    ];
}

/**
 * طلب من عنوان المدرسة العام، ومن جهاز المعمل نفسه إن طُلب (كعكة الجهاز ثابتة).
 */
function schoolRequest(string $ip = '198.51.100.40', bool $labDevice = false): TestCase
{
    $request = test()->withServerVariables(['REMOTE_ADDR' => $ip]);

    return $labDevice ? $request->withCookie(CreateNewUser::DEVICE_COOKIE, str_repeat('ab', 16)) : $request;
}

it('lets forty students register from one lab device behind one public school address, and sign in on an exam day', function () {
    Competition::factory()->student()->create();

    expect(SchoolNetworkLimits::registrationOpen())->toBeTrue()
        ->and(SchoolNetworkLimits::examWindow())->toBeTrue()
        ->and(SchoolNetworkLimits::limit('registration_per_ip_per_hour'))->toBe(600)
        ->and(SchoolNetworkLimits::limit('registration_per_device_per_hour'))->toBe(40)
        ->and(SchoolNetworkLimits::limit('login_failures_per_subscriber'))->toBe(300);

    foreach (range(1, 40) as $i) {
        schoolRequest(labDevice: true)->post('/register', schoolRegistration($i))->assertSessionHasNoErrors();
        auth()->logout();
    }

    // جهاز المعمل بلغ حده الموسّع (40 في الساعة)
    schoolRequest(labDevice: true)->post('/register', schoolRegistration(41))->assertSessionHasErrors('email');
    expect(User::query()->where('email', 'like', 'school%')->count())->toBe(40);

    // كل طالب يخطئ مرة ثم يدخل: أربعون خطأً من العنوان نفسه فوق الحد الأساسي (20)
    foreach (range(1, 40) as $i) {
        schoolRequest()->post('/login', ['login' => "school{$i}@example.test", 'password' => 'Wrong-Password-2026']);
        schoolRequest()->post('/login', ['login' => "school{$i}@example.test", 'password' => 'Long-Enough-Pass-2026'])->assertSessionHasNoErrors();
        expect(auth()->check())->toBeTrue();
        auth()->logout();
    }
});

it('widens the daily network limit for a school network while registration is open', function () {
    Competition::factory()->student()->create();

    // أربعون جهازاً بعناوين مختلفة في الشبكة العامة نفسها (/24): حد الشبكة اليومي الأساسي 30
    foreach (range(1, 40) as $i) {
        schoolRequest('198.51.100.'.$i)->post('/register', schoolRegistration($i))->assertSessionHasNoErrors();
        auth()->logout();
    }

    expect(User::query()->where('email', 'like', 'school%')->count())->toBe(40);
});

it('keeps the base daily network limit outside the registration window', function () {
    Competition::factory()->student()->closed()->create();
    Competition::query()->first()->rounds()->update(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);
    SchoolNetworkLimits::forget();

    expect(SchoolNetworkLimits::registrationOpen())->toBeFalse()->and(SchoolNetworkLimits::examWindow())->toBeFalse();

    foreach (range(1, 30) as $i) {
        schoolRequest('198.51.100.'.$i)->post('/register', schoolRegistration($i))->assertSessionHasNoErrors();
        auth()->logout();
    }

    schoolRequest('198.51.100.31')->post('/register', schoolRegistration(31))->assertSessionHasErrors('email');
    expect(User::query()->where('email', 'like', 'school%')->count())->toBe(30);
});

it('keeps the base sign-in limits during the registration weeks, widening them on exam days only', function () {
    $competition = Competition::factory()->student()->create();
    $competition->rounds()->update(['starts_at' => now()->addWeeks(2), 'ends_at' => now()->addWeeks(2)->addDay()]);
    SchoolNetworkLimits::forget();

    expect(SchoolNetworkLimits::registrationOpen())->toBeTrue()
        ->and(SchoolNetworkLimits::examWindow())->toBeFalse()
        ->and(SchoolNetworkLimits::limit('registration_per_ip_per_hour'))->toBe(600)
        ->and(SchoolNetworkLimits::limit('login_failures_per_subscriber'))->toBe(20);

    // قبل المرحلة بساعة يتسع الدخول
    $this->travelTo(now()->addWeeks(2)->subMinutes(30));
    SchoolNetworkLimits::forget();

    expect(SchoolNetworkLimits::limit('login_failures_per_subscriber'))->toBe(300);
});
