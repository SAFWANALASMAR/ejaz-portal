<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/*
| ترحيل تفرد الجوال على كل الحسابات (decisions/2026-11-05-phone-login.md): الرقم المكرر يبقى لحساب واحد، الموثق، ثم من لا
| معرّف دخول له غيره، ثم الأقدم، ويُفرَّغ من البقية. ويُشغَّل على قاعدة فيها تكرار يسمح به التفرد القديم على الموثق وحده.
*/

it('keeps each duplicate number for one account and clears it from the others before making it unique', function () {
    $migration = require database_path('migrations/2026_11_05_010000_make_phone_unique_for_all_accounts.php');
    $migration->down();

    $number = fn (int $i) => '+96651234567'.$i;
    // الأول: الموثق يبقى له الرقم ولو كان غيره أقدم
    $olderUnverified = User::factory()->create(['phone' => $number(1)]);
    $verified = User::factory()->create(['phone' => $number(1), 'phone_verified_at' => now()]);
    // الثاني: من لا اسم مستخدم له ولا بريد يبقى له، فلا يُغلق حسابه
    $withEmail = User::factory()->create(['phone' => $number(2)]);
    $phoneOnly = User::factory()->create(['phone' => $number(2), 'username' => null, 'email' => null]);
    // الثالث: الأقدم
    $oldest = User::factory()->create(['phone' => $number(3)]);
    $newer = User::factory()->create(['phone' => $number(3)]);
    $alone = User::factory()->create(['phone' => $number(4)]);

    $migration->up();

    expect($verified->fresh()->phone)->toBe($number(1))->and($olderUnverified->fresh()->phone)->toBeNull()
        ->and($phoneOnly->fresh()->phone)->toBe($number(2))->and($withEmail->fresh()->phone)->toBeNull()
        ->and($oldest->fresh()->phone)->toBe($number(3))->and($newer->fresh()->phone)->toBeNull()
        ->and($alone->fresh()->phone)->toBe($number(4))
        ->and(fn () => User::factory()->create(['phone' => $number(4)]))->toThrow(UniqueConstraintViolationException::class);
});
