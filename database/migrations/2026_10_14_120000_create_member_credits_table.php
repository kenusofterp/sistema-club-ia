<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo a favor del socio: movimientos positivos (dinero que queda a cuenta, ej. de un torneo cancelado)
 * y negativos (se usó para pagar). El saldo es la suma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('fee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 250);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_credits');
    }
};
