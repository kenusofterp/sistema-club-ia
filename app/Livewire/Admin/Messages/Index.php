<?php

namespace App\Livewire\Admin\Messages;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\ContactMessage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Mensajes')]
class Index extends Component
{
    use InteractsWithUi, WithPagination;

    public bool $onlyUnread = false;

    public ?int $openId = null;

    public function open(int $id): void
    {
        $message = ContactMessage::findOrFail($id);
        $message->read_at ??= now();
        $message->save();
        $this->openId = $this->openId === $id ? null : $id;
    }

    public function markUnread(int $id): void
    {
        ContactMessage::whereKey($id)->update(['read_at' => null]);
        $this->openId = null;
    }

    public function delete(int $id): void
    {
        $this->authorize('mensajes.ver');
        ContactMessage::whereKey($id)->delete();
        $this->notify('Mensaje eliminado.');
    }

    public function render()
    {
        return view('livewire.admin.messages.index', [
            'messages' => ContactMessage::query()->when($this->onlyUnread, fn ($q) => $q->unread())->latest()->paginate(20),
        ]);
    }
}
