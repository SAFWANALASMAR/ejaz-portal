<?php

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
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
