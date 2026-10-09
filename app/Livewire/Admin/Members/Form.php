<?php

namespace App\Livewire\Admin\Members;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Models\Person;
use App\Services\MedicalRecordService;
use App\Services\MemberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class Form extends Component
{
    use InteractsWithUi, WithFileUploads;

    public ?Member $member = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $document_type = 'DNI';

    public string $document_number = '';

    public string $birth_date = '';

    public string $gender = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $city = '';

    public ?int $member_category_id = null;

    public ?string $holder_search = '';

    public ?int $holder_id = null;

    public string $relationship = '';

    public string $emergency_contact_name = '';

    public string $emergency_contact_phone = '';

    public string $medical_notes = '';

    public string $notes = '';

    public string $admission_date = '';

    public $photo = null;

    /** @var array<int, mixed> ficha médica: id de campo => valor */
    public array $medical = [];

    public function mount(MedicalRecordService $medicalService, ?Member $member = null): void
    {
        if (auth()->user()->can('fichas_medicas.editar')) {
            $this->medical = $medicalService->formValues($medicalService->activeFields(), $member?->exists ? $member : null);
        }

        if ($member?->exists) {
            $this->member = $member;
            foreach ([
                'first_name', 'last_name', 'document_type', 'document_number', 'gender', 'email', 'phone', 'address', 'city',
                'relationship', 'emergency_contact_name', 'emergency_contact_phone', 'medical_notes', 'notes',
            ] as $field) {
                $this->{$field} = (string) $member->{$field};
            }
            $this->member_category_id = $member->member_category_id;
            $this->holder_id = $member->holder_id;
            $this->birth_date = $member->birth_date->toDateString();
            $this->admission_date = (string) $member->admission_date?->toDateString();
            $this->holder_search = $member->holder?->fullName() ?? '';
        } else {
            $this->admission_date = today()->toDateString();
        }
    }

    protected function rules(): array
    {
        return [
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'document_type' => ['required', Rule::in(array_keys(Member::DOCUMENT_TYPES))],
            'document_number' => ['required', 'string', 'max:30',
                org_unique('members')->where('document_type', $this->document_type)->ignore($this->member?->id)],
            'birth_date' => 'required|date|before:today|after:1900-01-01',
            'gender' => ['nullable', Rule::in(array_keys(Member::GENDERS))],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:80',
            'member_category_id' => ['required', org_exists('member_categories')],
            'holder_id' => ['nullable', org_exists('members'), Rule::notIn(array_filter([$this->member?->id]))],
            'relationship' => 'nullable|string|max:40',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_phone' => 'nullable|string|max:40',
            'medical_notes' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'admission_date' => 'nullable|date|before_or_equal:today',
            'photo' => 'nullable|image|max:3072',
        ];
    }

    /** Persona ya registrada en el sistema (otra entidad) con el documento ingresado. */
    public ?string $existingPerson = null;

    public function updatedDocumentNumber(): void
    {
        $this->fillFromExistingPerson();
    }

    public function updatedDocumentType(): void
    {
        $this->fillFromExistingPerson();
    }

    /** En el alta, si la persona ya existe en el sistema se completan sus datos personales. */
    private function fillFromExistingPerson(): void
    {
        $this->existingPerson = null;
        if ($this->member) {
            return;
        }

        $person = Person::findByDocument($this->document_type, $this->document_number);
        if (! $person) {
            return;
        }

        foreach ($person->personalData() as $field => $value) {
            if ($field !== 'photo_path' && property_exists($this, $field)) {
                $this->{$field} = (string) $value;
            }
        }
        $this->existingPerson = $person->fullName();
    }

    // Alta rápida de categoría sin salir de la pantalla del socio.
    public bool $showCategoryModal = false;

    /** @var array{name: string, description: string, monthly_fee: string, admission_fee: string, min_age: ?int, max_age: ?int} */
    public array $newCategory = [];

    public function openCategoryModal(): void
    {
        $this->authorize('categorias.gestionar');
        $this->newCategory = ['name' => '', 'description' => '', 'monthly_fee' => '0', 'admission_fee' => '0', 'min_age' => null, 'max_age' => null];
        $this->resetValidation(array_map(fn ($key) => "newCategory.{$key}", array_keys($this->newCategory)));
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        $this->authorize('categorias.gestionar');

        $data = $this->validate([
            'newCategory.name' => ['required', 'string', 'max:80', org_unique('member_categories', 'name')],
            'newCategory.description' => 'nullable|string|max:255',
            'newCategory.monthly_fee' => 'required|numeric|min:0|max:99999999',
            'newCategory.admission_fee' => 'required|numeric|min:0|max:99999999',
            'newCategory.min_age' => 'nullable|integer|min:0|max:120',
            'newCategory.max_age' => 'nullable|integer|min:0|max:120|gte:newCategory.min_age',
        ], [], [
            'newCategory.name' => 'nombre',
            'newCategory.description' => 'descripción',
            'newCategory.monthly_fee' => 'cuota mensual',
            'newCategory.admission_fee' => 'derecho de ingreso',
            'newCategory.min_age' => 'edad mínima',
            'newCategory.max_age' => 'edad máxima',
        ])['newCategory'];
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);

        $category = MemberCategory::create([
            ...$data,
            'is_active' => true,
            'sort_order' => (int) MemberCategory::max('sort_order') + 1,
        ]);

        $this->member_category_id = $category->id;
        $this->showCategoryModal = false;
        $this->notify("Categoría «{$category->name}» creada y seleccionada.");
    }

    public function selectHolder(int $id): void
    {
        $holder = Member::find($id);
        $this->holder_id = $holder?->id;
        $this->holder_search = $holder?->fullName() ?? '';
    }

    public function clearHolder(): void
    {
        $this->holder_id = null;
        $this->holder_search = '';
        $this->relationship = '';
    }

    public function save(MemberService $service, MedicalRecordService $medicalService)
    {
        $this->authorize($this->member ? 'socios.editar' : 'socios.crear');

        // La ficha médica es opcional en el alta: si no se completó nada, queda pendiente y no se valida.
        $medicalFields = auth()->user()->can('fichas_medicas.editar') ? $medicalService->activeFields() : collect();
        $checkMedical = $medicalFields->isNotEmpty() && ($this->member?->medicalRecord || ! $medicalService->isBlank($this->medical));

        $data = $this->validate(
            [...$this->rules(), ...($checkMedical ? $medicalService->rules($medicalFields) : [])],
            [],
            $checkMedical ? $medicalService->attributes($medicalFields) : [],
        );
        unset($data['photo'], $data['medical']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->photo) {
            $data['photo_path'] = $this->photo->store('members', 'public');
        }

        $member = $this->attempt(fn () => DB::transaction(function () use ($service, $medicalService, $data, $checkMedical, $medicalFields) {
            $member = $this->member
                ? $service->update($this->member, $data)
                : $service->create($data, activate: true);

            if ($checkMedical) {
                $medicalService->save($member, array_intersect_key($this->medical, $medicalFields->keyBy('id')->all()), onlyIfFilled: true);
            }

            return $member;
        }));

        if (! $member) {
            return null;
        }

        session()->flash('success', $this->member ? 'Datos del socio actualizados.' : 'Socio registrado correctamente.');

        return $this->redirectRoute('admin.members.show', $member, navigate: true);
    }

    public function render(MedicalRecordService $medicalService)
    {
        $holders = strlen((string) $this->holder_search) >= 2 && ! $this->holder_id
            ? Member::search($this->holder_search)->whereNull('holder_id')->whereKeyNot($this->member?->id)->limit(6)->get()
            : collect();

        return view('livewire.admin.members.form', [
            'categories' => MemberCategory::active()->get(),
            'holders' => $holders,
            'medicalFields' => auth()->user()->can('fichas_medicas.editar') ? $medicalService->activeFields() : collect(),
        ])->title($this->member ? 'Editar socio' : 'Nuevo socio');
    }
}
