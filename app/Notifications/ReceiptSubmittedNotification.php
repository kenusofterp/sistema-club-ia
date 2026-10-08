<?php

namespace App\Notifications;

use App\Models\PaymentReceipt;
use App\Notifications\Concerns\RendersForOrganization;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Un socio subió un comprobante que hay que revisar. */
class ReceiptSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization, SendsWebPush;

    public function __construct(public PaymentReceipt $receipt)
    {
        $this->organizationId = $receipt->organization_id;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [$this->pushChannel()];
    }

    protected function pushTitle(object $notifiable): string
    {
        return 'Comprobante para revisar';
    }

    protected function pushBody(object $notifiable): string
    {
        return $this->receipt->member->fullName().' informó un pago de '.money($this->receipt->amount);
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('admin.receipts');
    }

    protected function pushTag(): ?string
    {
        return (string) $this->receipt->id;
    }
}
