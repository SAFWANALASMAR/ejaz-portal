<?php

namespace App\Http\Controllers;

use App\Actions\Messaging\HandleWhatsAppWebhook;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * webhook واتساب (Meta): GET للتحقق عند الاشتراك برمز التحقق المضبوط، وPOST لحالات التسليم بتوقيع
 * App Secret (HandleWhatsAppWebhook). بلا جلسة ولا CSRF (routes/web.php وbootstrap/app.php).
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = MessagingSettings::whatsapp()['verify_token'];

        // PHP يحوّل النقطة في اسم المعامل إلى شرطة سفلية: hub.mode ← hub_mode
        if ($token === null || $request->query('hub_mode') !== 'subscribe' || ! hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response('', 403);
        }

        $challenge = (string) $request->query('hub_challenge');

        return response(ctype_digit($challenge) ? $challenge : '', 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, HandleWhatsAppWebhook $handle): JsonResponse
    {
        try {
            $handle->handle($request);
        } catch (AuthorizationException) {
            return response()->json(['received' => false], 403);
        }

        return response()->json(['received' => true]);
    }
}
