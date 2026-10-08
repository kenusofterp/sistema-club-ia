<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithUi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Mi perfil')]
class Profile extends Component
{
    use InteractsWithUi, WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public $avatar = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        $data = $this->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:40',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($this->avatar) {
            $data['avatar_path'] = $this->avatar->store('avatars', 'public');
        }
        unset($data['avatar']);

        $user->update([...$data, 'phone' => $data['phone'] ?: null]);
        $this->avatar = null;
        $this->notify('Perfil actualizado.');
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
        return view('livewire.admin.profile');
    }
}
