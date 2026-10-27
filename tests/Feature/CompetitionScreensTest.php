<?php

namespace Tests\Feature;

use App\Enums\EvaluationStatus;
use App\Enums\ScoreChangeStatus;
use App\Livewire\Admin\Competitions\Entries;
use App\Livewire\Admin\Competitions\Form;
use App\Livewire\Admin\Competitions\Queues;
use App\Livewire\Admin\Competitions\Results;
use App\Livewire\Admin\Competitions\Show;
use App\Livewire\Admin\Competitions\Stations;
use App\Livewire\Admin\Exercises\Index as ExercisesIndex;
use App\Livewire\Admin\Judge\Station as JudgeStation;
use App\Livewire\Admin\ScoreChanges\Index as ScoreChangesIndex;
use App\Livewire\Display\RankingScreen;
use App\Livewire\Display\StationScreen;
use App\Models\Competition;
use App\Models\Evaluation;
use App\Models\Exercise;
use App\Models\Organization;
use App\Models\ScoreChangeRequest;
use App\Models\User;
use Database\Seeders\CompetitionDemoSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class CompetitionScreensTest extends TestCase
{
    private User $organizer;

    /** @var array<int, User> */
    private array $judges;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = $this->staff('organizador');
        $this->judges = [1 => $this->staff('juez'), 2 => $this->staff('juez'), 3 => $this->staff('juez')];
    }

    /** Arma todo desde las pantallas: ejercicio, competencia, puesto con tribunal, 2 inscriptas, colas e inicio. */
    private function setUpCompetition(): Competition
    {
        $this->actingAs($this->organizer);

        Livewire::test(ExercisesIndex::class)
            ->call('create')
            ->set('name', 'Salto')
            ->set('criteria.0.name', 'Ejecución')
            ->call('addCriterion', 'deduccion')
            ->set('criteria.1.name', 'Penalización')
            ->call('save')
            ->assertHasNoErrors();
        $exercise = Exercise::where('name', 'Salto')->firstOrFail();
        $this->assertCount(2, $exercise->criteria);

        Livewire::test(Form::class)
            ->set('name', 'Copa Primavera')
            ->set('levels.0.name', 'Nivel 1')
            ->set('exerciseIds', [(string) $exercise->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();
        $competition = Competition::where('name', 'Copa Primavera')->firstOrFail();

        Livewire::test(Stations::class, ['competition' => $competition])
            ->call('create')
            ->assertSet('name', 'Salto 1')
            ->call('save')
            ->assertHasNoErrors();
        $station = $competition->stations()->firstOrFail();

        Livewire::test(Stations::class, ['competition' => $competition])
            ->set("panels.{$station->id}", [1 => (string) $this->judges[1]->id, 2 => (string) $this->judges[2]->id, 3 => (string) $this->judges[3]->id])
            ->call('savePanel', $station->id);
        $this->assertSame(3, $station->judges()->count());

        foreach (['Ana', 'Bea'] as $name) {
            Livewire::test(Entries::class, ['competition' => $competition])
                ->set('search', $name)
                ->call('pick', 'nueva')
                ->set('first_name', $name)
                ->set('last_name', 'Pérez')
                ->set('institution', 'Club Rival')
                ->call('save')
                ->assertHasNoErrors();
        }

        Livewire::test(Queues::class, ['competition' => $competition])->call('assignPending');
        $this->assertSame(2, $station->evaluations()->count());

        Livewire::test(Show::class, ['competition' => $competition])->call('start');
        $this->assertTrue($competition->fresh()->isInProgress());

        return $competition->fresh();
    }

    public function test_full_flow_from_the_screens(): void
    {
        $competition = $this->setUpCompetition();
        $station = $competition->stations()->first();
        $criteria = $competition->exercises()->first()->criteria;

        foreach (['puestos', 'participantes', 'colas', 'pantallas'] as $tab) {
            $this->get(route('admin.competitions.show', ['competition' => $competition, 'ver' => $tab]))->assertOk()->assertSee('Copa Primavera');
        }

        // Reordenar la cola: Bea pasa primero.
        $bea = Evaluation::whereHas('entry.competitor', fn ($q) => $q->where('first_name', 'Bea'))->firstOrFail();
        Livewire::test(Queues::class, ['competition' => $competition])->call('sortQueue', $bea->id, 0, $station->id);
        $this->assertSame($bea->id, $station->currentEvaluation()->id);

        // Los 3 jueces cargan desde su pantalla.
        foreach ($this->judges as $seat => $judge) {
            $this->actingAs($judge);
            $this->get(route('admin.judge'))->assertOk()->assertSee('Bea Pérez')->assertSee('Juez '.$seat);

            Livewire::test(JudgeStation::class)
                ->call('confirm', $bea->id, [$criteria[0]->id => '9', $criteria[1]->id => '0.5'])
                ->assertReturned(['ok' => true]);
        }

        $bea->refresh();
        $this->assertSame(EvaluationStatus::Published, $bea->status);
        $this->assertSame('8.500', (string) $bea->score);

        // Fuera de rango: el juez recibe el mensaje y no se guarda nada.
        $this->actingAs($this->judges[1]);
        $ana = $station->currentEvaluation();
        Livewire::test(JudgeStation::class)
            ->call('confirm', $ana->id, [$criteria[0]->id => '11', $criteria[1]->id => '0'])
            ->assertReturned(fn ($r) => $r['ok'] === false && str_contains($r['message'], 'entre 0 y 10'));
        $this->assertSame(0, $ana->sheets()->count());

        // Pedido de modificación del juez 1 sobre la nota de Bea y aprobación del organizador.
        $sheet = $bea->sheets()->where('seat', 1)->first();
        Livewire::test(JudgeStation::class)
            ->call('askChange', $sheet->id)
            ->set("changeValues.{$criteria[0]->id}", '8')
            ->set('changeReason', 'Error al tipear')
            ->call('requestChange')
            ->assertHasNoErrors();
        $request = ScoreChangeRequest::firstOrFail();

        $this->actingAs($this->organizer);
        $this->get(route('admin.score-changes'))->assertOk()->assertSee('Error al tipear');
        Livewire::test(ScoreChangesIndex::class)->call('approve', $request->id);
        $this->assertSame(ScoreChangeStatus::Approved, $request->fresh()->status);
        $this->assertSame('8.166', (string) $bea->fresh()->score); // (7.5 + 8.5 + 8.5) / 3

        // Resultados, CSV e imprimible.
        $this->get(route('admin.competitions.results', $competition))->assertOk()->assertSee('8,166');
        Livewire::test(Results::class, ['competition' => $competition])->assertSee('Pérez, Bea');
        $csv = $this->get(route('admin.competitions.results.csv', $competition));
        $csv->assertOk();
        $this->assertStringContainsString('"Nivel 1";1;2;Pérez;Bea;', $csv->streamedContent());
        $this->assertStringContainsString('8,166', $csv->streamedContent());
        $this->get(route('admin.competitions.results.print', $competition))->assertOk()->assertSee('Copa Primavera');
    }

    public function test_public_screens_use_token_and_competition_organization(): void
    {
        $competition = $this->setUpCompetition();
        $station = $competition->stations()->first();
        auth()->logout();

        // Desde otra entidad (dominio) la pantalla igual muestra la competencia de su token.
        Organization::setCurrent($this->makeOrganization('Otro Club', 'otro'));

        $this->get(route('display.station', $station->display_token))->assertOk()->assertSee('Salto 1')->assertSee('Ana Pérez');
        $this->get(route('display.ranking', $competition->display_token))->assertOk()->assertSee('Copa Primavera');
        $this->get(route('display.station', 'token-invalido'))->assertNotFound();
        $this->get(route('display.ranking', 'token-invalido'))->assertNotFound();

        Livewire::test(StationScreen::class, ['token' => $station->display_token])->assertSee('Juez 3');
        Livewire::test(RankingScreen::class, ['token' => $competition->display_token])
            ->assertSee('Todavía no hay notas publicadas')
            ->call('next')
            ->assertOk();
    }

    public function test_demo_seeder_builds_a_running_competition(): void
    {
        $this->staff();
        $this->seed(CompetitionDemoSeeder::class);

        $competition = Competition::where('name', 'Torneo Apertura (demo)')->firstOrFail();
        $this->assertTrue($competition->isInProgress());
        $this->assertSame(16, $competition->entries()->count());
        $this->assertSame(3, $competition->evaluations()->where('status', EvaluationStatus::Published)->count());
        $this->assertTrue(User::where('email', 'juez1@demo.com')->first()->can('notas.autorizar'));
    }

    public function test_judge_lands_on_station_and_others_cannot_score(): void
    {
        $competition = $this->setUpCompetition();
        $evaluation = $competition->stations()->first()->currentEvaluation();

        $this->assertSame(route('admin.judge'), $this->judges[1]->homeRoute());
        $this->actingAs($this->judges[1])->get(route('admin.competitions.index'))->assertForbidden();

        $outsider = $this->staff('juez');
        $this->actingAs($outsider);
        $this->get(route('admin.judge'))->assertOk()->assertSee('No tenés un puesto asignado');
        Livewire::test(JudgeStation::class)->call('confirm', $evaluation->id, [])->assertForbidden();

        $this->actingAs($this->organizer);
        $this->get(route('admin.judge'))->assertForbidden();
    }
}
