<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\LessonStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Facility;
use App\Models\Fee;
use App\Models\Lesson;
use App\Models\LessonSeries;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Reservation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agenda de clases de los profesores. Una clase pertenece a la entidad de su sede; el profesor
 * puede dar clases en varias entidades y no puede tener dos clases superpuestas en ninguna.
 */
class LessonService
{
    /** Máximo de semanas que abarca una serie. */
    public const MAX_SERIES_WEEKS = 53;

    public function __construct(private FeeService $fees, private MemberService $members) {}

    /**
     * Programa una clase única.
     *
     * @param  array<int>  $memberIds  socios (de cualquier entidad) que toman la clase
     */
    public function schedule(
        User $instructor,
        Facility $facility,
        Carbon $date,
        string $startTime,
        string $endTime,
        array $memberIds = [],
        ?string $price = null,
        ?string $notes = null,
        ?User $by = null,
    ): Lesson {
        [$start, $end] = $this->validateSlot($instructor, $facility, $startTime, $endTime, $price);

        return Organization::runFor($facility->organization_id, fn () => DB::transaction(function () use ($instructor, $facility, $date, $start, $end, $memberIds, $price, $notes, $by) {
            $this->lockInstructor($instructor);
            $this->assertInstructorIsFree($instructor, $date->toDateString(), $start, $end);

            $lesson = Lesson::create([
                'facility_id' => $facility->id,
                'instructor_id' => $instructor->id,
                'date' => $date->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'status' => LessonStatus::Scheduled,
                'price' => $price,
                'notes' => $notes,
                'created_by' => $by?->id,
            ]);

            foreach ($memberIds as $memberId) {
                $this->attachStudent($lesson, $memberId);
            }

            return $lesson;
        }));
    }

    /**
     * Programa una clase que se repite todas las semanas en los días indicados (1 = lunes … 7 = domingo).
     * Si alguna fecha choca con otra clase del profesor, no se crea ninguna.
     *
     * @param  array<int>  $daysOfWeek
     * @param  array<int>  $memberIds
     */
    public function scheduleSeries(
        User $instructor,
        Facility $facility,
        array $daysOfWeek,
        string $startTime,
        string $endTime,
        Carbon $from,
        Carbon $until,
        array $memberIds = [],
        ?string $price = null,
        ?string $notes = null,
        ?User $by = null,
    ): LessonSeries {
        [$start, $end] = $this->validateSlot($instructor, $facility, $startTime, $endTime, $price);

        $daysOfWeek = array_values(array_unique(array_map('intval', $daysOfWeek)));
        if ($daysOfWeek === [] || array_diff($daysOfWeek, range(1, 7)) !== []) {
            throw new BusinessRuleException('Elegí al menos un día de la semana.');
        }
        if ($until->lt($from)) {
            throw new BusinessRuleException('La fecha de fin debe ser posterior a la de inicio.');
        }
        if ($from->diffInWeeks($until) > self::MAX_SERIES_WEEKS) {
            throw new BusinessRuleException('Una serie puede abarcar como máximo un año. Al terminar se puede crear otra.');
        }

        $dates = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            if (in_array($day->dayOfWeekIso, $daysOfWeek, true)) {
                $dates[] = $day->toDateString();
            }
        }
        if ($dates === []) {
            throw new BusinessRuleException('No hay ninguna fecha en ese rango para los días elegidos.');
        }

