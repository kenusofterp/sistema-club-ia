<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devoluciones de dinero (por ejemplo, de un torneo cancelado). El pago original no se toca:
 * la devolución es un movimiento aparte que descuenta lo pagado del cargo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->date('refund_date');
            $table->string('notes', 250)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['fee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
