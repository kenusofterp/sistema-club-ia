<?php

namespace App\Services;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Fee;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de los planes de gimnasio: contratación, activación por pago, renovación y vencimiento.
 */
class SubscriptionService
{
    public function __construct(private FeeService $fees) {}

    public function subscribe(Member $member, Plan $plan, ?Carbon $start = null, bool $autoRenew = true, ?User $by = null): Subscription
    {
        if (! $member->isActive()) {
            throw new BusinessRuleException('Solo los socios activos pueden contratar planes.');
        }

        if (! $plan->is_active) {
            throw new BusinessRuleException('El plan no está disponible.');
        }

        $start = ($start ?? today())->copy()->startOfDay();
        if ($start->lt(today())) {
            throw new BusinessRuleException('El plan no puede comenzar en una fecha pasada.');
        }

        $end = $plan->endDateFrom($start);

        return DB::transaction(function () use ($member, $plan, $start, $end, $autoRenew, $by) {
            // Evita contrataciones simultáneas del mismo socio.
            Member::query()->whereKey($member->id)->lockForUpdate()->first();

            $overlaps = $member->subscriptions()
                ->where('plan_id', $plan->id)
                ->whereIn('status', [SubscriptionStatus::Pending, SubscriptionStatus::Active])
                ->whereDate('start_date', '<=', $end)
                ->whereDate('end_date', '>=', $start)
                ->exists();

            if ($overlaps) {
                throw new BusinessRuleException("El socio ya tiene el plan {$plan->name} vigente o pendiente en ese período.");
            }

            return $this->createPeriod($member, $plan, $start, $autoRenew, null, $by);
        });
    }

    /** Activa una suscripción pendiente. Las nuevas (no renovaciones) cuentan su vigencia desde el día de activación. */
    public function activate(Subscription $subscription): void
    {
        if ($subscription->status !== SubscriptionStatus::Pending) {
            return;
        }

        $data = ['status' => SubscriptionStatus::Active, 'activated_at' => now()];

        if (! $subscription->renewed_from_id && $subscription->start_date->lt(today())) {
            $data['start_date'] = today();
            $data['end_date'] = $subscription->plan->endDateFrom(today());
        }

        $subscription->update($data);
        activity('subscriptions')->performedOn($subscription)->event('activated')->log('Plan activado');
    }

    /** Se invoca cuando un pago deja un cargo saldado. */
    public function handleFeePaid(Fee $fee): void
    {
        if ($fee->subscription_id && $fee->status === FeeStatus::Paid && setting('gym.activate_on_payment', true)) {
            $this->activate($fee->subscription);
        }
    }

    /** Se invoca cuando la anulación de un pago deja un cargo nuevamente impago. */
    public function handleFeeReverted(Fee $fee): void
    {
        $subscription = $fee->subscription;

        if ($subscription && $subscription->status === SubscriptionStatus::Active
            && $fee->status !== FeeStatus::Paid && setting('gym.activate_on_payment', true)) {
            $subscription->update(['status' => SubscriptionStatus::Pending, 'activated_at' => null]);
            activity('subscriptions')->performedOn($subscription)->event('deactivated')->log('Plan vuelto a pendiente por anulación del pago');
        }
    }

    public function cancel(Subscription $subscription, string $reason): void
    {
        if (in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
            throw new BusinessRuleException('El plan ya no está vigente.');
        }

        DB::transaction(function () use ($subscription, $reason) {
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'auto_renew' => false,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            foreach ($subscription->fees()->whereIn('status', FeeStatus::open())->get() as $fee) {
                if (bccomp((string) $fee->paid_amount, '0', 2) === 0) {
                    $this->fees->cancel($fee, 'Plan cancelado');
                }
            }
        });
    }

    /** Genera el período siguiente de una suscripción (idempotente). */
    public function renew(Subscription $subscription, ?User $by = null): ?Subscription
    {
        $subscription->loadMissing(['plan', 'member', 'renewal']);

        if ($subscription->renewal) {
            return null;
        }

        if (! $subscription->plan->is_active || ! $subscription->member->isActive()) {
            return null;
        }

        return DB::transaction(fn () => $this->createPeriod(
            $subscription->member,
            $subscription->plan,
            $subscription->end_date->copy()->addDay(),
            $subscription->auto_renew,
            $subscription,
            $by,
        ));
    }

    /**
     * Proceso diario: vence planes, genera renovaciones automáticas y cancela altas impagas.
     *
     * @return array{expired: int, renewed: int, cancelled: int}
     */
    public function processDaily(): array
    {
        $expired = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereDate('end_date', '<', today())
            ->get()
            ->each(fn (Subscription $s) => $s->update(['status' => SubscriptionStatus::Expired]))
            ->count();

        $renewed = 0;
        $limit = today()->addDays((int) setting('gym.renewal_days_before', 3));
        Subscription::query()
            ->with(['plan', 'member', 'renewal'])
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Expired])
            ->where('auto_renew', true)
            ->whereDate('end_date', '<=', $limit)
            ->whereDate('end_date', '>=', today()->subDays(7))
            ->whereDoesntHave('renewal')
            ->each(function (Subscription $s) use (&$renewed) {
                $renewed += (int) ($this->renew($s) !== null);
            });

        $cancelled = 0;
        $days = (int) setting('gym.pending_expiry_days', 7);
        if ($days > 0) {
            Subscription::query()
                ->where('status', SubscriptionStatus::Pending)
                ->whereNull('renewed_from_id')
                ->where('created_at', '<', now()->subDays($days))
                ->whereDoesntHave('fees', fn ($q) => $q->where('paid_amount', '>', 0))
                ->each(function (Subscription $s) use (&$cancelled) {
                    $this->cancel($s, 'Cancelado automáticamente por falta de pago');
                    $cancelled++;
                });
        }

        return compact('expired', 'renewed', 'cancelled');
    }

    private function createPeriod(Member $member, Plan $plan, Carbon $start, bool $autoRenew, ?Subscription $previous, ?User $by): Subscription
    {
        $price = (string) $plan->price;
        $requiresPayment = setting('gym.activate_on_payment', true) && bccomp($price, '0', 2) > 0;

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'start_date' => $start,
            'end_date' => $plan->endDateFrom($start),
            'price' => $price,
            'status' => $requiresPayment ? SubscriptionStatus::Pending : SubscriptionStatus::Active,
            'activated_at' => $requiresPayment ? null : now(),
            'auto_renew' => $autoRenew,
            'renewed_from_id' => $previous?->id,
            'created_by' => $by?->id,
        ]);

        if (bccomp($price, '0', 2) > 0) {
            $fee = $this->fees->createCharge(
                $member,
                FeeType::Plan,
                "Plan {$plan->name} ({$subscription->periodLabel()})",
                $price,
                $start->copy(),
            );
            $fee->update(['subscription_id' => $subscription->id]);
        }

        return $subscription;
    }
}
