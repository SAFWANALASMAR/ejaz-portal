<?php

namespace App\Support\Assistant;

use App\Models\AssistantGuide;
use App\Models\AssistantSettings;
use Illuminate\Support\Facades\Cache;

/**
 * الأسئلة المقترحة في نافذة المساعد: من الإعدادات، وإلا عناوين مقالات الدليل المفعّلة بترتيبها. تُقرأ مع كل صفحة،
 * فتُخزَّن ويمسحها حفظ مقالة أو حذفها (AssistantGuide).
 */
final class AssistantWidget
{
    public const SUGGESTIONS_KEY = 'assistant.suggestions';

    /**
     * @return list<string>
     */
    public static function suggestions(AssistantSettings $settings): array
    {
        $configured = array_values(array_filter((array) $settings->suggested_questions, fn ($question): bool => is_string($question) && $question !== ''));

        if ($configured !== []) {
            return $configured;
        }

        return Cache::remember(self::SUGGESTIONS_KEY, now()->addHour(), fn (): array => AssistantGuide::query()
            ->active()
            ->orderBy('sort')
            ->orderBy('id')
            ->limit((int) config('assistant.suggestions'))
            ->pluck('title')
            ->all());
    }
}
