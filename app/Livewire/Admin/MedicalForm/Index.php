<?php

namespace App\Livewire\Admin\MedicalForm;

use App\Enums\MedicalFieldType;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\MedicalFormField;
use App\Services\MedicalRecordService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Armado del modelo de ficha médica de la entidad: preguntas, tipo, orden y obligatoriedad. */
#[Layout('layouts.admin')]
#[Title('Ficha médica')]
class Index extends Component
{
    use InteractsWithUi;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $label = '';

    public string $type = 'texto';

    public string $section = '';

    public string $help = '';

    /** Opciones de la lista, una por renglón. */
    public string $optionsText = '';

    public bool $is_required = false;

    public bool $is_active = true;

    /** Valores de la vista previa (no se guardan). */
    public array $preview = [];

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $field = MedicalFormField::findOrFail($id);
        $this->editingId = $field->id;
        $this->fill([
            'label' => $field->label,
            'type' => $field->type->value,
            'section' => (string) $field->section,
            'help' => (string) $field->help,
            'optionsText' => implode("\n", $field->options ?? []),
            'is_required' => $field->is_required,
            'is_active' => $field->is_active,
        ]);
        $this->showForm = true;
    }

    public function save(MedicalRecordService $service): void
    {
        $this->authorize('fichas_medicas.configurar');

        $data = $this->validate([
            'label' => 'required|string|max:150',
            'type' => ['required', Rule::in(MedicalFieldType::values())],
            'section' => 'nullable|string|max:80',
            'help' => 'nullable|string|max:255',
            'optionsText' => 'nullable|string|max:3000',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ], [], ['label' => 'pregunta', 'section' => 'sección', 'help' => 'ayuda', 'optionsText' => 'opciones']);

        $data['options'] = preg_split('/\r\n|\r|\n/', $data['optionsText'] ?? '');
        unset($data['optionsText']);

        $field = $this->editingId ? MedicalFormField::findOrFail($this->editingId) : null;
        if ($this->attempt(fn () => $service->saveField($field, $data), $field ? 'Campo actualizado.' : 'Campo agregado a la ficha.')) {
            $this->showForm = false;
        }
    }

    public function delete(int $id, MedicalRecordService $service): void
    {
        $this->authorize('fichas_medicas.configurar');
        $this->attempt(fn () => $service->deleteField(MedicalFormField::findOrFail($id)), 'Campo eliminado.');
    }

    public function toggle(int $id): void
    {
        $this->authorize('fichas_medicas.configurar');
        $field = MedicalFormField::findOrFail($id);
        $field->update(['is_active' => ! $field->is_active]);
        $this->notify($field->is_active ? 'El campo vuelve a pedirse en la ficha.' : 'Campo desactivado: ya no se pide, pero lo cargado se conserva.');
    }

    public function move(int $id, int $direction, MedicalRecordService $service): void
    {
        $this->authorize('fichas_medicas.configurar');
        $service->move(MedicalFormField::findOrFail($id), $direction);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'label', 'type', 'section', 'help', 'optionsText', 'is_required', 'is_active']);
        $this->resetValidation();
    }

    public function render(MedicalRecordService $service)
    {
        $fields = MedicalFormField::ordered()->get();
        $active = $fields->where('is_active', true)->values();
        $this->preview += $service->formValues($active);

        return view('livewire.admin.medical-form.index', [
            'fields' => $fields,
            'activeFields' => $active,
            'sections' => $fields->pluck('section')->filter()->unique()->values(),
        ]);
    }
}
