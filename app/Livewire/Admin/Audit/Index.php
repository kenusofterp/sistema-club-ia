<?php

namespace App\Livewire\Admin\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Auditoría')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $log = '';

    #[Url]
    public string $event = '';

    #[Url]
    public string $user = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $audits = AuditLog::query()
            ->with('causer')
            ->when($this->log, fn ($q) => $q->where('log_name', $this->log))
            ->when($this->event, fn ($q) => $q->where('event', $this->event))
            ->when($this->user, fn ($q) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $this->user))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->search, fn ($q) => $q->where('description', 'ilike', '%'.$this->search.'%'))
            ->latest('id')
            ->paginate(30);

        return view('livewire.admin.audit.index', [
            'audits' => $audits,
            'logs' => AuditLog::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter(),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event')->filter(),
            'users' => User::where(fn ($q) => $q->whereHas('roles')->orWhere('is_super_admin', true))->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
