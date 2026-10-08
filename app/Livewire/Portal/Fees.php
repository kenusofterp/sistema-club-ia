<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ForCurrentMember;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Mi cuenta')]
class Fees extends Component
{
    use ForCurrentMember;

    public string $tab = 'pendientes';

    public function render()
    {
        $member = $this->member();

        return view('livewire.portal.fees', [
            'balance' => $member->balance(),
            'openFees' => $member->openFees()->orderBy('due_date')->get(),
            'history' => $member->fees()->whereNotIn('status', ['pendiente', 'parcial', 'vencida'])->latest('due_date')->limit(36)->get(),
            'payments' => $member->payments()->latest('payment_date')->latest('id')->limit(24)->get(),
        ]);
    }
}
