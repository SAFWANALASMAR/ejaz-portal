<?php

namespace App\Http\Controllers\Surveys;

use App\Actions\Surveys\SubmitSurveyResponse;
use App\Contracts\BotChallenge;
use App\Enums\SurveyAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Surveys\SubmitSurveyRequest;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;

/**
 * الاستبيان برابط رمزه: النموذج، والإجابة. وصاحب الحساب يجيب مرة (قيد فريد)، وغير المسجل —إن سُمح
 * له— يُمنع تكراره من جهازه بكعكة مشفّرة، وبحد للطلبات.
 */
class SurveyController extends Controller
{
    /**
     * إجابات غير المسجلين على الاستبيان الواحد من العنوان الواحد في اليوم (شبكة جهة أو منزل).
     */
    private const GUEST_DAILY = 20;

    /**
     * «قياس الرضا» العام (بطاقة المركز الإعلامي والخدمات): الاستبيان المنشور المفتوح لفئة «عام» غير
     * المربوط بمسابقة، الأحدث فتحاً، وإلا 404. وبوجود المسار تظهر البطاقة (Route::has في قوالبها).
     */
    public function index(): RedirectResponse
    {
        $survey = Survey::query()
            ->where('is_published', true)
            ->where('audience', SurveyAudience::General)
            ->whereNull('subject_type')
            ->where(fn ($query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->latest('opens_at')
            ->latest('id')
            ->first();

        abort_if($survey === null, 404);

        return redirect()->route('surveys.show', $survey->token);
    }

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $survey = $this->survey($token);
        $user = $request->user();

        if (! $survey->isOpen()) {
            return view('surveys.show', ['survey' => $survey, 'state' => 'unavailable']);
        }

        // ولفئة محددة (الأعضاء أو المشاركون في مسابقة) يلزم الحساب ولو سُمح لغير المسجلين
        if ($user === null && (! $survey->allow_guests || ! SubmitSurveyResponse::eligible($survey, null))) {
            redirect()->setIntendedUrl(route('surveys.show', $token));

            return redirect()->route('login')->with('status', __('surveys.public.login'));
        }

        if (! SubmitSurveyResponse::eligible($survey, $user)) {
            return view('surveys.show', ['survey' => $survey, 'state' => 'not_audience']);
        }

        $answered = ($user instanceof User && SubmitSurveyResponse::answered($survey, $user)) || self::answeredOnDevice($request, $survey);

        return view('surveys.show', [
            'survey' => $survey->load('questions'),
            'state' => $answered ? 'answered' : 'open',
            // عنصر التحدي لغير المسجل وحده، متى ضُبط مفتاح الموقع
            'challengeKey' => $user === null ? app(BotChallenge::class)->siteKey() : null,
        ]);
    }

    public function submit(SubmitSurveyRequest $request, string $token, SubmitSurveyResponse $submit, BotChallenge $challenge): RedirectResponse
    {
        $survey = $this->survey($token);
        /** @var User|null $user */
        $user = $request->user();

        // كعكة الضيف تُفحص للجميع في الاستبيان المفتوح لغير المسجلين: فلا يجيب الضيف ثم يجيب ثانية بعد الدخول.
        // ولا تُزرع إلا لإجابة ضيف، فحساب بعد حساب على جهاز مشترك يضبطه القيد الفريد وحده
        if (self::answeredOnDevice($request, $survey)) {
            return redirect()->route('surveys.show', $token)->with('error', __('surveys.public.already'));
        }

        // التحدي ضد الآليات على غير المسجل (Turnstile)، يُتحقق منه في الخادم قبل أي احتساب أو حفظ
        if ($user === null && ! $challenge->passes((string) $request->validated('cf-turnstile-response', ''))) {
            return redirect()->route('surveys.show', $token)->with('error', __('surveys.public.challenge_failed'));
        }

        // سقف يومي لمحاولات غير المسجلين من عنوان واحد على هذا الاستبيان، فلا تُحرَّف النتائج بحذف الكعكة.
        // يُحتسب قبل الحفظ بزيادة ذرية، فلا يمر طلبان متزامنان معاً، والمرفوض يُحتسب أيضاً
        if ($user === null && RateLimiter::increment(self::guestKey($survey, (string) $request->ip()), 60 * 60 * 24) > self::GUEST_DAILY) {
            return redirect()->route('surveys.show', $token)->with('error', __('surveys.public.too_many'));
        }

        try {
            $submit->handle($user, $survey->load('questions'), (array) $request->validated('answers', []));
        } catch (AuthorizationException $exception) {
            return redirect()->route('surveys.show', $token)->with('error', $exception->getMessage());
        }

        if ($user === null) {
            Cookie::queue(self::cookie($survey), '1', 60 * 24 * 365);
        }

        return redirect()->route('surveys.show', $token)->with('status', __('surveys.public.thanks'));
    }

    /**
     * مفتاح سقف العنوان: ببصمة العنوان لا بنصه، فلا يُخزَّن عنوان الزائر في الذاكرة المؤقتة.
     */
    public static function guestKey(Survey $survey, string $ip): string
    {
        return 'survey-guest:'.$survey->id.'|'.hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    private static function answeredOnDevice(Request $request, Survey $survey): bool
    {
        return $survey->allow_guests && $request->hasCookie(self::cookie($survey));
    }

    private function survey(string $token): Survey
    {
        return Survey::query()->where('token', $token)->where('is_published', true)->firstOrFail();
    }

    private static function cookie(Survey $survey): string
    {
        return 'survey_'.$survey->uuid;
    }
}
