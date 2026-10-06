<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| اختبارات السلوك (Feature) تعمل على قاعدة ejaz_testing وتُعاد لحالتها بعد كل اختبار.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * مستخدم بدور محدد. الأدوار التي تشترط التحقق الثنائي يُفعَّل لها
 * حتى تمر من بوابة لوحة الإدارة في الاختبارات.
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(RoleName $role, array $attributes = []): User
{
    $factory = User::factory();

    if (in_array($role, RoleName::twoFactorRoles(), true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create($attributes);
    $user->assignRole($role->value);

    return $user;
}
