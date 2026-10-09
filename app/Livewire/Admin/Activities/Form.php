<?php

namespace App\Livewire\Admin\Activities;

use App\Models\Activity;
use App\Models\ActivitySchedule;
use App\Models\Facility;
use App\Models\User;
use App\Services\LevelLessonService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Form extends Component
{
    use WithFileUploads;

    public ?Activity $activity = null;

    public string $name = '';

    public string $slug = '';

    public string $summary = '';

    public string $description = '';

    public string $monthly_fee = '0';

    public string $enrollment_fee = '0';

    public ?int $capacity = null;

    public ?int $min_age = null;

    public ?int $max_age = null;

    public ?int $instructor_id = null;

    /** @var array<int, string> profesores a cargo (además del titular) */
    public array $instructorIds = [];

    public bool $is_public = true;

    public bool $allows_enrollment = true;

    public bool $is_active = true;

    public $image = null;

    /** @var array<int, array{day_of_week: int|string, start_time: string, end_time: string, location: string}> */
    public array $schedules = [];

    public function mount(?Activity $activity = null): void
    {
        if ($activity?->exists) {
            $this->activity = $activity;
            $this->name = $activity->name;
            $this->slug = $activity->slug;
            $this->summary = (string) $activity->summary;
            $this->description = (string) $activity->description;
            $this->monthly_fee = (string) $activity->monthly_fee;
            $this->enrollment_fee = (string) $activity->enrollment_fee;
            $this->capacity = $activity->capacity;
            $this->min_age = $activity->min_age;
            $this->max_age = $activity->max_age;
            $this->instructor_id = $activity->instructor_id;
            $this->instructorIds = $activity->instructors()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
            $this->is_public = $activity->is_public;
            $this->allows_enrollment = $activity->allows_enrollment;
            $this->is_active = $activity->is_active;
            $this->schedules = $activity->schedules->map(fn (ActivitySchedule $s) => [
                'day_of_week' => $s->day_of_week,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'location' => (string) $s->location,
                'facility_id' => $s->facility_id,
            ])->all();
        }
    }

    public function updatedName(): void
    {
        if (! $this->activity) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function addSchedule(): void
    {
        $this->schedules[] = ['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'location' => '', 'facility_id' => null];
    }

    public function removeSchedule(int $index): void
    {
        unset($this->schedules[$index]);
        $this->schedules = array_values($this->schedules);
    }

    public function save()
    {
        $this->authorize('actividades.gestionar');

        $data = $this->validate([
            'name' => 'required|string|max:100',
            'slug' => ['required', 'alpha_dash', 'max:120', org_unique('activities')->ignore($this->activity?->id)],
            'summary' => 'nullable|string|max:300',
            'description' => 'nullable|string|max:10000',
            'monthly_fee' => 'required|numeric|min:0|max:99999999',
            'enrollment_fee' => 'required|numeric|min:0|max:99999999',
            'capacity' => 'nullable|integer|min:1|max:100000',
            'min_age' => 'nullable|integer|min:0|max:120',
            'max_age' => 'nullable|integer|min:0|max:120|gte:min_age',
            'instructor_id' => 'nullable|exists:users,id',
            'instructorIds' => 'array',
            'instructorIds.*' => 'integer|exists:users,id',
            'is_public' => 'boolean',
            'allows_enrollment' => 'boolean',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:4096',
            'schedules' => 'array|max:21',
            'schedules.*.day_of_week' => 'required|integer|between:1,7',
            'schedules.*.start_time' => 'required|date_format:H:i',
            'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',
            'schedules.*.location' => 'nullable|string|max:100',
            'schedules.*.facility_id' => ['nullable', org_exists('facilities')],
        ], [], [
            'schedules.*.start_time' => 'hora de inicio',
            'schedules.*.end_time' => 'hora de fin',
        ]);

        // El cupo no puede quedar por debajo de los inscriptos actuales.
        if ($this->activity && $data['capacity'] !== null && $this->activity->activeEnrollments()->count() > $data['capacity']) {
            $this->addError('capacity', 'El cupo no puede ser menor a la cantidad de inscriptos activos.');

            return null;
        }

        $schedules = $data['schedules'];
        unset($data['schedules'], $data['image'], $data['instructorIds']);
        $instructorIds = array_values(array_unique(array_filter([$data['instructor_id'] ?? null, ...array_map('intval', $this->instructorIds)])));
        $data['summary'] = $data['summary'] ?: null;
        $data['description'] = $data['description'] ?: null;

        if ($this->image) {
            $data['image_path'] = $this->image->store('activities', 'public');
        }

        DB::transaction(function () use ($data, $schedules, $instructorIds) {
            $activity = Activity::updateOrCreate(['id' => $this->activity?->id], $data);
            $activity->instructors()->sync($instructorIds);
            $activity->schedules()->delete();
            foreach ($schedules as $schedule) {
                $activity->schedules()->create([
                    ...$schedule,
                    'location' => $schedule['location'] ?: null,
                    'facility_id' => $schedule['facility_id'] ?: null,
                ]);
            }

            // Clases por adelantado para la agenda, la asistencia y los avisos de ausencia.
            app(LevelLessonService::class)->syncSchedules($activity);
        });

        session()->flash('success', 'Cambios guardados.');

        return $this->redirectRoute('admin.activities.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.activities.form', [
            'instructors' => User::whereHas('roles')->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'facilities' => Facility::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ])->title(($this->activity ? 'Editar ' : 'Agregar ').mb_strtolower(activity_label()));
    }
}
