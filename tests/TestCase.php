<?php

namespace Tests;

use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * الأدوار موجودة في كل اختبار يستخدم قاعدة البيانات.
     */
    protected bool $seed = true;

    protected string $seeder = RolesSeeder::class;

    /**
     * رد خدمة كلمات المرور المسربة. الفارغ يعني أن الكلمة غير مسربة.
     */
    protected string $pwnedRangeResponse = '';

    protected function setUp(): void
    {
        parent::setUp();

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
