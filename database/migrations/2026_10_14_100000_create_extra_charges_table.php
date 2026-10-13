<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adicionales: vestimenta o artículos que el profesor compra y cobra a los alumnos que elige
 * (por ejemplo, la malla de un torneo, o la remera de un nivel). Cada alumno tiene su cargo
 * (fees.extra_charge_id) y se le puede anular individualmente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->date('due_date');
            $table->foreignId('tournament_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('fees', function (Blueprint $table) {
            $table->foreignId('extra_charge_id')->nullable()->after('tournament_id')->constrained()->nullOnDelete();
        });
        DB::statement("CREATE UNIQUE INDEX fees_unique_extra_charge ON fees (member_id, extra_charge_id) WHERE extra_charge_id IS NOT NULL AND status <> 'anulada'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS fees_unique_extra_charge');
        Schema::table('fees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('extra_charge_id');
        });
        Schema::dropIfExists('extra_charges');
    }
};
