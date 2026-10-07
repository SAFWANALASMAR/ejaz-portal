<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * إحصائيات البوابة من بياناتها الفعلية: تُحسب في الخلفية (ejaz:refresh-stats كل عشر دقائق) وتُخزَّن،
 * فلا تُحسب عند كل زيارة (performance.md). ولوحة الإدارة والرئيسية تقرآن المخزّن.
 * كل وحدة تضيف أرقامها هنا عند بنائها (الطلبات وحالاتها، والبرامج والتسجيلات).
 */
final class PortalStats
{
    private const KEY = 'portal.stats';

    /**
     * ما يجوز عرضه في الرئيسية مصدراً آلياً لخانة إحصائية (SiteSettings). وما سواه للإدارة وحدها.
     */
    public const PUBLIC = ['users', 'competitions', 'submissions'];

    /**
     * @return array<string, int>
     */
    public static function compute(): array
    {
        return [
            'users' => User::query()->whereNull('anonymized_at')->count(),
            'verified_users' => User::query()->whereNull('anonymized_at')->whereNotNull('phone_verified_at')->count(),
            'new_users' => User::query()->whereNull('anonymized_at')->where('created_at', '>=', now()->subDays(30))->count(),
            'competitions' => Competition::query()->published()->count(),
            'submissions' => CompetitionSubmission::query()->where('status', SubmissionStatus::Submitted)->count(),
            'drafts' => CompetitionSubmission::query()->where('status', SubmissionStatus::Draft)->count(),
            'surveys' => Survey::query()->where('is_published', true)->count(),
            'responses' => SurveyResponse::query()->count(),
        ];
    }

    /**
     * يحسب ويخزّن، ويعيد السابق للمقارنة.
     *
     * @return array{previous: array<string, int>|null, current: array<string, int>}
     */
    public static function refresh(): array
    {
        $previous = self::cached();
        $current = self::compute();

        // بلا مدة: يبقى آخر حساب حتى يحل محله التالي (سياسة إخلاء Redis في performance.md)
        Cache::forever(self::KEY, ['values' => $current, 'at' => now()->toIso8601String()]);

        return ['previous' => $previous, 'current' => $current];
    }

    /**
     * @return array<string, int>|null
     */
    public static function cached(): ?array
    {
        $stored = Cache::get(self::KEY);

        return is_array($stored) && is_array($stored['values'] ?? null) ? $stored['values'] : null;
    }

    public static function computedAt(): ?string
    {
        $stored = Cache::get(self::KEY);

        return is_array($stored) && is_string($stored['at'] ?? null) ? $stored['at'] : null;
    }
}