        return Organization::runFor($facility->organization_id, fn () => DB::transaction(function () use ($instructor, $facility, $daysOfWeek, $start, $end, $from, $until, $dates, $memberIds, $price, $notes, $by) {
            $this->lockInstructor($instructor);

            $conflicts = collect($dates)->filter(fn ($date) => $this->instructorConflict($instructor, $date, $start, $end));
            if ($conflicts->isNotEmpty()) {
                $list = $conflicts->take(5)->map(fn ($d) => Carbon::parse($d)->format('d/m'))->implode(', ');
                throw new BusinessRuleException("El profesor ya tiene clases en ese horario: {$list}".($conflicts->count() > 5 ? ' y otras.' : '.'));
            }

            $series = LessonSeries::create([
                'facility_id' => $facility->id,
                'instructor_id' => $instructor->id,
                'days_of_week' => $daysOfWeek,
                'start_time' => $start,
                'end_time' => $end,
                'starts_on' => $from->toDateString(),
                'ends_on' => $until->toDateString(),
                'price' => $price,
                'notes' => $notes,
                'created_by' => $by?->id,
            ]);

            $students = collect($memberIds)->map(fn ($id) => $this->resolveStudent($id))->unique('id');
            $series->students()->sync($students->pluck('id'));

            foreach ($dates as $date) {
                $lesson = $series->lessons()->create([
                    'organization_id' => $series->organization_id,
                    'facility_id' => $facility->id,
                    'instructor_id' => $instructor->id,
                    'date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => LessonStatus::Scheduled,
                    'price' => $price,
                    'notes' => $notes,
                    'created_by' => $by?->id,
                ]);
                $lesson->students()->attach($students->pluck('id'), ['attendance' => AttendanceStatus::Pending->value]);
            }

            return $series;
        }));
    }

    /** Cambia fecha, horario, precio o notas de una clase programada. */
    public function reschedule(Lesson $lesson, Carbon $date, string $startTime, string $endTime, ?string $price = null, ?string $notes = null): Lesson
    {
        $this->assertScheduled($lesson);
        [$start, $end] = $this->validateSlot($lesson->instructor, $lesson->facility, $startTime, $endTime, $price);

        return DB::transaction(function () use ($lesson, $date, $start, $end, $price, $notes) {
            $this->lockInstructor($lesson->instructor);
            $this->assertInstructorIsFree($lesson->instructor, $date->toDateString(), $start, $end, $lesson->id);

            $lesson->update([
                'date' => $date->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'price' => $price,
                'notes' => $notes,
            ]);

            return $lesson;
        });
    }

    /** Suma un alumno a una clase programada. Acepta un socio de cualquier entidad (se usa su persona). */
    public function addStudent(Lesson $lesson, int $memberId): Member
    {
        $this->assertScheduled($lesson);

        return Organization::runFor($lesson->organization_id, fn () => $this->attachStudent($lesson, $memberId));
    }

    public function removeStudent(Lesson $lesson, int $memberId): void
    {
        $this->assertScheduled($lesson);
        $lesson->students()->detach($memberId);
    }

    /**
     * Registra la clase como dada. Por cada alumno presente se descuenta una clase de su pack vigente
     * con el profesor; si no tiene pack o no le quedan clases, se le cobra la clase suelta a nombre del profesor.
     * Los ausentes no consumen ni pagan.
     *
     * @param  array<int, string>  $attendance  member_id => presente|ausente
     */
    public function markGiven(Lesson $lesson, array $attendance, ?User $by = null): Lesson
    {
        $this->assertScheduled($lesson);
        if ($lesson->date->isAfter(today())) {
            throw new BusinessRuleException('No se puede marcar como dada una clase de una fecha futura.');
        }

        return Organization::runFor($lesson->organization_id, fn () => DB::transaction(function () use ($lesson, $attendance) {
            $lesson->load('students', 'facility');
            $price = $lesson->price !== null ? (string) $lesson->price : $this->singlePrice($lesson->instructor);

            foreach ($lesson->students as $student) {
                $noticed = $student->pivot->attendance === AttendanceStatus::Notified->value;
                $default = $noticed ? AttendanceStatus::Absent : AttendanceStatus::Present;
                $status = AttendanceStatus::tryFrom($attendance[$student->id] ?? '') ?? $default;
                if (! in_array($status, [AttendanceStatus::Present, AttendanceStatus::Absent], true)) {
                    $status = $default;
                }
                // Si había avisado y no vino, queda registrado el aviso.
                if ($status === AttendanceStatus::Absent && $noticed) {
                    $status = AttendanceStatus::Notified;
                }

                $pivot = ['attendance' => $status->value, 'subscription_id' => null, 'fee_id' => null];

                // Las clases de un nivel se pagan con la cuota mensual: no descuentan packs ni cobran por clase.
                if ($status === AttendanceStatus::Present && ! $lesson->isLevelLesson()) {
                    $pack = $this->packFor($student, $lesson);
                    if ($pack) {
                        $pivot['subscription_id'] = $pack->id;
                    } elseif (bccomp($price, '0', 2) > 0) {
                        $pivot['fee_id'] = $this->fees->createCharge(
                            $student,
                            FeeType::Lesson,
                            'Clase '.$lesson->date->format('d/m/Y').' '.substr($lesson->start_time, 0, 5).' - '.$lesson->placeName(),
                            $price,
                            $lesson->date->copy(),
                            instructorId: $lesson->instructor_id,
                            lessonId: $lesson->id,
                        )->id;
                    }
                }

                // Se guarda alumno por alumno para que el pack descuente bien si dos alumnos comparten plan.
                $lesson->students()->updateExistingPivot($student->id, $pivot);
            }

            $lesson->update(['status' => LessonStatus::Given, 'given_at' => now()]);

            return $lesson->refresh();
        }));
    }

    /** Vuelve a dejar programada una clase dada (corrección): anula los cargos de clase suelta sin pagos. */
    public function reopen(Lesson $lesson): Lesson
    {
        if ($lesson->status !== LessonStatus::Given) {
            throw new BusinessRuleException('Solo se pueden reabrir clases dadas.');
        }

        return Organization::runFor($lesson->organization_id, fn () => DB::transaction(function () use ($lesson) {
            $fees = Fee::where('lesson_id', $lesson->id)->where('status', '!=', FeeStatus::Cancelled)->get();
            if ($fees->contains(fn (Fee $fee) => bccomp((string) $fee->paid_amount, '0', 2) > 0)) {
                throw new BusinessRuleException('Hay clases sueltas ya cobradas. Anulá primero esos pagos.');
            }
            $fees->each(fn (Fee $fee) => $this->fees->cancel($fee, 'Clase reabierta'));

            foreach ($lesson->students as $student) {
                $lesson->students()->updateExistingPivot($student->id, [
                    'attendance' => ($student->pivot->notice_at ? AttendanceStatus::Notified : AttendanceStatus::Pending)->value,
                    'subscription_id' => null,
                    'fee_id' => null,
                ]);
            }
            $lesson->update(['status' => LessonStatus::Scheduled, 'given_at' => null]);

            return $lesson->refresh();
        }));
    }

    public function cancel(Lesson $lesson, ?string $reason = null): void
    {
        $this->assertScheduled($lesson);

        $lesson->update([
            'status' => LessonStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }

    /** Cancela las clases programadas de la serie desde una fecha y la da por terminada. Devuelve cuántas canceló. */
    public function cancelSeries(LessonSeries $series, Carbon $from, ?string $reason = null): int
    {
        return DB::transaction(function () use ($series, $from, $reason) {
            $count = $series->lessons()
                ->where('status', LessonStatus::Scheduled)
                ->whereDate('date', '>=', $from)
                ->update(['status' => LessonStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason, 'updated_at' => now()]);

            $endsOn = $from->copy()->subDay();
            $series->update(['ends_on' => $endsOn->lt($series->starts_on) ? $series->starts_on : $endsOn]);

            return $count;
        });
    }

    /** Precio de la clase suelta: el del profesor, o el de la entidad actual. */
    public function singlePrice(User $instructor): string
    {
        $own = $instructor->preference('lessons.single_price');

        return (string) ($own !== null && $own !== '' ? $own : (setting('lessons.single_price') ?? 0));
    }

    /**
     * Otras ocupaciones de la sede en ese horario (reservas y clases de otros profesores), para advertir.
     *
     * @return Collection<int, string>
     */
    public function facilityConflicts(Facility $facility, string $date, string $startTime, string $endTime, ?int $ignoreLessonId = null): Collection
    {
        $start = $this->normalizeTime($startTime);
        $end = $this->normalizeTime($endTime);

        $reservations = Reservation::acrossOrganizations()
            ->where('facility_id', $facility->id)
            ->where('status', ReservationStatus::Confirmed)
            ->whereDate('date', $date)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->with('member')
            ->get()
            ->map(fn (Reservation $r) => 'Reserva de '.$r->member->fullName().' ('.$r->timeRange().')');

        $lessons = Lesson::acrossOrganizations()
            ->where('facility_id', $facility->id)
            ->active()
            ->overlapping($date, $start, $end)
            ->when($ignoreLessonId, fn ($q) => $q->whereKeyNot($ignoreLessonId))
            ->with('instructor')
            ->get()
            ->map(fn (Lesson $l) => 'Clase de '.$l->instructor->name.' ('.$l->timeRange().')');

        return $reservations->concat($lessons)->values();
    }

    // ---- Internos ----

    /** @return array{0: string, 1: string} */
    private function validateSlot(User $instructor, Facility $facility, string $startTime, string $endTime, ?string $price): array
    {
        $start = $this->normalizeTime($startTime);
        $end = $this->normalizeTime($endTime);

        if ($end <= $start) {
            throw new BusinessRuleException('La hora de fin debe ser posterior a la de inicio.');
        }

        if (! $facility->is_active) {
            throw new BusinessRuleException('La sede no está activa.');
        }

        if (! $instructor->facilities()->whereKey($facility->id)->exists()) {
            throw new BusinessRuleException("{$instructor->name} no tiene asignada la sede {$facility->name}. Asignala desde Usuarios.");
        }

        if ($price !== null && bccomp($price, '0', 2) < 0) {
            throw new BusinessRuleException('El precio no puede ser negativo.');
        }

        return [$start, $end];
    }

    private function normalizeTime(string $time): string
    {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:\d\d)?$/', $time, $m)) {
            throw new BusinessRuleException('Horario inválido.');
        }

        return "{$m[1]}:{$m[2]}:00";
    }

    /** Serializa la agenda del profesor para que dos altas simultáneas no se superpongan. */
    private function lockInstructor(User $instructor): void
    {
        User::query()->whereKey($instructor->id)->lockForUpdate()->first();
    }

    private function instructorConflict(User $instructor, string $date, string $start, string $end, ?int $ignoreLessonId = null): ?Lesson
    {
        return Lesson::acrossOrganizations()
            ->where('instructor_id', $instructor->id)
            ->active()
            ->overlapping($date, $start, $end)
            ->when($ignoreLessonId, fn ($q) => $q->whereKeyNot($ignoreLessonId))
            ->with(['facility', 'organization'])
            ->first();
    }

    private function assertInstructorIsFree(User $instructor, string $date, string $start, string $end, ?int $ignoreLessonId = null): void
    {
        $conflict = $this->instructorConflict($instructor, $date, $start, $end, $ignoreLessonId);
        if ($conflict) {
            throw new BusinessRuleException("{$instructor->name} ya tiene una clase de {$conflict->timeRange()} en {$conflict->organization->name} ({$conflict->facility->name}).");
        }
    }

    private function assertScheduled(Lesson $lesson): void
    {
        if (! $lesson->isScheduled()) {
            throw new BusinessRuleException('La clase ya está '.mb_strtolower($lesson->status->label()).'.');
        }
    }

    /** Membresía del alumno en la entidad actual: si es socio de otra entidad, se usa su persona. */
    private function resolveStudent(int $memberId): Member
    {
        $member = Member::acrossOrganizations()->find($memberId);
        if (! $member) {
            throw new BusinessRuleException('El alumno no existe.');
        }

        if ($member->organization_id === Organization::currentId()) {
            $local = $member;
        } else {
            $local = $this->members->joinOrganization(Person::findOrFail($member->person_id));
        }

        if (! $local->isActive()) {
            throw new BusinessRuleException("{$local->fullName()} no está activo/a en esta entidad.");
        }

        return $local;
    }

    private function attachStudent(Lesson $lesson, int $memberId): Member
    {
        $student = $this->resolveStudent($memberId);

        if ($lesson->students()->whereKey($student->id)->exists()) {
            throw new BusinessRuleException("{$student->fullName()} ya está en la clase.");
        }

        $lesson->students()->attach($student->id, ['attendance' => AttendanceStatus::Pending->value]);

        return $student;
    }

    /** Pack vigente del alumno con el profesor de la clase y con clases disponibles (el que vence primero). */
    private function packFor(Member $student, Lesson $lesson): ?Subscription
    {
        $at = $lesson->date->copy()->endOfDay();

        return Subscription::query()
            ->where('member_id', $student->id)
            ->current($lesson->date)
            ->whereHas('plan', fn ($q) => $q->where('instructor_id', $lesson->instructor_id))
            ->with('plan')
            ->orderBy('end_date')
            ->get()
            ->first(fn (Subscription $s) => $s->visitsRemaining($at) !== 0);
    }
}
