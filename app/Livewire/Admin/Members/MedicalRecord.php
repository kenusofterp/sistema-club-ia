<?php

namespace App\Livewire\Admin\Members;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\MedicalFormField;
use App\Models\Member;
use App\Services\MedicalRecordService;
use Livewire\Component;

/** Pestaña «Ficha médica» de la ficha del socio: ver y modificar sus respuestas. */
class MedicalRecord extends Component
{
    use InteractsWithUi;

    public Member $member;

    public bool $editing = false;

    /** @var array<int, mixed> id de campo => valor */
    public array $medical = [];

    public function edit(MedicalRecordService $service): void
    {
        $this->authorize('fichas_medicas.editar');
        $this->medical = $service->formValues($service->activeFields(), $this->member);
        $this->resetValidation();
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->resetValidation();
    }

    public function save(MedicalRecordService $service): void
    {
        $this->authorize('fichas_medicas.editar');
        $fields = $service->activeFields();
        $this->validate($service->rules($fields), [], $service->attributes($fields));

        $service->save($this->member, $this->medical);
        $this->editing = false;
        $this->notify('Ficha médica guardada.');
    }

    public function render(MedicalRecordService $service)
    {
        $this->member->loadMissing('medicalRecord.editor');
        $record = $this->member->medicalRecord;
        $active = $service->activeFields();

        // Además de los campos vigentes, se muestran los desactivados que tienen respuesta.
        $answeredIds = array_keys($record?->answers ?? []);
        $retired = $answeredIds
            ? MedicalFormField::where('is_active', false)->whereKey($answeredIds)->ordered()->get()
            : collect();

        return view('livewire.admin.members.medical-record', [
            'record' => $record,
            'fields' => $active,
            'retired' => $retired,
            'missing' => $service->missingRequired($this->member, $active),
        ]);
    }
}
