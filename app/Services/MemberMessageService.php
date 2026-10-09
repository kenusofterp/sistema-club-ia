<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Member;
use App\Models\MemberMessage;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\MemberMessageNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Mensajes masivos a alumnos: se guardan para verlos en el portal y se envían como notificación push
 * (a quienes tienen la app con notificaciones activadas).
 */
class MemberMessageService
{
    public const AUDIENCE_DEBTORS = 'deudores';

    public const AUDIENCE_TOURNAMENT = 'torneo';

    public const AUDIENCE_LEVEL = 'nivel';

    public const AUDIENCE_ALL = 'todos';

    public function __construct(private CashCollectionService $cash, private TournamentService $tournaments) {}

    /** Destinatarios según el público elegido. El profesor (sin agenda.todas) solo alcanza a sus alumnos. */
    public function audienceQuery(User $sender, string $audience, ?int $targetId = null): Builder
    {
        $base = $sender->can('agenda.todas') ? Member::active() : $this->cash->studentsQuery($sender)->active();

        return match ($audience) {
            // Deben cuotas, inscripción o torneos de la entidad ya vencidos o por vencer en la semana
            // (no los cobros propios de un profesor).
            self::AUDIENCE_DEBTORS => $base->whereHas('fees', fn ($q) => $q
                ->whereIn('status', FeeStatus::open())
                ->whereNull('instructor_id')
                ->whereDate('due_date', '<=', today()->addDays(7))),
            self::AUDIENCE_TOURNAMENT => $this->tournaments->debtorsQuery(
                $this->tournaments->queryFor($sender)->findOrFail((int) $targetId)
            )->active(),
            self::AUDIENCE_LEVEL => $base->whereHas('enrollments', fn ($q) => $q
                ->where('status', EnrollmentStatus::Active)
                ->where('activity_id', $targetId)),
            self::AUDIENCE_ALL => $base,
            default => throw new BusinessRuleException('Elegí a quién va el mensaje.'),
        };
    }

    /** @param  Collection<int, Member>  $members */
    public function send(User $sender, Collection $members, string $title, string $body, ?string $context = null): MemberMessage
    {
        if ($members->isEmpty()) {
            throw new BusinessRuleException('No hay destinatarios para este mensaje.');
        }

        $message = DB::transaction(function () use ($sender, $members, $title, $body, $context) {
            $message = MemberMessage::create([
                'sender_id' => $sender->id,
                'title' => $title,
                'body' => $body,
                'context' => $context,
            ]);
            $message->recipients()->attach($members->pluck('id')->unique()->all());

            return $message;
        });

        Notification::send($members, new MemberMessageNotification($message));

        return $message;
    }

    /** @return array{title: string, body: string} texto sugerido para los que deben un torneo */
    public function tournamentReminder(Tournament $tournament): array
    {
        return [
            'title' => "Torneo {$tournament->name}: falta tu pago",
            'body' => "Hola! Te recordamos que el torneo {$tournament->name} cuesta ".money($tournament->price)
                .' y se paga hasta el '.$tournament->payment_due_date->format('d/m').'. Podés pagarlo desde Mi cuenta (transferencia) o en efectivo con el profe.',
        ];
    }

    /** @return array{title: string, body: string} texto sugerido para los que deben cuotas */
    public function debtReminder(): array
    {
        return [
            'title' => 'Tenés pagos pendientes',
            'body' => 'Hola! Te recordamos que tenés pagos pendientes. Podés ver el detalle y pagar desde Mi cuenta (transferencia) o en efectivo con el profe.',
        ];
    }
}
