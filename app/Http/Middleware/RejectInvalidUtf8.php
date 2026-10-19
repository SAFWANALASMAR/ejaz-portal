<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * مدخل بترميز UTF-8 غير صالح (في حقول النموذج أو اسم ملف مرفوع) يُرفض بـ 400 قبل الجلسة والمتحكم. والاستعلام
 * خارجه: صفحات القوائم تعيد البحث غير الصالح إلى القائمة كاملة (LibraryTest وGovernanceTest وMediaTest).
 * وإلا رفضه PostgreSQL عند الحفظ فصار 500، وأسقط حفظَ الجلسة بصيغة JSON عند إعادة المدخل القديم بعد خطأ
 * تحقق، فخرج صاحبه من حسابه (اختبار الأمن قبل الرفع، 19 أكتوبر 2026). والمتصفح لا يرسل مثله أبداً.
 */
class RejectInvalidUtf8
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::valid($request->request->all()) && self::valid($request->allFiles()), 400);

        return $next($request);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function valid(array $values): bool
    {
        foreach ($values as $key => $value) {
            $ok = match (true) {
                ! mb_check_encoding((string) $key, 'UTF-8') => false,
                is_array($value) => self::valid($value),
                is_string($value) => mb_check_encoding($value, 'UTF-8'),
                $value instanceof UploadedFile => mb_check_encoding($value->getClientOriginalName(), 'UTF-8'),
                default => true,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }
}
