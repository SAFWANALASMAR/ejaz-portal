<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\HandlePaymentWebhook;
use App\Http\Controllers\Controller;
use App\Support\Payments\UnsupportedWebhook;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * إشعارات بوابة الدفع (مستثناة من CSRF بعد التحقق من التوقيع في الإجراء — docs/security.md).
 * ترد 2xx بسرعة بعد الاعتماد (وللحدث غير المعالَج بتوقيع صحيح)، و403 لتوقيع خاطئ، و400 لشكل غير
 * مقبول، و404 لعملية مجهولة.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, HandlePaymentWebhook $handle): JsonResponse
    {
        try {
            $handle->handle($request);
        } catch (AuthorizationException) {
            return response()->json(['received' => false], 403);
        } catch (UnsupportedWebhook) {
            // حدث لا نعالجه: 2xx حتى لا تعيد البوابة إرساله
            return response()->json(['received' => true, 'ignored' => true]);
        } catch (InvalidArgumentException) {
            return response()->json(['received' => false], 400);
        }

        return response()->json(['received' => true]);
    }
}
