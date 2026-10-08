<?php

namespace App\Notifications;

use App\Models\Lesson;
use App\Models\Member;
use App\Notifications\Concerns\RendersForOrganization;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Un alumno avisó que no va a una clase: se avisa a los profesores. */
class AbsenceNoticeNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization, SendsWebPush;

    public function __construct(public Lesson $lesson, public Member $member, public ?string $reason = null)
    {
        $this->organizationId = $lesson->organization_id;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [$this->pushChannel()];
    }

    protected function pushTitle(object $notifiable): string
    {
        return "{$this->member->fullName()} no va a clase";
    }

    protected function pushBody(object $notifiable): string
    {
        $when = $this->lesson->date->translatedFormat('l j/m').' '.substr($this->lesson->start_time, 0, 5);
        $title = $this->lesson->activity?->name ?? 'Clase';

        return "{$title} · {$when}".($this->reason ? " · {$this->reason}" : '');
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('admin.lessons.today', ['fecha' => $this->lesson->date->toDateString()]);
    }

    protected function pushTag(): ?string
    {
        return "{$this->lesson->id}-{$this->member->id}";
    }
}
