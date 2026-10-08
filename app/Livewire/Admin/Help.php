<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Manual de uso del sistema, paso a paso desde una instalación vacía. Visible para todo el personal. */
#[Layout('layouts.admin')]
#[Title('Manual de uso')]
class Help extends Component
{
    public function render()
    {
        return view('livewire.admin.help');
    }
}
