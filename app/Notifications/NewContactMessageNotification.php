<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use App\Notifications\Concerns\RendersForOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization;

    public function __construct(public ContactMessage $contactMessage)
    {
        $this->organizationId = $contactMessage->organization_id;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Si no se fijó la entidad, se usa la del destinatario (socio).
        $this->organizationId ??= $notifiable->organization_id ?? null;

        return $this->inOrganization(fn () => $this->buildMail($notifiable));
    }

    private function buildMail(object $notifiable): MailMessage
    {
        $m = $this->contactMessage;

        return (new MailMessage)
            ->subject('Nuevo mensaje desde la web: '.($m->subject ?: 'Consulta'))
            ->replyTo($m->email, $m->name)
            ->line("**De:** {$m->name} <{$m->email}>".($m->phone ? " — Tel: {$m->phone}" : ''))
            ->line($m->message)
            ->action('Ver en el sistema', route('admin.messages'));
    }
}
