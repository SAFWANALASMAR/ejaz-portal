<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قنوات التواصل (docs/guides/messaging.md):
 * - messaging_channels: مزود كل قناة (الرسائل النصية وواتساب) وإعداداتها من اللوحة، والمفاتيح مشفّرة.
 * - message_logs: حالة كل رسالة بلا نصها، والجوال مخفي إلا آخر أربعة أرقام.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->unique();
            $table->string('provider', 30)->nullable();
            $table->json('options')->nullable();
            // مشفّرة بمفتاح التطبيق (encrypted:array)، ولا تُعرض ولا تُسجَّل
            $table->text('secrets')->nullable();
            $table->timestamps();
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->string('provider', 30);
            $table->string('usage', 40);
            $table->string('phone_hint', 12);
            $table->string('status', 20);
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->string('error', 100)->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('messaging_channels');
    }
};
