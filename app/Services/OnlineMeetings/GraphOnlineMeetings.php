<?php

namespace App\Services\OnlineMeetings;

use App\Contracts\OnlineMeetings;
use App\Enums\OnlineMeetingFailure;
use App\Exceptions\OnlineMeetingFailed;
use App\Models\Meeting;
use App\Support\TeamsConnection;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Microsoft Graph بطلبات HTTP من لارافيل (بلا مكتبة): مفتاح تطبيق الجمعية في Microsoft 365 (client
 * credentials بصلاحية Calendars.ReadWrite)، والاجتماع حدث في تقويم حساب المنظِّم بـ isOnlineMeeting، فيعود
 * رابط تيمز. ولا مدعوين في الحدث: الدعوات بريد البوابة إلى صفحة الاجتماع، فلا يخرج الرابط إلا للأعضاء.
 * والإعداد من اللوحة (TeamsConnection)، وأي تعذر يُرمى OnlineMeetingFailed بسببه وحده، فتقرر المهمة
 * إعادة المحاولة أو عرضه للمسؤول (docs/guides/teams-meetings.md).
 */
final class GraphOnlineMeetings implements OnlineMeetings
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    public function __construct(private TeamsConnection $connection) {}

    /**
     * بـ transactionId ثابت للاجتماع (uuid والجيل): إعادة المحاولة بعد مهلة أو توقف العامل بعد الإنشاء
     * يردها Graph إلى الحدث نفسه، فلا يتكرر حدث برابط صالح في التقويم. والحدث المردود قد يحمل عنواناً أو
     * موعداً قديماً (عُدّل الاجتماع بين المحاولتين)، فيُحدَّث إلى الحال.
     */
    public function create(Meeting $meeting): array
    {
        $organizer = $this->connection->organizer;
        $payload = $this->payload($meeting);
        try {
            $event = $this->send(fn (PendingRequest $graph) => $graph->post($this->events($organizer), [
                ...$payload,
                'transactionId' => $meeting->uuid.'-'.$meeting->online_generation,
            ]));
        } catch (OnlineMeetingFailed $failed) {
            // رفض نهائي للإنشاء نفسه (أو للمفتاح قبله): لا حدث في التقويم. والعابر قد يكون أُنشئ ولم يصل رده
            throw $failed->reason->isTransient() ? $failed : new OnlineMeetingFailed($failed->reason, $failed->status, nothingCreated: true);
        }

        $id = $event->json('id');

        if (! is_string($id) || $id === '') {
            throw new OnlineMeetingFailed(OnlineMeetingFailure::Unexpected);
        }

        if (! self::matches($event, $payload)) {
            $this->send(fn (PendingRequest $graph) => $graph->patch($this->events($organizer).'/'.rawurlencode($id), $payload));
        }

        // قد يتأخر تجهيز الاجتماع عن إنشاء الحدث، فيُقرأ الحدث مرة. وإن بقي بلا رابط فالمحاولة التالية
        // تعود إلى الحدث نفسه (transactionId)، والحدث بلا رابط لا يُدخل أحداً
        $url = $event->json('onlineMeeting.joinUrl') ?? $this->send(fn (PendingRequest $graph) => $graph->get($this->events($organizer).'/'.rawurlencode($id)))->json('onlineMeeting.joinUrl');

        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new OnlineMeetingFailed(OnlineMeetingFailure::Unexpected);
        }

        return ['id' => $id, 'join_url' => $url, 'organizer' => $organizer];
    }

    public function update(Meeting $meeting): void
    {
        $this->send(fn (PendingRequest $graph) => $graph->patch($this->event($meeting), $this->payload($meeting)));
    }

    public function cancel(Meeting $meeting): void
    {
        try {
            $this->send(fn (PendingRequest $graph) => $graph->delete($this->event($meeting)));
        } catch (OnlineMeetingFailed $failed) {
            // المحذوف من التقويم أصلاً نجاح. وغيره (ومنه منظِّم لا يوجد) يظهر للمسؤول، فلا يبقى رابط صالح مجهول
            if ($failed->reason !== OnlineMeetingFailure::EventNotFound) {
                throw $failed;
            }
        }
    }

    /**
     * «اختبار الاتصال» في اللوحة: مفتاح جديد لا من الذاكرة، ثم قراءة تقويم المنظِّم، فيتحقق المستأجر
     * والتطبيق والسر والصلاحية ونطاق الصندوق معاً، بلا إنشاء شيء.
     */
    public function verify(): void
    {
        Cache::forget($this->tokenKey());

        $this->send(fn (PendingRequest $graph) => $graph->get(self::GRAPH.'/users/'.rawurlencode($this->connection->organizer).'/calendar', ['$select' => 'id']));
    }

    /**
     * العنوان والموعد وحدهما: لا جدول أعمال ولا أسماء ولا مدعوين.
     *
     * @return array<string, mixed>
     */
    private function payload(Meeting $meeting): array
    {
        // الاجتماع عن بعد بموعد انتهاء (SaveMeeting). والساعة احتياط لاسترداد حدث اجتماع صار في القاعة وحذفه
        $ends = $meeting->ends_at ?? $meeting->starts_at->copy()->addHour();

        return [
            'subject' => $meeting->title,
            'start' => ['dateTime' => $meeting->starts_at->copy()->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $ends->copy()->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'isOnlineMeeting' => true,
            'onlineMeetingProvider' => 'teamsForBusiness',
        ];
    }

    /**
     * الحدث المردود بعنوان الاجتماع وموعده الحاليين؟ والموعد لحظةٌ تُقارن، لا نصاً (Graph يضيف كسور الثانية).
     *
     * @param  array<string, mixed>  $payload
     */
    private static function matches(Response $event, array $payload): bool
    {
        // منطقة زمنية لا تُقرأ (اسم ويندوز مثلاً) اختلافٌ، فيُحدَّث الحدث ولا ضرر
        $same = fn (string $key): bool => rescue(fn (): bool => is_string($event->json($key.'.dateTime'))
            && Carbon::parse((string) $event->json($key.'.dateTime'), (string) ($event->json($key.'.timeZone') ?? 'UTC'))->utc()->format('Y-m-d\TH:i:s') === $payload[$key]['dateTime'], false, report: false);

        return $event->json('subject') === $payload['subject'] && $same('start') && $same('end');
    }

    private function events(string $organizer): string
    {
        return self::GRAPH.'/users/'.rawurlencode($organizer).'/events';
    }

    /**
     * الحدث في تقويم من أُنشئ عنده، لا المنظِّم الحالي في الإعداد.
     */
    private function event(Meeting $meeting): string
    {
        return $this->events($meeting->online_organizer ?? $this->connection->organizer).'/'.rawurlencode((string) $meeting->online_meeting_id);
    }

    /**
     * طلب إلى Graph، والرد الفاشل سببٌ لا نص رد.
     *
     * @param  Closure(PendingRequest): Response  $request
     */
    private function send(Closure $request): Response
    {
        $graph = $this->graph();

        try {
            $response = $request($graph);
        } catch (ConnectionException) {
            throw new OnlineMeetingFailed(OnlineMeetingFailure::Timeout);
        }

        if ($response->successful()) {
            return $response;
        }

        $code = $response->json('error.code');

        // رمز رُفض (سُحبت الصلاحية أو تغيّر التطبيق) لا يُعاد استعماله، فالمحاولة التالية بمفتاح جديد
        if (in_array($response->status(), [401, 403], true)) {
            Cache::forget($this->tokenKey());
        }

        throw new OnlineMeetingFailed(match (true) {
            $response->status() === 404 && $code === 'ErrorItemNotFound' => OnlineMeetingFailure::EventNotFound,
            $response->status() === 404, in_array($code, ['ErrorInvalidUser', 'MailboxNotEnabledForRESTAPI'], true) => OnlineMeetingFailure::OrganizerNotFound,
            $response->status() === 401 => OnlineMeetingFailure::InvalidCredentials,
            $response->status() === 403 => OnlineMeetingFailure::AccessDenied,
            $response->status() === 429 => OnlineMeetingFailure::Throttled,
            $response->serverError() => OnlineMeetingFailure::ServiceError,
            $response->status() === 400 => OnlineMeetingFailure::InvalidRequest,
            default => OnlineMeetingFailure::Unexpected,
        }, $response->status());
    }

    private function graph(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->connectTimeout(5)->timeout(15);
    }

    /**
     * مفتاح الدخول في الذاكرة المؤقتة مشفّراً حتى قبيل انتهائه. وبصمة السر (بمفتاح التطبيق) في اسمه، فلا
     * يُستعمل مفتاحُ سرٍّ قديم بعد تغييره في اللوحة.
     */
    private function token(): string
    {
        $cached = Cache::get($this->tokenKey());

        if (is_string($cached)) {
            return Crypt::decryptString($cached);
        }

        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(10)
                ->post('https://login.microsoftonline.com/'.rawurlencode($this->connection->tenantId).'/oauth2/v2.0/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->connection->clientId,
                    'client_secret' => $this->connection->secret(),
                    'scope' => 'https://graph.microsoft.com/.default',
                ]);
        } catch (ConnectionException) {
            throw new OnlineMeetingFailed(OnlineMeetingFailure::Timeout);
        }

        if (! $response->successful()) {
            throw new OnlineMeetingFailed(match (true) {
                $response->status() === 429 => OnlineMeetingFailure::Throttled,
                $response->serverError() => OnlineMeetingFailure::ServiceError,
                // مستأجر غير موجود، أو تطبيق أو سر مرفوض (400 و401)
                default => OnlineMeetingFailure::InvalidCredentials,
            }, $response->status());
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new OnlineMeetingFailed(OnlineMeetingFailure::InvalidCredentials);
        }

        Cache::put($this->tokenKey(), Crypt::encryptString($token), max(60, (int) $response->json('expires_in', 3600) - 300));

        return $token;
    }

    private function tokenKey(): string
    {
        // بصمة بمفتاح التطبيق، فلا تظهر بصمة السر نفسه في أسماء مفاتيح الذاكرة
        return 'ms-graph-token:'.hash_hmac('sha256', $this->connection->tenantId.'|'.$this->connection->clientId.'|'.$this->connection->secret(), (string) config('app.key'));
    }
}
