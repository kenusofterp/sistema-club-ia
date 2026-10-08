<?php

namespace App\Notifications;

use App\Notifications\Concerns\RendersForOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class FeeReminderNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization;

    /** @param  Collection<int, array{concept: string, balance: string, due_date: string, overdue: bool}>  $fees */
    public function __construct(public Collection $fees, public string $total) {}

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
        $hasOverdue = $this->fees->contains('overdue', true);

        $message = (new MailMessage)
            ->subject($hasOverdue ? 'Tenés cuotas vencidas' : 'Recordatorio de vencimiento de cuotas')
            ->greeting("Hola {$notifiable->first_name},")
            ->line($hasOverdue
                ? 'Te recordamos que registrás los siguientes saldos pendientes:'
                : 'Te recordamos que los siguientes cargos vencen en los próximos días:');

        foreach ($this->fees as $fee) {
            $message->line("- {$fee['concept']} (vence {$fee['due_date']}): ".money($fee['balance']).($fee['overdue'] ? ' — **vencida**' : ''));
        }

        return $message
            ->line('**Total:** '.money($this->total))
            ->action('Ver mi cuenta', route('portal.fees'))
            ->line('Si ya realizaste el pago, desestimá este mensaje.');
    }
}
