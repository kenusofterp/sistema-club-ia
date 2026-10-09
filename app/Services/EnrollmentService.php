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
    public function __construct(private FeeService $fees, private LevelLessonService $levelLessons) {}

    /**
     * @param  string|null  $feeAmount  cuota individual fija (descuento); null = la de la actividad
     * @param  string|null  $scholarshipPercent  beca en % sobre la cuota mensual (no aplica a torneos ni inscripción)
     */
    public function enroll(Member $member, Activity $activity, ?string $notes = null, ?string $feeAmount = null, ?string $scholarshipPercent = null): Enrollment
    {
        if ($scholarshipPercent !== null && (bccomp($scholarshipPercent, '0', 2) < 0 || bccomp($scholarshipPercent, '100', 2) > 0)) {
            throw new BusinessRuleException('La beca debe ser un porcentaje entre 0 y 100.');
        }

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

        $enrollment = DB::transaction(function () use ($member, $activity, $notes, $feeAmount, $scholarshipPercent) {
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
                'fee_amount' => $feeAmount,
                'scholarship_percent' => $scholarshipPercent ?: null,
                'notes' => $notes,
            ]);
        });

        // Se cobra el mes en curso al inscribirse (idempotente si ya fue generado).
        if (setting('club.charge_activity_on_enroll', true)) {
            $this->fees->generateActivityFee($member, $activity, today());
        }

        // Inscripción anual del nivel (si tiene costo), una vez por año.
        $this->fees->generateRegistrationFee($member, $activity, today()->year);

        $this->levelLessons->syncEnrollment($member, $activity, true);

        return $enrollment;
    }

    /**
     * Cuota individual fija y/o beca en %. Se aplica desde la próxima cuota que se genere;
     * las ya generadas no cambian.
     */
    public function updateFee(Enrollment $enrollment, ?string $feeAmount, ?string $scholarshipPercent): void
    {
        if ($scholarshipPercent !== null && (bccomp($scholarshipPercent, '0', 2) < 0 || bccomp($scholarshipPercent, '100', 2) > 0)) {
            throw new BusinessRuleException('La beca debe ser un porcentaje entre 0 y 100.');
        }

        $enrollment->update([
            'fee_amount' => $feeAmount,
            'scholarship_percent' => $scholarshipPercent !== null && bccomp($scholarshipPercent, '0', 2) > 0 ? $scholarshipPercent : null,
        ]);
    }

    /**
     * Cobra la inscripción anual del nivel a todos sus inscriptos activos (reinscripción de un año nuevo).
     *
     * @return int cantidad de cargos creados
     */
    public function chargeRegistration(Activity $activity, int $year): int
    {
        if (bccomp((string) $activity->enrollment_fee, '0', 2) <= 0) {
            throw new BusinessRuleException("{$activity->name} no tiene costo de inscripción anual.");
        }

        $created = 0;
        $activity->activeEnrollments()->with('member')->get()
            ->each(function (Enrollment $enrollment) use ($activity, $year, &$created) {
                if ($enrollment->member?->isActive()) {
                    $created += (int) $this->fees->generateRegistrationFee($enrollment->member, $activity, $year);
                }
            });

        return $created;
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

        $this->levelLessons->syncEnrollment($enrollment->member, $enrollment->activity, false);
    }
}
