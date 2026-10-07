<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\HandlePaymentWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إشعارات بوابة الدفع (مستثناة من CSRF بعد التحقق من التوقيع في الإجراء — docs/security.md).
 * ترد 2xx بسرعة بعد الاعتماد، و403 لتوقيع خاطئ، و404 لعملية مجهولة.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, HandlePaymentWebhook $handle): JsonResponse
    {
        try {
            $handle->handle($request);
        } catch (AuthorizationException) {
            return response()->json(['received' => false], 403);
        }

        return response()->json(['received' => true]);
    }
}
