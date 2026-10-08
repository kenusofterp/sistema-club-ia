<?php

namespace App\Notifications;

use App\Notifications\Concerns\RendersForOrganization;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** No hay clase: se avisa a los alumnos inscriptos (push y correo). */
class LessonsSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersForOrganization, SendsWebPush;

    /** @param  array<int, string>  $classes  descripción de cada clase suspendida (nivel y horario) */
    public function __construct(public string $date, public array $classes, public ?string $reason, int $organizationId)
    {
        $this->organizationId = $organizationId;
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return array_values(array_filter([$this->pushChannel(), $notifiable->email ? 'mail' : null]));
    }

    private function day(): string
    {
        $date = Carbon::parse($this->date);

        return match (true) {
            $date->isToday() => 'hoy',
            $date->isTomorrow() => 'mañana',
            default => 'el '.$date->translatedFormat('l j \d\e F'),
        };
    }

    protected function pushTitle(object $notifiable): string
    {
        return 'No hay clase '.$this->day();
    }

    protected function pushBody(object $notifiable): string
    {
        return implode(', ', $this->classes).($this->reason ? " · {$this->reason}" : '');
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('portal.lessons');
    }

    protected function pushTag(): ?string
    {
        return $this->date;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->inOrganization(function () use ($notifiable) {
            $message = (new MailMessage)
                ->subject('No hay clase '.$this->day())
                ->greeting("Hola {$notifiable->first_name},")
                ->line('Te avisamos que '.$this->day().' no hay clase de: '.implode(', ', $this->classes).'.');

            if ($this->reason) {
                $message->line("Motivo: {$this->reason}");
            }

            return $message->action('Ver mis clases', route('portal.lessons'));
        });
    }
}
