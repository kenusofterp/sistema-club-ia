<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Recuperar contraseña')]
class ForgotPassword extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    public bool $sent = false;

    public function sendLink(): void
    {
        $this->validate();

        $key = 'forgot:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Demasiados intentos. Probá nuevamente en unos minutos.');

            return;
        }
        RateLimiter::hit($key, 300);

        // Por seguridad, la respuesta es la misma exista o no el correo.
        Password::sendResetLink(['email' => $this->email]);

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
