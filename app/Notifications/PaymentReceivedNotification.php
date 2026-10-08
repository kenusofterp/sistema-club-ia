<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\RendersForOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization;

    public function __construct(public Payment $payment)
    {
        $this->organizationId = $payment->organization_id;
        $this->afterCommit();
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
        $payment = $this->payment->loadMissing('fees');

        $message = (new MailMessage)
            ->subject("Recibo de pago {$payment->receipt_number}")
            ->greeting("Hola {$notifiable->first_name},")
            ->line('Registramos tu pago. ¡Gracias!')
            ->line("**Recibo:** {$payment->receipt_number}")
            ->line('**Fecha:** '.$payment->payment_date->format('d/m/Y'))
            ->line('**Importe:** '.money($payment->amount))
            ->line('**Medio de pago:** '.$payment->method->label())
            ->line('**Conceptos:**');

        foreach ($payment->fees as $fee) {
            $message->line("- {$fee->concept}: ".money($fee->pivot->amount));
        }

        return $message->action('Ver mi cuenta', route('portal.fees'));
    }
}
