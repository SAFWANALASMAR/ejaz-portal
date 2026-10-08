<?php

namespace Database\Seeders;

use App\Enums\RedirectType;
use App\Models\Redirect;
use App\Support\RedirectMap;
use Illuminate\Database\Seeder;

/**
 * روابط الموقع الحالي (ejaz.sa) ونظائرها في البوابة، من خريطة النقل في docs/content-inventory.md،
 * بمعرّفات المبادرات الثابتة في ContentSeeder ومسارات الخدمات كما بُنيت في وحدتها. وروابط الوثائق
 * وعناصر المكتبة القديمة ينشئها أمر الاستيراد مع سجلاتها.
 *
 * تُنشئ الناقص وحده، فلا تغيّر تحويلاً عدّله المحرر من اللوحة.
 */
class LegacyRedirectsSeeder extends Seeder
{
    public const MAP = [
        // صفحة «التقارير» القديمة فارغة، وأقرب ما يقابلها التقارير السنوية
        '/التقارير/' => '/governance?category=annual-reports',

        '/مبادرة-قيـم/' => '/initiatives/qiyam',
        '/مبادرة-تطوير/' => '/initiatives/tatweer',
        '/مبادرة-كنوز/' => '/initiatives/kunooz',
        '/مبادرة-ابتكــــار/' => '/initiatives/ibtikar',
        '/مبادرة-ارتقاء/' => '/initiatives/irtiqa',

        '/ejaz_library' => '/library',
        '/video_library/' => '/library/videos',
        '/audio_library/' => '/library/audio',
        '/books-and-publications' => '/library/books',
        '/research-and-studies' => '/library/research',

        '/الاستفسارات-والاقتراحات/' => '/contact',

        // الخدمات الإلكترونية (المرحلة 3)
        '/طلب-الانضمام-للجمعية-العمومية/' => '/services/assembly-membership',
        '/التطوع/' => '/services/volunteering',
        '/التوظيف/' => '/services/jobs',
    ];

    public function run(): void
    {
        foreach (self::MAP as $source => $target) {
            Redirect::query()->firstOrCreate(['source' => RedirectMap::normalize($source)], [
                'target' => $target,
                'type' => RedirectType::Permanent,
                'is_active' => true,
            ]);
        }
    }
}
