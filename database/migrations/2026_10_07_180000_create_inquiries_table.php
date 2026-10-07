<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رسائل «الاستفسارات والاقتراحات» (البند ٧): طلب من زائر أو صاحب حساب، برقم مرجعي عام وحالة
 * ومسؤول. حذف ناعم، لأن الطلبات من السجلات المهمة (docs/architecture.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('type', 20);
            $table->string('subject', 200);
            $table->text('message');
            $table->string('status', 20);
            $table->foreignId('assigned_to')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->text('internal_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
