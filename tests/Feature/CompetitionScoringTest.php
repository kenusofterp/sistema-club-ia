<?php

namespace Tests\Feature;

use App\Enums\CompetitionStatus;
use App\Enums\EvaluationStatus;
use App\Enums\ScoreChangeStatus;
use App\Events\EvaluationPublished;
use App\Events\JudgeConfirmed;
use App\Events\ScoreUpdated;
use App\Events\StationAdvanced;
use App\Exceptions\BusinessRuleException;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionStation;
use App\Models\Evaluation;
use App\Models\Exercise;
use App\Models\JudgeScore;
use App\Models\JudgeSheet;
use App\Models\User;
use App\Services\CompetitionRankingService;
use App\Services\CompetitionService;
use App\Services\CompetitorService;
use App\Services\ExerciseService;
use App\Services\JudgingService;
use App\Services\ScoreChangeService;
use App\Services\StationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CompetitionScoringTest extends TestCase
{
    private User $organizer;

    /** @var array<int, User> */
    private array $judges;

    private Exercise $vault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = $this->staff('organizador');
        $this->judges = [1 => $this->staff('juez'), 2 => $this->staff('juez'), 3 => $this->staff('juez')];

        $this->vault = app(ExerciseService::class)->save(null, ['name' => 'Salto'], [
            ['name' => 'Ejecución', 'type' => 'puntaje', 'min' => '0', 'max' => '10', 'decimals' => 1, 'weight' => '1'],
            ['name' => 'Penalización', 'type' => 'deduccion', 'min' => '0', 'max' => '2', 'decimals' => 1, 'weight' => '1'],
            ['name' => 'Elemento obligatorio', 'type' => 'si_no', 'min' => '0', 'max' => '1', 'decimals' => 0, 'weight' => '0.5'],
        ]);
    }

    /** Competencia con un nivel, el ejercicio Salto y un puesto (con o sin sus 3 jueces). */
    private function competition(bool $fullPanel = true): array
    {
        $competition = app(CompetitionService::class)->save(null, ['name' => 'Copa Primavera', 'date' => today()->toDateString(), 'location' => 'Gimnasio'],
            [['name' => 'Nivel 1']], [$this->vault->id], $this->organizer);

        $exercise = $competition->exercises->first();
        $station = app(StationService::class)->save($competition, null, ['name' => 'Salto 1', 'competition_exercise_id' => $exercise->id]);
        $panel = $fullPanel ? [1 => $this->judges[1]->id, 2 => $this->judges[2]->id, 3 => $this->judges[3]->id] : [1 => $this->judges[1]->id, 2 => $this->judges[2]->id];
        app(StationService::class)->setJudges($station, $panel);

        return [$competition->fresh(), $station->fresh()];
    }

    private function enroll(Competition $competition, string $name, ?CompetitionStation $station = null): CompetitionEntry
    {
        $competitor = app(CompetitorService::class)->save(null, ['first_name' => $name, 'last_name' => 'Gómez', 'institution' => 'Club Rival']);
        $entry = app(CompetitorService::class)->enroll($competition, $competitor, $competition->levels->first()->id, null);

        if ($station) {
            app(StationService::class)->assign($station, $entry);
        }

        return $entry;
    }

    /** Los 3 jueces cargan [ejecución, penalización, obligatorio] por asiento. */
    private function judge(Evaluation $evaluation, array $bySeat): void
    {
        $criteria = $evaluation->competitionExercise->criteria;

        foreach ($bySeat as $seat => [$execution, $penalty, $required]) {
            app(JudgingService::class)->confirm($evaluation, $this->judges[$seat], [
                $criteria[0]->id => $execution,
                $criteria[1]->id => $penalty,
                $criteria[2]->id => $required,
            ]);
        }
    }

    public function test_cannot_start_with_incomplete_panel_and_start_freezes_the_template(): void
    {
        [$competition, $station] = $this->competition(fullPanel: false);

        try {
            app(CompetitionService::class)->start($competition);
            $this->fail('Debió exigir 3 jueces.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('3 jueces', $e->getMessage());
        }

        app(StationService::class)->setJudges($station, [1 => $this->judges[1]->id, 2 => $this->judges[2]->id, 3 => $this->judges[3]->id]);
        app(CompetitionService::class)->start($competition);

        $exercise = $competition->exercises()->first();
        $this->assertSame(CompetitionStatus::InProgress, $competition->fresh()->status);
        $this->assertSame('Salto', $exercise->template_snapshot['exercise']['name']);
        $this->assertCount(3, $exercise->template_snapshot['criteria']);
        $this->assertCount(3, $exercise->criteria);

        // Cambiar la plantilla después no altera la competencia iniciada.
        $criteria = $this->vault->criteria->map(fn ($c) => [...$c->only(['id', 'name', 'min', 'decimals', 'weight']), 'type' => $c->type->value, 'max' => '20'])->all();
        app(ExerciseService::class)->save($this->vault, ['name' => 'Salto'], $criteria);

        $this->assertSame('10.000', (string) $exercise->criteria()->first()->max);
        $this->assertSame('10.000', $exercise->fresh()->template_snapshot['criteria'][0]['max']);
    }

    public function test_one_evaluation_per_exercise_and_station_must_match_exercise(): void
    {
        [$competition, $station] = $this->competition();
        $second = app(StationService::class)->save($competition, null, ['name' => 'Salto 2', 'competition_exercise_id' => $station->competition_exercise_id]);
        $entry = $this->enroll($competition, 'Ana', $station);

        // Regla de negocio…
        $this->expectBusinessRule(fn () => app(StationService::class)->assign($second, $entry), 'ya está asignada');

        // …y constraint de la base.
        $this->expectException(QueryException::class);
        Evaluation::create([
            'competition_id' => $competition->id, 'competition_entry_id' => $entry->id,
            'competition_exercise_id' => $station->competition_exercise_id, 'competition_station_id' => $second->id,
            'position' => 1, 'status' => EvaluationStatus::Pending,
        ]);
    }

    public function test_station_of_another_exercise_is_rejected_by_composite_foreign_key(): void
    {
        $floor = app(ExerciseService::class)->save(null, ['name' => 'Suelo'], [
            ['name' => 'Ejecución', 'type' => 'puntaje', 'min' => '0', 'max' => '10', 'decimals' => 1, 'weight' => '1'],
        ]);
        [$competition, $station] = $this->competition();
        $competition = app(CompetitionService::class)->save($competition, $competition->only(['name', 'location']) + ['date' => today()->toDateString()],
            [['id' => $competition->levels->first()->id, 'name' => 'Nivel 1']], [$this->vault->id, $floor->id], $this->organizer);
        $floorExercise = $competition->exercises->firstWhere('exercise_id', $floor->id);
        $entry = $this->enroll($competition, 'Ana');

        $this->expectException(QueryException::class);
        Evaluation::create([
            'competition_id' => $competition->id, 'competition_entry_id' => $entry->id,
            'competition_exercise_id' => $floorExercise->id, 'competition_station_id' => $station->id,
            'position' => 1, 'status' => EvaluationStatus::Pending,
        ]);
    }

    public function test_three_judges_confirm_and_score_is_the_truncated_average(): void
    {
        Event::fake([JudgeConfirmed::class, EvaluationPublished::class, StationAdvanced::class]);
        [$competition, $station] = $this->competition();
        $ana = $this->enroll($competition, 'Ana', $station);
        $bea = $this->enroll($competition, 'Bea', $station);
        app(CompetitionService::class)->start($competition);

        $evaluation = $station->currentEvaluation();
        $this->assertSame($ana->id, $evaluation->competition_entry_id);

        // Rango y decimales según la plantilla congelada.
        $criteria = $evaluation->competitionExercise->criteria;
        $this->expectBusinessRule(fn () => app(JudgingService::class)->confirm($evaluation, $this->judges[1], [$criteria[0]->id => '10.5', $criteria[1]->id => '0']), 'entre 0 y 10');
        $this->expectBusinessRule(fn () => app(JudgingService::class)->confirm($evaluation, $this->judges[1], [$criteria[0]->id => '8.25', $criteria[1]->id => '0']), 'hasta 1 decimales');

        // Un juez que no es del puesto no puede cargar (Policy + regla).
        $outsider = $this->staff('juez');
        $this->assertFalse($outsider->can('create', [JudgeSheet::class, $evaluation]));
        $this->expectBusinessRule(fn () => app(JudgingService::class)->confirm($evaluation, $outsider, [$criteria[0]->id => '8']), 'No sos juez');

        // Totales: 8.4 / 8.5 / 8.5 (sin penalización ni obligatorio) → 25.4 / 3 = 8.4666… → 8.466.
        $this->judge($evaluation, [1 => ['8.4', '0', false], 2 => ['8.5', '0', false]]);
        $this->assertSame(EvaluationStatus::Pending, $evaluation->fresh()->status);

        // Reintento idempotente (conexión inestable): mismo valor, sin error ni duplicado.
        app(JudgingService::class)->confirm($evaluation, $this->judges[1], [$criteria[0]->id => '8.4', $criteria[1]->id => '0', $criteria[2]->id => false]);
        $this->assertSame(2, $evaluation->sheets()->count());

        $this->judge($evaluation, [3 => ['8.5', '0', false]]);

        $evaluation->refresh();
        $this->assertSame(EvaluationStatus::Published, $evaluation->status);
        $this->assertSame('8.466', (string) $evaluation->score);
        $this->assertSame($bea->id, $station->currentEvaluation()->competition_entry_id, 'El puesto avanza a la siguiente.');

        Event::assertDispatchedTimes(JudgeConfirmed::class, 3);
        Event::assertDispatched(EvaluationPublished::class, fn ($e) => $e->evaluation->is($evaluation)
            && $e->broadcastWith()['score'] === '8.466'
            && collect($e->broadcastOn())->map(fn ($c) => $c->name)->all() === [
                'display.station.'.$station->display_token,
                'display.competition.'.$competition->display_token,
            ]);
        Event::assertDispatched(StationAdvanced::class);
    }

    public function test_judge_total_applies_deductions_yes_no_weight_and_floor(): void
    {
        [$competition, $station] = $this->competition();
        $this->enroll($competition, 'Ana', $station);
        app(CompetitionService::class)->start($competition);
        $evaluation = $station->currentEvaluation();

        // 9.0 − 0.3 + 0.5 = 9.2 ; 1.0 − 2.0 + 0 = −1 → 0 ; 8 − 0 + 0.5 = 8.5  → (9.2 + 0 + 8.5) / 3 = 5.9
        $this->judge($evaluation, [1 => ['9', '0.3', true], 2 => ['1', '2', false], 3 => ['8', '0', true]]);

        $this->assertSame(['9.200', '0.000', '8.500'], $evaluation->sheets()->orderBy('seat')->pluck('total')->map(fn ($t) => (string) $t)->all());
        $this->assertSame('5.900', (string) $evaluation->fresh()->score);
    }

    public function test_ties_share_the_rank_and_top_includes_everyone_tied(): void
    {
        [$competition, $station] = $this->competition();
        $entries = collect(['Ana', 'Bea', 'Caro', 'Dani'])->map(fn ($n) => $this->enroll($competition, $n, $station));
        app(CompetitionService::class)->start($competition);

        $scores = [['8.4', '8.5', '8.5'], ['8.5', '8.4', '8.5'], ['8.3', '8.3', '8.3'], ['8.3', '8.3', '8.3']];
        foreach ($scores as [$a, $b, $c]) {
            $this->judge($station->currentEvaluation(), [1 => [$a, '0', false], 2 => [$b, '0', false], 3 => [$c, '0', false]]);
        }

        $exercise = $competition->exercises()->first();
        $level = $competition->levels()->first();
        $ranking = app(CompetitionRankingService::class)->byExercise($competition, $exercise, $level->id);

        $this->assertSame([1, 1, 3, 3], $ranking->pluck('rank')->all());
        $this->assertSame(['8.466', '8.466', '8.300', '8.300'], $ranking->pluck('score')->all());

        // Top 3 con empate en el 3er puesto: aparecen las dos empatadas.
        $this->assertCount(4, app(CompetitionRankingService::class)->byExercise($competition, $exercise, $level->id, limit: 3));
        $this->assertCount(2, app(CompetitionRankingService::class)->byExercise($competition, $exercise, $level->id, limit: 2));

        $allAround = app(CompetitionRankingService::class)->allAround($competition, $level->id);
        $this->assertSame([1, 1, 3, 3], $allAround->pluck('rank')->all());
        $this->assertFalse($allAround->first()->incomplete);
        $this->assertTrue($entries->pluck('id')->contains($allAround->first()->entry_id));
    }

    public function test_absent_does_not_rank_and_all_around_marks_incomplete(): void
    {
        $floor = app(ExerciseService::class)->save(null, ['name' => 'Suelo'], [
            ['name' => 'Ejecución', 'type' => 'puntaje', 'min' => '0', 'max' => '10', 'decimals' => 1, 'weight' => '1'],
        ]);
        [$competition, $station] = $this->competition();
        $competition = app(CompetitionService::class)->save($competition, ['name' => $competition->name, 'date' => today()->toDateString()],
            [['id' => $competition->levels->first()->id, 'name' => 'Nivel 1']], [$this->vault->id, $floor->id], $this->organizer);
        $floorStation = app(StationService::class)->save($competition, null, ['name' => 'Suelo 1', 'competition_exercise_id' => $competition->exercises->firstWhere('exercise_id', $floor->id)->id]);
        app(StationService::class)->setJudges($floorStation, [1 => $this->staff('juez')->id, 2 => $this->staff('juez')->id, 3 => $this->staff('juez')->id]);

        $ana = $this->enroll($competition, 'Ana');
        $bea = $this->enroll($competition, 'Bea');
        $this->assertSame(4, app(StationService::class)->assignPending($competition));
        app(CompetitionService::class)->start($competition);

        // Salto: Ana publicada, Bea ausente. Suelo: nadie todavía.
        $this->judge($station->currentEvaluation(), [1 => ['9', '0', false], 2 => ['9', '0', false], 3 => ['9', '0', false]]);
        app(StationService::class)->markAbsent($station->currentEvaluation());

        $level = $competition->levels()->first();
        $ranking = app(CompetitionRankingService::class)->byExercise($competition, $competition->exercises()->firstWhere('exercise_id', $this->vault->id), $level->id);
        $this->assertSame([$ana->id], $ranking->pluck('entry_id')->all());

        $allAround = app(CompetitionRankingService::class)->allAround($competition, $level->id);
        $this->assertCount(1, $allAround);
        $this->assertTrue($allAround->first()->incomplete);
        $this->assertSame('9.000', $allAround->first()->score);
        $this->assertNull($station->currentEvaluation());
        $this->assertNotContains($bea->id, $allAround->pluck('entry_id'));
    }

    public function test_change_request_needs_another_authorizer_and_recalculates_ranking(): void
    {
        Event::fake([ScoreUpdated::class]);
        [$competition, $station] = $this->competition();
        $ana = $this->enroll($competition, 'Ana', $station);
        $bea = $this->enroll($competition, 'Bea', $station);
        app(CompetitionService::class)->start($competition);

        $this->judge($station->currentEvaluation(), [1 => ['9', '0', false], 2 => ['9', '0', false], 3 => ['9', '0', false]]);
        $this->judge($station->currentEvaluation(), [1 => ['8.5', '0', false], 2 => ['8.5', '0', false], 3 => ['8.5', '0', false]]);

        $exercise = $competition->exercises()->first();
        $level = $competition->levels()->first();
        $this->assertSame([$ana->id, $bea->id], app(CompetitionRankingService::class)->byExercise($competition, $exercise, $level->id)->pluck('entry_id')->all());

        // El juez 1 pide bajar la ejecución de Ana a 7.5.
        $evaluation = Evaluation::where('competition_entry_id', $ana->id)->first();
        $sheet = $evaluation->sheets()->where('seat', 1)->first();
        $criteria = $evaluation->competitionExercise->criteria;
        $this->assertTrue($this->judges[1]->can('requestChange', $sheet));
        $this->assertFalse($this->judges[2]->can('requestChange', $sheet));

        $request = app(ScoreChangeService::class)->request($sheet, [$criteria[0]->id => '7.5', $criteria[1]->id => '0', $criteria[2]->id => false], 'Error de tipeo', $this->judges[1]);
        $this->assertSame('9.000', (string) $evaluation->fresh()->score, 'Sin aprobar no cambia nada.');

        // Un supervisor que pidió el cambio no puede autoaprobarlo; un juez sin permiso tampoco.
        $supervisor = $this->staff('juez_principal');
        $ownRequest = $request->replicate(['status'])->fill(['requested_by' => $supervisor->id]);
        $this->assertFalse($supervisor->can('resolve', $ownRequest));
        $this->expectBusinessRule(fn () => app(ScoreChangeService::class)->approve($request, $this->judges[2]), 'No tenés permiso');

        app(ScoreChangeService::class)->approve($request, $supervisor, 'Verificado con el video');

        $request->refresh();
        $this->assertSame(ScoreChangeStatus::Approved, $request->status);
        $this->assertSame(['9.000', '7.500'], [(string) $request->old_total, (string) $request->new_total]);
        $this->assertSame(['9.000', '8.500'], [(string) $request->old_score, (string) $request->new_score]);
        $this->assertSame($supervisor->id, $request->resolved_by);
        $this->assertSame('9.000', $request->old_values[$criteria[0]->id]);

        // (7.5 + 9 + 9) / 3 = 8.5 → empata con Bea.
        $this->assertSame('8.500', (string) $evaluation->fresh()->score);
        $this->assertSame([1, 1], app(CompetitionRankingService::class)->byExercise($competition, $exercise, $level->id)->pluck('rank')->all());
        Event::assertDispatched(ScoreUpdated::class, fn ($e) => $e->oldScore === '9.000' && $e->newScore === '8.500');

        // Auditoría del valor modificado.
        $this->assertDatabaseHas('activity_log', ['subject_type' => (new JudgeScore)->getMorphClass(), 'event' => 'updated']);
    }

    public function test_confirmed_note_cannot_be_changed_without_authorization(): void
    {
        [$competition, $station] = $this->competition();
        $this->enroll($competition, 'Ana', $station);
        app(CompetitionService::class)->start($competition);
        $evaluation = $station->currentEvaluation();
        $criteria = $evaluation->competitionExercise->criteria;

        app(JudgingService::class)->confirm($evaluation, $this->judges[1], [$criteria[0]->id => '8', $criteria[1]->id => '0']);
        $this->expectBusinessRule(fn () => app(JudgingService::class)->confirm($evaluation, $this->judges[1], [$criteria[0]->id => '9', $criteria[1]->id => '0']), 'pedí una modificación');
    }

    private function expectBusinessRule(callable $action, string $message): void
    {
        try {
            $action();
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString($message, $e->getMessage());

            return;
        }

        $this->fail("Se esperaba una regla de negocio: {$message}");
    }
}
