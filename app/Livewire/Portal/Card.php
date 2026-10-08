<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ForCurrentMember;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
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

        $qr = (new QRCode(new QROptions([
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'addQuietzone' => true,
        ])))->render($member->verificationUrl());

        return view('livewire.portal.card', [
            'member' => $member,
            'qr' => $qr,
            'overdue' => $member->overdueFeesCount(),
        ]);
    }
}
