<?php

namespace App\Livewire\Admin\Facilities;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Facility;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Instalaciones')]
class Index extends Component
{
    use InteractsWithUi, WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $hourly_rate = '0';

    public ?int $capacity = null;

    public string $opens_at = '08:00';

    public string $closes_at = '22:00';

    public int $slot_minutes = 60;

    public int $max_slots_per_booking = 2;

    public bool $is_public = true;

    public bool $is_bookable = true;

    public bool $is_active = true;

    public $image = null;

    public ?string $currentImage = null;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $f = Facility::findOrFail($id);
        $this->editingId = $f->id;
        $this->fill([
            'name' => $f->name, 'description' => (string) $f->description, 'hourly_rate' => (string) $f->hourly_rate,
            'capacity' => $f->capacity, 'opens_at' => substr($f->opens_at, 0, 5), 'closes_at' => substr($f->closes_at, 0, 5),
            'slot_minutes' => $f->slot_minutes, 'max_slots_per_booking' => $f->max_slots_per_booking,
            'is_public' => $f->is_public, 'is_bookable' => $f->is_bookable, 'is_active' => $f->is_active,
        ]);
        $this->currentImage = $f->imageUrl();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('instalaciones.gestionar');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => 'nullable|string|max:2000',
            'hourly_rate' => 'required|numeric|min:0|max:99999999',
            'capacity' => 'nullable|integer|min:1',
            'opens_at' => 'required|date_format:H:i',
            'closes_at' => 'required|date_format:H:i|after:opens_at',
            'slot_minutes' => ['required', 'integer', Rule::in([30, 45, 60, 90, 120, 180, 240])],
            'max_slots_per_booking' => 'required|integer|min:1|max:12',
            'is_public' => 'boolean',
            'is_bookable' => 'boolean',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:4096',
        ]);
        unset($data['image']);
        $data['description'] = $data['description'] ?: null;

        if ($this->image) {
            $data['image_path'] = $this->image->store('facilities', 'public');
        }

        if (! $this->editingId) {
            $base = Str::slug($data['name']);
            $data['slug'] = Facility::withTrashed()->where('slug', $base)->exists() ? $base.'-'.Str::lower(Str::random(4)) : $base;
        }

        Facility::updateOrCreate(['id' => $this->editingId], $data);

        $this->showForm = false;
        $this->notify('Instalación guardada.');
    }

    public function delete(int $id): void
    {
        $this->authorize('instalaciones.gestionar');
        $facility = Facility::findOrFail($id);

        if ($facility->reservations()->confirmed()->whereDate('date', '>=', today())->exists()) {
            $this->notify('La instalación tiene reservas futuras. Cancelalas o desactivá la instalación.', 'error');

            return;
        }

        $facility->delete();
        $this->notify('Instalación eliminada.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'hourly_rate', 'capacity', 'opens_at', 'closes_at', 'slot_minutes', 'max_slots_per_booking', 'is_public', 'is_bookable', 'is_active', 'image', 'currentImage']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.facilities.index', ['facilities' => Facility::orderBy('name')->get()]);
    }
}
