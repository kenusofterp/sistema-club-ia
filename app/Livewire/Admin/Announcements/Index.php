<?php

namespace App\Livewire\Admin\Announcements;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Announcement;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Avisos a socios')]
class Index extends Component
{
    use InteractsWithUi;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $body = '';

    public string $level = 'info';

    public string $published_at = '';

    public string $expires_at = '';

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'body', 'level', 'expires_at']);
        $this->published_at = now()->format('Y-m-d\TH:i');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $a = Announcement::findOrFail($id);
        $this->editingId = $a->id;
        $this->title = $a->title;
        $this->body = $a->body;
        $this->level = $a->level;
        $this->published_at = (string) $a->published_at?->format('Y-m-d\TH:i');
        $this->expires_at = (string) $a->expires_at?->format('Y-m-d\TH:i');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('avisos.gestionar');
        $data = $this->validate([
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:5000',
            'level' => ['required', Rule::in(array_keys(Announcement::LEVELS))],
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        Announcement::updateOrCreate(['id' => $this->editingId], [
            ...$data,
            'published_at' => $data['published_at'] ?: null,
            'expires_at' => $data['expires_at'] ?: null,
            'created_by' => $this->editingId ? Announcement::find($this->editingId)?->created_by : auth()->id(),
        ]);

        $this->showForm = false;
        $this->notify('Aviso guardado.');
    }

    public function delete(int $id): void
    {
        $this->authorize('avisos.gestionar');
        Announcement::whereKey($id)->first()?->delete();
        $this->notify('Aviso eliminado.');
    }

    public function render()
    {
        return view('livewire.admin.announcements.index', ['announcements' => Announcement::latest('published_at')->latest('id')->get()]);
    }
}
