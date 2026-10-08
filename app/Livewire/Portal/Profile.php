<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Portal\Concerns\ForCurrentMember;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/** El socio puede actualizar sus datos de contacto; los datos de identidad los modifica la secretaría. */
#[Layout('layouts.portal')]
#[Title('Mis datos')]
class Profile extends Component
{
    use ForCurrentMember, InteractsWithUi, WithFileUploads;

    public string $phone = '';

    public string $address = '';

    public string $city = '';

    public string $emergency_contact_name = '';

    public string $emergency_contact_phone = '';

    public $photo = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $member = $this->member();
        foreach (['phone', 'address', 'city', 'emergency_contact_name', 'emergency_contact_phone'] as $field) {
            $this->{$field} = (string) $member->{$field};
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'phone' => 'required|string|max:40',
            'address' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:80',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_phone' => 'nullable|string|max:40',
            'photo' => 'nullable|image|max:3072',
        ]);
        unset($data['photo']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->photo) {
            $data['photo_path'] = $this->photo->store('members', 'public');
        }

        $this->member()->update($data);
        auth()->user()->update(['phone' => $data['phone']]);
        $this->photo = null;
        $this->notify('Datos actualizados.');
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        auth()->user()->update(['password' => Hash::make($this->password)]);
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->notify('Contraseña actualizada.');
    }

    public function render()
    {
        return view('livewire.portal.profile', ['member' => $this->member()->load('category')]);
    }
}
