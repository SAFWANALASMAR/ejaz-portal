<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\StartDonation;
use App\Contracts\BotChallenge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StartDonationRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * التبرع: النموذج بالمبالغ المقترحة من اللوحة، ثم التحويل إلى صفحة الدفع عند البوابة.
 * لا تظهر الصفحة حتى تُفعَّل التبرعات من اللوحة. والزائر يمر بالتحدي ضد الآليات (Turnstile) وبحد
 * لكل عنوان في الساعة، لأن كل إرسال سجل دائم وفاتورة عند البوابة.
 */
class DonationController extends Controller
{
    public function create(Request $request): View
    {
        $settings = $this->settings();
        $user = $request->user();

        return view('payments.donate', [
            'intro' => is_string($settings['intro'] ?? null) ? $settings['intro'] : null,
            'presets' => array_values(array_filter(array_map('intval', (array) ($settings['amounts'] ?? [])), fn (int $amount): bool => $amount > 0)),
            'user' => $user,
            // عنصر التحدي لغير المسجل وحده، متى ضُبط مفتاح الموقع
            'challengeKey' => $user === null ? app(BotChallenge::class)->siteKey() : null,
        ]);
    }

    public function store(StartDonationRequest $request, StartDonation $start): RedirectResponse
    {
        $this->settings();

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            // يُحتسب قبل الفحص الخارجي بزيادة ذرية، فلا يمر طلبان متزامنان معاً، والمرفوض يُحتسب أيضاً
            if (RateLimiter::increment('donations:ip:'.hash('sha256', (string) $request->ip()), 3600) > (int) config('payments.donation.per_ip_per_hour')) {
                return redirect()->route('donate')->withInput()->with('error', __('payments.donate.busy'));
            }

            // يُحل هنا وحده: خطأ إعداده يظهر عند أول زائر، ولا يمس صاحب الحساب
            if (! app(BotChallenge::class)->passes((string) $request->validated('cf-turnstile-response', ''), 'donation')) {
                return redirect()->route('donate')->withInput()->with('error', __('payments.donate.challenge_failed'));
            }
        }

        return redirect()->away($start->handle($user, $request->validated())->url);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = Setting::group('donations');

        abort_unless((bool) ($settings['enabled'] ?? false), 404);

        return $settings;
    }
}
