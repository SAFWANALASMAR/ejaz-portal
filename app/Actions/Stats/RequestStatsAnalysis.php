<?php

namespace App\Actions\Stats;

use App\Actions\Assistant\CheckAssistantBudget;
use App\Enums\StatsAnalysisStatus;
use App\Jobs\GenerateStatsAnalysis;
use App\Models\AssistantSettings;
use App\Models\StatsAnalysis;
use App\Models\User;
use App\Support\StatsAnalysisFigures;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * طلب تقرير تحليل للإحصائيات لفترة (إضافة صفوان، 19 أكتوبر 2026): لمن له stats.view، ومتى شُغّل التحليل بمزود صالح
 * وفي السقف الشهري المشترك مع المساعد. الأرقام تُحسب هنا في الخادم وتُحفظ، والتقرير يكتبه المزود في الطابور
 * (GenerateStatsAnalysis)، فلا ينتظر المستخدم.
 */
class RequestStatsAnalysis
{
    public const MAX_DAYS = 366;

    public const PER_HOUR = 5;

    public function __construct(private readonly CheckAssistantBudget $budget) {}

    /**
     * @param  array<string, mixed>  $data  from وto بصيغة Y-m-d
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $data): StatsAnalysis
    {
        if (! $actor->can('stats.view')) {
            throw new AuthorizationException;
        }

        $settings = AssistantSettings::current();

        if (! self::available($settings)) {
            throw ValidationException::withMessages(['from' => __('analysis.errors.disabled')]);
        }

        $today = now((string) config('app.display_timezone'))->toDateString();

        /** @var array{from: string, to: string} $validated */
        $validated = Validator::make($data, [
            'from' => ['required', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
        ], attributes: ['from' => __('analysis.from'), 'to' => __('analysis.to')])->validate();

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            throw ValidationException::withMessages(['from' => __('analysis.errors.too_long', ['days' => self::MAX_DAYS])]);
        }

        if (! $this->budget->allows($settings)) {
            throw ValidationException::withMessages(['from' => __('analysis.errors.cap')]);
        }

        // كل طلب يكلّف: حد لكل حساب في الساعة
        $key = 'stats-analysis:'.$actor->getKey();

        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw ValidationException::withMessages(['from' => __('analysis.errors.too_many')]);
        }

        RateLimiter::hit($key, 3600);

        $analysis = StatsAnalysis::query()->create([
            'requested_by' => $actor->getKey(),
            'period_from' => $from,
            'period_to' => $to,
            'status' => StatsAnalysisStatus::Queued,
            'figures' => StatsAnalysisFigures::compute($from, $to),
        ]);

        GenerateStatsAnalysis::dispatch($analysis->id)->afterCommit();

        return $analysis;
    }

    /**
     * التحليل مشغّل بمزود صالح (مفتاحه مستقل عن المساعد، والمزود والسقف مشتركان).
     */
    public static function available(AssistantSettings $settings): bool
    {
        return $settings->analysis_enabled && $settings->hasUsableProvider();
    }
}
