<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Asistencia "tipo colegio" desde Control de acceso: se elige un nivel y una fecha, se listan todos
 * sus alumnos tildados como presentes y el profesor destilda a los ausentes. Se guarda en la clase
 * del nivel de ese día (la misma de "Clases de hoy"); si el nivel no tenía clase ese día, se crea.
 */
class RollCallService
{
    public function __construct(private LevelLessonService $levels, private LessonService $lessons) {}

    /** Niveles en los que el usuario puede tomar asistencia. */
    public function activitiesFor(User $user): Collection
    {
        return Activity::query()
            ->where('is_active', true)
            ->when(! $user->can('agenda.todas'), fn ($q) => $q->whereIn('id', $this->levels->activityIdsTaughtBy($user)))
            ->orderBy('name')
            ->get(['id', 'name', 'instructor_id']);
    }

    public function canTake(User $user, Activity $activity): bool
    {
        return $user->can('agenda.todas') || in_array($activity->id, $this->levels->activityIdsTaughtBy($user), true);
    }

    /** Clase del nivel ese día (no suspendida), si existe. */
    public function lessonFor(Activity $activity, Carbon $date): ?Lesson
    {
        return Lesson::query()
            ->where('activity_id', $activity->id)
            ->whereDate('date', $date)
            ->where('status', '!=', LessonStatus::Cancelled)
            ->orderBy('start_time')
            ->first();
    }

    public function isSuspended(Activity $activity, Carbon $date): bool
    {
        return $this->lessonFor($activity, $date) === null
            && Lesson::where('activity_id', $activity->id)->whereDate('date', $date)->where('status', LessonStatus::Cancelled)->exists();
    }

    /**
     * Lista para tomar asistencia: todos los inscriptos activos del nivel, con su estado sugerido
     * (presente, salvo quien avisó que falta o la asistencia ya guardada).
     *
     * @return Collection<int, array{member: Member, present: bool, notice: ?string}>
     */
    public function roster(Activity $activity, Carbon $date): Collection
    {
        $lesson = $this->lessonFor($activity, $date)?->load('students');
        $saved = $lesson?->students->keyBy('id') ?? collect();

        $members = Member::query()
            ->whereHas('enrollments', fn ($q) => $q
                ->where('activity_id', $activity->id)
                ->where('status', EnrollmentStatus::Active)
                ->whereDate('start_date', '<=', $date))
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        // También quienes figuran en la clase aunque ya no estén inscriptos (asistencia ya tomada).
        $members = $members->concat($saved->values()->whereNotIn('id', $members->pluck('id')))->values();

        return $members->map(function (Member $member) use ($saved) {
            $pivot = $saved->get($member->id)?->pivot;
            $status = $pivot?->attendance;

            return [
                'member' => $member,
                'present' => ! in_array($status, [AttendanceStatus::Absent->value, AttendanceStatus::Notified->value], true),
                'notice' => $pivot?->notice_at ? ($pivot->notice_reason ?: 'Avisó que falta') : null,
            ];
        });
    }

    /**
     * Guarda la asistencia. $absentIds = alumnos destildados; el resto queda presente.
     *
     * @param  array<int, int>  $absentIds
     */
    public function save(Activity $activity, Carbon $date, array $absentIds, User $by): Lesson
    {
        if (! $this->canTake($by, $activity)) {
            throw new BusinessRuleException('No podés tomar asistencia en este '.mb_strtolower(activity_label()).'.');
        }

        if ($date->isAfter(today())) {
            throw new BusinessRuleException('No se puede tomar asistencia de una fecha futura.');
        }

        if ($date->lt(today()) && ! $this->lessons->pastAttendanceAllowed()) {
            throw new BusinessRuleException('La asistencia solo se puede tomar el mismo día de la clase.');
        }

        if ($this->isSuspended($activity, $date)) {
            throw new BusinessRuleException('La clase de ese día está suspendida.');
        }

        return DB::transaction(function () use ($activity, $date, $absentIds, $by) {
            $lesson = $this->lessonFor($activity, $date);

            if (! $lesson) {
                $this->levels->generate($activity, $date->copy(), $date->copy());
                $lesson = $this->lessonFor($activity, $date) ?? $this->createLesson($activity, $date, $by);
            }

            if ($lesson->status === LessonStatus::Given) {
                $lesson = $this->lessons->reopen($lesson);
            }

            $this->levels->syncLessonStudents($lesson);
            $lesson->load('students');

            $absent = array_flip(array_map('intval', $absentIds));
            $attendance = $lesson->students
                ->mapWithKeys(fn (Member $m) => [$m->id => isset($absent[$m->id]) ? 'ausente' : 'presente'])
                ->all();

            return $this->lessons->markGiven($lesson, $attendance, $by);
        });
    }

    /** Clase del nivel para un día sin horario cargado (asistencia tomada igual). */
    private function createLesson(Activity $activity, Carbon $date, User $by): Lesson
    {
        $start = $date->isToday() ? now()->startOfHour() : $date->copy()->setTime(12, 0);

        return Lesson::create([
            'activity_id' => $activity->id,
            'instructor_id' => $activity->instructor_id ?? $by->id,
            'date' => $date->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->copy()->addHour()->format('H:i:s'),
            'status' => LessonStatus::Scheduled,
            'notes' => 'Asistencia tomada desde Control de acceso',
            'created_by' => $by->id,
        ]);
    }
}
