<?php

namespace App\Livewire\Admin\ExtraCharges;

use App\Enums\FeeStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\ExtraCharge;
use App\Models\Member;
use App\Services\ExtraChargeService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Quién pagó, quién debe y a quién se le anuló (ej. "la consiguió por otro lado"). */
#[Layout('layouts.admin')]
class Show extends Component
{
    use InteractsWithUi;

    public ExtraCharge $extraCharge;

    // ---- Anular a un alumno ----
    public bool $showCancel = false;

    public ?int $cancelMemberId = null;

    public string $cancelReason = '';

    // ---- Editar ----
    public bool $showEdit = false;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $due_date = '';

    // ---- Agregar alumnos ----
    public bool $showAdd = false;

    /** @var array<int, string> */
    public array $toAdd = [];

    public function mount(ExtraCharge $extraCharge): void
    {
        abort_unless(app(ExtraChargeService::class)->queryFor(auth()->user())->whereKey($extraCharge->id)->exists(), 404);
        $this->extraCharge = $extraCharge;
    }

    public function askCancel(int $memberId): void
    {
        $this->cancelMemberId = $memberId;
        $this->cancelReason = 'La consiguió por otro lado';
        $this->showCancel = true;
        $this->resetValidation();
    }

    public function confirmCancel(ExtraChargeService $service): void
    {
        $this->authorize('articulos.gestionar');
        $this->validate(['cancelReason' => 'required|string|min:3|max:250'], [], ['cancelReason' => 'motivo']);

        $member = Member::withTrashed()->findOrFail($this->cancelMemberId);
        if ($this->attempt(fn () => $service->cancelFor($this->extraCharge, $member, $this->cancelReason), "Se le anuló {$this->extraCharge->name} a {$member->fullName()}.")) {
            $this->showCancel = false;
        }
    }

    public function openEdit(): void
    {
        $this->name = $this->extraCharge->name;
        $this->description = (string) $this->extraCharge->description;
        $this->price = (string) $this->extraCharge->price;
        $this->due_date = $this->extraCharge->due_date->toDateString();
        $this->resetValidation();
        $this->showEdit = true;
    }

    public function saveEdit(ExtraChargeService $service): void
    {
        $this->authorize('articulos.gestionar');
        $data = $this->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0.01|max:99999999',
            'due_date' => 'required|date',
        ], [], ['name' => 'nombre', 'price' => 'precio', 'due_date' => 'vencimiento']);

        $done = $this->attempt(fn () => $service->update($this->extraCharge, [...$data, 'price' => number_format((float) $data['price'], 2, '.', '')]),
            'Guardado. Se actualizaron los cargos de quienes todavía no pagaron.');
        if ($done) {
            $this->showEdit = false;
        }
    }

    public function openAdd(): void
    {
        $this->toAdd = [];
        $this->showAdd = true;
    }

    public function addMembers(ExtraChargeService $service): void
    {
        $this->authorize('articulos.gestionar');
        $members = $this->candidates()->whereIn('id', array_map('intval', $this->toAdd));
        $created = $this->attempt(fn () => $service->addMembers($this->extraCharge, $members));

        if ($created !== null) {
            $this->showAdd = false;
            $this->notify($created ? "Se le cargó a {$created} alumnos más." : 'No se agregó a nadie.');
        }
    }

    /** Candidatos que todavía no lo tienen activo. */
    private function candidates()
    {
        $service = app(ExtraChargeService::class);
        $active = $this->extraCharge->fees()->where('status', '!=', FeeStatus::Cancelled)->pluck('member_id');

        return $service->candidatesQuery(auth()->user(), $this->extraCharge->tournament, $this->extraCharge->activity)
            ->whereNotIn('members.id', $active)
            ->orderBy('last_name')->orderBy('first_name')
            ->get();
    }

    public function render(ExtraChargeService $service)
    {
        $this->extraCharge->load(['tournament', 'activity']);

        // La fila de cada alumno: su último cargo (si se anuló y se volvió a cargar, se muestra el vigente).
        $rows = $this->extraCharge->fees()
            ->with(['member' => fn ($q) => $q->withTrashed()])
            ->orderByRaw("status = 'anulada'")
            ->latest('id')
            ->get()
            ->unique('member_id')
            ->sortBy(fn ($fee) => [$fee->status === FeeStatus::Cancelled ? 1 : 0, $fee->member?->sortableName()])
            ->values();

        return view('livewire.admin.extra-charges.show', [
            'rows' => $rows,
            'summary' => $service->summary($this->extraCharge),
            'addCandidates' => $this->showAdd ? $this->candidates() : collect(),
        ])->title($this->extraCharge->name);
    }
}
