<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Becas por porcentaje en la inscripción (solo cuota mensual) e inscripción anual por actividad/nivel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->decimal('scholarship_percent', 5, 2)->nullable()->after('fee_amount');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->decimal('enrollment_fee', 12, 2)->default(0)->after('monthly_fee');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', fn (Blueprint $table) => $table->dropColumn('scholarship_percent'));
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn('enrollment_fee'));
    }
};
