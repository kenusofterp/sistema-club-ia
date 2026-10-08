<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte para gimnasio: planes (membresías) y suscripciones de socios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->decimal('price', 12, 2);
            $table->string('duration_unit', 10)->default('meses');
            $table->unsignedSmallInteger('duration_value')->default(1);
            $table->string('access_type', 20)->default('libre');
            $table->unsignedSmallInteger('visit_limit')->nullable();
            $table->string('visit_period', 10)->default('mes');
            $table->json('access_windows')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_plan', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->primary(['plan_id', 'activity_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price', 12, 2);
            $table->string('status', 20)->default('pendiente')->index();
            $table->boolean('auto_renew')->default(true);
            $table->foreignId('renewed_from_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index('end_date');
        });

        Schema::table('fees', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('reservation_id')->constrained()->nullOnDelete();
        });

        Schema::table('access_logs', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
        });

        Schema::table('activities', function (Blueprint $table) {
            // false = la actividad solo se accede mediante un plan (clase de gimnasio), sin inscripción mensual.
            $table->boolean('allows_enrollment')->default(true)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn('allows_enrollment'));
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
            $table->dropConstrainedForeignId('subscription_id');
        });
        Schema::table('fees', fn (Blueprint $table) => $table->dropConstrainedForeignId('subscription_id'));
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('activity_plan');
        Schema::dropIfExists('plans');
    }
};
