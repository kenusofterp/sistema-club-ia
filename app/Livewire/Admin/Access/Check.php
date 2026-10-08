<?php

namespace App\Livewire\Admin\Access;

use App\Models\AccessLog;
use App\Services\AccessService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Control de acceso')]
class Check extends Component
{
    public string $code = '';

    public ?int $lastLogId = null;

    public bool $notFound = false;

    public function check(AccessService $access): void
    {
        $this->authorize('acceso.registrar');
        $this->validate(['code' => 'required|string|max:200'], [], ['code' => 'código']);

        $member = $access->findMember($this->code);
        $this->notFound = $member === null;
        $this->lastLogId = $member ? $access->register($member, auth()->user())->id : null;
        $this->code = '';
        $this->dispatch('access-checked');
    }

    public function render()
    {
        return view('livewire.admin.access.check', [
            'last' => $this->lastLogId ? AccessLog::with(['member.category'])->find($this->lastLogId) : null,
            'recent' => AccessLog::with('member')->latest('checked_at')->limit(12)->get(),
            'todayCount' => AccessLog::whereDate('checked_at', today())->where('result', 'permitido')->count(),
        ]);
    }
}
