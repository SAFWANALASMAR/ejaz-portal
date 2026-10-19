<?php

namespace Database\Seeders;

use App\Enums\AssistantReviewStatus;
use App\Models\AssistantGuide;
use Illuminate\Database\Seeder;

/**
 * مسودات «دليل استخدام البوابة» من database/data/assistant-guide.php (مكتوبة من الشيفرة الفعلية). يضيف الناقص وحده
 * بحالة المسودة، ولا يمس ما عدّله المسؤول أو فعّله. والمسودة لا يستعملها المساعد حتى يراجعها المسؤول.
 *
 * عند النشر: php artisan db:seed --class=AssistantGuideSeeder --force
 */
class AssistantGuideSeeder extends Seeder
{
    public const DATA = 'data/assistant-guide.php';

    public function run(): void
    {
        /** @var list<array{slug: string, title: string, keywords: string, route: string, steps: list<string>, labels: list<string>}> $articles */
        $articles = require database_path(self::DATA);

        foreach ($articles as $index => $article) {
            AssistantGuide::query()->firstOrCreate(['slug' => $article['slug']], [
                'title' => $article['title'],
                'keywords' => $article['keywords'],
                'steps' => $article['steps'],
                'route_name' => $article['route'],
                'status' => AssistantReviewStatus::Draft,
                'sort' => ($index + 1) * 10,
            ]);
        }
    }
}
