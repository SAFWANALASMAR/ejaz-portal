<?php

namespace App\Services\OnlineMeetings;

use App\Contracts\OnlineMeetings;
use App\Models\Meeting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Microsoft Graph بطلبات HTTP من لارافيل (بلا مكتبة): مفتاح تطبيق الجمعية في Microsoft 365 (client
 * credentials بصلاحية Calendars.ReadWrite)، والاجتماع حدث في تقويم حساب المنظِّم بـ isOnlineMeeting، فيعود
 * رابط تيمز. ولا مدعوين في الحدث: الدعوات بريد البوابة إلى صفحة الاجتماع، فلا يخرج الرابط إلا للأعضاء.
 * وأي تعذر يُرمى، فتعيد المهمة المحاولة.
 */
final class GraphOnlineMeetings implements OnlineMeetings
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private string $tenantId,
        private string $clientId,
        private string $clientSecret,
        private string $organizer,
    ) {}

    public function create(Meeting $meeting): array
    {
        $event = $this->graph()->post($this->events(), $this->payload($meeting))->throw();
        $id = $event->json('id');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Microsoft Graph returned no event for the meeting.');
        }

        // قد يتأخر تجهيز الاجتماع عن إنشاء الحدث، فيُقرأ الحدث مرة
        $url = $event->json('onlineMeeting.joinUrl') ?? $this->graph()->get($this->events().'/'.rawurlencode($id))->throw()->json('onlineMeeting.joinUrl');

        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            // لا يبقى في التقويم حدث بلا رابط، فلا تتكرر الأحداث بإعادة المحاولة
            $this->graph()->delete($this->events().'/'.rawurlencode($id));

            throw new RuntimeException('Microsoft Graph returned no Teams link for the meeting.');
        }

        return ['id' => $id, 'join_url' => $url];
    }

    public function update(Meeting $meeting): void
    {
        $this->graph()->patch($this->event($meeting), $this->payload($meeting))->throw();
    }

    public function cancel(Meeting $meeting): void
    {
        $response = $this->graph()->delete($this->event($meeting));

        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    /**
     * العنوان والموعد وحدهما: لا جدول أعمال ولا أسماء.
     *
     * @return array<string, mixed>
     */
    private function payload(Meeting $meeting): array
    {
        $ends = $meeting->ends_at ?? throw new RuntimeException('An online meeting needs an end time.');

        return [
            'subject' => $meeting->title,
            'start' => ['dateTime' => $meeting->starts_at->copy()->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $ends->copy()->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'isOnlineMeeting' => true,
            'onlineMeetingProvider' => 'teamsForBusiness',
        ];
    }

    private function events(): string
    {
        return self::GRAPH.'/users/'.rawurlencode($this->organizer).'/events';
    }

    private function event(Meeting $meeting): string
    {
        return $this->events().'/'.rawurlencode((string) $meeting->online_meeting_id);
    }

    private function graph(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->timeout(15);
    }

    /**
     * مفتاح الدخول في الذاكرة المؤقتة مشفّراً حتى قبيل انتهائه.
     */
    private function token(): string
    {
        $key = 'ms-graph-token:'.hash('sha256', $this->tenantId.'|'.$this->clientId);
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return Crypt::decryptString($cached);
        }

        $response = Http::asForm()->timeout(10)
            ->post('https://login.microsoftonline.com/'.rawurlencode($this->tenantId).'/oauth2/v2.0/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ])
            ->throw();

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Microsoft Graph returned no access token.');
        }

        Cache::put($key, Crypt::encryptString($token), max(60, (int) $response->json('expires_in', 3600) - 300));

        return $token;
    }
}
