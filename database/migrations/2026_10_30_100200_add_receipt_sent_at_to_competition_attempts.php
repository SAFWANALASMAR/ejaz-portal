<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إشعار البريد عند تسليم محاولة في مسابقة الطلاب، يدوياً أو آلياً (قرار صفوان والمدير التنفيذي، 30 أكتوبر 2026):
 * وقت إرساله على المحاولة، فلا يُرسل مرتين لمحاولة واحدة (SubmitAttempt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_attempts', function (Blueprint $table): void {
            $table->timestamp('receipt_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competition_attempts', function (Blueprint $table): void {
            $table->dropColumn('receipt_sent_at');
        });
    }
};
