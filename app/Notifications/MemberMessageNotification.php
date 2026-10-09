<?php

namespace App\Notifications;

use App\Models\MemberMessage;
use App\Notifications\Concerns\RendersForOrganization;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Mensaje del profesor o la administración a un alumno: notificación push (también queda en el portal). */
class MemberMessageNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization, SendsWebPush;

    public function __construct(public MemberMessage $message)
    {
        $this->organizationId = $message->organization_id;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [$this->pushChannel()];
    }

    protected function pushTitle(object $notifiable): string
    {
        return $this->message->title;
    }

    protected function pushBody(object $notifiable): string
    {
        return Str::limit($this->message->body, 180);
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('portal.messages');
    }

    protected function pushTag(): ?string
    {
        return 'msg-'.$this->message->id;
    }
}
