<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «مسابقاتي» بدل «مشاركاتي» في بطاقة «حسابي» (ملاحظات المدير التنفيذي، ليلة 4 نوفمبر 2026). البطاقة أنشأها ترحيل 25 أكتوبر بالنص
 * وقتها، فتُحدَّث هنا ما دامت بنصها الأصلي وحده: ما عدّلته الإدارة من اللوحة يبقى كما هو.
 */
return new class extends Migration
{
    private const OLD = ['title' => 'مشاركاتي', 'description' => 'مشاركاتك في المسابقات وحالة كل منها ورقمها المرجعي.'];

    private const NEW = ['title' => 'مسابقاتي', 'description' => 'مسابقاتك وحالة كل منها ورقمها المرجعي.'];

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    /**
     * @param  array{title: string, description: string}  $from
     * @param  array{title: string, description: string}  $to
     */
    private function swap(array $from, array $to): void
    {
        foreach (['title', 'description'] as $column) {
            DB::table('account_cards')->where('route', 'account.competitions')->where($column, $from[$column])
                ->update([$column => $to[$column], 'updated_at' => now()]);
        }
    }
};
