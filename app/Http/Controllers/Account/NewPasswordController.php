<?php

namespace App\Http\Controllers\Account;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Timebox;
use Laravel\Fortify\Http\Controllers\NewPasswordController as FortifyNewPasswordController;

/**
 * تعيين كلمة المرور الجديدة برمز الرابط، في زمن ثابت: البريد المسجل برمز قائم يجزّئ الرمز (bcrypt)، والبريد غير
 * المسجل يرد فوراً، فكان الزمن يكشف الحساب ولو تطابقت الرسالة (مراجعة المشرف على الاختبار الأمني 0.14.0).
 */
class NewPasswordController extends FortifyNewPasswordController
{
    // أطول من تجزئة الرمز وتجزئة كلمة المرور الجديدة معاً بتكلفة 12
    public const MICROSECONDS = 700_000;

    public function store(Request $request): Responsable
    {
        /** @var Responsable */
        return app(Timebox::class)->call(fn (): Responsable => parent::store($request), self::MICROSECONDS);
    }
}
