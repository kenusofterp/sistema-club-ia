<?php

namespace App\Livewire\Admin\Settlements;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SettlementStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\CashSettlement;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Services\CashCollectionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Rendiciones de efectivo de los profesores: confirmar lo recibido y ver quién tiene efectivo sin rendir. */
#[Layout('layouts.admin')]
#[Title('Rendiciones')]
class Index extends Component
{
    use InteractsWithUi;

    public ?int $detailId = null;

    public bool $showDetail = false;

    public function show(int $id): void
    {
        $this->detailId = CashSettlement::findOrFail($id)->id;
        $this->showDetail = true;
    }

    public function confirm(int $id, CashCollectionService $cash): void
    {
        $this->authorize('rendiciones.gestionar');
        if ($this->attempt(fn () => $cash->confirm(CashSettlement::findOrFail($id), auth()->user()), 'Rendición confirmada.')) {
            $this->showDetail = false;
        }
    }

    public function revert(int $id, CashCollectionService $cash): void
    {
        $this->authorize('rendiciones.gestionar');
        if ($this->attempt(fn () => $cash->revert(CashSettlement::findOrFail($id)), 'Rendición devuelta: los cobros vuelven a quedar a cargo del profesor.')) {
            $this->showDetail = false;
        }
    }

    public function render()
    {
        // Efectivo cobrado por el personal y todavía sin rendir, agrupado por quien lo tiene.
        $inHand = Payment::query()
            ->where('method', PaymentMethod::Cash)
            ->where('status', PaymentStatus::Confirmed)
            ->whereNull('settlement_id')
            ->whereNull('instructor_id')
            ->whereIn('received_by', User::withPermissionIn('cobros.niveles', Organization::currentId())->pluck('id'))
            ->selectRaw('received_by, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('received_by')
            ->get()
            ->map(fn ($row) => ['user' => User::withTrashed()->find($row->received_by), 'total' => (string) $row->total, 'count' => (int) $row->count]);

        return view('livewire.admin.settlements.index', [
            'pending' => CashSettlement::with('user')->where('status', SettlementStatus::Pending)->latest('id')->get(),
            'confirmed' => CashSettlement::with(['user', 'confirmer'])->where('status', SettlementStatus::Confirmed)->latest('confirmed_at')->limit(30)->get(),
            'inHand' => $inHand,
            'detail' => $this->showDetail && $this->detailId
                ? CashSettlement::with(['user', 'payments.member' => fn ($q) => $q->withTrashed()])->find($this->detailId)
                : null,
            'requiresSettlement' => app(CashCollectionService::class)->requiresSettlement(),
            'selfConfirm' => app(CashCollectionService::class)->selfConfirmAllowed() || auth()->user()->isSuperAdmin(),
        ]);
    }
}
