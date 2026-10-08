<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación push al teléfono (PWA instalada). Cada notificación define título, texto y la URL que abre.
 * Si el destinatario no activó las notificaciones, el canal no envía nada.
 */
trait SendsWebPush
{
    abstract protected function pushTitle(object $notifiable): string;

    abstract protected function pushBody(object $notifiable): string;

    abstract protected function pushUrl(object $notifiable): string;

    protected function pushChannel(): string
    {
        return WebPushChannel::class;
    }

    protected function pushTag(): ?string
    {
        return null;
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return $this->inOrganization(fn () => (new WebPushMessage)
            ->title($this->pushTitle($notifiable))
            ->body($this->pushBody($notifiable))
            ->icon(route('pwa.icon', 192))
            ->badge(route('pwa.icon', 192))
            ->tag(class_basename($this).'-'.($this->pushTag() ?? uniqid()))
            ->data(['url' => $this->pushUrl($notifiable)]));
    }
}
