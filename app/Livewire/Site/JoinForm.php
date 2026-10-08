<?php

namespace App\Livewire\Site;

use App\Exceptions\BusinessRuleException;
use App\Models\Member;
use App\Models\MemberCategory;
use App\Services\MemberService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Solicitud de asociación desde la web: crea el socio en estado "pendiente" para que la administración lo apruebe. */
#[Layout('layouts.site', ['transparentHeader' => true])]
#[Title('Asociate')]
class JoinForm extends Component
{
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

    public bool $accept = false;

    public string $website = '';

    public bool $sent = false;

    public function mount(): void
    {
        abort_unless(setting('site.join_enabled', true), 404);
    }

    protected function rules(): array
    {
        return [
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'document_type' => ['required', Rule::in(array_keys(Member::DOCUMENT_TYPES))],
            'document_number' => ['required', 'string', 'max:30', org_unique('members')->where('document_type', $this->document_type)],
            'birth_date' => 'required|date|before:today|after:1900-01-01',
            'gender' => ['nullable', Rule::in(array_keys(Member::GENDERS))],
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:40',
            'address' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:80',
            'member_category_id' => ['required', org_exists('member_categories')->where('is_active', true)],
            'accept' => 'accepted',
        ];
    }

    protected function messages(): array
    {
        return [
            'document_number.unique' => 'Ya existe una solicitud o socio con ese documento. Comunicate con la secretaría.',
            'accept.accepted' => 'Debés aceptar el estatuto y reglamento del club.',
        ];
    }

    public function submit(MemberService $members): void
    {
        $key = 'join:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('accept', 'Demasiados intentos. Probá nuevamente más tarde.');

            return;
        }

        $data = $this->validate();

        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        RateLimiter::hit($key, 3600);
        unset($data['accept']);

        try {
            $members->create([...$data, 'gender' => $data['gender'] ?: null], activate: false);
        } catch (BusinessRuleException $e) {
            $this->addError('member_category_id', $e->getMessage());

            return;
        }

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.site.join-form', [
            'categories' => MemberCategory::active()->get(),
        ]);
    }
}
