<?php

namespace App\Http\Requests;

/**
 * رقم الصفحة في قوائم الموقع العام. والمدخل الخاطئ يعيد إلى أول القائمة (redirectRoute في كل طلب).
 */
final class ListPage
{
    public const RULES = ['nullable', 'integer', 'min:1', 'max:1000'];
}
