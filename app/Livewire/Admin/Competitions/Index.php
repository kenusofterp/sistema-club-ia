<?php

namespace App\Livewire\Admin\Competitions;

use App\Enums\EvaluationStatus;
use App\Models\Competition;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Competencias')]
class Index extends Component
{
    public function render()
    {
        return view('livewire.admin.competitions.index', [
            'competitions' => Competition::query()
                ->withCount([
                    'entries',
                    'stations',
                    'evaluations as published_count' => fn ($q) => $q->where('status', EvaluationStatus::Published),
                    'evaluations',
                ])
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
