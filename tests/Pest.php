<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Support\ContentCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

/**
 * مسار مؤقت لقسم لم يُبنَ بعد، ليُختبر ما يظهر حين يُبنى (بطاقته في الرئيسية مثلاً).
 */
function registerSectionRoute(string $uri, string $name): void
{
    Route::get($uri, fn () => 'ok')->name($name);
    Route::getRoutes()->refreshNameLookups();
    ContentCache::flush();
}

/**
 * خانة إحصائية معتمدة لاختبارات الرئيسية.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function approvedStat(array $overrides = []): array
{
    return ['label' => 'مستفيد', 'value' => 25000, 'suffix' => '+', 'icon' => 'users', ...$overrides];
}
