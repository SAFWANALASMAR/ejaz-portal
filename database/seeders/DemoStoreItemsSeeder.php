<?php

namespace Database\Seeders;

use App\Enums\StoreItemCategory;
use App\Models\StoreItem;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * عناصر «تجريبية» لصفحة «المتجر والتبرعات» في التطوير ورابط العرض وحدهما (docs/decisions/2026-11-04-store-page.md): بأسماء
 * منتجات التبرع السبعة الظاهرة في متجر الجمعية https://ejaz.org.sa (قُرئت من صفحته الرئيسية في 4 نوفمبر 2026) وروابط صفحاتها
 * فيه، وكل عنصر موسوم «تجريبي» في وصفه، وبلا صور (رسم مؤقت محايد) ولا مبالغ: المبلغ يكتبه المتبرع في سلة. ولا تعمل خارج البيئة
 * المحلية: العناصر الحقيقية يدخلها المسؤول من «المتجر» في اللوحة.
 *
 *   php artisan db:seed --class=DemoStoreItemsSeeder
 */
class DemoStoreItemsSeeder extends Seeder
{
    public const MARK = 'تجريبي';

    /**
     * الاسم كما في المتجر ← رابط صفحته فيه.
     *
     * @var array<string, string>
     */
    public const ITEMS = [
        'أجرٌ لا ينقطع وأثرٌ لا يزول' => 'https://ejaz.org.sa/%D8%A3%D8%AC%D8%B1%D9%8C-%D9%84%D8%A7-%D9%8A%D9%86%D9%82%D8%B7%D8%B9-%D9%88%D8%A3%D8%AB%D8%B1%D9%8C-%D9%84%D8%A7-%D9%8A%D8%B2%D9%88%D9%84/p1701312129',
        'زكاتك حياة' => 'https://ejaz.org.sa/%D8%B2%D9%83%D8%A7%D8%AA%D9%83-%D8%AD%D9%8A%D8%A7%D8%A9/p1971442778',
        'صدقتك نجاة' => 'https://ejaz.org.sa/%D8%B5%D8%AF%D9%82%D8%AA%D9%83-%D9%86%D8%AC%D8%A7%D8%A9/p1087375311',
        'عطاءٌ ممتد ،، وثوابٌ لا ينفد' => 'https://ejaz.org.sa/%D8%B9%D8%B7%D8%A7%D8%A1%D9%8C-%D9%85%D9%85%D8%AA%D8%AF-%D8%8C%D8%8C-%D9%88%D8%AB%D9%88%D8%A7%D8%A8%D9%8C-%D9%84%D8%A7-%D9%8A%D9%86%D9%81%D8%AF/p742930526',
        'منتجات جمعية إعجاز' => 'https://ejaz.org.sa/%D9%85%D9%86%D8%AA%D8%AC%D8%A7%D8%AA-%D8%AC%D9%85%D8%B9%D9%8A%D8%A9-%D8%A5%D8%B9%D8%AC%D8%A7%D8%B2/p104620710',
        'البرامج التقنية بجمعية إعجاز' => 'https://ejaz.org.sa/%D8%A7%D9%84%D8%A8%D8%B1%D8%A7%D9%85%D8%AC-%D8%A7%D9%84%D8%AA%D9%82%D9%86%D9%8A%D8%A9-%D8%A8%D8%AC%D9%85%D8%B9%D9%8A%D8%A9-%D8%A5%D8%B9%D8%AC%D8%A7%D8%B2/p336808555',
        'وبالوالدين إحسانًا' => 'https://ejaz.org.sa/%D9%88%D8%A8%D8%A7%D9%84%D9%88%D8%A7%D9%84%D8%AF%D9%8A%D9%86-%D8%A5%D8%AD%D8%B3%D8%A7%D9%86%D9%8B%D8%A7/p1855620461',
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('DemoStoreItemsSeeder: بيانات تجريبية للبيئة المحلية وحدها.');
        }

        $sort = 0;

        // كلها تبرعات في سلة (يكتب المتبرع مبلغ المساهمة)، وأيها إصدار سؤال للجمعية (docs/open-questions.md)
        foreach (self::ITEMS as $title => $url) {
            StoreItem::query()->updateOrCreate(['salla_url' => $url], [
                'title' => $title.' ('.self::MARK.')',
                'description' => self::MARK.' — بطاقة للعرض وحده، يحل محلها وصف الجمعية وصورتها من «المتجر» في اللوحة.',
                'category' => StoreItemCategory::Donation,
                'salla_product_id' => null,
                'image' => null,
                'sort' => ++$sort,
                'is_published' => true,
            ]);
        }
    }
}
