<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ForCurrentMember;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Mensajes que el profesor o la administración le mandó al alumno. Al verlos quedan leídos. */
#[Layout('layouts.portal')]
#[Title('Mensajes')]
class Messages extends Component
{
    use ForCurrentMember;

    public function render()
    {
        $member = $this->member();
        $messages = $member->messages()->with('sender')->latest('member_messages.id')->limit(50)->get();

        $unread = $messages->whereNull('pivot.read_at')->pluck('id');
        if ($unread->isNotEmpty()) {
            $member->messages()->updateExistingPivot($unread->all(), ['read_at' => now()]);
        }

        return view('livewire.portal.messages', ['messages' => $messages, 'unread' => $unread->all()]);
    }
}
