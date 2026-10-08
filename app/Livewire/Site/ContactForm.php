<?php

namespace App\Livewire\Site;

use App\Models\ContactMessage;
use App\Notifications\NewContactMessageNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ContactForm extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|email|max:150')]
    public string $email = '';

    #[Validate('nullable|string|max:40')]
    public string $phone = '';

    #[Validate('nullable|string|max:150')]
    public string $subject = '';

    #[Validate('required|string|min:10|max:3000')]
    public string $message = '';

    /** Campo trampa anti-spam: los humanos no lo ven. */
    public string $website = '';

    public bool $sent = false;

    public function send(): void
    {
        $key = 'contact:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('message', 'Enviaste demasiados mensajes. Intentá nuevamente en unos minutos.');

            return;
        }

        $data = $this->validate();

        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        RateLimiter::hit($key, 600);

        $contactMessage = ContactMessage::create([...$data, 'ip_address' => request()->ip()]);

        if ($to = setting('contact.email')) {
            Notification::route('mail', $to)->notify(new NewContactMessageNotification($contactMessage));
        }

        $this->reset(['name', 'email', 'phone', 'subject', 'message']);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.site.contact-form');
    }
}
