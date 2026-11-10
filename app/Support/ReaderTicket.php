<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * رمز القارئ لمرة واحدة لملف «للقراءة فقط» (docs/decisions/2026-11-10-feat-reader-download-protection.md): يُصدر عند رسم صفحة
 * الكتاب أو الوثيقة، أو بطلب POST صغير لإعادة المحاولة، مربوطاً بالجلسة والعنصر، ومدته دقيقة، ويُستهلك عند أول طلب للملف.
 * ومعه مفتاح عشوائي تُخلط به بايتات الملف (ReaderFileResponse)، فما يلتقطه برنامج تحميل من الرابط لا يفتح شيئاً.
 * ولا يُخزَّن إلا بصمة الرمز وبصمة معرّف الجلسة.
 */
final class ReaderTicket
{
    public const TTL_SECONDS = 60;

    public const KEY_BYTES = 32;

    /**
     * علامة في قالب القارئ (library.reader) تُستبدل بالرمز والمفتاح لكل طلب، لأن صفحة العنصر تُخزَّن نصاً واحداً للجميع.
     */
    public const SLOT = 'data-pdf-ticket-slot';

    public static function scope(Model $item): string
    {
        return $item->getMorphClass().':'.$item->getKey();
    }

    /**
     * @return array{ticket: string, key: string} الرمز (64 حرفاً ست عشرية) والمفتاح بترميز base64
     */
    public static function issue(Request $request, string $scope): array
    {
        $ticket = bin2hex(random_bytes(32));
        $key = random_bytes(self::KEY_BYTES);

        Cache::put(self::cacheKey($ticket), [
            'session' => hash('sha256', $request->session()->getId()),
            'scope' => $scope,
            'key' => base64_encode($key),
        ], self::TTL_SECONDS);

        return ['ticket' => $ticket, 'key' => base64_encode($key)];
    }

    /**
     * صفحة العنصر المخزنة بعلامة القارئ، وفيها رمز ومفتاح لهذا الطلب. وبلا قارئ (null) تبقى كما هي.
     */
    public static function inject(string $html, Request $request, ?string $scope): string
    {
        if ($scope === null || ! str_contains($html, self::SLOT)) {
            return $html;
        }

        ['ticket' => $ticket, 'key' => $key] = self::issue($request, $scope);

        return str_replace(self::SLOT, 'data-pdf-ticket="'.$ticket.'" data-pdf-key="'.$key.'"', $html);
    }

    /**
     * يستهلك رمز الطلب (?t=) لهذه الجلسة وهذا العنصر، ويعيد المفتاح الخام، أو null لرمز غائب أو مستعمل أو منتهٍ أو لغيرهما.
     */
    public static function consume(Request $request, string $scope): ?string
    {
        $ticket = $request->query('t');

        if (! is_string($ticket) || preg_match('/\A[0-9a-f]{64}\z/', $ticket) !== 1) {
            return null;
        }

        $cacheKey = self::cacheKey($ticket);
        $data = Cache::get($cacheKey);

        // الحذف يعيد true لمن حذفه وحده، فلا يمر طلبان متزامنان بالرمز نفسه
        if (! is_array($data) || ! Cache::forget($cacheKey)) {
            return null;
        }

        $key = base64_decode((string) ($data['key'] ?? ''), true);

        if (! hash_equals((string) ($data['session'] ?? ''), hash('sha256', $request->session()->getId()))
            || ($data['scope'] ?? null) !== $scope || $key === false || strlen($key) !== self::KEY_BYTES) {
            return null;
        }

        return $key;
    }

    private static function cacheKey(string $ticket): string
    {
        return 'reader-ticket:'.hash('sha256', $ticket);
    }
}
