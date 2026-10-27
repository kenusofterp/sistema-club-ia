<?php

namespace App\Livewire\Admin\Competitions;

use App\Models\Competition;
use App\Services\CompetitionRankingService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Resultados por nivel/grupo: all-around y puesto en cada ejercicio. Exporta a CSV (Excel) e imprime/PDF. */
#[Layout('layouts.admin')]
class Results extends Component
{
    public Competition $competition;

    #[Url(as: 'nivel')]
    public string $category = '';

    public function mount(Competition $competition): void
    {
        $this->competition = $competition;
    }

    public function render(CompetitionRankingService $rankings)
    {
        $this->competition->load('exercises');
        $categories = $rankings->categories($this->competition);
        $selected = $categories->firstWhere('key', $this->category) ?? $categories->first();

        return view('livewire.admin.competitions.results', [
            'categories' => $categories,
            'selected' => $selected,
            'rows' => $selected ? $rankings->table($this->competition, $selected['level_id'], $selected['group_id']) : collect(),
        ])->title('Resultados · '.$this->competition->name);
    }
}
