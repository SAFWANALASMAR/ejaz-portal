<?php

namespace Database\Seeders;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Models\SocialPost;
use App\Rules\SocialPostUrl;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * منشورات «تجريبية» لرابط العرض والتطوير وحدهما (طلب صفوان، 3 نوفمبر 2026، البند ٦): موسومة في نصها، وروابطها روابط عرض لا
 * منشورات حقيقية. ولا تعمل خارج البيئة المحلية: المنشورات الحقيقية يدخلها المسؤول أو الاستيراد.
 *
 *   php artisan db:seed --class=DemoSocialPostsSeeder
 */
class DemoSocialPostsSeeder extends Seeder
{
    public const MARK = 'منشور تجريبي';

    public function run(): void
    {
        // البيئة المحلية وحدها كالمسابقة التجريبية (ejaz:demo-student-competition)، ورابط العرض محلي
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('DemoSocialPostsSeeder: بيانات تجريبية للبيئة المحلية وحدها.');
        }

        $posts = [
            [SocialPlatform::X, SocialPostKind::Post, 'https://x.com/ejaz-demo/status/1'],
            [SocialPlatform::Instagram, SocialPostKind::Event, 'https://instagram.com/p/ejaz-demo-2'],
            [SocialPlatform::YouTube, SocialPostKind::Post, 'https://youtube.com/watch?v=ejaz-demo-3'],
            [SocialPlatform::LinkedIn, SocialPostKind::Event, 'https://linkedin.com/posts/ejaz-demo-4'],
            [SocialPlatform::Snapchat, SocialPostKind::Post, 'https://snapchat.com/add/ejaz-demo-5'],
            [SocialPlatform::TikTok, SocialPostKind::Post, 'https://tiktok.com/@ejaz-demo/video/6'],
        ];

        foreach ($posts as $index => [$platform, $kind, $url]) {
            SocialPost::query()->updateOrCreate(['url' => SocialPostUrl::normalize($url)], [
                'platform' => $platform,
                'kind' => $kind,
                'body' => self::MARK.' '.($index + 1).' — نص للعرض وحده، يحل محله منشور الجمعية من اللوحة أو الاستيراد.',
                'posted_on' => now()->subDays($index * 3)->toDateString(),
                'is_published' => true,
            ]);
        }
    }
}
