<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda de clases de los profesores (individuales o grupales) y cobros separados por profesor.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sedes (instalaciones) donde trabaja cada profesor; la entidad queda implícita en la sede.
        Schema::create('facility_user', function (Blueprint $table) {
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['facility_id', 'user_id']);
        });

        Schema::create('lesson_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->json('days_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('price', 12, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('lesson_series')->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 20)->default('programada')->index();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('given_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['instructor_id', 'date']);
            $table->index(['facility_id', 'date']);
        });

        Schema::create('lesson_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attendance', 20)->default('pendiente');
            $table->foreignId('fee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['lesson_id', 'member_id']);
            $table->index(['subscription_id', 'attendance']);
        });

        Schema::create('lesson_series_member', function (Blueprint $table) {
            $table->foreignId('lesson_series_id')->constrained('lesson_series')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->primary(['lesson_series_id', 'member_id']);
        });

        foreach (['plans', 'fees', 'payments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('instructor_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
            });
        }

        Schema::table('fees', function (Blueprint $table) {
            $table->foreignId('lesson_id')->nullable()->after('reservation_id')->constrained()->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('preferences'));
        Schema::table('fees', fn (Blueprint $table) => $table->dropConstrainedForeignId('lesson_id'));
        foreach (['plans', 'fees', 'payments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('instructor_id'));
        }
        Schema::dropIfExists('lesson_series_member');
        Schema::dropIfExists('lesson_member');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('lesson_series');
        Schema::dropIfExists('facility_user');
    }
};
