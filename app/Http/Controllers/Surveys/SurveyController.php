<?php

namespace App\Http\Controllers\Surveys;

use App\Actions\Surveys\SubmitSurveyResponse;
use App\Contracts\BotChallenge;
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
     * «قياس الرضا» العام (بطاقة المركز الإعلامي والخدمات): الاستبيان العام المفتوح (Survey::openGeneral)،
     * وإلا 404. وبطاقة الخدمات لا تظهر إلا بوجوده (config/eservices.php).
     */
    public function index(): RedirectResponse
    {
        $survey = Survey::openGeneral();

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

        $answered = ($user instanceof User && SubmitSurveyResponse::answered($survey, $user)) || self::answeredOnDevice($request, $survey, $user);

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

        if (self::answeredOnDevice($request, $survey, $user)) {
            return redirect()->route('surveys.show', $token)->with('error', __('surveys.public.already'));
        }

        // الفحوص المحلية الرخيصة أولاً: ضيف لا يجيب هذا الاستبيان أصلاً (مغلق، أو لا يقبل الضيوف، أو لفئة،
        // أو اكتمل سقفه) يرفضه الإجراء برسالته، بلا طلب تحقق خارجي ولا احتساب في حد شبكته
        if ($user === null && self::guestMayAnswer($survey)) {
            // التحدي ضد الآليات (Turnstile)، يُتحقق منه في الخادم قبل الاحتساب والحفظ
            if (! $challenge->passes((string) $request->validated('cf-turnstile-response', ''), 'survey')) {
                return redirect()->route('surveys.show', $token)->with('error', __('surveys.public.challenge_failed'));
            }

            // سقف يومي لمحاولات غير المسجلين من عنوان واحد، فلا تُحرَّف النتائج بحذف الكعكة. يُحتسب قبل الحفظ
            // بزيادة ذرية، فلا يمر طلبان متزامنان معاً، والمرفوض يُحتسب أيضاً
            if (RateLimiter::increment(self::guestKey($survey, (string) $request->ip()), 60 * 60 * 24) > self::GUEST_DAILY) {
                return redirect()->route('surveys.show', $token)->with('error', __('surveys.public.too_many'));
            }
        }

        try {
            $submit->handle($user, $survey->load('questions'), (array) $request->validated('answers', []));
        } catch (AuthorizationException $exception) {
            return redirect()->route('surveys.show', $token)->with('error', $exception->getMessage());
        }

        // في الاستبيان المفتوح للضيوف كعكة بمصدر الإجابة: g للضيف وu لصاحب الحساب (مشفّرة كبقية الكعكات)
        if ($survey->allow_guests) {
            Cookie::queue(self::cookie($survey), $user === null ? 'g' : 'u', 60 * 24 * 365);
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

    /**
     * أُجيب من هذا الجهاز: الضيف يُمنع بأي كعكة (فلا يجيب صاحب حساب ثم يخرج ويجيب ضيفاً)، وصاحب الحساب
     * بكعكة الضيف وحدها (فلا يجيب ضيف ثم يدخل ويجيب)، ولا يُمنع حساب بعد حساب على جهاز مشترك.
     */
    private static function answeredOnDevice(Request $request, Survey $survey, ?User $user): bool
    {
        $source = $survey->allow_guests ? $request->cookie(self::cookie($survey)) : null;

        return $source !== null && ($user === null || $source === 'g');
    }

    /**
     * يجيب غير المسجل هذا الاستبيان: مفتوح، ويقبل الضيوف، وليس لفئة، ولم يكتمل سقفه.
     */
    private static function guestMayAnswer(Survey $survey): bool
    {
        return $survey->isOpen()
            && $survey->allow_guests
            && SubmitSurveyResponse::eligible($survey, null)
            && $survey->guest_limit !== null
            && $survey->guest_responses < $survey->guest_limit;
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
