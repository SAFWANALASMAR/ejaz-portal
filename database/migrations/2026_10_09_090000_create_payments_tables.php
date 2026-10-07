<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المدفوعات (المرحلة 5): عملية لكل دفع بمبلغها من الخادم (هللات) وحالتها ومرجعها عند البوابة، وسجل
 * مالي ثابت لأحداثها. المبلغ والعملة والغرض وصاحب العملية لا تُعدَّل بعد الإنشاء، ولا تُحذف عملية،
 * ولا يُعدَّل حدث ولا يُحذف: مشغّلات في قاعدة البيانات لا يتجاوزها أي استعلام.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('purpose', 30);
            // رسوم العضوية والبرامج تُربط بسجلها مع وحدتيهما
            $table->nullableMorphs('payable');
            // بالهللات
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('SAR');
            $table->string('status', 20)->default('pending');
            $table->string('gateway', 20);
            // رقم الفاتورة عند البوابة، ورقم الدفعة داخلها (للاسترداد)
            $table->string('gateway_reference', 100)->nullable()->unique();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->string('donor_name', 100)->nullable();
            $table->string('donor_email')->nullable();
            $table->string('description')->nullable();
            $table->string('receipt_number', 30)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->string('gateway_message')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason', 500)->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->string('type', 20);
            // معرّف إشعار البوابة: الإشعار المكرر يُرد بالقيد الفريد
            $table->string('gateway_event_id', 100)->nullable()->unique();
            // الحالة والمبلغ والعملة والرسالة فقط، بلا بيانات بطاقة
            $table->jsonb('payload')->nullable();
            $table->timestamp('created_at');

            $table->index(['payment_id', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION payment_records_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'payment records are append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION payments_guard_update() RETURNS trigger AS $$
            BEGIN
                IF NEW.amount <> OLD.amount
                    OR NEW.currency <> OLD.currency
                    OR NEW.purpose <> OLD.purpose
                    OR NEW.user_id IS DISTINCT FROM OLD.user_id
                    OR NEW.payable_type IS DISTINCT FROM OLD.payable_type
                    OR NEW.payable_id IS DISTINCT FROM OLD.payable_id THEN
                    RAISE EXCEPTION 'payment amount, currency, purpose and owner are immutable';
                END IF;
                -- المكتملة لا تعود: المدفوعة لا تصير إلا مستردة، والمستردة لا تتغير
                IF OLD.status IN ('paid', 'refunded') AND NEW.status <> OLD.status
                    AND NOT (OLD.status = 'paid' AND NEW.status = 'refunded') THEN
                    RAISE EXCEPTION 'a settled payment cannot change its status';
                END IF;
                IF (OLD.receipt_number IS NOT NULL AND NEW.receipt_number IS DISTINCT FROM OLD.receipt_number)
                    OR (OLD.paid_at IS NOT NULL AND NEW.paid_at IS DISTINCT FROM OLD.paid_at) THEN
                    RAISE EXCEPTION 'the receipt of a payment is immutable';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER payments_no_delete
                BEFORE DELETE ON payments
                FOR EACH ROW EXECUTE FUNCTION payment_records_immutable();

            CREATE TRIGGER payments_guard_update
                BEFORE UPDATE ON payments
                FOR EACH ROW EXECUTE FUNCTION payments_guard_update();

            CREATE TRIGGER payment_events_immutable
                BEFORE UPDATE OR DELETE ON payment_events
                FOR EACH ROW EXECUTE FUNCTION payment_records_immutable();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        DB::unprepared('DROP FUNCTION IF EXISTS payments_guard_update()');
        DB::unprepared('DROP FUNCTION IF EXISTS payment_records_immutable()');
    }
};
