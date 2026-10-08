<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Niveles (actividades con varios profesores y clases generadas), avisos de ausencia,
 * comprobantes de transferencia subidos por los socios y rendiciones del efectivo cobrado por profesores.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Niveles / actividades ----
        Schema::create('activity_instructor', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['activity_id', 'user_id']);
        });
        DB::statement('INSERT INTO activity_instructor (activity_id, user_id) SELECT id, instructor_id FROM activities WHERE instructor_id IS NOT NULL');

        Schema::table('activity_schedules', function (Blueprint $table) {
            $table->foreignId('facility_id')->nullable()->after('activity_id')->constrained()->nullOnDelete();
        });

        // Cuota individual (beca o descuento): reemplaza la cuota de la actividad para ese socio.
        Schema::table('enrollments', function (Blueprint $table) {
            $table->decimal('fee_amount', 12, 2)->nullable()->after('end_date');
        });

        // ---- Clases de nivel ----
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('activity_id')->nullable()->after('series_id')->constrained()->nullOnDelete();
            $table->index(['activity_id', 'date']);
        });
        DB::statement('ALTER TABLE lessons ALTER COLUMN facility_id DROP NOT NULL');

        Schema::table('lesson_member', function (Blueprint $table) {
            $table->timestamp('notice_at')->nullable()->after('attendance');
            $table->string('notice_reason', 200)->nullable()->after('notice_at');
        });

        // ---- Rendiciones del efectivo cobrado por profesores ----
        Schema::create('cash_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('payments_count');
            $table->string('status', 20)->default('pendiente')->index();
            $table->string('notes', 500)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('settlement_id')->nullable()->after('received_by')->constrained('cash_settlements')->nullOnDelete();
        });

        // ---- Comprobantes de transferencia ----
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('transfer_date');
            $table->string('reference', 100)->nullable();
            $table->string('file_path');
            $table->string('status', 20)->default('pendiente')->index();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('fee_payment_receipt', function (Blueprint $table) {
            $table->foreignId('payment_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_id')->constrained()->cascadeOnDelete();
            $table->primary(['payment_receipt_id', 'fee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payment_receipt');
        Schema::dropIfExists('payment_receipts');
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('settlement_id'));
        Schema::dropIfExists('cash_settlements');
        Schema::table('lesson_member', fn (Blueprint $table) => $table->dropColumn(['notice_at', 'notice_reason']));
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['activity_id', 'date']);
            $table->dropConstrainedForeignId('activity_id');
        });
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropColumn('fee_amount'));
        Schema::table('activity_schedules', fn (Blueprint $table) => $table->dropConstrainedForeignId('facility_id'));
        Schema::dropIfExists('activity_instructor');
    }
};
