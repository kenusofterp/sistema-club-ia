<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LessonStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Member;
use App\Models\User;
use App\Notifications\AbsenceNoticeNotification;
use App\Notifications\LessonsSuspendedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Clases de las actividades / niveles con horario fijo: se generan por adelantado a partir de los horarios,
 * sus alumnos son los inscriptos, se paga con la cuota mensual y los alumnos pueden avisar si faltan.
 * Trabaja en la entidad actual.
 */
class LevelLessonService
{
    /**
     * Genera las clases de las próximas semanas (o del rango indicado) de una actividad o de todas.
     * Es idempotente: no duplica una clase ya creada (aunque se haya cancelado).
     * Las actividades sin profesor no generan clases.
     */
    public function generate(?Activity $activity = null, ?Carbon $from = null, ?Carbon $to = null): int
    {
        $from = ($from ?? today())->copy()->startOfDay();
        $to = ($to ?? today()->addWeeks(max(1, (int) setting('lessons.generate_weeks', 4))))->copy()->startOfDay();

        $activities = $activity
            ? collect([$activity->loadMissing('schedules', 'instructors')])
            : Activity::where('is_active', true)->whereHas('schedules')->with('schedules', 'instructors')->get();

        $created = 0;
        foreach ($activities as $item) {
            $instructorId = $item->instructor_id ?? $item->instructors->first()?->id;
            if (! $item->is_active || ! $instructorId || $item->schedules->isEmpty()) {
                continue;
            }

            DB::transaction(function () use ($item, $instructorId, $from, $to, &$created) {
                $existing = Lesson::withTrashed()
                    ->where('activity_id', $item->id)
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->get(['date', 'start_time'])
                    ->map(fn (Lesson $l) => $l->date->toDateString().' '.$l->start_time)
                    ->flip();

                $enrollments = $item->activeEnrollments()->get(['member_id', 'start_date']);

                for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                    foreach ($item->schedules->where('day_of_week', $day->dayOfWeekIso) as $schedule) {
                        $start = strlen($schedule->start_time) === 5 ? $schedule->start_time.':00' : $schedule->start_time;
                        if ($existing->has($day->toDateString().' '.$start)) {
                            continue;
                        }

                        $lesson = Lesson::create([
                            'activity_id' => $item->id,
                            'facility_id' => $schedule->facility_id,
                            'instructor_id' => $instructorId,
                            'date' => $day->toDateString(),
                            'start_time' => $start,
                            'end_time' => $schedule->end_time,
                            'status' => LessonStatus::Scheduled,
                        ]);

                        $members = $enrollments->filter(fn ($e) => $e->start_date->lte($day))->pluck('member_id');
                        $lesson->students()->attach($members, ['attendance' => AttendanceStatus::Pending->value]);
                        $created++;
                    }
                }
            });
        }

