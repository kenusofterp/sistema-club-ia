<?php

namespace App\Notifications;

use App\Models\CashSettlement;
use App\Notifications\Concerns\RendersForOrganization;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Un profesor rindió el efectivo cobrado: hay que confirmarlo al recibir el dinero. */
class SettlementSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization, SendsWebPush;

    public function __construct(public CashSettlement $settlement)
    {
        $this->organizationId = $settlement->organization_id;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [$this->pushChannel()];
    }

    protected function pushTitle(object $notifiable): string
    {
        return 'Rendición de efectivo';
    }

    protected function pushBody(object $notifiable): string
    {
        return $this->settlement->user->name.' rinde '.money($this->settlement->amount)." ({$this->settlement->payments_count} cobros)";
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('admin.settlements');
    }

    protected function pushTag(): ?string
    {
        return (string) $this->settlement->id;
    }
}
