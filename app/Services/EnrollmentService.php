<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    public function __construct(private FeeService $fees) {}

    public function enroll(Member $member, Activity $activity, ?string $notes = null): Enrollment
    {
        if (! $member->isActive()) {
            throw new BusinessRuleException('Solo los socios activos pueden inscribirse en actividades.');
        }

        if (! $activity->is_active) {
            throw new BusinessRuleException('La actividad no está disponible.');
        }

        if (! $activity->allows_enrollment) {
            throw new BusinessRuleException("A {$activity->name} se accede contratando un plan que la incluya.");
        }

        if (! $activity->acceptsAge($member->age())) {
            throw new BusinessRuleException("La edad del socio ({$member->age()} años) no corresponde al rango de la actividad.");
        }

        if (setting('club.enrollment_requires_no_debt', true) && $member->overdueFeesCount() > 0) {
            throw new BusinessRuleException('El socio tiene cuotas vencidas. Debe regularizar su deuda para inscribirse.');
        }

        $enrollment = DB::transaction(function () use ($member, $activity, $notes) {
            // Bloqueo de la actividad para controlar el cupo de forma concurrente.
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);

            $alreadyEnrolled = $member->enrollments()
                ->where('activity_id', $activity->id)
                ->where('status', EnrollmentStatus::Active)
                ->exists();

            if ($alreadyEnrolled) {
                throw new BusinessRuleException('El socio ya está inscripto en esta actividad.');
            }

            if ($activity->capacity !== null && $activity->activeEnrollments()->count() >= $activity->capacity) {
                throw new BusinessRuleException('La actividad no tiene cupos disponibles.');
            }

            return Enrollment::create([
                'member_id' => $member->id,
                'activity_id' => $activity->id,
                'status' => EnrollmentStatus::Active,
                'start_date' => today(),
                'notes' => $notes,
            ]);
        });

        // Se cobra el mes en curso al inscribirse (idempotente si ya fue generado).
        if (setting('club.charge_activity_on_enroll', true)) {
            $this->fees->generateActivityFee($member, $activity, today());
        }

        return $enrollment;
    }

    public function unenroll(Enrollment $enrollment, ?string $notes = null): void
    {
        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw new BusinessRuleException('La inscripción ya fue dada de baja.');
        }

        $enrollment->update([
            'status' => EnrollmentStatus::Ended,
            'end_date' => today(),
            'notes' => $notes ?? $enrollment->notes,
        ]);
    }
}
