<?php

namespace App\Livewire\Admin\Reservations;

use App\Enums\ReservationStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Facility;
use App\Models\Member;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Reservas')]
class Index extends Component
{
    use InteractsWithUi;

    #[Url(as: 'fecha')]
    public string $date = '';

    public bool $showForm = false;

    public ?int $facilityId = null;

    public string $startTime = '';

    public int $slotCount = 1;

    public string $memberSearch = '';

    public ?int $memberId = null;

    public string $notes = '';

    public function mount(): void
    {
        $this->date = $this->date ?: today()->toDateString();
    }

    public function shiftDate(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
    }

    public function openForm(?int $facilityId = null, ?string $start = null): void
    {
        $this->authorize('reservas.gestionar');
        $this->reset(['memberSearch', 'memberId', 'notes', 'slotCount']);
        $this->facilityId = $facilityId;
        $this->startTime = (string) $start;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function updatedMemberSearch(): void
    {
        $this->memberId = null;
    }

    public function selectMember(int $id): void
    {
        $member = Member::findOrFail($id);
        $this->memberId = $member->id;
        $this->memberSearch = $member->fullName();
    }

    public function book(ReservationService $service): void
    {
        $this->authorize('reservas.gestionar');
        $this->validate([
            'facilityId' => 'required|exists:facilities,id',
            'memberId' => 'required|exists:members,id',
            'date' => 'required|date',
            'startTime' => 'required|date_format:H:i',
            'slotCount' => 'required|integer|min:1|max:12',
            'notes' => 'nullable|string|max:250',
        ], [], ['facilityId' => 'instalación', 'memberId' => 'socio', 'startTime' => 'horario', 'slotCount' => 'turnos']);

        $done = $this->attempt(fn () => $service->book(
            Facility::findOrFail($this->facilityId),
            Member::findOrFail($this->memberId),
            Carbon::parse($this->date),
            $this->startTime,
            $this->slotCount,
            auth()->user(),
            $this->notes ?: null,
        ), 'Reserva confirmada.');

        if ($done) {
            $this->showForm = false;
        }
    }

    public function cancel(int $id, ReservationService $service): void
    {
        $this->authorize('reservas.gestionar');
        $this->attempt(fn () => $service->cancel(Reservation::findOrFail($id), 'Cancelada por administración'), 'Reserva cancelada.');
    }

    public function render()
    {
        $facilities = Facility::bookable()->get();
        $reservations = Reservation::with('member')
            ->where('status', ReservationStatus::Confirmed)
            ->whereDate('date', $this->date)
            ->get()
            ->groupBy('facility_id');

        $formFacility = $this->facilityId ? $facilities->firstWhere('id', $this->facilityId) : null;

        return view('livewire.admin.reservations.index', [
            'facilities' => $facilities,
            'reservations' => $reservations,
            'day' => Carbon::parse($this->date),
            'formSlots' => $formFacility?->slots() ?? [],
            'formFacility' => $formFacility,
            'memberResults' => ! $this->memberId && strlen($this->memberSearch) >= 2 ? Member::active()->search($this->memberSearch)->limit(6)->get() : collect(),
        ]);
    }
}
