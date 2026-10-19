<?php

namespace App\Actions\Assistant;

use App\Enums\RoleName;
use App\Models\AssistantSettings;
use App\Models\AssistantUsage;
use App\Models\User;
use App\Notifications\AssistantCapReached;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * حدود الإنفاق على المزود (requirements/10)، مشتركة بين كل ما يرسل إليه. التكلفة تقديرية من عدد الوحدات وسعرها في
 * الإعدادات، وتُجمع في AssistantUsage لا في السجل (فحذفه بمدة الاحتفاظ لا يُنقصها). وبلا سقف أو بلا سعر لا يُرسل شيء.
 *
 * - **السقف الشهري:** عند بلوغه يتوقف التوليد حتى الشهر التالي (أو رفع السقف)، ويُنبَّه المشرف العام مرة في الشهر.
 * - **حصة اليوم** من السقف الشهري (config/assistant.php)، فلا يُستنزف الشهر في يوم واحد.
 * - **حد عام للطلبات في الدقيقة** من البوابة كلها، فوق حد كل زائر وشبكته.
 * (المراجعة الأمنية، م1 وم3)
 */
class CheckAssistantBudget
{
    public const GLOBAL_KEY = 'assistant:generate';

    public function allows(AssistantSettings $settings): bool
    {
        if ($settings->monthly_cap === null || $settings->input_price === null || $settings->output_price === null) {
            return false;
        }

        $cap = (float) $settings->monthly_cap;
        $spent = AssistantUsage::monthCost();

        if ($spent >= $cap) {
            $this->alert($settings, $spent, $cap);

            return false;
        }

        return AssistantUsage::dayCost() < $cap * (float) config('assistant.daily_share');
    }

    /**
     * طلب واحد من الحد العام في الدقيقة: يُحجز قبل الإرسال، وما زاد يُرد عليه «المساعد مشغول».
     */
    public function takeSlot(): bool
    {
        return RateLimiter::attempt(self::GLOBAL_KEY, (int) config('assistant.global_per_minute'), fn (): bool => true, 60) === true;
    }

    public function cost(AssistantSettings $settings, int $inputTokens, int $outputTokens): float
    {
        return round(($inputTokens * (float) $settings->input_price + $outputTokens * (float) $settings->output_price) / 1_000_000, 6);
    }

    public function record(float $cost): void
    {
        AssistantUsage::add($cost);
    }

    private function alert(AssistantSettings $settings, float $spent, float $cap): void
    {
        $month = now((string) config('app.display_timezone'))->format('Y-m');

        // تحديث شرطي واحد: طلبان متزامنان لا يرسلان تنبيهين
        $claimed = AssistantSettings::query()
            ->whereKey($settings->getKey())
            ->where(fn ($query) => $query->whereNull('cap_alerted_month')->orWhere('cap_alerted_month', '!=', $month))
            ->update(['cap_alerted_month' => $month]);

        if ($claimed === 0) {
            return;
        }

        Cache::forget(AssistantSettings::CACHE_KEY);
        Log::warning('AI assistant reached its monthly cost cap.', ['spent' => $spent, 'cap' => $cap]);

        Notification::send(
            User::role(RoleName::SuperAdmin->value)->where('is_active', true)->get(),
            new AssistantCapReached($spent, $cap),
        );
    }
}
