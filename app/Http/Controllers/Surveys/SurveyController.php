<?php

namespace App\Http\Controllers\Surveys;

use App\Actions\Surveys\SubmitSurveyResponse;
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

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $survey = $this->survey($token);
        $user = $request->user();

        if (! $survey->isOpen()) {
            return view('surveys.show', ['survey' => $survey, 'state' => 'unavailable']);
        }

        if ($user === null && ! $survey->allow_guests) {
            redirect()->setIntendedUrl(route('surveys.show', $token));

            return redirect()->route('login')->with('status', __('surveys.public.login'));
        }

        $answered = $user instanceof User ? SubmitSurveyResponse::answered($survey, $user) : $request->hasCookie(self::cookie($survey));

        return view('surveys.show', ['survey' => $survey->load('questions'), 'state' => $answered ? 'answered' : 'open']);
    }

    public function submit(SubmitSurveyRequest $request, string $token, SubmitSurveyResponse $submit): RedirectResponse
    {
        $survey = $this->survey($token);
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null && $request->hasCookie(self::cookie($survey))) {
            return redirect()->route('surveys.show', $token)->with('error', __('surveys.public.already'));
        }

        // سقف يومي لإجابات غير المسجلين من عنوان واحد على هذا الاستبيان، فلا تُحرَّف النتائج بحذف الكعكة
        $guestKey = 'survey-guest:'.$survey->id.'|'.$request->ip();

        if ($user === null && RateLimiter::tooManyAttempts($guestKey, self::GUEST_DAILY)) {
            return redirect()->route('surveys.show', $token)->with('error', __('surveys.public.already'));
        }

        try {
            $submit->handle($user, $survey->load('questions'), (array) $request->validated('answers', []));
        } catch (AuthorizationException $exception) {
            return redirect()->route('surveys.show', $token)->with('error', $exception->getMessage());
        }

        if ($user === null) {
            Cookie::queue(self::cookie($survey), '1', 60 * 24 * 365);
            RateLimiter::hit($guestKey, 60 * 60 * 24);
        }

        return redirect()->route('surveys.show', $token)->with('status', __('surveys.public.thanks'));
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
