<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Competencias (torneos que organiza el club, con jueceo): ejercicios con plantilla de criterios,
 * niveles/grupos, puestos con 3 jueces, colas de pasada, notas por juez × criterio y
 * modificaciones autorizadas. Los puntajes son numeric(8,3): se guardan y comparan con 3 decimales.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Catálogo reutilizable ----
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 250)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX exercises_org_name_unique ON exercises (organization_id, lower(name)) WHERE deleted_at IS NULL');

        Schema::create('exercise_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->decimal('min', 8, 3)->default(0);
            $table->decimal('max', 8, 3)->default(10);
            $table->unsignedSmallInteger('decimals')->default(1);
            $table->decimal('weight', 6, 3)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['exercise_id', 'name']);
        });
        $this->criterionChecks('exercise_criteria');

        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('document_number', 20)->nullable();
            $table->string('institution', 120)->nullable();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'last_name']);
        });
        DB::statement('CREATE UNIQUE INDEX competitors_org_document_unique ON competitors (organization_id, document_number) WHERE document_number IS NOT NULL AND deleted_at IS NULL');

        // ---- Competencia ----
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->date('date');
            $table->string('location', 200)->nullable();
            $table->string('status', 20)->default('borrador');
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_token', 40)->unique();
            $table->string('scoring_strategy', 40)->default('promedio');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'date']);
        });

        Schema::create('competition_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['competition_id', 'name']);
        });

        Schema::create('competition_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_level_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['competition_level_id', 'name']);
        });

        Schema::create('competition_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->jsonb('template_snapshot')->nullable();
            $table->timestamp('snapshotted_at')->nullable();
            $table->unique(['competition_id', 'exercise_id']);
        });

        // Proyección normalizada del snapshot (no se edita): destino de las FK de las notas.
        Schema::create('competition_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('source_criterion_id')->nullable();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->decimal('min', 8, 3);
            $table->decimal('max', 8, 3);
            $table->unsignedSmallInteger('decimals');
            $table->decimal('weight', 6, 3);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });
        $this->criterionChecks('competition_criteria');

        // ---- Puestos y jueces ----
        Schema::create('competition_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competition_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 60);
            $table->string('display_token', 40)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['competition_id', 'name']);
            // Habilita la FK compuesta de evaluations (el puesto tiene que ser del mismo ejercicio).
            $table->unique(['id', 'competition_exercise_id']);
        });

        Schema::create('competition_station_judges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('seat');
            $table->timestamps();
            $table->unique(['competition_station_id', 'seat']);
            $table->unique(['competition_station_id', 'user_id']);
            // Un juez trabaja en un solo puesto por competencia.
            $table->unique(['competition_id', 'user_id']);
        });
        DB::statement('ALTER TABLE competition_station_judges ADD CONSTRAINT competition_station_judges_seat_check CHECK (seat BETWEEN 1 AND 3)');

        // ---- Inscripción y evaluaciones ----
        Schema::create('competition_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->restrictOnDelete();
            $table->foreignId('competition_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('competition_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('bib_number', 10);
            $table->timestamps();
            $table->unique(['competition_id', 'competitor_id']);
            $table->unique(['competition_id', 'bib_number']);
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('competition_station_id');
            $table->unsignedInteger('position');
            $table->string('status', 20)->default('pendiente');
            $table->decimal('score', 8, 3)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            // Una participante tiene una sola evaluación por ejercicio en el torneo.
            $table->unique(['competition_entry_id', 'competition_exercise_id']);
            $table->foreign(['competition_station_id', 'competition_exercise_id'])
                ->references(['id', 'competition_exercise_id'])->on('competition_stations')->cascadeOnDelete();
            $table->index(['competition_station_id', 'status', 'position']);
            $table->index(['competition_id', 'competition_exercise_id', 'status', 'score']);
        });

        Schema::create('judge_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('seat');
            $table->decimal('total', 8, 3)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_id', 'seat']);
            $table->unique(['evaluation_id', 'user_id']);
        });

        Schema::create('judge_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judge_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_criterion_id')->constrained('competition_criteria')->restrictOnDelete();
            $table->decimal('value', 8, 3);
            $table->timestamps();
            $table->unique(['judge_sheet_id', 'competition_criterion_id']);
        });

        // ---- Modificaciones autorizadas (registro completo, nunca se pisa sin dejar rastro) ----
        Schema::create('score_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('judge_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->jsonb('old_values');
            $table->jsonb('new_values');
            $table->decimal('old_total', 8, 3);
            $table->decimal('new_total', 8, 3);
            $table->decimal('old_score', 8, 3)->nullable();
            $table->decimal('new_score', 8, 3)->nullable();
            $table->string('status', 20)->default('pendiente');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 250)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX score_change_requests_one_pending ON score_change_requests (judge_sheet_id) WHERE status = 'pendiente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('score_change_requests');
        Schema::dropIfExists('judge_scores');
        Schema::dropIfExists('judge_sheets');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('competition_entries');
        Schema::dropIfExists('competition_station_judges');
        Schema::dropIfExists('competition_stations');
        Schema::dropIfExists('competition_criteria');
        Schema::dropIfExists('competition_exercises');
        Schema::dropIfExists('competition_groups');
        Schema::dropIfExists('competition_levels');
        Schema::dropIfExists('competitions');
        Schema::dropIfExists('competitors');
        Schema::dropIfExists('exercise_criteria');
        Schema::dropIfExists('exercises');
    }

    private function criterionChecks(string $table): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_range_check CHECK (min <= max)");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_decimals_check CHECK (decimals BETWEEN 0 AND 3)");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_weight_check CHECK (weight > 0)");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_type_check CHECK (type IN ('puntaje', 'deduccion', 'si_no'))");
    }
};
