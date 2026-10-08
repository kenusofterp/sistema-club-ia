<?php

namespace App\Livewire\Portal;

use App\Enums\ReservationStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Portal\Concerns\ForCurrentMember;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Reservas')]
class Reservations extends Component
{
    use ForCurrentMember, InteractsWithUi;

    public ?int $facilityId = null;

    public string $date = '';

    public int $slotCount = 1;

    public function mount(): void
    {
        $this->facilityId = Facility::bookable()->value('id');
        $this->date = today()->toDateString();
    }

    public function book(string $start, ReservationService $service): void
    {
        $this->validate([
            'facilityId' => 'required|exists:facilities,id',
            'date' => 'required|date|after_or_equal:today',
            'slotCount' => 'required|integer|min:1|max:12',
        ]);

        $facility = Facility::bookable()->findOrFail($this->facilityId);

        $this->attempt(
            fn () => $service->book($facility, $this->member(), Carbon::parse($this->date), $start, $this->slotCount, auth()->user()),
            "Reserva confirmada: {$facility->name}, ".Carbon::parse($this->date)->format('d/m')." a las {$start}."
        );
    }

    public function cancel(int $id, ReservationService $service): void
    {
        $reservation = $this->member()->reservations()->findOrFail($id);
        $this->attempt(fn () => $service->cancel($reservation, 'Cancelada por el socio', byMember: true), 'Reserva cancelada.');
    }

    public function render()
    {
        $facilities = Facility::bookable()->get();
        $facility = $facilities->firstWhere('id', $this->facilityId);
        $date = Carbon::parse($this->date ?: today());

        $taken = $facility
            ? Reservation::where('facility_id', $facility->id)->where('status', ReservationStatus::Confirmed)->whereDate('date', $date)->get(['start_time', 'end_time'])
            : collect();

        $slots = collect($facility?->slots() ?? [])->map(function ($slot) use ($taken, $date) {
            $busy = $taken->contains(fn ($r) => substr($r->start_time, 0, 5) < $slot['end'] && substr($r->end_time, 0, 5) > $slot['start']);
            $past = Carbon::parse($date->format('Y-m-d').' '.$slot['start'])->isPast();

            return [...$slot, 'available' => ! $busy && ! $past];
        });

        return view('livewire.portal.reservations', [
            'facilities' => $facilities,
            'facility' => $facility,
            'slotList' => $slots,
            'mine' => $this->member()->reservations()->confirmed()->upcoming()->with('facility')->get(),
            'maxDate' => today()->addDays((int) setting('club.reservation_max_days_ahead', 30))->toDateString(),
        ]);
    }
}
