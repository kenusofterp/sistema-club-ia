<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ficha médica configurable: cada entidad arma su modelo de ficha (preguntas/campos) y
 * cada socio tiene una ficha con las respuestas, guardadas por id de campo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('section', 80)->nullable();
            $table->string('label', 150);
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->string('help', 255)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'is_active', 'sort_order']);
        });

        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('answers');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('medical_form_fields');
    }
};
