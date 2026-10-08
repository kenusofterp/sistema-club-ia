<?php

namespace App\Notifications;

use App\Notifications\Concerns\RendersForOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alta en el portal. Con $token: cuenta nueva (debe crear su contraseña).
 * Sin $token: la persona ya tenía cuenta y se le agregó la membresía de otra entidad.
 */
class WelcomeMemberNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization;

    public function __construct(public ?string $token, ?int $organizationId = null)
    {
        $this->organizationId = $organizationId;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->inOrganization(function () use ($notifiable) {
            $club = setting('site.name', config('app.name'));

            $message = (new MailMessage)
                ->subject("Bienvenido/a a {$club}")
                ->greeting("¡Hola {$notifiable->name}!");

            if (! $this->token) {
                return $message
                    ->line("Tu membresía en {$club} ya está disponible en tu cuenta.")
                    ->line('Ingresá con tu correo y tu contraseña habitual; desde el portal podés elegir la entidad para ver tu carnet, cuotas y planes.')
                    ->action('Ir al portal', route('portal.dashboard'));
            }

            return $message
                ->line("Tu cuenta en el portal de socios de {$club} ya está habilitada.")
                ->line('Desde el portal podés consultar tus cuotas, inscribirte en actividades, reservar instalaciones y ver tu carnet digital.')
                ->action('Crear mi contraseña', route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]))
                ->line('El enlace vence en '.config('auth.passwords.users.expire').' minutos. Si vence, usá la opción "¿Olvidaste tu contraseña?".');
        });
    }
}
