<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug');
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('monthly_fee', 12, 2)->default(0);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('activity_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('activa')->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Un socio no puede tener dos inscripciones activas a la misma actividad.
        DB::statement("CREATE UNIQUE INDEX enrollments_one_active ON enrollments (member_id, activity_id) WHERE status = 'activa'");
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('activity_schedules');
        Schema::dropIfExists('activities');
    }
};
