<?php

namespace App\Services;

use App\Enums\AccessResult;
use App\Models\AccessLog;
use App\Models\Activity;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AccessService
{
    /** Busca un socio por el código del carnet (UUID del QR), número de socio o documento. */
    public function findMember(string $code): ?Member
    {
        $code = trim($code);

        // El QR del carnet contiene la URL de verificación; nos quedamos con el UUID final.
        if (str_contains($code, '/')) {
            $code = Str::afterLast(rtrim($code, '/'), '/');
        }

        if (Str::isUuid($code)) {
            $member = Member::with('category')->where('uuid', $code)->first();
            if ($member) {
                return $member;
            }

            // Carnet de otra entidad: se identifica a la persona y se busca su membresía en esta.
            $other = Member::acrossOrganizations()->where('uuid', $code)->first();
            if (! $other) {
                return null;
            }

            return Member::with('category')->where('person_id', $other->person_id)->first();
        }

        return Member::with('category')
            ->where(fn ($q) => $q->where('member_number', $code)->orWhere('document_number', $code))
            ->first();
    }

    /** ¿El ingreso exige un plan de gimnasio vigente según el modo de funcionamiento? */
    public function requiresPlan(): bool
    {
        return match (club_mode()) {
            'gimnasio' => true,
            'mixto' => (bool) setting('gym.access_requires_plan', false),
            default => false,
        };
    }

    /**
     * Evalúa si el socio puede ingresar.
     *
     * @return array{result: AccessResult, reason: string|null, subscription_id: int|null, activity_id: int|null}
     */
    public function evaluate(Member $member, ?Carbon $at = null): array
    {
        $at ??= now();
        $deny = fn (string $reason) => ['result' => AccessResult::Denied, 'reason' => $reason, 'subscription_id' => null, 'activity_id' => null];

        if (! $member->isActive()) {
            return $deny('Socio '.mb_strtolower($member->status->label()));
        }

        $maxOverdue = (int) setting('club.max_overdue_for_access', 2);
        $overdue = $member->overdueFeesCount();
        if ($maxOverdue > 0 && $overdue >= $maxOverdue) {
            return $deny("Registra {$overdue} cuotas vencidas");
        }

        $warning = $overdue > 0 ? "Atención: {$overdue} cuota(s) vencida(s)" : null;

        if (uses_gym()) {
            // Los packs de clases de un profesor no habilitan el ingreso: se consumen en la agenda.
            $subscriptions = $member->subscriptions()->current($at->copy()->startOfDay())
                ->whereHas('plan', fn ($q) => $q->whereNull('instructor_id'))
                ->with('plan.activities.schedules')->get();
            $reasons = [];

            foreach ($subscriptions as $subscription) {
                $check = $this->checkSubscription($subscription, $at);
                if ($check['ok']) {
                    return [
                        'result' => AccessResult::Granted,
                        'reason' => collect([$check['detail'], $warning])->filter()->implode(' · ') ?: null,
                        'subscription_id' => $subscription->id,
                        'activity_id' => $check['activity_id'],
                    ];
                }
                $reasons[] = $check['detail'];
            }

            if ($this->requiresPlan()) {
                return $deny($reasons ? implode(' · ', $reasons) : 'No tiene un plan vigente');
            }
        }

        return ['result' => AccessResult::Granted, 'reason' => $warning, 'subscription_id' => null, 'activity_id' => null];
    }

    /**
     * Valida un plan concreto en un momento dado: franja horaria, clases incluidas y límite de visitas.
     *
     * @return array{ok: bool, detail: string, activity_id: int|null}
     */
    public function checkSubscription(Subscription $subscription, Carbon $at): array
    {
        $plan = $subscription->plan;
        $fail = fn (string $detail) => ['ok' => false, 'detail' => "{$plan->name}: {$detail}", 'activity_id' => null];

        if (! $plan->allowsTime($at)) {
            return $fail('fuera del horario del plan ('.implode('; ', $plan->windowsLabels()).')');
        }

        $activity = null;
        if (! $plan->access_type->allowsFreeAccess()) {
            $activity = $this->classInProgress($plan->activities, $at);
            if (! $activity) {
                return $fail('no hay una clase incluida en este horario');
            }
        }

        $remaining = $subscription->visitsRemaining($at);
        $enteredToday = $subscription->accessLogs()->where('result', AccessResult::Granted)->whereDate('checked_at', $at)->exists();

        // Reingresar el mismo día no consume otra visita.
        if ($remaining === 0 && ! $enteredToday) {
            return $fail('alcanzó el límite de '.$plan->visitLimitLabel());
        }

        $detail = $plan->name.($activity ? " · Clase: {$activity->name}" : '');
        if ($remaining !== null) {
            $left = $enteredToday ? $remaining : $remaining - 1;
            $detail .= " · Quedan {$left} visita(s)";
        }
        $detail .= ' · Vence '.$subscription->end_date->format('d/m');

        return ['ok' => true, 'detail' => $detail, 'activity_id' => $activity?->id];
    }

    /** Clase incluida que está por empezar (dentro de la tolerancia) o en curso. */
    private function classInProgress($activities, Carbon $at): ?Activity
    {
        $tolerance = (int) setting('gym.class_checkin_tolerance', 20);
        $time = $at->format('H:i:s');
        $earliest = $at->copy()->addMinutes($tolerance)->format('H:i:s');

        foreach ($activities as $activity) {
            foreach ($activity->schedules as $schedule) {
                if ($schedule->day_of_week === $at->dayOfWeekIso && $schedule->start_time <= $earliest && $schedule->end_time >= $time) {
                    return $activity;
                }
            }
        }

        return null;
    }

    public function register(Member $member, ?User $by = null): AccessLog
    {
        $evaluation = $this->evaluate($member);

        return AccessLog::create([
            'member_id' => $member->id,
            'subscription_id' => $evaluation['subscription_id'],
            'activity_id' => $evaluation['activity_id'],
            'result' => $evaluation['result'],
            'reason' => $evaluation['reason'],
            'checked_by' => $by?->id,
            'checked_at' => now(),
        ]);
    }
}
