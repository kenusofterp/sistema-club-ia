<?php

namespace App\Livewire\Admin\MemberMessages;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Activity;
use App\Models\MemberMessage;
use App\Services\LevelLessonService;
use App\Services\MemberMessageService;
use App\Services\TournamentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Mensaje masivo a alumnos (push + portal): a los que deben, a los que deben un torneo, a un nivel o a todos. */
#[Layout('layouts.admin')]
#[Title('Mensajes a alumnos')]
class Compose extends Component
{
    use InteractsWithUi;

    public string $audience = MemberMessageService::AUDIENCE_DEBTORS;

    #[Url(as: 'torneo')]
    public ?int $tournamentId = null;

    public ?int $activityId = null;

    public string $title = '';

    public string $body = '';

    public function mount(MemberMessageService $service): void
    {
        if ($this->tournamentId) {
            $this->audience = MemberMessageService::AUDIENCE_TOURNAMENT;
        }
        $this->fillTemplate($service);
    }

    public function updatedAudience(MemberMessageService $service): void
    {
        $this->fillTemplate($service);
    }

    public function updatedTournamentId(MemberMessageService $service): void
    {
        $this->fillTemplate($service);
    }

    /** Texto sugerido según el público (se puede editar). */
    private function fillTemplate(MemberMessageService $service): void
    {
        $template = match ($this->audience) {
            MemberMessageService::AUDIENCE_DEBTORS => $service->debtReminder(),
            MemberMessageService::AUDIENCE_TOURNAMENT => ($tournament = $this->tournament())
                ? $service->tournamentReminder($tournament)
                : null,
            default => null,
        };

        if ($template) {
            $this->title = $template['title'];
            $this->body = $template['body'];
        }
    }

    private function tournament()
    {
        return $this->tournamentId
            ? app(TournamentService::class)->queryFor(auth()->user())->find($this->tournamentId)
            : null;
    }

    private function targetId(): ?int
    {
        return match ($this->audience) {
            MemberMessageService::AUDIENCE_TOURNAMENT => $this->tournamentId,
            MemberMessageService::AUDIENCE_LEVEL => $this->activityId,
            default => null,
        };
    }

    public function send(MemberMessageService $service): void
    {
        $this->authorize('mensajes.enviar');
        $this->validate([
            'audience' => 'required|in:deudores,torneo,nivel,todos',
            'tournamentId' => 'required_if:audience,torneo|nullable|integer',
            'activityId' => 'required_if:audience,nivel|nullable|integer',
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:1000',
        ], [
            'tournamentId.required_if' => 'Elegí el torneo.',
            'activityId.required_if' => 'Elegí el '.mb_strtolower(activity_label()).'.',
        ], ['title' => 'título', 'body' => 'mensaje']);

        $message = $this->attempt(fn () => $service->send(
            auth()->user(),
            $service->audienceQuery(auth()->user(), $this->audience, $this->targetId())->get(),
            $this->title,
            $this->body,
            $this->contextLabel(),
        ));

        if ($message) {
            $this->notify('Mensaje enviado a '.$message->recipients()->count().' alumnos.');
        }
    }

    private function contextLabel(): ?string
    {
        return match ($this->audience) {
            MemberMessageService::AUDIENCE_DEBTORS => 'Los que deben',
            MemberMessageService::AUDIENCE_TOURNAMENT => 'Deben el torneo '.($this->tournament()?->name ?? ''),
            MemberMessageService::AUDIENCE_LEVEL => activity_label().' '.(Activity::find($this->activityId)?->name ?? ''),
            default => 'Todos mis alumnos',
        };
    }

    public function render(MemberMessageService $service, TournamentService $tournaments)
    {
        $user = auth()->user();
        $mine = $user->can('agenda.todas') ? null : app(LevelLessonService::class)->activityIdsTaughtBy($user);

        $recipients = collect();
        $ready = match ($this->audience) {
            MemberMessageService::AUDIENCE_TOURNAMENT => (bool) $this->tournamentId,
            MemberMessageService::AUDIENCE_LEVEL => (bool) $this->activityId,
            default => true,
        };
        if ($ready) {
            try {
                $recipients = $service->audienceQuery($user, $this->audience, $this->targetId())->orderBy('last_name')->get();
            } catch (ModelNotFoundException|BusinessRuleException) {
                $recipients = collect();
            }
        }

        return view('livewire.admin.member-messages.compose', [
            'recipients' => $recipients,
            'tournaments' => $tournaments->queryFor($user)->active()->with('days')->latest('id')->limit(30)->get(),
            'activities' => Activity::where('is_active', true)->when($mine !== null, fn ($q) => $q->whereIn('id', $mine))->orderBy('name')->get(['id', 'name']),
            'sent' => MemberMessage::withCount('recipients')
                ->when(! $user->can('agenda.todas'), fn ($q) => $q->where('sender_id', $user->id))
                ->latest('id')->limit(15)->get(),
        ]);
    }
}
