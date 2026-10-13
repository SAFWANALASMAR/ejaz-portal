<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * حد رفع ملفات اللوحة المؤقت (Livewire) لكل حساب في الدقيقة والساعة (security.uploads_per_*، و0 بلا حد).
 * يُحتسب في موضعين بمفتاحين، فلا يُعد الملف مرتين: بدء الرفع (_startUpload)، ومنه يمر كل رفع حتى المباشر
 * إلى التخزين (s3) الذي لا يمر بالتطبيق؛ ومسار الرفع إلى القرص المحلي (throttle:uploads).
 */
final class UploadLimits
{
    /**
     * @return list<Limit> فارغة إن كان الحدان 0 (بلا حد)
     */
    public static function for(Request $request, string $prefix): array
    {
        // الرفع خلف الدخول اليوم؛ والعنوان (بمشتركه) حارس إن أُتيح رفع لزائر يوماً
        $key = $prefix.':'.($request->user()?->getAuthIdentifier() ?? NetworkPrefix::subscriber($request->ip()));
        $limits = [];

        if (($perMinute = (int) config('security.uploads_per_minute')) > 0) {
            $limits[] = Limit::perMinute($perMinute)->by($key);
        }

        if (($perHour = (int) config('security.uploads_per_hour')) > 0) {
            $limits[] = Limit::perHour($perHour)->by($key.':hour');
        }

        return $limits;
    }

    /**
     * بدء رفع جديد من مكوّن Livewire: يُرفض بعد الحد (429)، وإلا يُحتسب.
     *
     * @throws ThrottleRequestsException
     */
    public static function hitStart(Request $request): void
    {
        $limits = self::for($request, 'upload-starts');

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
                throw new ThrottleRequestsException;
            }
        }

        foreach ($limits as $limit) {
            RateLimiter::hit($limit->key, $limit->decaySeconds);
        }
    }
}
