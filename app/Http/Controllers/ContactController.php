<?php

namespace App\Http\Controllers;

use App\Actions\Contact\SubmitInquiry;
use App\Http\Requests\ContactRequest;
use App\Models\Setting;
use App\Support\FormTimer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * «تواصل معنا»: بيانات التواصل ونموذج «الاستفسارات والاقتراحات». والحماية من الرسائل الآلية بلا
 * مكتبة: فخ مخفي وختم وقت مربوط بالجلسة (ContactRequest)، والختم لمرة واحدة، وحد لكل عنوان
 * (المسار)، وحد يومي لكل صندوق بريد (هنا).
 */
class ContactController extends Controller
{
    private const DAILY_PER_EMAIL = 5;

    public function show(Request $request): View
    {
        return view('contact.show', [
            'contact' => Setting::group('contact'),
            'user' => $request->user(),
            'token' => FormTimer::token($request->session()->getId()),
        ]);
    }

    public function store(ContactRequest $request, SubmitInquiry $submit): RedirectResponse
    {
        // الحد اليومي لكل صندوق بريد، فلا يُغرق بريد أحد برسائل التأكيد
        $key = self::mailboxKey((string) $request->validated('email'));

        if (RateLimiter::tooManyAttempts($key, self::DAILY_PER_EMAIL)) {
            return back()->withInput()->withErrors(['email' => __('contact.form.daily_limit')]);
        }

        // والختم لمرة واحدة: ضغطتان على الزر أو إعادة الإرسال لا تنشئان رسالتين
        if (! FormTimer::consume((string) $request->validated('form_token'), ContactRequest::MAX_SECONDS)) {
            return back()->withInput()->withErrors(['form_token' => __('contact.form.already_sent')]);
        }

        RateLimiter::hit($key, 86400);

        $inquiry = $submit->handle($request->safe()->only(['name', 'email', 'phone', 'type', 'subject', 'message']), $request->user());

        return redirect()->route('contact')->with('status', __('contact.form.sent', ['reference' => $inquiry->reference]));
    }

    /**
     * مفتاح الحد لصندوق البريد لا لكتابته: بلا فرق في حالة الأحرف، ولا وسم بعد +، ولا نقاط جيميل،
     * فلا يُلتف على الحد بصيغ أخرى للبريد نفسه. والمفتاح بصمة لا البريد نفسه.
     */
    private static function mailboxKey(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', Str::lower(trim($email)), 2), 2, '');
        $local = Str::before($local, '+');

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return 'contact-email:'.hash('sha256', $local.'@'.$domain);
    }
}
