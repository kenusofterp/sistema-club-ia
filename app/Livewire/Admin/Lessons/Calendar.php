<?php

namespace App\Livewire\Admin\Lessons;

use App\Enums\LessonStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\SearchesPeople;
use App\Models\Activity;
use App\Models\Facility;
use App\Models\Fee;
use App\Models\Lesson;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Services\LessonService;
use App\Services\LevelLessonService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Agenda de clases. El profesor elige ver todos sus clubes juntos (agenda global) o uno solo;
 * quien tiene "agenda.todas" en una entidad ve además las clases de los demás profesores de esa entidad.
 * La vista elegida se recuerda por usuario.
 */
#[Layout('layouts.admin')]
#[Title('Agenda de clases')]
class Calendar extends Component
{
    use InteractsWithUi, SearchesPeople;

    public const VIEWS = ['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes'];

    /** Colores por club o sede (clases completas para que Tailwind las incluya). */
    public const PALETTE = [
        'bg-sky-100 text-sky-900 ring-sky-300',
        'bg-amber-100 text-amber-900 ring-amber-300',
        'bg-emerald-100 text-emerald-900 ring-emerald-300',
        'bg-violet-100 text-violet-900 ring-violet-300',
        'bg-rose-100 text-rose-900 ring-rose-300',
        'bg-teal-100 text-teal-900 ring-teal-300',
        'bg-orange-100 text-orange-900 ring-orange-300',
        'bg-indigo-100 text-indigo-900 ring-indigo-300',
    ];

    #[Url(as: 'vista')]
    public string $view = '';

    #[Url(as: 'fecha')]
    public string $date = '';

    /** "todas" (todos mis clubes) o el id de una entidad. */
    #[Url(as: 'ver')]
    public string $scope = '';

    /** Filtro de profesor para quien ve todas: "" = todos, o el id. */
    #[Url(as: 'profesor')]
    public string $instructorFilter = '';

    // ---- Formulario de clase ----
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $formInstructorId = null;

    public ?int $facilityId = null;

    public string $formDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public string $price = '';

    public string $notes = '';

    public bool $repeat = false;

    /** @var array<int, string> */
    public array $days = [];

    public string $until = '';

    /** @var array<int, array{id: int, name: string}> alumnos elegidos (id de socio de cualquier entidad) */
    public array $students = [];

    public string $studentSearch = '';

    // ---- Detalle ----
    public bool $showDetail = false;

    public ?int $detailId = null;

    /** @var array<int, string> member_id => presente|ausente */
    public array $attendance = [];

