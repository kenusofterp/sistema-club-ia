<?php

namespace App\Livewire\Admin\Access;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\AccessLog;
use App\Models\Activity;
use App\Services\AccessService;
use App\Services\LessonService;
use App\Services\RollCallService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dos modos: "ingreso" (verificar QR / N° de socio en la puerta) y "asistencia" (tomar lista de un
 * nivel como en un colegio: todos presentes por defecto, se destilda a los ausentes).
 */
#[Layout('layouts.admin')]
#[Title('Control de acceso')]
class Check extends Component
{
    use InteractsWithUi;

    /** ingreso | asistencia */
    #[Url(as: 'modo')]
    public string $mode = 'ingreso';

    public string $code = '';

    public ?int $lastLogId = null;

    public bool $notFound = false;

    // ---- Asistencia por nivel ----
    #[Url(as: 'nivel')]
    public ?int $activityId = null;

    #[Url(as: 'fecha')]
    public string $date = '';

    /** @var array<int, int> alumnos destildados (ausentes) */
    public array $absent = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->can('acceso.registrar') || $user->can('agenda.gestionar'), 403);

        // Quien no controla el ingreso (ej. profesor) entra directo a tomar asistencia.
        if (! $user->can('acceso.registrar')) {
            $this->mode = 'asistencia';
        }
        if ($this->mode === 'asistencia' && ! $user->can('agenda.gestionar')) {
            $this->mode = 'ingreso';
        }

        $this->date = $this->date ?: today()->toDateString();
        if (! $this->pastAllowed() && Carbon::parse($this->date)->lt(today())) {
            $this->date = today()->toDateString();
        }

        if ($this->mode === 'asistencia') {
            $this->activityId ??= app(RollCallService::class)->activitiesFor($user)->first()?->id;
            $this->loadRoster();
        }
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'asistencia' ? 'asistencia' : 'ingreso';
        $this->mount();
    }

    public function check(AccessService $access): void
    {
        $this->authorize('acceso.registrar');
        $this->validate(['code' => 'required|string|max:200'], [], ['code' => 'código']);

        $member = $access->findMember($this->code);
        $this->notFound = $member === null;
        $this->lastLogId = $member ? $access->register($member, auth()->user())->id : null;
        $this->code = '';
        $this->dispatch('access-checked');
    }

    // ---- Asistencia ----

    public function updatedActivityId(): void
    {
        $this->loadRoster();
    }

    public function updatedDate(): void
    {
        if (! $this->pastAllowed() && Carbon::parse($this->date)->lt(today())) {
            $this->date = today()->toDateString();
            $this->notify('La asistencia solo se puede tomar el mismo día de la clase.', 'error');
        }
        $this->loadRoster();
    }

    private function pastAllowed(): bool
    {
        return app(LessonService::class)->pastAttendanceAllowed();
    }

    /** Estado inicial: todos presentes, salvo quien avisó que falta o la asistencia ya guardada. */
    private function loadRoster(): void
    {
        $this->absent = [];
        $activity = $this->activity();
        if (! $activity) {
            return;
        }

        $this->absent = app(RollCallService::class)->roster($activity, Carbon::parse($this->date))
            ->reject(fn ($row) => $row['present'])
            ->map(fn ($row) => $row['member']->id)
            ->values()->all();
    }

    public function toggleAbsent(int $memberId): void
    {
        $this->absent = in_array($memberId, $this->absent, true)
            ? array_values(array_diff($this->absent, [$memberId]))
            : [...$this->absent, $memberId];
    }

    public function allPresent(): void
    {
        $this->absent = [];
    }

    public function saveAttendance(RollCallService $rollCall): void
    {
        $this->authorize('agenda.gestionar');
        $this->validate(['activityId' => 'required|integer', 'date' => 'required|date'], [], ['activityId' => mb_strtolower(activity_label()), 'date' => 'fecha']);

        $activity = $this->activity() ?? abort(404);
        $lesson = $this->attempt(fn () => $rollCall->save($activity, Carbon::parse($this->date), $this->absent, auth()->user()));

        if ($lesson) {
            $present = $lesson->students->where('pivot.attendance', 'presente')->count();
            $this->notify("Asistencia guardada: {$present} presentes y ".($lesson->students->count() - $present).' ausentes.');
        }
    }

    private function activity(): ?Activity
    {
        if (! $this->activityId) {
            return null;
        }

        $activity = Activity::find($this->activityId);

        return $activity && app(RollCallService::class)->canTake(auth()->user(), $activity) ? $activity : null;
    }

    public function render(RollCallService $rollCall)
    {
        $user = auth()->user();
        $data = [
            'canAccess' => $user->can('acceso.registrar'),
            'canRollCall' => $user->can('agenda.gestionar'),
        ];

        if ($this->mode === 'asistencia') {
            $activity = $this->activity();
            $day = Carbon::parse($this->date);

            return view('livewire.admin.access.check', $data + [
                'activities' => $rollCall->activitiesFor($user),
                'roster' => $activity ? $rollCall->roster($activity, $day) : collect(),
                'lesson' => $activity ? $rollCall->lessonFor($activity, $day) : null,
                'suspended' => $activity && $rollCall->isSuspended($activity, $day),
                'day' => $day,
                'pastAllowed' => $this->pastAllowed(),
            ]);
        }

        return view('livewire.admin.access.check', $data + [
            'last' => $this->lastLogId ? AccessLog::with(['member.category'])->find($this->lastLogId) : null,
            'recent' => AccessLog::with('member')->latest('checked_at')->limit(12)->get(),
            'todayCount' => AccessLog::whereDate('checked_at', today())->where('result', 'permitido')->count(),
        ]);
    }
}
