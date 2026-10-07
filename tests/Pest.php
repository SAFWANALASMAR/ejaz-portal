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

/**
 * محتوى PDF صغير صالح للاختبارات، مع إضافة اختيارية داخله (كائن فيه محتوى نشط مثلاً).
 */
function pdfContents(string $extra = ''): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n".$extra."\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

/**
 * محتوى MP3 صغير للاختبارات: وسم ID3 ثم إطارات صوت فارغة، ويتعرف عليه نوعاً audio/mpeg.
 */
function mp3Contents(): string
{
    return "ID3\x04\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x64".str_repeat("\x00", 413), 4);
}

/**
 * مقطع MP4 صغير بمدته: صندوق ftyp، ثم moov فيه mvhd (الإصدار 0)، ثم بيانات بالحجم المطلوب.
 */
function mp4Contents(int $seconds = 30, int $padding = 0): string
{
    $box = fn (string $type, string $body): string => pack('N', 8 + strlen($body)).$type.$body;
    $mvhd = "\x00\x00\x00\x00".pack('N', 0).pack('N', 0).pack('N', 1000).pack('N', $seconds * 1000).str_repeat("\x00", 80);

    return $box('ftyp', 'isom'.pack('N', 512).'isomiso2mp41')
        .$box('moov', $box('mvhd', $mvhd))
        .$box('mdat', str_repeat("\x00", $padding));
}
