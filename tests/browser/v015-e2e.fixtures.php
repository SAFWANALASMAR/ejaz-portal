<?php

// حسابات وبيانات اختبار v015-e2e.spec.mjs للبيئة المحلية وحدها. كلمات المرور عشوائية،
// وتُطبع JSON على stdout فقط، فتُحوَّل إلى storage/playwright/v015-creds/accounts.json (خارج git). يُشغَّل بـ:
//   php artisan tinker --execute="require base_path('tests/browser/v015-e2e.fixtures.php');" < /dev/null

use App\Enums\AchievementStatus;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Models\Achievement;
use App\Models\LibraryItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;

abort_unless(app()->environment('local'), 1, 'local only');

$twoFactor = app(TwoFactorAuthenticationProvider::class);
$out = [];

$accounts = [
    'beneficiary' => [RoleName::Member, false, '+966500000101'],
    'assembly' => [RoleName::AssemblyMember, false, '+966500000102'],
    'board' => [RoleName::BoardMember, true, null],
    'director' => [RoleName::ExecutiveDirector, true, null],
];

foreach ($accounts as $key => [$role, $mfa, $phone]) {
    $email = "e2e.{$key}@ejaz.test";
    $password = Str::password(20);
    $user = User::query()->firstOrNew(['email' => $email]);
    $user->forceFill([
        'name' => 'حساب اختبار '.$key,
        'phone' => $phone,
        'password' => $password,
        'email_verified_at' => now(),
        'phone_verified_at' => $phone ? now() : null,
        'privacy_accepted_at' => now(),
        'is_active' => true,
    ])->save();
    $user->syncRoles([$role->value]);
    $entry = ['email' => $email, 'password' => $password];

    if ($mfa) {
        $secret = $twoFactor->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt((string) json_encode(Collection::times(8, fn () => RecoveryCode::generate())->all())),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $entry['totp_secret'] = $secret;
    }

    $out[$key] = $entry;
}

// كتاب منشور «للقراءة فقط» من صفحتين
$pdf = "%PDF-1.4\n";
$objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 400] /Contents 4 0 R /Resources << /Font << /F1 7 0 R >> >> >>',
    null,
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 400] /Contents 6 0 R /Resources << /Font << /F1 7 0 R >> >> >>',
    null,
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
];
foreach ([3 => 'Page one', 5 => 'Page two'] as $index => $text) {
    $stream = "BT /F1 24 Tf 50 300 Td ({$text}) Tj ET";
    $objects[$index] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
}
$offsets = [];
foreach ($objects as $i => $body) {
    $offsets[] = strlen($pdf);
    $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
}
$xref = strlen($pdf);
$pdf .= 'xref\n0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
$pdf = str_replace('xref\n', "xref\n", $pdf);
foreach ($offsets as $offset) {
    $pdf .= sprintf("%010d 00000 n \n", $offset);
}
$pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

$path = 'library/e2e-read-only-book.pdf';
Storage::disk(LibraryItem::disk())->put($path, $pdf);
LibraryItem::query()->updateOrCreate(['slug' => 'e2e-read-only-book'], [
    'title' => 'كتاب تجريبي للقراءة فقط (للاختبار)',
    'section' => LibrarySection::Books,
    'description' => 'وصف تجريبي لاختبار الواجهة، لا محتوى للجمعية.',
    'file' => $path,
    'file_size' => strlen($pdf),
    'is_published' => true,
    'is_downloadable' => false,
]);
$out['read_only_slug'] = 'e2e-read-only-book';

// إنجاز منشور لبوابة المجلس
if (! Achievement::query()->where('title', 'إنجاز تجريبي للاختبار e2e')->exists()) {
    Achievement::factory()->create(['title' => 'إنجاز تجريبي للاختبار e2e', 'status' => AchievementStatus::Published]);
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
