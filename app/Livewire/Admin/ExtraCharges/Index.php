<?php

namespace App\Livewire\Admin\ExtraCharges;

use App\Enums\FeeStatus;
use App\Services\ExtraChargeService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Vestimenta y artículos')]
class Index extends Component
{
    public string $search = '';

    public function render(ExtraChargeService $service)
    {
        $charges = $service->queryFor(auth()->user())
            ->with(['tournament', 'activity'])
            ->withCount(['fees as charged_count' => fn ($q) => $q->where('status', '!=', FeeStatus::Cancelled)])
            ->withCount(['fees as debtors_count' => fn ($q) => $q->whereIn('status', FeeStatus::open())])
            ->withSum(['fees as paid_total' => fn ($q) => $q->where('status', '!=', FeeStatus::Cancelled)], 'paid_amount')
            ->when($this->search, fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
            ->latest('id')
            ->limit(100)
            ->get();

        return view('livewire.admin.extra-charges.index', ['charges' => $charges]);
    }
}
