<?php

namespace App\Livewire\Portal;

use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Portal\Concerns\ForCurrentMember;
use App\Models\Plan;
use App\Services\SubscriptionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Mi plan')]
class Plans extends Component
{
    use ForCurrentMember, InteractsWithUi;

    public function purchase(int $planId, SubscriptionService $service): void
    {
        abort_unless(setting('gym.allow_portal_purchase', true), 403);

        $plan = Plan::visible()->findOrFail($planId);
        $member = $this->member();

        // Si ya tiene el mismo plan vigente, el nuevo período arranca al terminar el actual.
        $current = $member->subscriptions()->where('plan_id', $plan->id)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Pending])
            ->latest('end_date')->first();
        $start = $current ? $current->end_date->copy()->addDay() : today();

        $subscription = $this->attempt(fn () => $service->subscribe($member, $plan, $start, true, auth()->user()));

        if ($subscription) {
            $this->notify($subscription->status === SubscriptionStatus::Pending
                ? "Contrataste {$plan->name}. Se activa cuando se registre el pago."
                : "¡Listo! Tu plan {$plan->name} ya está activo.");
        }
    }

    public function toggleAutoRenew(int $id): void
    {
        $subscription = $this->member()->subscriptions()->findOrFail($id);
        $subscription->update(['auto_renew' => ! $subscription->auto_renew]);
        $this->notify($subscription->auto_renew ? 'Renovación automática activada.' : 'Renovación automática desactivada.');
    }

    public function cancelPending(int $id, SubscriptionService $service): void
    {
        $subscription = $this->member()->subscriptions()->where('status', SubscriptionStatus::Pending)->findOrFail($id);
        $this->attempt(fn () => $service->cancel($subscription, 'Cancelado por el socio antes de pagar'), 'Solicitud cancelada.');
    }

    public function render()
    {
        $member = $this->member();
        $subscriptions = $member->subscriptions()
            ->with(['plan.activities.schedules'])
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Pending])
            ->whereDate('end_date', '>=', today())
            ->orderBy('start_date')
            ->get();

        return view('livewire.portal.plans', [
            'member' => $member,
            'subscriptions' => $subscriptions,
            'history' => $member->subscriptions()->with('plan')->whereIn('status', [SubscriptionStatus::Expired, SubscriptionStatus::Cancelled])->latest('end_date')->limit(10)->get(),
            'plans' => setting('gym.allow_portal_purchase', true) ? Plan::visible()->with('activities')->get() : collect(),
        ]);
    }
}
