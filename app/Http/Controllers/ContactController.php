<?php

namespace App\Http\Controllers;

use App\Actions\Contact\SubmitInquiry;
use App\Http\Requests\ContactRequest;
use App\Models\Inquiry;
use App\Models\Setting;
use App\Support\FormTimer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * «تواصل معنا»: بيانات التواصل ونموذج «الاستفسارات والاقتراحات». والحماية من الرسائل الآلية بلا
 * مكتبة: فخ مخفي وختم وقت مربوط بالجلسة (ContactRequest)، والختم لمرة واحدة، وحدود لكل مشترك
 * وللبوابة (المحدِّد contact)، وحد يومي لكل صندوق بريد (هنا).
 */
class ContactController extends Controller
{
    private const DAILY_PER_EMAIL = 5;

    public function show(Request $request): View
    {
        $session = $request->session()->getId();
        $old = $request->old('form_token');
        $elapsed = FormTimer::secondsSince($old, $session);

        // بعد خطأ تحقق يبقى ختم الفتح الأول ما دام صالحاً ولم يُستهلك، فلا يُحسب التصحيح السريع
        // إرسالاً آلياً
        $token = is_string($old) && $elapsed !== null && $elapsed <= ContactRequest::MAX_SECONDS && ! FormTimer::isConsumed($old)
            ? $old
            : FormTimer::token($session);

        return view('contact.show', [
            'contact' => Setting::group('contact'),
            'user' => $request->user(),
            'token' => $token,
        ]);
    }

    /**
     * والإرسال من الصفحة بلا إعادة تحميل (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ٢٣ — resources/js/contact-form.js): الطلب
     * نفسه بالحماية نفسها، والرد JSON (201 برقم الطلب، و422 بالأخطاء). وبلا سكربت تحويل إلى الصفحة ولوحة النجاح مكان النموذج.
     */
    public function store(ContactRequest $request, SubmitInquiry $submit): RedirectResponse|JsonResponse
    {
        // الحد اليومي لكل صندوق بريد، فلا يُغرق بريد أحد برسائل التأكيد
        $key = self::mailboxKey((string) $request->validated('email'));

        // والختم لمرة واحدة: ضغطتان على الزر أو إعادة الإرسال لا تنشئان رسالتين. والختم المستهلك لا
        // يعود إلى الصفحة
        if (! FormTimer::consume((string) $request->input('form_token'), ContactRequest::MAX_SECONDS)) {
            return self::failed($request, ['form_token' => __('contact.form.already_sent')], $request->except('form_token'));
        }

        // وبلوغ الحد يُرد نجاحاً وهمياً كالفخ (قرار صفوان عبر المشرف، 2 نوفمبر 2026): الحالة واللوحة نفسها ورقم بالصيغة نفسها، ولا
        // يُحفظ شيء ولا يُرسل بريد، فلا يعرف المرسل أن هذا البريد بلغ حده اليوم (المراجعة الأمنية لـ #118، منخفضة ٣)
        if (RateLimiter::tooManyAttempts($key, self::DAILY_PER_EMAIL)) {
            return self::sent($request, Inquiry::randomReference());
        }

        RateLimiter::hit($key, 86400);

        $inquiry = $submit->handle($request->safe()->only(['name', 'email', 'phone', 'type', 'subject', 'message']), $request->user());

        return self::sent($request, $inquiry->reference);
    }

    /**
     * رد النجاح بالرقم، حقيقياً أو وهمياً، بصيغة واحدة: JSON للإرسال بلا إعادة تحميل، وإلا التحويل ولوحة النجاح.
     */
    private static function sent(ContactRequest $request, string $reference): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => __('contact.success.registered', ['reference' => $reference]), 'reference' => $reference], 201);
        }

        return redirect()->to(route('contact').'#contact-form')->with('inquiry_reference', $reference);
    }

    /**
     * @param  array<string, string>  $errors
     * @param  array<string, mixed>  $input
     */
    private static function failed(ContactRequest $request, array $errors, array $input): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => reset($errors), 'errors' => array_map(fn (string $message): array => [$message], $errors)], 422);
        }

        return back()->withInput($input)->withErrors($errors);
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
