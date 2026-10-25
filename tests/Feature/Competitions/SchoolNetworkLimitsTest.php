<?php

use App\Models\Competition;
use App\Models\User;
use App\Support\SchoolNetworkLimits;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Notification;

/*
| حدود الدخول والتسجيل لكل عنوان أثناء نافذة مسابقة الطلاب (SchoolNetworkLimits، مراجعة المطابقة): مدرسة كاملة خلف
| عنوان واحد. والحدود الأساسية تُخفض هنا (30 تسجيلاً و20 دخولاً خاطئاً) فيظهر الفرق بأربعين طالباً.
*/

beforeEach(function () {
    Notification::fake();
    config([
        'security.registration_per_ip_per_hour' => 30,
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

function schoolRequest(): TestCase
{
    return test()->withServerVariables(['REMOTE_ADDR' => '10.20.30.40']);
}

it('lets forty students behind one school address register and sign in while a student competition is open', function () {
    Competition::factory()->student()->create();

    expect(SchoolNetworkLimits::windowActive())->toBeTrue()
        ->and(SchoolNetworkLimits::limit('registration_per_ip_per_hour'))->toBe(600)
        ->and(SchoolNetworkLimits::limit('login_failures_per_subscriber'))->toBe(300);

    foreach (range(1, 40) as $i) {
        schoolRequest()->post('/register', schoolRegistration($i))->assertSessionHasNoErrors();
        auth()->logout();
    }

    expect(User::query()->where('email', 'like', 'school%')->count())->toBe(40);

    // كل طالب يخطئ مرة ثم يدخل: أربعون خطأً من العنوان نفسه فوق الحد الأساسي (20)
    foreach (range(1, 40) as $i) {
        schoolRequest()->post('/login', ['login' => "school{$i}@example.test", 'password' => 'Wrong-Password-2026']);
        schoolRequest()->post('/login', ['login' => "school{$i}@example.test", 'password' => 'Long-Enough-Pass-2026'])->assertSessionHasNoErrors();
        expect(auth()->check())->toBeTrue();
        auth()->logout();
    }
});

it('keeps the base limits outside a competition window', function () {
    Competition::factory()->student()->closed()->create();
    Competition::query()->first()->rounds()->update(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);
    SchoolNetworkLimits::forget();

    expect(SchoolNetworkLimits::windowActive())->toBeFalse();

    foreach (range(1, 30) as $i) {
        schoolRequest()->post('/register', schoolRegistration($i))->assertSessionHasNoErrors();
        auth()->logout();
    }

    schoolRequest()->post('/register', schoolRegistration(31))->assertSessionHasErrors('email');
    expect(User::query()->where('email', 'like', 'school%')->count())->toBe(30);
});