        return $created;
    }

    /**
     * Tras cambiar los horarios de una actividad: quita las clases futuras programadas que ya no
     * corresponden a ningún horario (si nadie avisó ni se tomó asistencia) y genera las que faltan.
     */
    public function syncSchedules(Activity $activity): void
    {
        $activity->load('schedules');
        $valid = $activity->schedules->map(fn ($s) => $s->day_of_week.' '.substr($s->start_time, 0, 5).' '.substr($s->end_time, 0, 5));

        Lesson::where('activity_id', $activity->id)
            ->where('status', LessonStatus::Scheduled)
            ->whereDate('date', '>', today())
            ->whereDoesntHave('students', fn ($q) => $q->whereNotNull('lesson_member.notice_at'))
            ->get()
            ->reject(fn (Lesson $l) => $valid->contains($l->date->dayOfWeekIso.' '.substr($l->start_time, 0, 5).' '.substr($l->end_time, 0, 5)))
            ->each(fn (Lesson $l) => $l->forceDelete());

        // Si cambió el profesor titular, las clases futuras pasan al nuevo.
        if ($activity->instructor_id) {
            Lesson::where('activity_id', $activity->id)
                ->where('status', LessonStatus::Scheduled)
                ->whereDate('date', '>=', today())
                ->update(['instructor_id' => $activity->instructor_id]);
        }

        $this->generate($activity);
    }

    /** Alta o baja en una actividad: se suma o se quita al socio de las clases futuras programadas. */
    public function syncEnrollment(Member $member, Activity $activity, bool $enrolled): void
    {
        $lessons = Lesson::where('activity_id', $activity->id)
            ->where('status', LessonStatus::Scheduled)
            ->whereDate('date', '>=', today())
            ->get();

        foreach ($lessons as $lesson) {
            $has = $lesson->students()->whereKey($member->id)->exists();
            if ($enrolled && ! $has) {
                $lesson->students()->attach($member->id, ['attendance' => AttendanceStatus::Pending->value]);
            } elseif (! $enrolled && $has && ! ($lesson->date->isToday() && $lesson->startsAt()->isPast())) {
                $lesson->students()->detach($member->id);
            }
        }
    }

    /** El alumno avisa que no va. Solo hasta que empieza la clase; avisa a los profesores. */
    public function notifyAbsence(Lesson $lesson, Member $member, ?string $reason = null): void
    {
        if (! setting('lessons.absence_notice_enabled', true)) {
            throw new BusinessRuleException('Los avisos de ausencia no están habilitados.');
        }

        $this->assertNoticeOpen($lesson);

        $student = $lesson->students()->whereKey($member->id)->first();
        if (! $student) {
            throw new BusinessRuleException('No estás anotado/a en esa clase.');
        }

        $reason = trim((string) $reason) ?: null;
        $lesson->students()->updateExistingPivot($member->id, [
            'attendance' => AttendanceStatus::Notified->value,
            'notice_at' => now(),
            'notice_reason' => $reason ? mb_substr($reason, 0, 200) : null,
        ]);

        Notification::send($this->instructorsOf($lesson), new AbsenceNoticeNotification($lesson, $member, $reason));
    }

    /** Deshace el aviso ("al final voy"). */
    public function withdrawAbsence(Lesson $lesson, Member $member): void
    {
        $this->assertNoticeOpen($lesson);

        $lesson->students()->updateExistingPivot($member->id, [
            'attendance' => AttendanceStatus::Pending->value,
            'notice_at' => null,
            'notice_reason' => null,
        ]);
    }

    /**
     * Suspende las clases de un día ("no hay clase") de las actividades indicadas o de todas,
     * y avisa a los alumnos. Quien no ve toda la agenda solo puede suspender sus actividades.
     *
     * @param  array<int>|null  $activityIds  null = todas las actividades
     * @return int clases suspendidas
     */
    public function suspend(Carbon $date, ?array $activityIds, ?string $reason, User $by): int
    {
        if ($date->copy()->startOfDay()->lt(today())) {
            throw new BusinessRuleException('No se pueden suspender clases de días pasados.');
        }

        $seesAll = $by->can('agenda.todas');
        $own = $this->activityIdsTaughtBy($by);

        if (! $seesAll) {
            $activityIds = $activityIds === null ? $own : array_values(array_intersect($activityIds, $own));
            if ($activityIds === []) {
                throw new BusinessRuleException('Solo podés suspender las clases de tus propios grupos.');
            }
        }

        // Si el día está más allá de lo generado, primero se crean sus clases.
        $this->generate(null, $date, $date);

        $lessons = Lesson::whereNotNull('activity_id')
            ->when($activityIds !== null, fn ($q) => $q->whereIn('activity_id', $activityIds))
            ->where('status', LessonStatus::Scheduled)
            ->whereDate('date', $date)
            ->with(['activity', 'students'])
            ->get();

        if ($lessons->isEmpty()) {
            throw new BusinessRuleException('No hay clases programadas ese día para suspender.');
        }

        $reason = trim((string) $reason) ?: null;

        DB::transaction(function () use ($lessons, $reason) {
            foreach ($lessons as $lesson) {
                $lesson->update([
                    'status' => LessonStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancel_reason' => $reason ?? 'Clase suspendida',
                ]);
            }
        });

        // Un solo aviso por alumno, con todas sus clases suspendidas de ese día.
        $lessons->flatMap(fn (Lesson $l) => $l->students->map(fn (Member $m) => [$m, $l]))
            ->groupBy(fn ($pair) => $pair[0]->id)
            ->each(function (Collection $pairs) use ($date, $reason) {
                $member = $pairs->first()[0];
                $classes = $pairs->map(fn ($p) => $p[1]->activity->name.' '.substr($p[1]->start_time, 0, 5))->values()->all();
                $member->notify(new LessonsSuspendedNotification($date->toDateString(), $classes, $reason, $member->organization_id));
            });

        return $lessons->count();
    }

    /** @return Collection<int, User> profesores de la clase (titular y los de la actividad) */
    public function instructorsOf(Lesson $lesson): Collection
    {
        $users = collect([$lesson->instructor]);
        if ($lesson->activity) {
            $users = $users->concat($lesson->activity->instructors);
        }

        return $users->filter()->unique('id')->values();
    }

    /** ¿El usuario es profesor de esta clase (titular o de la actividad)? */
    public function teaches(User $user, Lesson $lesson): bool
    {
        return $lesson->instructor_id === $user->id
            || ($lesson->activity_id && in_array($lesson->activity_id, $this->activityIdsTaughtBy($user), true));
    }

    /** @return array<int, int> actividades de la entidad actual donde el usuario es profesor */
    public function activityIdsTaughtBy(User $user): array
    {
        return Activity::query()
            ->where(fn ($q) => $q->where('instructor_id', $user->id)
                ->orWhereHas('instructors', fn ($i) => $i->whereKey($user->id)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function assertNoticeOpen(Lesson $lesson): void
    {
        if (! $lesson->isScheduled()) {
            throw new BusinessRuleException('La clase ya no está programada.');
        }

        if ($lesson->startsAt()->isPast()) {
            throw new BusinessRuleException('La clase ya empezó.');
        }
    }
}
