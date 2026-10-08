<?php

namespace App\Services;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Facility;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(private FeeService $fees) {}

    /**
     * Reserva una instalación por una cantidad de turnos consecutivos.
     */
    public function book(Facility $facility, Member $member, Carbon $date, string $startTime, int $slots = 1, ?User $by = null, ?string $notes = null): Reservation
    {
        if (! $facility->is_active || ! $facility->is_bookable) {
            throw new BusinessRuleException('La instalación no admite reservas.');
        }

        if (! $member->isActive()) {
            throw new BusinessRuleException('Solo los socios activos pueden reservar.');
        }

        if (setting('club.reservation_requires_no_debt', true) && $member->overdueFeesCount() > 0) {
            throw new BusinessRuleException('El socio tiene cuotas vencidas y no puede reservar.');
        }

        if ($slots < 1 || $slots > $facility->max_slots_per_booking) {
            throw new BusinessRuleException("Se pueden reservar entre 1 y {$facility->max_slots_per_booking} turnos consecutivos.");
        }

        $maxDays = (int) setting('club.reservation_max_days_ahead', 30);
        if ($date->copy()->startOfDay()->gt(today()->addDays($maxDays))) {
            throw new BusinessRuleException("Solo se puede reservar con hasta {$maxDays} días de anticipación.");
        }

        $start = Carbon::parse($date->format('Y-m-d').' '.$startTime);
        $end = $start->copy()->addMinutes($facility->slot_minutes * $slots);

        if ($start->lt(now())) {
            throw new BusinessRuleException('No se puede reservar en un horario pasado.');
        }

        $validStarts = array_column($facility->slots(), 'start');
        if (! in_array($start->format('H:i'), $validStarts, true)) {
            throw new BusinessRuleException('El horario elegido no corresponde a un turno de la instalación.');
        }

        $closing = Carbon::parse($date->format('Y-m-d').' '.substr($facility->closes_at, 0, 5));
        if ($end->gt($closing)) {
            throw new BusinessRuleException('La reserva excede el horario de cierre de la instalación.');
        }

        $hours = bcdiv((string) ($facility->slot_minutes * $slots), '60', 4);
        $amount = bcmul((string) $facility->hourly_rate, $hours, 2);

        return DB::transaction(function () use ($facility, $member, $date, $start, $end, $amount, $by, $notes) {
            // Serializa las reservas de la misma instalación para evitar superposiciones.
            Facility::query()->whereKey($facility->id)->lockForUpdate()->first();

            $overlaps = Reservation::query()
                ->where('facility_id', $facility->id)
                ->where('status', ReservationStatus::Confirmed)
                ->whereDate('date', $date)
                ->where('start_time', '<', $end->format('H:i:s'))
                ->where('end_time', '>', $start->format('H:i:s'))
                ->exists();

            if ($overlaps) {
                throw new BusinessRuleException('El horario ya está reservado.');
            }

            $reservation = Reservation::create([
                'facility_id' => $facility->id,
                'member_id' => $member->id,
                'date' => $date->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'status' => ReservationStatus::Confirmed,
                'amount' => $amount,
                'notes' => $notes,
                'created_by' => $by?->id,
            ]);

            if (bccomp($amount, '0', 2) > 0) {
                $this->fees->createCharge(
                    $member,
                    FeeType::Reservation,
                    "Reserva {$facility->name} {$date->format('d/m/Y')} {$reservation->timeRange()}",
                    $amount,
                    $date->copy(),
                    $reservation->id,
                );
            }

            return $reservation;
        });
    }

    /**
     * Cancela una reserva. Si la cancela el propio socio, se respeta la anticipación mínima configurada.
     */
    public function cancel(Reservation $reservation, ?string $reason = null, bool $byMember = false): void
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            throw new BusinessRuleException('La reserva ya está cancelada.');
        }

        if ($byMember) {
            $hours = (int) setting('club.reservation_cancel_hours', 24);
            if (now()->diffInHours($reservation->startsAt(), false) < $hours) {
                throw new BusinessRuleException("Las reservas se pueden cancelar con al menos {$hours} horas de anticipación.");
            }
        }

        DB::transaction(function () use ($reservation, $reason) {
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $fee = $reservation->fee;
            if ($fee && $fee->status !== FeeStatus::Cancelled && bccomp((string) $fee->paid_amount, '0', 2) === 0) {
                $this->fees->cancel($fee, 'Reserva cancelada');
            }
        });
    }
}
