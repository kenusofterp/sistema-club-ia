<?php

namespace App\Notifications;

use App\Enums\ReceiptStatus;
use App\Models\PaymentReceipt;
use App\Notifications\Concerns\RendersForOrganization;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Resultado de la revisión de un comprobante (acreditado o rechazado). */
class ReceiptReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization, SendsWebPush;

    public function __construct(public PaymentReceipt $receipt)
    {
        $this->organizationId = $receipt->organization_id;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        // Si se acreditó, el recibo ya llega por correo (PaymentReceivedNotification).
        $rejected = $this->receipt->status === ReceiptStatus::Rejected;

        return array_values(array_filter([$this->pushChannel(), $rejected && $notifiable->email ? 'mail' : null]));
    }

    protected function pushTitle(object $notifiable): string
    {
        return $this->receipt->status === ReceiptStatus::Approved ? 'Pago acreditado' : 'Comprobante rechazado';
    }

    protected function pushBody(object $notifiable): string
    {
        return $this->receipt->status === ReceiptStatus::Approved
            ? 'Acreditamos tu pago de '.money($this->receipt->amount).'. ¡Gracias!'
            : 'No pudimos acreditar tu pago de '.money($this->receipt->amount).': '.$this->receipt->reject_reason;
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('portal.fees');
    }

    protected function pushTag(): ?string
    {
        return (string) $this->receipt->id;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->inOrganization(fn () => (new MailMessage)
            ->subject('Comprobante rechazado')
            ->greeting("Hola {$notifiable->first_name},")
            ->line('No pudimos acreditar el pago de '.money($this->receipt->amount).' que informaste el '.$this->receipt->created_at->format('d/m/Y').'.')
            ->line('Motivo: '.$this->receipt->reject_reason)
            ->action('Ver mi cuenta', route('portal.fees')));
    }
}
