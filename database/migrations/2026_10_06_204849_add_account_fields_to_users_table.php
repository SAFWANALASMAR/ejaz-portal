<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // الجوال بصيغة دولية موحدة: +9665XXXXXXXX
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->timestamp('privacy_accepted_at')->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('privacy_accepted_at');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->timestamp('deletion_requested_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'phone_verified_at', 'privacy_accepted_at', 'is_active', 'last_login_at', 'deletion_requested_at']);
        });
    }
};
