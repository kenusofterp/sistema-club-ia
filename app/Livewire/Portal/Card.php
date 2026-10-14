<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ForCurrentMember;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Carnet digital')]
class Card extends Component
{
    use ForCurrentMember;

    public function render()
    {
        $member = $this->member()->load('category');

        return view('livewire.portal.card', [
            'member' => $member,
            'qr' => $member->qrSvg(),
            'overdue' => $member->overdueFeesCount(),
        ]);
    }
}