    public string $detailSearch = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->view = array_key_exists($this->view, self::VIEWS) ? $this->view : $user->preference('agenda.view', 'semana');
        $this->scope = $this->scope ?: (string) $user->preference('agenda.scope', 'todas');
        if ($this->scope !== 'todas' && ! $this->scopeOptions()->has((int) $this->scope)) {
            $this->scope = 'todas';
        }
        $this->date = $this->date ?: today()->toDateString();
    }

    // ---- Navegación y preferencias ----

    public function updatedView(): void
    {
        $this->view = array_key_exists($this->view, self::VIEWS) ? $this->view : 'semana';
        auth()->user()->setPreference('agenda.view', $this->view);
    }

    public function updatedScope(): void
    {
        if ($this->scope !== 'todas' && ! $this->scopeOptions()->has((int) $this->scope)) {
            $this->scope = 'todas';
        }
        auth()->user()->setPreference('agenda.scope', $this->scope);
    }

    public function shift(int $direction): void
    {
        $date = Carbon::parse($this->date);
        $date = match ($this->view) {
            'dia' => $date->addDays($direction),
            'mes' => $date->startOfMonth()->addMonths($direction),
            default => $date->addWeeks($direction),
        };
        $this->date = $date->toDateString();
    }

    public function goTo(string $date, string $view = 'dia'): void
    {
        $this->date = Carbon::parse($date)->toDateString();
        $this->view = $view;
        $this->updatedView();
    }

    // ---- Alta / edición ----

    public function create(?string $date = null, ?string $time = null): void
    {
        $user = auth()->user();
        abort_unless($this->manageableOrganizationIds() !== [], 403);

        $this->reset(['editingId', 'facilityId', 'price', 'notes', 'repeat', 'days', 'students', 'studentSearch']);
        $this->resetValidation();
        $this->formInstructorId = $this->instructorFilter !== '' && $this->instructorFilter !== (string) $user->id && $this->seeAllOrganizationIds() !== []
            ? (int) $this->instructorFilter
            : $user->id;
        $this->formDate = $date ?: $this->date;
        $this->startTime = $time ?: '09:00';
        $this->endTime = Carbon::parse($this->startTime)->addMinutes((int) (setting('lessons.default_minutes') ?: 60))->format('H:i');
        $this->until = Carbon::parse($this->formDate)->addMonths(3)->toDateString();
        $this->days = [(string) Carbon::parse($this->formDate)->dayOfWeekIso];

        $facilities = $this->formFacilities();
        if ($this->scope !== 'todas') {
            $facilities = $facilities->where('organization_id', (int) $this->scope);
        }
        $this->facilityId = $facilities->count() === 1 ? $facilities->first()->id : null;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $lesson = $this->findManageable($id);

        $this->resetValidation();
        $this->editingId = $lesson->id;
        $this->formInstructorId = $lesson->instructor_id;
        $this->facilityId = $lesson->facility_id;
        $this->formDate = $lesson->date->toDateString();
        $this->startTime = substr($lesson->start_time, 0, 5);
        $this->endTime = substr($lesson->end_time, 0, 5);
        $this->price = $lesson->price !== null ? (string) $lesson->price : '';
        $this->notes = (string) $lesson->notes;
        $this->repeat = false;
        $this->students = [];
        $this->showDetail = false;
        $this->showForm = true;
    }

    public function updatedFormInstructorId(): void
    {
        $this->facilityId = null;
    }

    public function updatedStartTime(): void
    {
        if (preg_match('/^\d\d:\d\d$/', $this->startTime) && (! $this->endTime || $this->endTime <= $this->startTime)) {
            $this->endTime = Carbon::parse($this->startTime)->addMinutes((int) (setting('lessons.default_minutes') ?: 60))->format('H:i');
        }
    }

    public function addStudent(int $memberId): void
    {
        $member = Member::acrossOrganizations()->findOrFail($memberId);
        if (! collect($this->students)->contains('id', $member->id)) {
            $this->students[] = ['id' => $member->id, 'name' => $member->fullName()];
        }
        $this->studentSearch = '';
    }

    public function removeStudentFromForm(int $memberId): void
    {
        $this->students = array_values(array_filter($this->students, fn ($s) => $s['id'] !== $memberId));
    }

    public function save(LessonService $service): void
    {
        $this->validate([
            'formInstructorId' => 'required|integer',
            'facilityId' => 'required|integer',
            'formDate' => 'required|date',
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i|after:startTime',
            'price' => 'nullable|numeric|min:0|max:99999999',
            'notes' => 'nullable|string|max:500',
            'days' => $this->repeat && ! $this->editingId ? 'required|array|min:1' : 'array',
            'days.*' => 'integer|between:1,7',
            'until' => $this->repeat && ! $this->editingId ? 'required|date|after_or_equal:formDate' : 'nullable',
        ], [], [
            'formInstructorId' => 'profesor', 'facilityId' => 'sede', 'formDate' => 'fecha', 'startTime' => 'inicio',
            'endTime' => 'fin', 'price' => 'precio', 'days' => 'días', 'until' => 'repetir hasta',
        ]);

        $facility = $this->formFacilities()->firstWhere('id', $this->facilityId);
        abort_unless($facility, 403);
        $this->assertCanManage($this->formInstructorId, $facility->organization_id);

        $instructor = User::findOrFail($this->formInstructorId);
        $price = $this->price === '' ? null : number_format((float) $this->price, 2, '.', '');
        $notes = $this->notes ?: null;
        $memberIds = array_column($this->students, 'id');

        if ($this->editingId) {
            $lesson = $this->findManageable($this->editingId);
            $done = $this->attempt(fn () => Organization::runFor($lesson->organization_id, fn () => $service->reschedule(
                $lesson, Carbon::parse($this->formDate), $this->startTime, $this->endTime, $price, $notes,
            )), 'Clase actualizada.');
        } elseif ($this->repeat) {
            $done = $this->attempt(fn () => $service->scheduleSeries(
                $instructor, $facility, array_map('intval', $this->days), $this->startTime, $this->endTime,
                Carbon::parse($this->formDate), Carbon::parse($this->until), $memberIds, $price, $notes, auth()->user(),
            ), 'Clases programadas.');
        } else {
            $done = $this->attempt(fn () => $service->schedule(
                $instructor, $facility, Carbon::parse($this->formDate), $this->startTime, $this->endTime,
                $memberIds, $price, $notes, auth()->user(),
            ), 'Clase programada.');
        }

        if ($done) {
            $this->showForm = false;
            $this->date = $this->formDate;
        }
    }

    // ---- Detalle, asistencia y estado ----

    public function show(int $id): void
    {
        $lesson = $this->findVisible($id);
        $this->detailId = $lesson->id;
        $this->detailSearch = '';
        $this->attendance = $lesson->students->mapWithKeys(fn (Member $m) => [
            $m->id => in_array($m->pivot->attendance, ['ausente', 'aviso'], true) ? 'ausente' : 'presente',
        ])->all();
        $this->showDetail = true;
    }

    public function markGiven(LessonService $service): void
    {
        $lesson = $this->findManageable($this->detailId);
        $this->attempt(fn () => $service->markGiven($lesson, $this->attendance, auth()->user()), 'Clase registrada como dada.');
    }

    public function reopen(LessonService $service): void
    {
        $lesson = $this->findManageable($this->detailId);
        if ($this->attempt(fn () => $service->reopen($lesson), 'La clase volvió a quedar programada.')) {
            $this->show($lesson->id);
        }
    }

    public function cancelLesson(LessonService $service): void
    {
        $lesson = $this->findManageable($this->detailId);

        // Clase de un nivel: se suspende y se avisa a los alumnos.
        $done = $lesson->isLevelLesson()
            ? $this->attempt(fn () => Organization::runFor($lesson->organization_id, fn () => app(LevelLessonService::class)
                ->suspend($lesson->date->copy(), [$lesson->activity_id], null, auth()->user())), 'Clase suspendida; se avisó a los alumnos.')
            : $this->attempt(fn () => $service->cancel($lesson, 'Cancelada desde la agenda'), 'Clase cancelada.');

        if ($done) {
            $this->showDetail = false;
        }
    }

    public function cancelSeries(LessonService $service): void
    {
        $lesson = $this->findManageable($this->detailId);
        abort_unless($lesson->series_id, 404);

        $count = $this->attempt(fn () => Organization::runFor($lesson->organization_id, fn () => $service->cancelSeries($lesson->series, $lesson->date, 'Serie cancelada desde la agenda')));
        if ($count !== null) {
            $this->notify("Se cancelaron {$count} clases de la serie.");
            $this->showDetail = false;
        }
    }

    public function addStudentToLesson(int $memberId, LessonService $service): void
    {
        $lesson = $this->findManageable($this->detailId);
        if ($this->attempt(fn () => $service->addStudent($lesson, $memberId), 'Alumno agregado.')) {
            $this->show($lesson->id);
        }
    }

    public function removeStudentFromLesson(int $memberId, LessonService $service): void
    {
        $lesson = $this->findManageable($this->detailId);
        if ($this->attempt(fn () => $service->removeStudent($lesson, $memberId), 'Alumno quitado de la clase.')) {
            $this->show($lesson->id);
        }
    }

    // ---- Alcance y autorización ----

    /** @return array<int, int> entidades donde ve todas las clases */
    private function seeAllOrganizationIds(): array
    {
        return auth()->user()->organizationIdsWith('agenda.todas');
    }

    /** @return array<int, int> entidades donde ve su propia agenda */
    private function ownOrganizationIds(): array
    {
        return array_values(array_unique([...auth()->user()->organizationIdsWith('agenda.ver'), ...$this->seeAllOrganizationIds()]));
    }

    /** @return array<int, int> */
    private function manageableOrganizationIds(): array
    {
        return array_values(array_unique([...auth()->user()->organizationIdsWith('agenda.gestionar'), ...$this->seeAllOrganizationIds()]));
    }

    /** @return Collection<int, string> id => nombre de las entidades que puede elegir en "Ver" */
    private function scopeOptions(): Collection
    {
        return Organization::query()->whereIn('id', $this->ownOrganizationIds())->orderBy('name')->pluck('name', 'id');
    }

    /** @return array<int, int> */
    private function scopeOrganizationIds(): array
    {
        return $this->scope === 'todas' ? $this->ownOrganizationIds() : array_intersect($this->ownOrganizationIds(), [(int) $this->scope]);
    }

    private function visibleQuery()
    {
        $user = auth()->user();
        $seeAll = $this->seeAllOrganizationIds();

        return Lesson::acrossOrganizations()
            ->whereIn('organization_id', $this->scopeOrganizationIds())
            ->where(fn ($q) => $q->where('instructor_id', $user->id)->orWhereIn('activity_id', $this->myActivityIds())->orWhereIn('organization_id', $seeAll))
            ->when($this->instructorFilter !== '' && $seeAll !== [], fn ($q) => $q->where('instructor_id', (int) $this->instructorFilter));
    }

    /** @var array<int, int>|null */
    private ?array $myActivityIdsCache = null;

    /** @return array<int, int> actividades (de cualquier entidad) donde el usuario es profesor titular o adjunto */
    private function myActivityIds(): array
    {
        $userId = auth()->id();

        return $this->myActivityIdsCache ??= Activity::acrossOrganizations()
            ->where(fn ($q) => $q->where('instructor_id', $userId)->orWhereHas('instructors', fn ($i) => $i->whereKey($userId)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function findVisible(?int $id): Lesson
    {
        $lesson = $this->visibleQuery()->with(['students', 'facility', 'organization', 'instructor', 'series', 'activity'])->find($id);
        abort_unless($lesson, 404);

        return $lesson;
    }

    private function findManageable(?int $id): Lesson
    {
        $lesson = $this->findVisible($id);
        $this->assertCanManage($lesson->instructor_id, $lesson->organization_id, $lesson->activity_id);

        return $lesson;
    }

    private function canManage(int $instructorId, int $organizationId, ?int $activityId = null): bool
    {
        $user = auth()->user();

        return $user->hasPermissionIn('agenda.todas', $organizationId)
            || (($instructorId === $user->id || ($activityId && in_array($activityId, $this->myActivityIds(), true))) && $user->hasPermissionIn('agenda.gestionar', $organizationId));
    }

    private function assertCanManage(int $instructorId, int $organizationId, ?int $activityId = null): void
    {
        abort_unless($this->canManage($instructorId, $organizationId, $activityId), 403);
    }

    /** Sedes del profesor del formulario en las entidades donde se le pueden programar clases. */
    private function formFacilities(): Collection
    {
        $instructor = $this->formInstructorId ? User::find($this->formInstructorId) : null;
        if (! $instructor) {
            return collect();
        }

        return $instructor->facilities()
            ->where('facilities.is_active', true)
            ->with('organization')
            ->get()
            ->filter(fn (Facility $f) => $this->canManage($instructor->id, $f->organization_id))
            ->sortBy(fn (Facility $f) => $f->organization->name.' '.$f->name)
            ->values();
    }

    /** Profesores que puede elegir quien ve todas: los que tienen sedes en esas entidades. */
    private function instructorOptions(): Collection
    {
        $seeAll = $this->seeAllOrganizationIds();
        if ($seeAll === []) {
            return collect();
        }

        return User::query()
            ->whereHas('facilities', fn ($q) => $q->withoutGlobalScope('organization')->whereIn('facilities.organization_id', $seeAll))
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    // ---- Render ----

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(): array
    {
        $date = Carbon::parse($this->date);

        return match ($this->view) {
            'dia' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'mes' => [$date->copy()->startOfMonth()->startOfWeek(), $date->copy()->endOfMonth()->endOfWeek()],
            default => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
        };
    }

    public function render()
    {
        [$from, $to] = $this->range();

        $lessons = $this->visibleQuery()
            ->between($from, $to)
            ->with(['facility', 'organization', 'instructor', 'students', 'activity'])
            ->orderBy('date')->orderBy('start_time')
            ->get();

        $byClub = $this->scope === 'todas';
        $colorKeys = $lessons->map(fn (Lesson $l) => $byClub ? $l->organization_id : $l->facility_id)->unique()->sort()->values();
        $colorFor = fn (Lesson $l) => self::PALETTE[$colorKeys->search($byClub ? $l->organization_id : $l->facility_id) % count(self::PALETTE)];
        $legend = $lessons->unique(fn (Lesson $l) => $byClub ? $l->organization_id : $l->facility_id)
            ->mapWithKeys(fn (Lesson $l) => [($byClub ? $l->organization->name : ($l->facility?->name ?? $l->activity?->name ?? 'Sin sede')) => $colorFor($l)])
            ->sortKeys();

        $active = $lessons->where('status', '!=', LessonStatus::Cancelled);
        $visibleDays = $this->view === 'mes' ? [] : collect(range(0, (int) $from->diffInDays($to)))->map(fn ($i) => $from->copy()->addDays($i));
        $hours = $this->gridHours($active);

        $detail = $this->showDetail && $this->detailId ? $this->visibleQuery()->with(['students', 'facility', 'organization', 'instructor', 'series', 'activity'])->find($this->detailId) : null;

        $instructorId = $this->formInstructorId;
        $formFacility = $this->showForm && $this->facilityId ? $this->formFacilities()->firstWhere('id', $this->facilityId) : null;

        return view('livewire.admin.lessons.calendar', [
            'lessons' => $lessons,
            'lessonsByDay' => $lessons->groupBy(fn (Lesson $l) => $l->date->toDateString()),
            'from' => $from,
            'to' => $to,
            'current' => Carbon::parse($this->date),
            'visibleDays' => $visibleDays,
            'hours' => $hours,
            'colorFor' => $colorFor,
            'legend' => $legend,
            'stats' => [
                'count' => $active->count(),
                'hours' => round($active->sum(fn (Lesson $l) => $l->minutes()) / 60, 1),
                'given' => $active->where('status', LessonStatus::Given)->count(),
                'students' => $active->flatMap->students->pluck('person_id')->unique()->count(),
            ],
            'scopeOptions' => $this->scopeOptions(),
            'instructorOptions' => $this->instructorOptions(),
            'canCreate' => $this->manageableOrganizationIds() !== [],
            'formFacilities' => $this->showForm ? $this->formFacilities() : collect(),
            'formConflicts' => $formFacility && preg_match('/^\d\d:\d\d$/', $this->startTime) && preg_match('/^\d\d:\d\d$/', $this->endTime) && $this->endTime > $this->startTime
                ? app(LessonService::class)->facilityConflicts($formFacility, $this->formDate, $this->startTime, $this->endTime, $this->editingId)
                : collect(),
            'defaultPrice' => $instructorId && $formFacility
                ? Organization::runFor($formFacility->organization_id, fn () => app(LessonService::class)->singlePrice(User::find($instructorId)))
                : null,
            'studentResults' => $this->showForm ? $this->searchPeople($this->studentSearch, $formFacility?->organization_id) : collect(),
            'detail' => $detail,
            'detailCanManage' => $detail ? $this->canManage($detail->instructor_id, $detail->organization_id, $detail->activity_id) : false,
            'detailResults' => $detail ? $this->searchPeople($this->detailSearch, $detail->organization_id)->reject(fn ($r) => $detail->students->contains('person_id', $r['person_id'])) : collect(),
            'detailFees' => $detail && $detail->status === LessonStatus::Given
                ? Fee::acrossOrganizations()->where('lesson_id', $detail->id)->get()->keyBy('member_id')
                : collect(),
        ]);
    }

    /** @return array<int, int> horas (enteras) que muestra la grilla de día y semana */
    private function gridHours(Collection $lessons): array
    {
        $first = (int) min(8, ...$lessons->map(fn (Lesson $l) => (int) substr($l->start_time, 0, 2))->all() ?: [8]);
        $last = (int) max(21, ...$lessons->map(fn (Lesson $l) => (int) ceil((strtotime($l->end_time) - strtotime('00:00:00')) / 3600) - 1)->all() ?: [21]);

        return range(max(0, $first), min(23, $last));
    }
}
