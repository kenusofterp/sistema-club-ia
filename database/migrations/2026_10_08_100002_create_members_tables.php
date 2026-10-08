<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->decimal('monthly_fee', 12, 2)->default(0);
            $table->decimal('admission_fee', 12, 2)->default(0);
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('member_number', 20)->nullable();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('document_type', 20)->default('DNI');
            $table->string('document_number', 30);
            $table->date('birth_date');
            $table->string('gender', 1)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->foreignId('member_category_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pendiente')->index();
            $table->date('admission_date')->nullable();
            $table->date('leave_date')->nullable();
            $table->string('leave_reason')->nullable();
            $table->string('photo_path')->nullable();
            // La misma cuenta de usuario puede ser socia en varias entidades (una membresía por entidad).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('holder_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('relationship', 40)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->text('medical_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'member_number']);
            $table->unique(['organization_id', 'document_type', 'document_number']);
            $table->unique(['organization_id', 'user_id']);
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
        Schema::dropIfExists('member_categories');
    }
};
