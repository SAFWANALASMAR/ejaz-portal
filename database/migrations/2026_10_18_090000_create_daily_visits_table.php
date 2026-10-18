<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عدّاد الزيارات البسيط (docs/plans/admin-stats.md): مجموع كل يوم وحده، بلا عنوان ولا متصفح ولا معرّف لزائر.
 * واليوم بتوقيت الرياض، وينقله ejaz:refresh-stats من الذاكرة المؤقتة (App\Support\Visits).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_visits', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_visits');
    }
};
