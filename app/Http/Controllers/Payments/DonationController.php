<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\StartDonation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StartDonationRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * التبرع: النموذج بالمبالغ المقترحة من اللوحة، ثم التحويل إلى صفحة الدفع عند البوابة.
 * لا تظهر الصفحة حتى تُفعَّل التبرعات من اللوحة.
 */
class DonationController extends Controller
{
    public function create(Request $request): View
    {
        $settings = $this->settings();

        return view('payments.donate', [
            'intro' => is_string($settings['intro'] ?? null) ? $settings['intro'] : null,
            'presets' => array_values(array_filter(array_map('intval', (array) ($settings['amounts'] ?? [])), fn (int $amount): bool => $amount > 0)),
            'user' => $request->user(),
        ]);
    }

    public function store(StartDonationRequest $request, StartDonation $start): RedirectResponse
    {
        $this->settings();

        /** @var User|null $user */
        $user = $request->user();

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
