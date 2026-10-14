<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gastos de la entidad: el alquiler del salón, servicios, sueldos, insumos… Junto con lo cobrado
 * arman el resultado del mes en el tablero. Opcionalmente se vinculan a una instalación y a un mes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('expense_date');
            $table->string('category', 60);
            $table->string('description', 250)->nullable();
            $table->string('supplier', 150)->nullable();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            // Mes al que corresponde (ej. alquiler de octubre pagado el 3/11), primer día del mes.
            $table->date('period')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
