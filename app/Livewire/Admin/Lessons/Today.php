<?php

namespace App\Livewire\Admin\Lessons;

use App\Enums\AttendanceStatus;
use App\Enums\LessonStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Lesson;
use App\Services\CashCollectionService;
use App\Services\LessonService;
use App\Services\LevelLessonService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Clases del día para el profesor, pensada para el celular: asistencia con un toque, avisos de ausencia,
 * marcar la clase como dada y suspenderla. Coordinación ve todas las clases del día y puede suspender todas.
 */
#[Layout('layouts.admin')]
#[Title('Clases de hoy')]
class Today extends Component
{
    use InteractsWithUi;

    #[Url(as: 'fecha')]
    public string $date = '';

    /** @var array<int, array<int, string>> lesson_id => [member_id => presente|ausente] */
    public array $attendance = [];

    public ?int $openLessonId = null;

    // ---- Suspender ----
    public bool $showSuspend = false;

    /** null = todas las clases del día (solo coordinación); si no, la clase elegida */
    public ?int $suspendLessonId = null;

    public string $suspendReason = '';

    public function mount(): void
    {
        $this->date = $this->date ?: today()->toDateString();
    }

    public function shift(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
        $this->reset(['attendance', 'openLessonId']);
    }

    public function toggle(int $lessonId): void
    {
        $this->openLessonId = $this->openLessonId === $lessonId ? null : $lessonId;
    }

    public function setAttendance(int $lessonId, int $memberId, string $value): void
    {
        $this->findManageable($lessonId);
        $this->attendance[$lessonId][$memberId] = $value === 'ausente' ? 'ausente' : 'presente';
    }

    public function markGiven(int $lessonId, LessonService $service): void
    {
        $lesson = $this->findManageable($lessonId);
        if ($this->attempt(fn () => $service->markGiven($lesson, $this->attendance[$lessonId] ?? [], auth()->user()), 'Asistencia guardada.')) {
            $this->openLessonId = null;
        }
    }

    public function reopen(int $lessonId, LessonService $service): void
    {
        $lesson = $this->findManageable($lessonId);
        $this->attempt(fn () => $service->reopen($lesson), 'La clase volvió a quedar abierta para tomar asistencia.');
    }

    public function askSuspend(?int $lessonId = null): void
    {
        if ($lessonId === null) {
            $this->authorize('agenda.todas');
        } else {
            $this->findManageable($lessonId);
        }
        $this->suspendLessonId = $lessonId;
        $this->suspendReason = '';
        $this->showSuspend = true;
    }

    public function confirmSuspend(LevelLessonService $levels, LessonService $lessons): void
    {
        $this->validate(['suspendReason' => 'nullable|string|max:200'], [], ['suspendReason' => 'motivo']);
        $reason = $this->suspendReason ?: null;

        if ($this->suspendLessonId === null) {
            $this->authorize('agenda.todas');
            $count = $this->attempt(fn () => $levels->suspend(Carbon::parse($this->date), null, $reason, auth()->user()));
            if ($count !== null) {
                $this->notify("Se suspendieron {$count} clases y se avisó a los alumnos.");
                $this->showSuspend = false;
            }

            return;
        }

        $lesson = $this->findManageable($this->suspendLessonId);
        $done = $lesson->isLevelLesson()
            ? $this->attempt(fn () => $levels->suspend($lesson->date->copy(), [$lesson->activity_id], $reason, auth()->user()))
            : $this->attempt(fn () => $lessons->cancel($lesson, $reason));

        if ($done !== null) {
            $this->notify('Clase suspendida.'.($lesson->isLevelLesson() ? ' Se avisó a los alumnos.' : ''));
            $this->showSuspend = false;
        }
    }

    // ---- Alcance ----

    private function seesAll(): bool
    {
        return auth()->user()->can('agenda.todas');
    }

    private function query()
    {
        $user = auth()->user();
        $mine = app(LevelLessonService::class)->activityIdsTaughtBy($user);

        return Lesson::query()
            ->whereDate('date', $this->date)
            ->when(! $this->seesAll(), fn ($q) => $q->where(fn ($w) => $w->where('instructor_id', $user->id)->orWhereIn('activity_id', $mine)));
    }

    private function findManageable(int $id): Lesson
    {
        $lesson = $this->query()->findOrFail($id);
        abort_unless($this->seesAll() || auth()->user()->can('agenda.gestionar'), 403);

        return $lesson;
    }

    public function render()
    {
        $lessons = $this->query()
            ->with(['activity', 'facility', 'instructor', 'students'])
            ->orderBy('start_time')
            ->get();

        // Asistencia por defecto: presente, salvo quien avisó que falta.
        foreach ($lessons as $lesson) {
            foreach ($lesson->students as $student) {
                $this->attendance[$lesson->id][$student->id] ??= in_array($student->pivot->attendance, [AttendanceStatus::Notified->value, AttendanceStatus::Absent->value], true) ? 'ausente' : 'presente';
            }
        }

        $cash = app(CashCollectionService::class);
        $user = auth()->user();

        return view('livewire.admin.lessons.today', [
            'lessons' => $lessons,
            'day' => Carbon::parse($this->date),
            'seesAll' => $this->seesAll(),
            'canCollect' => $user->can('cobros.niveles') && setting('payments.instructors_collect_cash', true),
            'cashPending' => $user->can('cobros.niveles') && $cash->requiresSettlement() ? $cash->pendingTotal($user) : null,
            'scheduledCount' => $lessons->where('status', LessonStatus::Scheduled)->whereNotNull('activity_id')->count(),
            'activityLabel' => activity_label(),
        ]);
    }
}
