<?php

namespace App\Livewire\Admin\Gym;

use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Planes de socios')]
class Subscriptions extends Component
{
    use InteractsWithUi, WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'activa';

    #[Url]
    public string $plan = '';

    #[Url(as: 'por-vencer')]
    public bool $expiring = false;

    // Alta
    public bool $showForm = false;

    public string $memberSearch = '';

    #[Url(as: 'socio')]
    public ?int $memberId = null;

    public ?int $planId = null;

    public string $startDate = '';

    public bool $autoRenew = true;

    // Cancelación
    public bool $showCancel = false;

    public ?int $cancelId = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->startDate = today()->toDateString();

        if ($this->memberId && $member = Member::find($this->memberId)) {
            $this->memberSearch = $member->fullName();
            $this->showForm = true;
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'plan', 'expiring'], true)) {
            $this->resetPage();
        }
    }

    public function updatedMemberSearch(): void
    {
        $this->memberId = null;
    }

    public function create(): void
    {
        $this->reset(['memberSearch', 'memberId', 'planId', 'autoRenew']);
        $this->startDate = today()->toDateString();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function selectMember(int $id): void
    {
        $member = Member::findOrFail($id);
        $this->memberId = $member->id;
        $this->memberSearch = $member->fullName();
    }

    public function subscribe(SubscriptionService $service): void
    {
        $this->authorize('suscripciones.gestionar');
        $this->validate([
            'memberId' => 'required|exists:members,id',
            'planId' => 'required|exists:plans,id',
            'startDate' => 'required|date|after_or_equal:today',
            'autoRenew' => 'boolean',
        ], [], ['memberId' => 'socio', 'planId' => 'plan', 'startDate' => 'fecha de inicio']);

        $subscription = $this->attempt(fn () => $service->subscribe(
            Member::findOrFail($this->memberId),
            Plan::findOrFail($this->planId),
            Carbon::parse($this->startDate),
            $this->autoRenew,
            auth()->user(),
        ));

        if ($subscription) {
            $this->showForm = false;
            $this->notify($subscription->status === SubscriptionStatus::Pending
                ? 'Plan asignado. Se activará al registrarse el pago.'
                : 'Plan asignado y activo.');
        }
    }

    public function renew(int $id, SubscriptionService $service): void
    {
        $this->authorize('suscripciones.gestionar');
        $renewal = $service->renew(Subscription::findOrFail($id), auth()->user());
        $this->notify($renewal ? 'Renovación generada.' : 'Ya tiene una renovación o el plan/socio no está activo.', $renewal ? 'success' : 'error');
    }

    public function toggleAutoRenew(int $id): void
    {
        $this->authorize('suscripciones.gestionar');
        $subscription = Subscription::findOrFail($id);
        $subscription->update(['auto_renew' => ! $subscription->auto_renew]);
    }

    public function confirmCancel(int $id): void
    {
        $this->authorize('suscripciones.gestionar');
        $this->cancelId = $id;
        $this->cancelReason = '';
        $this->resetValidation();
        $this->showCancel = true;
    }

    public function cancel(SubscriptionService $service): void
    {
        $this->authorize('suscripciones.gestionar');
        $this->validate(['cancelReason' => 'required|string|min:3|max:250'], [], ['cancelReason' => 'motivo']);

        if ($this->attempt(fn () => $service->cancel(Subscription::findOrFail($this->cancelId), $this->cancelReason), 'Plan cancelado.')) {
            $this->showCancel = false;
        }
    }

    public function render()
    {
        $subscriptions = Subscription::query()
            ->with(['member', 'plan', 'renewal'])
            ->when($this->search, fn ($q) => $q->whereHas('member', fn ($m) => $m->search($this->search)))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->plan, fn ($q) => $q->where('plan_id', $this->plan))
            ->when($this->expiring, fn ($q) => $q->where('status', SubscriptionStatus::Active)->whereBetween('end_date', [today(), today()->addDays(7)]))
            ->orderBy('end_date')
            ->paginate(25);

        return view('livewire.admin.gym.subscriptions', [
            'subscriptions' => $subscriptions,
            'plans' => Plan::ofOrganization()->orderBy('name')->get(['id', 'name', 'price', 'duration_unit', 'duration_value', 'is_active']),
            'statuses' => SubscriptionStatus::options(),
            'memberResults' => ! $this->memberId && strlen($this->memberSearch) >= 2 ? Member::active()->search($this->memberSearch)->limit(6)->get() : collect(),
            'stats' => [
                'active' => Subscription::current()->count(),
                'pending' => Subscription::where('status', SubscriptionStatus::Pending)->count(),
                'expiring' => Subscription::where('status', SubscriptionStatus::Active)->whereBetween('end_date', [today(), today()->addDays(7)])->count(),
            ],
        ]);
    }
}
