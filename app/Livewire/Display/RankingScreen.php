<?php

namespace App\Livewire\Display;

use App\Livewire\Display\Concerns\ResolvesCompetition;
use App\Models\Competition;
use App\Services\CompetitionRankingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla general (TV): ranking en vivo de los 12 primeros puestos por nivel/grupo (los empatados
 * comparten puesto; si hay empate en el 12 se muestran todos). Rota entre categorías y vistas
 * (all-around y cada ejercicio) o se fija con ?nivel=…&vista=….
 */
#[Layout('layouts.display')]
class RankingScreen extends Component
{
    use ResolvesCompetition;

    public const TOP = 12;

    #[Locked]
    public string $token;

    /** Categoría fija («nivel» o «nivel-grupo»); vacío = rota entre todas. */
    #[Url(as: 'nivel')]
    public string $category = '';

    /** «general» (all-around), id de ejercicio de la competencia, o vacío = rota entre todas. */
    #[Url(as: 'vista')]
    public string $show = '';

    /** Segundos entre pantallas al rotar. */
    #[Url(as: 'rotar')]
    public int $rotate = 20;

    /** Índice de la pantalla actual en la rotación. */
    public int $slide = 0;

    public function mount(string $token): void
    {
        $competition = Competition::acrossOrganizations()->where('display_token', $token)->firstOrFail();
        $this->token = $token;
        $this->competitionId = $competition->id;
        $this->useOrganizationOf($competition->id);
        $this->rotate = max(5, min(300, $this->rotate));
    }

    public function getListeners(): array
    {
        return [
            "echo:display.competition.{$this->token},EvaluationPublished" => '$refresh',
            "echo:display.competition.{$this->token},ScoreUpdated" => '$refresh',
        ];
    }

    public function next(): void
    {
        $this->slide++;
    }

    public function render(CompetitionRankingService $rankings)
    {
        $competition = Competition::with('exercises')->findOrFail($this->competitionId);

        $categories = $rankings->categories($competition)
            ->when($this->category !== '', fn ($c) => $c->where('key', $this->category))
            ->values();

        $views = collect([['key' => 'general', 'label' => 'All-around']])
            ->concat($competition->exercises->map(fn ($e) => ['key' => (string) $e->id, 'label' => $e->name()]))
            ->when($this->show !== '', fn ($v) => $v->where('key', $this->show))
            ->values();

        // Pantallas = categoría × vista; se recorren en orden.
        $slides = $categories->crossJoin($views)->values();
        $count = max(1, $slides->count());
        [$category, $panel] = $slides->get($this->slide % $count) ?? [null, null];

        $rows = collect();
        if ($category && $panel) {
            $rows = $panel['key'] === 'general'
                ? $rankings->allAround($competition, $category['level_id'], $category['group_id'], self::TOP)
                : $rankings->byExercise($competition, $competition->exercises->firstWhere('id', (int) $panel['key']), $category['level_id'], $category['group_id'], self::TOP);
        }

        return view('livewire.display.ranking-screen', [
            'competition' => $competition,
            'current' => $category,
            'panel' => $panel,
            'rows' => $rows,
            'rotating' => $count > 1,
            'slideNumber' => ($this->slide % $count) + 1,
            'slideCount' => $count,
        ])->title('Resultados · '.$competition->name);
    }
}
