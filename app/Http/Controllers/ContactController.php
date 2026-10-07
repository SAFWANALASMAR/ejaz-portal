<?php

namespace App\Http\Controllers;

use App\Actions\Contact\SubmitInquiry;
use App\Http\Requests\ContactRequest;
use App\Models\Inquiry;
use App\Models\Setting;
use App\Support\FormTimer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * «تواصل معنا»: بيانات التواصل ونموذج «الاستفسارات والاقتراحات». والحماية من الرسائل الآلية بلا
 * مكتبة: فخ مخفي، وختم وقت مشفّر، وحد لكل عنوان (المسار)، وحد يومي لكل بريد (هنا).
 */
class ContactController extends Controller
{
    /**
     * أدنى ثوانٍ لتعبئة النموذج، وأقصاها قبل أن يُطلب تحديث الصفحة.
     */
    private const MIN_SECONDS = 3;

    private const MAX_SECONDS = 7200;

    private const DAILY_PER_EMAIL = 5;

    public function show(Request $request): View
    {
        return view('contact.show', [
            'contact' => Setting::group('contact'),
            'user' => $request->user(),
            'token' => FormTimer::token(),
        ]);
    }

    public function store(ContactRequest $request, SubmitInquiry $submit): RedirectResponse
    {
        $elapsed = FormTimer::secondsSince($request->input('form_token'));

        if ($elapsed === null || $elapsed > self::MAX_SECONDS) {
            return back()->withInput()->withErrors(['form_token' => __('contact.form.expired')]);
        }

        // الفخ لا يراه إلا روبوت، فيُعطى نجاحاً برقم لا يُحفظ، فلا يتعلم منه
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('status', __('contact.form.sent', ['reference' => Inquiry::newReference()]));
        }

        if ($elapsed < self::MIN_SECONDS) {
            return back()->withInput()->withErrors(['form_token' => __('contact.form.too_fast')]);
        }

        // الحد اليومي لكل بريد، فلا يُغرق بريد أحد برسائل التأكيد
        $key = 'contact-email:'.sha1(Str::lower((string) $request->validated('email')));

        if (RateLimiter::tooManyAttempts($key, self::DAILY_PER_EMAIL)) {
            return back()->withInput()->withErrors(['email' => __('contact.form.daily_limit')]);
        }

        RateLimiter::hit($key, 86400);

        $inquiry = $submit->handle($request->safe()->only(['name', 'email', 'phone', 'type', 'subject', 'message']), $request->user());

        return redirect()->route('contact')->with('status', __('contact.form.sent', ['reference' => $inquiry->reference]));
    }
}
