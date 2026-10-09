<?php

namespace App\Livewire\Admin\Lessons;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\CashSettlement;
use App\Services\CashCollectionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Efectivo que el profesor tiene en su poder y sus rendiciones. */
#[Layout('layouts.admin')]
#[Title('Efectivo a rendir')]
class Cash extends Component
{
    use InteractsWithUi;

    public string $notes = '';

    public function mount(CashCollectionService $cash): void
    {
        abort_unless($cash->requiresSettlement(), 404);
    }

    public function settle(CashCollectionService $cash): void
    {
        $this->validate(['notes' => 'nullable|string|max:500']);

        $settlement = $this->attempt(fn () => $cash->settle(auth()->user(), $this->notes ?: null));
        if ($settlement) {
            $this->notes = '';
            $this->notify('Rendición enviada por '.money($settlement->amount).'. Queda por confirmar cuando entregues el dinero.');
        }
    }

    /** Autorrendición: el mismo profesor confirma su rendición pendiente (si la configuración lo permite). */
    public function confirmOwn(int $id, CashCollectionService $cash): void
    {
        $settlement = CashSettlement::where('user_id', auth()->id())->findOrFail($id);

        $this->attempt(fn () => $cash->confirm($settlement, auth()->user()), 'Rendición confirmada.');
    }

    public function render(CashCollectionService $cash)
    {
        $user = auth()->user();

        return view('livewire.admin.lessons.cash', [
            'selfConfirm' => $cash->selfConfirmAllowed(),
            'pending' => $cash->pendingQuery($user)->with(['member' => fn ($q) => $q->withTrashed()])->latest('payment_date')->latest('id')->get(),
            'total' => $cash->pendingTotal($user),
            'settlements' => CashSettlement::where('user_id', $user->id)->latest('id')->limit(15)->get(),
        ]);
    }
}
