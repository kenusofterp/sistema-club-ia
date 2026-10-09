<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Torneos: costo único, fecha máxima de pago, uno o varios días con sus niveles/actividades,
 * libres (se anotan los alumnos de esos niveles) o restringidos (los elige el profesor).
 * El cargo de cada participante es un cargo de la entidad (fees.tournament_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->date('payment_due_date');
            $table->boolean('is_open')->default(true);
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 250)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tournament_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('notes', 200)->nullable();
            $table->index(['tournament_id', 'date']);
        });

        Schema::create('tournament_day_activity', function (Blueprint $table) {
            $table->foreignId('tournament_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->primary(['tournament_day_id', 'activity_id']);
        });

        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->default('profesor');
            $table->boolean('is_exception')->default(false);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tournament_id', 'member_id']);
        });

        Schema::table('fees', function (Blueprint $table) {
            $table->foreignId('tournament_id')->nullable()->after('lesson_id')->constrained()->nullOnDelete();
        });
        DB::statement("CREATE UNIQUE INDEX fees_unique_tournament ON fees (member_id, tournament_id) WHERE tournament_id IS NOT NULL AND status <> 'anulada'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS fees_unique_tournament');
        Schema::table('fees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tournament_id');
        });
        Schema::dropIfExists('tournament_participants');
        Schema::dropIfExists('tournament_day_activity');
        Schema::dropIfExists('tournament_days');
        Schema::dropIfExists('tournaments');
    }
};
