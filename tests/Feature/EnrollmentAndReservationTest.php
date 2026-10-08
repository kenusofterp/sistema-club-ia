<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\MemberStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Facility;
use App\Models\Fee;
use App\Models\Setting;
use App\Services\EnrollmentService;
use App\Services\ReservationService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnrollmentAndReservationTest extends TestCase
{
    // ---- Inscripciones ----

    public function test_enrollment_charges_current_month(): void
    {
        $member = $this->activeMember();
        $activity = Activity::factory()->create(['monthly_fee' => 7000]);

        $enrollment = app(EnrollmentService::class)->enroll($member, $activity);

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertDatabaseHas('fees', ['member_id' => $member->id, 'type' => FeeType::Activity->value, 'amount' => 7000]);
    }

    public function test_cannot_enroll_twice(): void
    {
        $member = $this->activeMember();
        $activity = Activity::factory()->create();
        app(EnrollmentService::class)->enroll($member, $activity);

        $this->expectException(BusinessRuleException::class);
        app(EnrollmentService::class)->enroll($member, $activity);
    }

    public function test_capacity_is_enforced(): void
    {
        $activity = Activity::factory()->create(['capacity' => 1]);
        app(EnrollmentService::class)->enroll($this->activeMember(), $activity);

        $this->expectExceptionMessage('cupos');
        app(EnrollmentService::class)->enroll($this->activeMember(), $activity);
    }

    public function test_age_range_is_enforced(): void
    {
        $activity = Activity::factory()->create(['min_age' => 5, 'max_age' => 12]);

        $this->expectException(BusinessRuleException::class);
        app(EnrollmentService::class)->enroll($this->activeMember(), $activity);
    }

    public function test_members_with_overdue_debt_cannot_enroll(): void
    {
        $member = $this->activeMember();
        Fee::factory()->overdue()->create(['member_id' => $member->id]);

        $this->expectExceptionMessage('cuotas vencidas');
        app(EnrollmentService::class)->enroll($member, Activity::factory()->create());
    }

    public function test_suspended_members_cannot_enroll(): void
    {
        $this->expectException(BusinessRuleException::class);
        app(EnrollmentService::class)->enroll($this->activeMember(['status' => MemberStatus::Suspended]), Activity::factory()->create());
    }

    public function test_unenroll_frees_a_spot(): void
    {
        $activity = Activity::factory()->create(['capacity' => 1]);
        $enrollment = app(EnrollmentService::class)->enroll($this->activeMember(), $activity);
        app(EnrollmentService::class)->unenroll($enrollment);

        $this->assertSame(EnrollmentStatus::Ended, $enrollment->fresh()->status);
        app(EnrollmentService::class)->enroll($this->activeMember(), $activity);
        $this->assertSame(1, $activity->activeEnrollments()->count());
    }

    // ---- Reservas ----

    private function tomorrowAt(string $time): array
    {
        return [today()->addDay(), $time];
    }

    public function test_booking_creates_reservation_and_charge(): void
    {
        $facility = Facility::factory()->create(['hourly_rate' => 8000, 'slot_minutes' => 60]);
        $member = $this->activeMember();
        [$date, $time] = $this->tomorrowAt('10:00');

        $reservation = app(ReservationService::class)->book($facility, $member, $date, $time, 2);

        $this->assertSame('12:00:00', $reservation->end_time);
        $this->assertSame('16000.00', (string) $reservation->amount);
        $this->assertDatabaseHas('fees', ['reservation_id' => $reservation->id, 'type' => FeeType::Reservation->value, 'amount' => 16000]);
    }

    public function test_overlapping_bookings_are_rejected(): void
    {
        $facility = Facility::factory()->create();
        [$date] = $this->tomorrowAt('10:00');
        app(ReservationService::class)->book($facility, $this->activeMember(), $date, '10:00', 2);

        $this->expectExceptionMessage('ya está reservado');
        app(ReservationService::class)->book($facility, $this->activeMember(), $date, '11:00');
    }

    public function test_adjacent_bookings_are_allowed(): void
    {
        $facility = Facility::factory()->create();
        [$date] = $this->tomorrowAt('10:00');
        app(ReservationService::class)->book($facility, $this->activeMember(), $date, '10:00');
        app(ReservationService::class)->book($facility, $this->activeMember(), $date, '11:00');

        $this->assertSame(2, $facility->reservations()->count());
    }

    public function test_booking_validates_hours_slots_and_past_dates(): void
    {
        $facility = Facility::factory()->create(['opens_at' => '08:00', 'closes_at' => '22:00', 'max_slots_per_booking' => 2]);
        $service = app(ReservationService::class);
        $member = $this->activeMember();

        foreach ([
            [today()->addDay(), '07:00', 1],  // antes de abrir
            [today()->addDay(), '10:30', 1],  // fuera de grilla
            [today()->addDay(), '21:00', 2],  // excede el cierre
            [today()->addDay(), '10:00', 3],  // demasiados turnos
            [today()->subDay(), '10:00', 1],  // fecha pasada
            [today()->addDays(60), '10:00', 1],  // demasiada anticipación
        ] as [$date, $time, $slots]) {
            try {
                $service->book($facility, $member, $date, $time, $slots);
                $this->fail("Debió rechazarse la reserva {$date->toDateString()} {$time} x{$slots}");
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_member_with_overdue_debt_cannot_book(): void
    {
        $member = $this->activeMember();
        Fee::factory()->overdue()->create(['member_id' => $member->id]);

        $this->expectException(BusinessRuleException::class);
        app(ReservationService::class)->book(Facility::factory()->create(), $member, today()->addDay(), '10:00');
    }

    public function test_member_cancellation_respects_minimum_notice_and_cancels_unpaid_fee(): void
    {
        Setting::set('club.reservation_cancel_hours', 24);
        $facility = Facility::factory()->create();
        $member = $this->activeMember();
        $service = app(ReservationService::class);

        Carbon::setTestNow(today()->setTime(9, 0));
        $soon = $service->book($facility, $member, today(), '20:00');
        try {
            $service->cancel($soon, byMember: true);
            $this->fail('No debería poder cancelar con menos de 24 h');
        } catch (BusinessRuleException) {
        }

        $later = $service->book($facility, $member, today()->addDays(3), '10:00');
        $service->cancel($later, 'No puedo ir', byMember: true);

        $this->assertSame(ReservationStatus::Cancelled, $later->fresh()->status);
        $this->assertSame(FeeStatus::Cancelled, $later->fee->fresh()->status);
        Carbon::setTestNow();
    }
}
