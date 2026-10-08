<?php

namespace App\Livewire\Admin\Members;

use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Services\MemberService;
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

    public function mount(?Member $member = null): void
    {
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

    public function save(MemberService $service)
    {
        $this->authorize($this->member ? 'socios.editar' : 'socios.crear');

        $data = $this->validate();
        unset($data['photo']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->photo) {
            $data['photo_path'] = $this->photo->store('members', 'public');
        }

        $member = $this->attempt(fn () => $this->member
            ? $service->update($this->member, $data)
            : $service->create($data, activate: true));

        if (! $member) {
            return null;
        }

        session()->flash('success', $this->member ? 'Datos del socio actualizados.' : 'Socio registrado correctamente.');

        return $this->redirectRoute('admin.members.show', $member, navigate: true);
    }

    public function render()
    {
        $holders = strlen((string) $this->holder_search) >= 2 && ! $this->holder_id
            ? Member::search($this->holder_search)->whereNull('holder_id')->whereKeyNot($this->member?->id)->limit(6)->get()
            : collect();

        return view('livewire.admin.members.form', [
            'categories' => MemberCategory::active()->get(),
            'holders' => $holders,
        ])->title($this->member ? 'Editar socio' : 'Nuevo socio');
    }
}
