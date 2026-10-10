<?php

namespace Tests;

use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

abstract class TestCase extends BaseTestCase
{
    /**
     * الأدوار والصلاحيات موجودة في كل اختبار يستخدم قاعدة البيانات.
     */
    protected bool $seed = true;

    protected string $seeder = AccessSeeder::class;

    /**
     * رد خدمة كلمات المرور المسربة. الفارغ يعني أن الكلمة غير مسربة.
     */
    protected string $pwnedRangeResponse = '';

    protected function setUp(): void
    {
        parent::setUp();

        // قائمة المضيفين الموثوقين خاصية ثابتة في Symfony، يضبطها trustHosts حين يجعل اختبارٌ البيئة
        // إنتاجاً، فتبقى للاختبارات بعده. تُصفَّر هنا
        SymfonyRequest::setTrustedHosts([]);

        // لا اتصال بالشبكة في الاختبارات
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => fn () => Http::response($this->pwnedRangeResponse)]);
    }

    /**
     * يجعل خدمة كلمات المرور المسربة تعدّ هذه الكلمة مسربة.
     */
    protected function fakeLeakedPassword(string $password): void
    {
        $this->pwnedRangeResponse = substr(strtoupper(sha1($password)), 5).':42';
    }
}
